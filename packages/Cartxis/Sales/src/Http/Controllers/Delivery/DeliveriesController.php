<?php

namespace Cartxis\Sales\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Services\DeliveryStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveriesController extends Controller
{
    protected DeliveryStatusService $deliveryStatusService;

    public function __construct(DeliveryStatusService $deliveryStatusService)
    {
        $this->deliveryStatusService = $deliveryStatusService;
    }

    /**
     * List deliveries assigned to the logged-in driver.
     */
    public function index(): Response
    {
        $driverId = auth('delivery')->id();

        $active = Delivery::with(['shipment.order', 'order.shippingAddress'])
            ->where('assigned_to', $driverId)
            ->whereIn('status', [
                Delivery::STATUS_ASSIGNED,
                Delivery::STATUS_OUT_FOR_DELIVERY,
                Delivery::STATUS_ARRIVING,
            ])
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_date')
            ->get();

        $history = Delivery::with(['shipment.order'])
            ->where('assigned_to', $driverId)
            ->whereIn('status', [
                Delivery::STATUS_DELIVERED,
                Delivery::STATUS_UNDELIVERED,
                Delivery::STATUS_CANCELLED,
            ])
            ->latest()
            ->limit(10)
            ->get();

        $mapper = fn (Delivery $d) => [
            'id' => $d->id,
            'status' => $d->status,
            'status_badge' => $d->status_badge,
            'priority' => $d->priority,
            'shipment_number' => $d->shipment?->shipment_number,
            'order_number' => $d->order?->order_number,
            'customer_email' => $d->order?->customer_email,
            'customer_phone' => $d->customer_phone,
            'address_line' => $d->order?->shippingAddress?->full_address,
            'city' => $d->order?->shippingAddress?->city,
            'scheduled_date' => $d->scheduled_date?->toDateTimeString(),
            'cod_amount' => $d->cod_amount,
        ];

        return Inertia::render('Delivery/Deliveries/Index', [
            'active' => $active->map($mapper),
            'history' => $history->map($mapper),
        ]);
    }

    /**
     * Delivery detail for the assigned driver.
     */
    public function show(int $id): Response
    {
        $delivery = $this->findOwned($id);

        return Inertia::render('Delivery/Deliveries/Show', [
            'delivery' => [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'status_badge' => $delivery->status_badge,
                'priority' => $delivery->priority,
                'shipment_number' => $delivery->shipment?->shipment_number,
                'order_number' => $delivery->order?->order_number,
                'customer_email' => $delivery->order?->customer_email,
                'customer_phone' => $delivery->customer_phone,
                'cod_amount' => $delivery->cod_amount,
                'cod_received' => $delivery->cod_received,
                'scheduled_date' => $delivery->scheduled_date?->toDateTimeString(),
                'notes' => $delivery->notes,
                'recipient_name' => $delivery->recipient_name,
                'delivered_photo_path' => $delivery->delivered_photo_path,
                'failure_reason' => $delivery->failure_reason,
                'failure_note' => $delivery->failure_note,
                'address' => $delivery->order?->shippingAddress ? [
                    'full_name' => $delivery->order->shippingAddress->full_name,
                    'full_address' => $delivery->order->shippingAddress->full_address,
                    'phone' => $delivery->order->shippingAddress->phone,
                    'latitude' => $delivery->order->shippingAddress->latitude !== null ? (float) $delivery->order->shippingAddress->latitude : null,
                    'longitude' => $delivery->order->shippingAddress->longitude !== null ? (float) $delivery->order->shippingAddress->longitude : null,
                ] : null,
                'events' => $delivery->events()->orderBy('created_at', 'desc')->get()->map(function ($e) {
                    return [
                        'id' => $e->id,
                        'status_change' => $e->status_change,
                        'note' => $e->note,
                        'created_at' => $e->created_at->toDateTimeString(),
                    ];
                }),
            ],
            'failureReasons' => collect(Delivery::getFailureReasons())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * Driver clicks "start delivery" (assigned → out for delivery).
     */
    public function start(int $id): RedirectResponse
    {
        try {
            $this->deliveryStatusService->transition(
                $this->findOwned($id),
                Delivery::STATUS_OUT_FOR_DELIVERY,
                note: 'Driver started the delivery'
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivery started. Go get it!');
    }

    /**
     * Driver marks "arriving today".
     */
    public function arriving(int $id): RedirectResponse
    {
        try {
            $this->deliveryStatusService->transition(
                $this->findOwned($id),
                Delivery::STATUS_ARRIVING,
                note: 'Driver will arrive today'
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked as arriving today.');
    }

    /**
     * Driver marks the package delivered (with proof + recipient + COD).
     */
    public function deliver(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'cod_received' => ['nullable', 'numeric', 'min:0'],
            'delivered_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $photoPath = null;
        if ($request->hasFile('delivered_photo')) {
            $photoPath = $request->file('delivered_photo')->store('deliveries', 'public');
        }

        try {
            $this->deliveryStatusService->transition(
                $this->findOwned($id),
                Delivery::STATUS_DELIVERED,
                [
                    'recipient_name' => $validated['recipient_name'],
                    'delivered_photo_path' => $photoPath ? '/storage/' . $photoPath : null,
                    'cod_received' => $validated['cod_received'] ?? null,
                ],
                $validated['note'] ?? 'Package delivered'
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('delivery.deliveries.index')
            ->with('success', 'Package marked as delivered.');
    }

    /**
     * Driver marks a delivery as undelivered.
     */
    public function undelivered(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'in:' . implode(',', array_keys(Delivery::getFailureReasons()))],
            'failure_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->deliveryStatusService->transition(
                $this->findOwned($id),
                Delivery::STATUS_UNDELIVERED,
                $validated,
                'Not delivered: ' . ($validated['failure_note'] ?? $validated['failure_reason'])
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('delivery.deliveries.index')
            ->with('success', 'Marked as undelivered.');
    }

    /**
     * Driver shares their live location during out-for-delivery / arriving.
     */
    public function location(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->deliveryStatusService->updateLocation(
            $this->findOwned($id),
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            isset($validated['accuracy']) ? (float) $validated['accuracy'] : null
        );

        return response()->json(['shared' => true]);
    }

    /**
     * Find a delivery that is assigned to the logged-in driver (403 otherwise).
     */
    protected function findOwned(int $id): Delivery
    {
        $delivery = Delivery::with(['shipment.order.shippingAddress', 'order.shippingAddress', 'events'])->findOrFail($id);

        abort_unless($delivery->assigned_to === auth('delivery')->id(), 403, 'This delivery is not assigned to you.');

        return $delivery;
    }
}