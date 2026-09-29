<?php

use App\Models\User;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Services\ServiceBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/helpers.php';

beforeEach(function () {
    configureServices();
});

test('a visitor can book a service without an account', function () {
    $service = makeService(['price' => 2500]);

    $this->post('/services/' . $service->slug . '/book', [
        'customer_name' => 'Ahmad Rahimi',
        'customer_phone' => '0700000123',
        'customer_email' => 'ahmad@example.test',
        'address' => 'House 12, Street 4, Kabul',
        'city' => 'Kabul',
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('service_bookings', 1);

    $booking = ServiceBooking::first();

    expect($booking->status)->toBe('booked')
        ->and($booking->customer_name)->toBe('Ahmad Rahimi')
        ->and($booking->reference)->toStartWith('SRV-')
        // Fixed price: what the page promised is what the job is worth.
        ->and((float) $booking->price_snapshot)->toBe(2500.0)
        ->and($booking->effectiveAmount())->toBe(2500.0)
        // Paid after the work is done, so nothing is collected yet. The payment
        // state itself lives on the order, where the rest of the shop keeps it.
        ->and($booking->amount_collected)->toBeNull()
        ->and($booking->order->payment_status)->toBe('pending')
        ->and($booking->order->status)->toBe('pending');
});

test('booking a service creates a matching order with the price agreed', function () {
    $service = makeService(['price' => 2500, 'name' => 'Deep Cleaning']);

    bookServiceJob($service);

    $this->assertDatabaseHas('orders', [
        'order_number' => DB::table('service_bookings')->value('reference'),
        'payment_status' => 'pending',
        'status' => 'pending',
    ]);

    $item = DB::table('order_items')->first();

    // A service is not a product, so the product column stays empty and the
    // details are copied onto the order itself.
    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe('Deep Cleaning')
        ->and((float) $item->price)->toBe(2500.0)
        ->and((float) $item->total)->toBe(2500.0);
});

test('a service order never earns a referral commission', function () {
    /*
     * The customer here is a genuinely referred shopper sitting just under the
     * reward threshold. A weak version of this test books for a stranger, which
     * earns nothing whether or not the order events fire -- so it would pass even
     * with the suppression removed. This setup is chosen so that removing the
     * suppression *does* pay a commission, which is the only way the assertion
     * below means anything.
     */
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    $referral = linkReferral($referrer, $referred);

    // A small earlier order that leaves the shopper under the threshold, so the
    // referral is still open and the *service* order is what crosses the line.
    referralOrder($referred, 400);

    expect(Cartxis\Referral\Models\ReferralCommission::count())->toBe(0)
        ->and($referral->fresh()->hasBeenRewarded())->toBeFalse();

    // The new spend, through the real service booking path.
    $before = Cartxis\Referral\Models\ReferralCommission::count();
    $ledgerBefore = DB::table('referral_ledger')->count();

    $admin = serviceAdmin();
    $service = makeService();
    $worker = serviceWorker();

    $booking = bookServiceJob($service, [
        'customer_email' => $referred->email,
    ], $referred);

    $this->actingAs($admin, 'admin')->post("/admin/services/bookings/{$booking->reference}/assign", [
        'assigned_to' => $worker->id,
    ])->assertStatus(302);

    expect($booking->fresh()->assigned_to)->toBe($worker->id);

    // The booking has moved on, so the next step works from the current state.
    $statuses = app(Cartxis\Service\Services\ServiceBookingStatusService::class);

    $statuses->start($booking->fresh());
    $statuses->complete($booking->fresh(), 3000.0);

    // Referral money is for referrals. A service job must never create any,
    // otherwise the store pays commission it never agreed to. The counts are
    // compared against the state before the service job, not against zero,
    // because the shopper is genuinely part-way to a reward.
    expect(Cartxis\Referral\Models\ReferralCommission::count())->toBe($before)
        ->and(DB::table('referral_ledger')->count())->toBe($ledgerBefore)
        ->and($referral->fresh()->hasBeenRewarded())->toBeFalse();

    // The order it did create is a real, completed, paid one, and it is marked
    // as a service order so it stays identifiable in reports.
    $this->assertDatabaseHas('orders', [
        'order_number' => $booking->reference,
        'user_id' => $referred->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'source_channel' => 'services',
    ]);
});

test('an ordinary order for the same shopper does pay commission', function () {
    /*
     * A test that never fails proves nothing, so this is the control: the same
     * referred shopper under the same threshold, but an ordinary order written
     * with events switched on. The first order is under the line, the second
     * crosses it, and a commission appears. If this pays, then a service order
     * would really pay too, so the assertion in the test above is measuring the
     * suppression rather than an accident of the fixture.
     */
    configureReferral(['threshold_amount' => 500, 'reward_amount' => 10]);

    $referrer = referralUser('Referrer');
    $referred = referralUser('Shopper');
    linkReferral($referrer, $referred);

    referralOrder($referred, 400);
    expect(Cartxis\Referral\Models\ReferralCommission::count())->toBe(0);

    referralOrder($referred, 3000);
    expect(Cartxis\Referral\Models\ReferralCommission::count())->toBe(1);
});

test('a customer can look up a booking with the reference and phone number', function () {
    $booking = bookServiceJob(null, ['customer_phone' => '0700000456']);

    $this->get('/services/booked/' . $booking->reference . '?phone=0700000456')
        ->assertOk()
        ->assertSee($booking->reference);

    // The wrong phone number must not open someone else's booking.
    $this->get('/services/booked/' . $booking->reference . '?phone=0799999999')
        ->assertStatus(403);

    $this->get('/services/booked/' . $booking->reference)
        ->assertStatus(403);
});

test('a booking is refused when the chosen day is in the past', function () {
    $service = makeService();

    $this->post('/services/' . $service->slug . '/book', bookingPayload([
        'scheduled_date' => CarbonImmutable::today()->subDay()->format('Y-m-d'),
    ]))->assertSessionHasErrors('scheduled_date');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a booking inside the lead time is refused', function () {
    $service = makeService();

    // The owner wants at least a day's notice.
    $this->post('/services/' . $service->slug . '/book', bookingPayload([
        'scheduled_date' => CarbonImmutable::today()->format('Y-m-d'),
    ]))->assertSessionHasErrors('scheduled_date');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a booking beyond the booking window is refused', function () {
    $service = makeService();

    $this->post('/services/' . $service->slug . '/book', bookingPayload([
        'scheduled_date' => CarbonImmutable::today()->addDays(40)->format('Y-m-d'),
    ]))->assertSessionHasErrors('scheduled_date');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a booking is refused when the service is switched off', function () {
    $service = makeService(['status' => 'disabled']);

    // A hidden service is not on the website at all.
    $this->get('/services/' . $service->slug)->assertStatus(404);

    // Posting to the address anyway is refused, and says why, rather than
    // quietly making a job nobody will turn up for.
    $this->post('/services/' . $service->slug . '/book', bookingPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('form');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a booking is refused when online booking is switched off', function () {
    configureServices(['booking_enabled' => false]);
    $service = makeService();

    $this->post('/services/' . $service->slug . '/book', bookingPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('form');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a service that cannot be booked online is refused with a reason', function () {
    $service = makeService(['booking_enabled' => false]);

    $this->post('/services/' . $service->slug . '/book', bookingPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('form');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('an unknown time slot is refused', function () {
    $service = makeService();

    $this->post('/services/' . $service->slug . '/book', bookingPayload([
        'scheduled_slot' => 'Middle of the Night',
    ]))->assertSessionHasErrors('scheduled_slot');

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a slot that is already full is refused', function () {
    configureServices(['capacity_per_slot' => 1]);
    $service = makeService();

    bookServiceJob($service);

    $this->post('/services/' . $service->slug . '/book', bookingPayload([
        'customer_phone' => '0700000999',
    ]))->assertSessionHasErrors('scheduled_slot');

    $this->assertDatabaseCount('service_bookings', 1);
});

test('submitting the same booking twice only creates one job', function () {
    $service = makeService();
    $payload = bookingPayload([
        'customer_phone' => '0700000777',
        'request_token' => 'form-token-abc123',
    ]);

    // People double-tap submit, and a lost connection sends the form again.
    $first = $this->post('/services/' . $service->slug . '/book', $payload);
    $second = $this->post('/services/' . $service->slug . '/book', $payload);

    $this->assertDatabaseCount('service_bookings', 1);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 1);

    // Both taps lead to the same confirmation, not to two different jobs.
    $reference = ServiceBooking::first()->reference;

    $first->assertRedirect(route('services.booked', [
        'reference' => $reference,
        'phone' => '0700000777',
    ]));
    $second->assertRedirect(route('services.booked', [
        'reference' => $reference,
        'phone' => '0700000777',
    ]));
});

test('the same token cannot be used for a different job', function () {
    $service = makeService();
    $other = makeService(['name' => 'Window Cleaning']);

    bookServiceJob($service, ['request_token' => 'shared-token']);

    // A token is only ever trusted for the form it came from.
    $this->post('/services/' . $other->slug . '/book', bookingPayload([
        'request_token' => 'shared-token',
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('service_bookings', 1);
});

test('a booking needs a name, a phone number and an address', function () {
    $service = makeService();

    $this->post('/services/' . $service->slug . '/book', [
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ])->assertSessionHasErrors(['customer_name', 'customer_phone', 'address']);

    $this->assertDatabaseCount('service_bookings', 0);
});

test('a hidden service does not appear in the catalogue', function () {
    makeService(['name' => 'Visible Job', 'status' => 'enabled']);
    makeService(['name' => 'Hidden Job', 'status' => 'disabled']);

    $this->get('/services')
        ->assertOk()
        ->assertSee('Visible Job')
        ->assertDontSee('Hidden Job');
});

test('a hidden category is not shown on the storefront', function () {
    $category = makeServiceCategory(['name' => 'Cleaning', 'status' => 'enabled']);
    $hidden = makeServiceCategory(['name' => 'Secret Trade', 'status' => 'disabled']);

    makeService(['name' => 'Open Job', 'service_category_id' => $category->id]);
    makeService(['name' => 'Closed Job', 'service_category_id' => $hidden->id]);

    $this->get('/services')->assertOk()->assertSee('Cleaning')->assertDontSee('Secret Trade');
    $this->get('/services/category/secret-trade')->assertStatus(404);
});

test('the storefront shows what is included and how long the work takes', function () {
    $service = makeService([
        'name' => 'Full House Cleaning',
        'duration_minutes' => 180,
        'includes' => ['Windows cleaned', 'Floors mopped'],
        'service_area' => 'Kabul, Herat',
    ]);

    $this->get('/services/' . $service->slug)
        ->assertOk()
        ->assertSee('Full House Cleaning')
        ->assertSee('Windows cleaned')
        ->assertSee('Kabul, Herat');
});

test('a disabled service cannot be booked through the service class either', function () {
    $service = makeService(['status' => 'disabled']);

    expect(fn () => bookServiceJob($service))->toThrow(RuntimeException::class);
});

test('the admin sees new bookings on the bookings list', function () {
    $admin = serviceAdmin();
    $booking = bookServiceJob(null, ['customer_name' => 'Booked Customer']);

    $this->actingAs($admin, 'admin')
        ->get('/admin/services/bookings')
        ->assertOk()
        ->assertSee('Booked Customer')
        ->assertSee($booking->reference);
});

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'customer_name' => 'Ahmad Rahimi',
        'customer_phone' => '0700000123',
        'customer_email' => 'ahmad@example.test',
        'address' => 'House 12, Street 4, Kabul',
        'city' => 'Kabul',
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ], $overrides);
}
