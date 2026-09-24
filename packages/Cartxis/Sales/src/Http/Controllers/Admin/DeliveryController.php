<?php

namespace Cartxis\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\Shipment;
use Cartxis\Sales\Services\DeliveryStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    protected DeliveryStatusService $deliveryStatusService;

    public function __construct(DeliveryStatusService $deliveryStatusService)
    {
        $this->deliveryStatusService = $deliveryStatusService;
    }

    /**
     * List deliveries with filters.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->input('status'),
            'driver' => $request->input('driver'),
            'search' => $request->input('search'),
            'per_page' => $request->input('per_page', 15),
        ];

        $query = Delivery::with(['shipment.order', 'assignedTo', 'assignedBy']);

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['driver']) {
            $query->where('assigned_to', $filters['driver']);
        }

        if ($filters['search']) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->where('order_number', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('customer_phone', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('customer_email', 'like', '%' . $filters['search'] . '%');
            });
        }

        $deliveries = $query->latest()
            ->paginate($filters['per_page'])
            ->withQueryString();

        $deliveries->getCollection()->transform(function (Delivery $delivery) {
            return [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'status_badge' => $delivery->status_badge,
                'priority' => $delivery->priority,
                'scheduled_date' => $delivery->scheduled_date?->toDateTimeString(),
                'customer_phone' => $delivery->customer_phone,
                'cod_amount' => $delivery->cod_amount,
                'shipment_number' => $delivery->shipment?->shipment_number,
                'order' => $delivery->order ? [
                    'id' => $delivery->order->id,
                    'order_number' => $delivery->order->order_number,
                    'customer_email' => $delivery->order->customer_email,
                ] : null,
                'assigned_to' => $delivery->assignedTo ? [
                    'id' => $delivery->assignedTo->id,
                    'name' => $delivery->assignedTo->name,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Sales/Deliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'statuses' => collect(Delivery::getStatuses())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'drivers' => User::where('role', 'delivery')->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'availableShipments' => $this->availableShipments()->map(fn (Shipment $s) => [
                'id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'order_number' => $s->order?->order_number,
                'customer' => $s->order?->customer_email,
                'status' => $s->status,
            ]),
        ]);
    }

    /**
     * Assign a shipment to a driver.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'assigned_to' => 'required|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'priority' => 'nullable|string|in:normal,high',
            'cod_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $driver = User::find($validated['assigned_to']);
        if ($driver->role !== 'delivery' || !$driver->is_active) {
            return back()->with('error', 'Select an active delivery person for this assignment.');
        }

        try {
            $delivery = $this->deliveryStatusService->assign(
                Shipment::findOrFail($validated['shipment_id']),
                $validated['assigned_to'],
                $validated
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.sales.deliveries.show', $delivery->id)
            ->with('success', 'Delivery assigned successfully.');
    }

    /**
     * Live delivery board: all active runs with driver positions and drop-off points.
     */
    public function board(): Response
    {
        $active = Delivery::with(['shipment', 'order.shippingAddress', 'assignedTo'])
            ->whereIn('status', [
                Delivery::STATUS_ASSIGNED,
                Delivery::STATUS_OUT_FOR_DELIVERY,
                Delivery::STATUS_ARRIVING,
            ])
            ->latest()
            ->get();

        $activeDeliveries = $active->map(function (Delivery $delivery) {
            $address = $delivery->order?->shippingAddress;

            return [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'status_badge' => $delivery->status_badge,
                'priority' => $delivery->priority,
                'shipment_number' => $delivery->shipment?->shipment_number,
                'order_number' => $delivery->order?->order_number,
                'customer_phone' => $delivery->customer_phone,
                'scheduled_date' => $delivery->scheduled_date?->toDateTimeString(),
                'cod_amount' => $delivery->cod_amount !== null ? (float) $delivery->cod_amount : null,
                'destination' => $address ? [
                    'lat' => $address->latitude !== null ? (float) $address->latitude : null,
                    'lng' => $address->longitude !== null ? (float) $address->longitude : null,
                    'label' => $address->full_address,
                ] : null,
                'driver' => $delivery->assignedTo ? [
                    'id' => $delivery->assignedTo->id,
                    'name' => $delivery->assignedTo->name,
                    'phone' => $delivery->assignedTo->phone,
                ] : null,
                'driver_location' => $delivery->last_latitude !== null && $delivery->last_longitude !== null ? [
                    'lat' => (float) $delivery->last_latitude,
                    'lng' => (float) $delivery->last_longitude,
                    'live_at' => $delivery->last_location_at?->toDateTimeString(),
                    'live' => $delivery->last_location_at !== null && $delivery->last_location_at->diffInMinutes(now()) <= 20,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Sales/Deliveries/Board', [
            'activeDeliveries' => $activeDeliveries,
        ]);
    }

    /**
     * Delivery detail with events.
     */
    public function show(int $id): Response
    {
        $delivery = Delivery::with([
            'shipment.order.shippingAddress',
            'assignedTo',
            'assignedBy',
            'events.actor',
        ])->findOrFail($id);

        return Inertia::render('Admin/Sales/Deliveries/Show', [
            'delivery' => $delivery,
            'drivers' => User::where('role', 'delivery')->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'statuses' => collect(Delivery::getStatuses())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * Reassign or reschedule a delivery.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $delivery = Delivery::findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'priority' => 'nullable|string|in:normal,high',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            if (!empty($validated['assigned_to'])) {
                $driver = User::find($validated['assigned_to']);
                if ($driver->role !== 'delivery' || !$driver->is_active) {
                    return back()->with('error', 'Select an active delivery person.');
                }

                $delivery = $this->deliveryStatusService->reassign($delivery, $driver->id, 'Reassigned by admin');
            }

            if (array_key_exists('scheduled_date', $validated) && $validated['scheduled_date'] !== $delivery->scheduled_date?->toDateTimeString()) {
                $delivery = $this->deliveryStatusService->reschedule($delivery, $validated['scheduled_date']);
            }

            if (array_key_exists('priority', $validated) && $validated['priority'] !== null) {
                $delivery->update(['priority' => $validated['priority']]);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivery updated.');
    }

    /**
     * Cancel a delivery.
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $delivery = Delivery::findOrFail($id);

        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->deliveryStatusService->cancel($delivery, $validated['note'] ?? 'Cancelled by admin');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivery cancelled.');
    }

    /**
     * Shipments that can still be assigned to a driver.
     */
    protected function availableShipments()
    {
        $activeIds = Delivery::whereIn('status', [
            Delivery::STATUS_ASSIGNED,
            Delivery::STATUS_OUT_FOR_DELIVERY,
            Delivery::STATUS_ARRIVING,
        ])->pluck('shipment_id');

        return Shipment::with('order')
            ->whereIn('status', [Shipment::STATUS_PENDING, Shipment::STATUS_SHIPPED])
            ->whereNotIn('id', $activeIds)
            ->latest()
            ->limit(50)
            ->get();
    }
}