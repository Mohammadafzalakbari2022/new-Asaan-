<?php

declare(strict_types=1);

namespace Cartxis\Service\Services;

use App\Models\User;
use Cartxis\Service\Exceptions\ServiceBookingException;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Models\ServiceBookingEvent;
use Cartxis\Shop\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turning a customer's request into a booked job.
 *
 * A booking and its order row are written in one transaction, so a job can
 * never exist without the money record behind it, and the money record can
 * never exist without the job.
 *
 * Every write to the order is quiet. That is the whole reason a service job
 * stays out of the referral programme: the referral package watches every order
 * that becomes paid, and this code never lets it see one. Nothing in that
 * package is touched to achieve it.
 */
class ServiceBookingService
{
    public function __construct(
        protected ServiceSettings $settings,
        protected ServiceSlotService $slots,
    ) {}

    /**
     * @param  array{scheduled_date: string, scheduled_slot: string, customer_name: string, customer_phone: string, customer_email?: ?string, address: string, city?: ?string, notes?: ?string}  $data
     */
    public function book(Service $service, array $data, ?User $user = null): ServiceBooking
    {
        $token = $data['request_token'] ?? null;

        // The same form sent twice is one job. Checked first so the common case
        // of a second tap is instant, and enforced by the unique column below so
        // two taps arriving together still cannot both win.
        if ($token !== null) {
            $existing = ServiceBooking::query()->where('request_token', $token)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $this->assertBookable($service);
        $this->assertDateIsBookable($data['scheduled_date']);
        $this->assertSlotIsBookable($data['scheduled_slot'], $data['scheduled_date']);

        try {
            return DB::transaction(function () use ($service, $data, $user, $token) {
                $reference = $this->uniqueReference();
                $price = round((float) $service->price, 2);

                $order = $this->createOrderQuietly([
                    'reference' => $reference,
                    'price' => $price,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'address' => $data['address'],
                    'notes' => $data['notes'] ?? null,
                    'user' => $user,
                ]);

                $booking = ServiceBooking::create([
                    'reference' => $reference,
                    'request_token' => $token,
                    'order_id' => $order->id,
                    'service_id' => $service->id,
                    'user_id' => $user?->id,
                    'status' => ServiceBooking::STATUS_BOOKED,
                    'scheduled_date' => $data['scheduled_date'],
                    'scheduled_slot' => $data['scheduled_slot'],
                    'service_name' => $service->name,
                    'price_snapshot' => $price,
                    'price_unit' => $service->price_unit,
                    'payment_method' => config('service.order.payment_method', 'cash'),
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'address' => $data['address'],
                    'city' => $data['city'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'source' => 'web',
                ]);

                $this->addOrderItemQuietly($order, $service, $price);

                ServiceBookingEvent::create([
                    'service_booking_id' => $booking->id,
                    'actor_id' => $user?->id,
                    'from_status' => null,
                    'to_status' => ServiceBooking::STATUS_BOOKED,
                    'note' => 'Booked on the website.',
                    'created_at' => now(),
                ]);

                return $booking->fresh(['service', 'order', 'worker', 'events']);
            });
        } catch (UniqueConstraintViolationException $e) {
            // The other of two identical submissions won the race. That booking
            // is the one the customer wanted, so it is returned rather than
            // showing them an error for something they have already done.
            if ($token === null) {
                throw $e;
            }

            $existing = ServiceBooking::query()
                ->where('request_token', $token)
                ->first();

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    /**
     * A reference nobody else is using, safe to read out over the phone.
     */
    public function uniqueReference(): string
    {
        $prefix = $this->settings->referencePrefix();

        do {
            $reference = $prefix . strtoupper(Str::random(6));
        } while (ServiceBooking::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * The order number, in the same shape as a shop order so it sorts and reads
     * the same way in every list, with the SRV prefix marking it as a job.
     */
    public function orderNumberFor(): string
    {
        $prefix = trim((string) config('service.order_number_prefix', 'SRV-'), '-');
        $number = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

        return $prefix . '-' . now()->format('ymd') . '-' . $number;
    }

    /**
     * Whether a service may be booked right now. Re-checked on the server so a
     * hidden or disabled service cannot be booked by posting to the endpoint.
     */
    public function assertBookable(Service $service): void
    {
        if (! $this->settings->isBookingEnabled()) {
            throw ServiceBookingException::form('Booking is switched off at the moment. Please call us instead.');
        }

        if ($service->status !== 'enabled') {
            throw ServiceBookingException::form('This service is not available.');
        }

        if (! $service->booking_enabled) {
            throw ServiceBookingException::form('This service cannot be booked online.');
        }
    }

    public function assertDateIsBookable(string $date): void
    {
        $error = $this->slots->dateError($date);

        if ($error !== null) {
            throw ServiceBookingException::date($error);
        }
    }

    public function assertSlotIsBookable(string $label, string $date): void
    {
        if (! in_array($label, $this->settings->timeSlotLabels(), true)) {
            throw ServiceBookingException::slot('Please choose one of the offered time slots.');
        }

        if (! $this->slots->hasCapacity($date, $label)) {
            throw ServiceBookingException::slot('That time slot is now full. Please choose another.');
        }
    }

    /**
     * The order row for a service job.
     *
     * Written with model events suppressed. It is created unpaid, so the
     * referral observer would ignore it anyway, but the suppression is kept for
     * the whole feature so the rule holds no matter which path writes the order.
     */
    protected function createOrderQuietly(array $data): Order
    {
        return Order::withoutEvents(function () use ($data) {
            return Order::create([
                'user_id' => $data['user']?->id,
                // One number for the whole job, so the customer can quote the same
                // thing on the phone, on the order screen and to the worker.
                'order_number' => $data['reference'],
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal' => $data['price'],
                'tax' => (float) config('service.order.tax', 0),
                'shipping_cost' => (float) config('service.order.shipping_cost', 0),
                'discount' => 0,
                'total' => $data['price'],
                'payment_method' => config('service.order.payment_method', 'cash'),
                'shipping_method' => null,
                'tracking_number' => null,
                'customer_email' => $data['customer_email'] ?? $data['user']?->email,
                'customer_phone' => $data['customer_phone'],
                'notes' => trim('Service booking ' . $data['reference'] . "\n" . ($data['notes'] ?? '')),
                'source_channel' => config('service.order.source_channel', 'services'),
            ]);
        });
    }

    /**
     * One line on the order, holding the service as a snapshot.
     *
     * order_items.product_id is nullable precisely so a line can exist without a
     * product, and the name, price and image copied here are what the invoice
     * and the order page read.
     */
    protected function addOrderItemQuietly(Order $order, Service $service, float $price): void
    {
        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => null,
            'product_sku' => null,
            'product_name' => $service->name,
            'product_image' => $service->image,
            'quantity' => 1,
            'price' => $price,
            'total' => $price,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'options' => json_encode([
                'kind' => 'service',
                'duration' => $service->duration_display,
                'service_area' => $service->service_area,
                'unit' => $service->unitLabel(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
