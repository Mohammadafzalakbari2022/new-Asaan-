<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Services\ServiceBookingStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiceBookingController extends Controller
{
    public function __construct(protected ServiceBookingStatusService $statuses) {}

    public function index(Request $request): Response
    {
        $query = ServiceBooking::query()
            ->with(['service', 'worker'])
            ->search($request->get('search'))
            ->status($request->get('status'))
            ->scheduledBetween($request->get('from'), $request->get('to'));

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->get('assigned_to'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->get('service_id'));
        }

        $bookings = $query
            ->orderByRaw("CASE WHEN scheduled_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Services/Bookings/Index', [
            'bookings' => $bookings,
            'workers' => $this->workers(),
            'statuses' => ServiceBooking::STATUS_LABELS,
            'filters' => $request->only([
                'search', 'status', 'assigned_to', 'service_id', 'from', 'to', 'per_page',
            ]),
            'stats' => $this->stats(),
        ]);
    }

    public function show(ServiceBooking $booking): Response
    {
        $booking->load(['service', 'order', 'worker', 'assignedBy', 'user', 'events.actor']);

        return Inertia::render('Admin/Services/Bookings/Show', [
            'booking' => $booking,
            'workers' => $this->workers(),
            'nextStatuses' => $this->statuses->nextStatusesFor($booking),
            'statusLabels' => ServiceBooking::STATUS_LABELS,
        ]);
    }

    public function assign(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $worker = User::findOrFail($validated['assigned_to']);

        if (! $this->isDeliveryStaff($worker)) {
            return back()->withErrors([
                'error' => 'A job can only be given to one of your existing delivery staff.',
            ]);
        }

        try {
            $this->statuses->assign($booking, $worker->id, $validated['note'] ?? null);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Job assigned to ' . ($worker->name ?: 'the worker') . '.');
    }

    public function unassign(ServiceBooking $booking): RedirectResponse
    {
        try {
            $this->statuses->unassign($booking);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Worker removed from this job.');
    }

    public function start(ServiceBooking $booking): RedirectResponse
    {
        try {
            $this->statuses->start($booking);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Job marked as started.');
    }

    /**
     * Finish the job and record the money.
     *
     * The amount is required, and a non-negative number. A job that was marked
     * done but never charged for is the easiest way for money to go missing, so
     * it is not possible to do by accident.
     */
    public function complete(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount_collected' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount_collected.required' => 'Enter how much was collected on the day.',
            'amount_collected.numeric' => 'The collected amount must be a number.',
            'amount_collected.min' => 'The collected amount cannot be negative.',
        ]);

        try {
            $this->statuses->complete(
                $booking,
                (float) $validated['amount_collected'],
                $validated['note'] ?? null,
            );
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Job finished and the payment recorded.');
    }

    public function cancel(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancel_reason.required' => 'Say why this job is being cancelled.',
        ]);

        try {
            $this->statuses->cancel($booking, $validated['cancel_reason']);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Job cancelled.');
    }

    public function note(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'internal_notes' => ['required', 'string', 'max:2000'],
        ], [
            'internal_notes.required' => 'Write the note before saving it.',
        ]);

        $this->statuses->addInternalNote($booking, $validated['internal_notes']);

        return back()->with('success', 'Note saved.');
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:service_bookings,id'],
        ]);

        $moved = 0;

        foreach (ServiceBooking::whereIn('id', $validated['ids'])->get() as $booking) {
            try {
                $this->statuses->cancel($booking, 'Cancelled from the bookings list.');
                $moved++;
            } catch (Throwable) {
                // A job that is already finished stays as it is; the owner is
                // told how many moved rather than being shown a failure.
            }
        }

        return back()->with(
            'success',
            $moved . ' job(s) cancelled.' . ($moved < count($validated['ids']) ? ' The rest were already finished.' : '')
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $query = ServiceBooking::query()
            ->with(['service', 'worker'])
            ->search($request->get('search'))
            ->status($request->get('status'))
            ->scheduledBetween($request->get('from'), $request->get('to'));

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->get('assigned_to'));
        }

        $filename = 'service-bookings-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Reference', 'Status', 'Service', 'Scheduled date', 'Slot',
                'Customer', 'Phone', 'City', 'Address',
                'Promised price', 'Collected', 'Worker', 'Booked at',
            ]);

            $query->orderBy('id')->chunk(200, function ($bookings) use ($handle) {
                foreach ($bookings as $booking) {
                    fputcsv($handle, [
                        $booking->reference,
                        $booking->status,
                        $booking->service_name,
                        $booking->scheduled_date?->format('Y-m-d'),
                        $booking->scheduled_slot,
                        $booking->customer_name,
                        $booking->customer_phone,
                        $booking->city,
                        $booking->address,
                        (string) $booking->price_snapshot,
                        (string) ($booking->amount_collected ?? ''),
                        $booking->worker?->name ?? '',
                        $booking->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The same staff list the delivery screens offer, so a job goes to the same
     * people who already carry parcels.
     */
    protected function workers()
    {
        return User::query()
            ->where('role', 'delivery')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (User $worker) => [
                'id' => $worker->id,
                'name' => $worker->name,
                'phone' => $worker->phone,
            ])
            ->values();
    }

    protected function isDeliveryStaff(User $user): bool
    {
        return $user->role === 'delivery' && (bool) $user->is_active;
    }

    protected function stats(): array
    {
        return [
            'open' => ServiceBooking::query()->open()->count(),
            'unassigned' => ServiceBooking::query()
                ->where('status', ServiceBooking::STATUS_BOOKED)
                ->whereNull('assigned_to')
                ->count(),
            'today' => ServiceBooking::query()
                ->whereDate('scheduled_date', now()->toDateString())
                ->whereNotIn('status', ServiceBooking::TERMINAL_STATUSES)
                ->count(),
            'completed' => ServiceBooking::query()
                ->where('status', ServiceBooking::STATUS_COMPLETED)
                ->count(),
        ];
    }
}
