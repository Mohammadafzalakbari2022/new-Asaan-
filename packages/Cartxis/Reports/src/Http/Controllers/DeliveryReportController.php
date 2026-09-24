<?php

namespace Cartxis\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Reports\Services\DeliveryReportService;
use Cartxis\Sales\Models\Delivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryReportController extends Controller
{
    public function __construct(
        protected DeliveryReportService $service
    ) {}

    /**
     * Per-driver delivery report.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['start_date', 'end_date', 'driver_id', 'status']);

        $reportData = $this->service->getReportData($filters);

        return Inertia::render('Admin/Reports/Delivery/Index', [
            'statistics' => $reportData['statistics'],
            'perDriver' => $reportData['perDriver'],
            'chart' => $reportData['chart'],
            'deliveries' => $reportData['deliveries'],
            'filters' => $reportData['filters'],
            'drivers' => $reportData['drivers'],
            'statuses' => collect(Delivery::getStatuses())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * Export the filtered deliveries as CSV.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'driver_id', 'status']);

        $rows = $this->service->exportRows($filters);

        $filename = 'delivery_report_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Delivery ID',
                'Order Number',
                'Shipment Number',
                'Driver',
                'Status',
                'Priority',
                'Customer Phone',
                'COD Expected',
                'COD Collected',
                'Recipient',
                'Assigned At',
                'Scheduled Date',
                'Delivered At',
                'Failure Reason',
                'Notes',
            ]);

            foreach ($rows as $row) {
                fputcsv($file, [
                    $row['delivery_id'],
                    $row['order_number'],
                    $row['shipment_number'],
                    $row['driver'],
                    $row['status'],
                    $row['priority'],
                    $row['customer_phone'],
                    $row['cod_expected'],
                    $row['cod_collected'],
                    $row['recipient'],
                    $row['assigned_at'],
                    $row['scheduled_date'],
                    $row['delivered_at'],
                    $row['failure_reason'],
                    $row['notes'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}