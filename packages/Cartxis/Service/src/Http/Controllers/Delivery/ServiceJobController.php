<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Services\ServiceBookingStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The worker-facing half of the services feature.
 *
 * This deliberately sits inside the existing delivery portal: the same guard,
 * the same session, the same login page, the same role check. A worker who can
 * see a delivery can see a job, and there is no second app to install.
 *
 * There is no map and no location sharing here. A plumber going to a house does
 * not need to broadcast a map pin, so the job screen carries what a worker
 * actually needs on a doorstep: who, where, what, and how much.
 */
class ServiceJobController extends Controller
{
    public function __construct(protected ServiceBookingStatusService $statuses) {}

    /**
     * The worker's jobs, newest date first, with the ones still waiting to be
     * collected at the top.
     */
    public function index(): Response
    {
        $workerId = (int) Auth::id();

        $jobs = ServiceBooking::query()
            ->with(['service', 'events'])
            ->forWorker($workerId)
            ->whereIn('status', [
                ServiceBooking::STATUS_ASSIGNED,
                ServiceBooking::STATUS_IN_PROGRESS,
            ])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ServiceBooking $booking) => $booking->scheduled_date?->format('Y-m-d') ?? 'No date');

        return Inertia::render('Delivery/Jobs/Index', [
            'groups' => $jobs->map(fn ($group, $date) => [
                'date' => $date,
                'label' => $date === 'No date'
                    ? 'No date set'
                    : \Carbon\CarbonImmutable::parse($date)->format('l, j M Y'),
                'jobs' => $group->values(),
            ])->values(),
            'counts' => [
                'open' => ServiceBooking::query()->forWorker($workerId)->open()->count(),
                'in_progress' => ServiceBooking::query()->forWorker($workerId)
                    ->where('status', ServiceBooking::STATUS_IN_PROGRESS)->count(),
                'completed_today' => ServiceBooking::query()->forWorker($workerId)
                    ->where('status', ServiceBooking::STATUS_COMPLETED)
                    ->whereDate('completed_at', now()->toDateString())
                    ->count(),
            ],
        ]);
    }

    public function show(ServiceBooking $booking): Response
    {
        $this->guardJobIsMine($booking);

        return Inertia::render('Delivery/Jobs/Show', [
            'booking' => $booking->load(['service', 'events.actor']),
            'statusLabels' => ServiceBooking::STATUS_LABELS,
        ]);
    }

    public function start(ServiceBooking $booking): RedirectResponse
    {
        return $this->act($booking, fn () => $this->statuses->start($booking), 'Job started.');
    }

    /**
     * Finish the job. The amount is required, so money cannot quietly go
     * unrecorded.
     */
    public function complete(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount_collected' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount_collected.required' => 'Enter how much you collected.',
            'amount_collected.numeric' => 'Enter a number, for example 3000.',
            'amount_collected.min' => 'The amount cannot be negative.',
        ]);

        return $this->act(
            $booking,
            fn () => $this->statuses->complete(
                $booking,
                (float) $validated['amount_collected'],
                $validated['note'] ?? null,
            ),
            'Job finished. Thank you.'
        );
    }

    public function cancel(Request $request, ServiceBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Say why you could not do this job.',
        ]);

        return $this->act(
            $booking,
            fn () => $this->statuses->cancel($booking, $validated['reason']),
            'Job marked as not done. The office has been told.'
        );
    }

    /**
     * A worker may only touch their own jobs. This is the server-side guard;
     * the buttons they can see are only a convenience.
     */
    protected function guardJobIsMine(ServiceBooking $booking): void
    {
        if ((int) $booking->assigned_to !== (int) Auth::id()) {
            abort(403, 'This job is assigned to someone else.');
        }
    }

    /**
     * Run a status change, turning any refusal into a message the worker can
     * act on rather than an error page.
     */
    protected function act(ServiceBooking $booking, callable $action, string $success): RedirectResponse
    {
        $this->guardJobIsMine($booking);

        try {
            $action();
        } catch (Throwable $e) {
            Log::warning('Worker service job action refused', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
