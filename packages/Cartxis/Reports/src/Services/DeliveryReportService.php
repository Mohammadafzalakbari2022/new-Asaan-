<?php

namespace Cartxis\Reports\Services;

use App\Models\User;
use Carbon\Carbon;
use Cartxis\Reports\Services\ReportCacheService;
use Cartxis\Sales\Models\Delivery;
use Cartxis\Sales\Models\DeliveryEvent;
use Illuminate\Support\Collection;

class DeliveryReportService
{
    public function __construct(
        protected ReportCacheService $cacheService
    ) {}

    /**
     * Build the filtered deliveries query for reports and exports.
     */
    public function applyFilters($query, array $filters): void
    {
        [$startDate, $endDate] = $this->parseDateRange($filters);

        $query->whereBetween('deliveries.created_at', [$startDate, $endDate]);

        if (!empty($filters['driver_id']) && (int) $filters['driver_id'] > 0) {
            $query->where('deliveries.assigned_to', (int) $filters['driver_id']);
        }

        if (!empty($filters['status']) && array_key_exists($filters['status'], Delivery::getStatuses())) {
            $query->where('deliveries.status', $filters['status']);
        }
    }

    /**
     * Get the complete delivery report data.
     */
    public function getReportData(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        // Only the aggregates are cached; the paginated list keeps fresh pages.
        $summary = $this->cacheService->remember('delivery', $filters, function () use ($filters) {
            return [
                'statistics' => $this->getStatistics($filters),
                'perDriver' => $this->getPerDriverStats($filters),
                'chart' => $this->getDailyChart($filters),
            ];
        });

        return array_merge($summary, [
            'deliveries' => $this->getDeliveries($filters),
            'filters' => $filters,
            'drivers' => User::where('role', 'delivery')->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    /**
     * Delivery rows for CSV export, matching the filtered report.
     */
    public function exportRows(array $filters = []): Collection
    {
        $filters = $this->normalizeFilters($filters);

        $query = Delivery::query()
            ->with(['shipment', 'order', 'assignedTo'])
            ->leftJoin('shipments', 'shipments.id', '=', 'deliveries.shipment_id')
            ->leftJoin('orders', 'orders.id', '=', 'deliveries.order_id');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('deliveries.created_at')->get()->map(function (Delivery $delivery) {
            return [
                'delivery_id' => $delivery->id,
                'order_number' => $delivery->order?->order_number ?? 'N/A',
                'shipment_number' => $delivery->shipment?->shipment_number ?? 'N/A',
                'driver' => $delivery->assignedTo?->name ?? 'Unassigned',
                'status' => Delivery::getStatuses()[$delivery->status] ?? $delivery->status,
                'priority' => ucfirst($delivery->priority),
                'customer_phone' => $delivery->customer_phone ?? 'N/A',
                'cod_expected' => $delivery->cod_amount !== null ? (float) $delivery->cod_amount : 0,
                'cod_collected' => $delivery->status === Delivery::STATUS_DELIVERED ? (float) $delivery->cod_received : 0,
                'recipient' => $delivery->recipient_name ?? 'N/A',
                'assigned_at' => $delivery->created_at?->format('Y-m-d H:i:s'),
                'scheduled_date' => $delivery->scheduled_date?->format('Y-m-d H:i:s') ?? 'N/A',
                'delivered_at' => $delivery->shipment?->delivered_at?->format('Y-m-d H:i:s') ?? 'N/A',
                'failure_reason' => $delivery->failure_reason ?? '',
                'notes' => $delivery->notes ?? '',
            ];
        });
    }

    /**
     * Sanitize filters to sensible defaults.
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'start_date' => $this->safeDate($filters['start_date'] ?? null, now()->subDays(30))->format('Y-m-d'),
            'end_date' => $this->safeDate($filters['end_date'] ?? null, now())->format('Y-m-d'),
            'driver_id' => isset($filters['driver_id']) && (int) $filters['driver_id'] > 0 ? (int) $filters['driver_id'] : null,
            'status' => !empty($filters['status']) && array_key_exists($filters['status'], Delivery::getStatuses()) ? $filters['status'] : null,
        ];
    }

    /**
     * Parse a raw date value defensively.
     */
    protected function safeDate(mixed $value, Carbon $default): Carbon
    {
        if (is_string($value) && strlen($value) > 0) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // fall through to the default
            }
        }

        return $default;
    }

    protected function parseDateRange(array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();

        return [$startDate, $endDate];
    }

    protected function getStatistics(array $filters): array
    {
        $base = Delivery::query();
        $this->applyFilters($base, $filters);

        $total = (clone $base)->count();
        $delivered = (clone $base)->where('status', Delivery::STATUS_DELIVERED)->count();
        $undelivered = (clone $base)->where('status', Delivery::STATUS_UNDELIVERED)->count();
        $cancelled = (clone $base)->where('status', Delivery::STATUS_CANCELLED)->count();
        $onRoad = (clone $base)->whereIn('status', [
            Delivery::STATUS_ASSIGNED,
            Delivery::STATUS_OUT_FOR_DELIVERY,
            Delivery::STATUS_ARRIVING,
        ])->count();

        $codExpected = (float) (clone $base)->sum('cod_amount');
        $codCollected = (float) (clone $base)->where('status', Delivery::STATUS_DELIVERED)->sum('cod_received');

        $resolved = $delivered + $undelivered;
        $deliveryRate = $resolved > 0 ? round($delivered / $resolved * 100, 1) : null;

        return [
            'total' => $total,
            'delivered' => $delivered,
            'undelivered' => $undelivered,
            'cancelled' => $cancelled,
            'on_road' => $onRoad,
            'cod_expected' => $codExpected,
            'cod_collected' => $codCollected,
            'delivery_rate' => $deliveryRate,
        ];
    }

    protected function getPerDriverStats(array $filters): Collection
    {
        $rows = Delivery::query()
            ->with('assignedTo')
            ->selectRaw('deliveries.assigned_to')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when deliveries.status = ? then 1 else 0 end) as delivered", [Delivery::STATUS_DELIVERED])
            ->selectRaw("sum(case when deliveries.status = ? then 1 else 0 end) as undelivered", [Delivery::STATUS_UNDELIVERED])
            ->selectRaw("sum(case when deliveries.status = ? then 1 else 0 end) as cancelled", [Delivery::STATUS_CANCELLED])
            ->selectRaw("sum(case when deliveries.status in (?,?,?) then 1 else 0 end) as on_road", [
                Delivery::STATUS_ASSIGNED,
                Delivery::STATUS_OUT_FOR_DELIVERY,
                Delivery::STATUS_ARRIVING,
            ])
            ->selectRaw('coalesce(sum(deliveries.cod_amount), 0) as cod_expected')
            ->selectRaw("coalesce(sum(case when deliveries.status = ? then deliveries.cod_received else 0 end), 0) as cod_collected", [Delivery::STATUS_DELIVERED])
            ->groupBy('deliveries.assigned_to');

        $this->applyFilters($rows, $filters);

        return $rows->get()->map(function ($row) {
            $resolved = (int) $row->delivered + (int) $row->undelivered;
            $rate = $resolved > 0 ? round((int) $row->delivered / $resolved * 100, 1) : null;

            return [
                'driver' => $row->assignedTo ? [
                    'id' => $row->assignedTo->id,
                    'name' => $row->assignedTo->name,
                    'phone' => $row->assignedTo->phone,
                ] : null,
                'total' => (int) $row->total,
                'delivered' => (int) $row->delivered,
                'undelivered' => (int) $row->undelivered,
                'cancelled' => (int) $row->cancelled,
                'on_road' => (int) $row->on_road,
                'cod_expected' => (float) $row->cod_expected,
                'cod_collected' => (float) $row->cod_collected,
                'delivery_rate' => $rate,
            ];
        })->values('driver');
    }

    protected function getDailyChart(array $filters): array
    {
        [$startDate, $endDate] = $this->parseDateRange($filters);

        $days = [];
        for ($day = $startDate->copy()->startOfDay(); $day->lte($endDate); $day->addDay()) {
            $days[$day->format('Y-m-d')] = ['date' => $day->format('Y-m-d'), 'created' => 0, 'delivered' => 0];
        }

        $created = Delivery::query()
            ->selectRaw("date(deliveries.created_at) as day")
            ->selectRaw('count(*) as count')
            ->whereBetween('deliveries.created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->get();

        foreach ($created as $row) {
            $day = $row->day;
            if (isset($days[$day])) {
                $days[$day]['created'] = (int) $row->count;
            }
        }

        $deliveredEvents = DeliveryEvent::query()
            ->selectRaw("date(delivery_events.created_at) as day")
            ->selectRaw('count(*) as count')
            ->where('delivery_events.to_status', Delivery::STATUS_DELIVERED)
            ->whereBetween('delivery_events.created_at', [$startDate, $endDate])
            ->groupBy('day')
            ->get();

        foreach ($deliveredEvents as $row) {
            $day = $row->day;
            if (isset($days[$day])) {
                $days[$day]['delivered'] = (int) $row->count;
            }
        }

        return array_values($days);
    }

    protected function getDeliveries(array $filters)
    {
        $query = Delivery::query()
            ->with(['shipment', 'order', 'assignedTo'])
            ->leftJoin('shipments', 'shipments.id', '=', 'deliveries.shipment_id')
            ->leftJoin('orders', 'orders.id', '=', 'deliveries.order_id');

        $this->applyFilters($query, $filters);

        return $query->select('deliveries.*')
            ->orderByDesc('deliveries.created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Delivery $delivery) {
                return [
                    'id' => $delivery->id,
                    'order_number' => $delivery->order?->order_number,
                    'shipment_number' => $delivery->shipment?->shipment_number,
                    'driver' => $delivery->assignedTo ? [
                        'id' => $delivery->assignedTo->id,
                        'name' => $delivery->assignedTo->name,
                    ] : null,
                    'status' => $delivery->status,
                    'status_badge' => $delivery->status_badge,
                    'priority' => $delivery->priority,
                    'customer_phone' => $delivery->customer_phone,
                    'cod_amount' => $delivery->cod_amount !== null ? (float) $delivery->cod_amount : null,
                    'cod_received' => $delivery->cod_received !== null ? (float) $delivery->cod_received : null,
                    'recipient_name' => $delivery->recipient_name,
                    'assigned_at' => $delivery->created_at?->toDateTimeString(),
                    'scheduled_date' => $delivery->scheduled_date?->toDateTimeString(),
                ];
            });
    }

    /**
     * Clear the delivery report cache.
     */
    public function clearCache(): void
    {
        $this->cacheService->forget('delivery');
    }
}