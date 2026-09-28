<?php

declare(strict_types=1);

namespace Cartxis\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Service\Exceptions\ServiceBookingException;
use Cartxis\Service\Http\Requests\StoreServiceBookingRequest;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Services\ServiceBookingService;
use Cartxis\Service\Services\ServiceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ServiceBookingController extends Controller
{
    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected ServiceBookingService $bookings,
        protected ServiceSettings $settings,
    ) {}

    /**
     * Take a customer's request and turn it into a booked job.
     *
     * A failure here must not be a blank page: the reason is put under the
     * field it belongs to, so the customer can see which part to fix and fix it
     * without losing what they typed.
     */
    public function store(StoreServiceBookingRequest $request, Service $service): RedirectResponse
    {
        try {
            $booking = $this->bookings->book($service, $request->validated(), Auth::user());
        } catch (ServiceBookingException $e) {
            // A full slot, a closed booking window or a switched-off service are
            // all a problem with one field, and the service says which one.
            return back()
                ->withInput()
                ->withErrors([$e->field => $e->getMessage()]);
        } catch (Throwable $e) {
            // Anything unforeseen is our fault, not the customer's, so it is
            // logged in full and reported without giving away how it works.
            Log::error('Service booking failed unexpectedly', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'form' => 'Something went wrong on our side. Please try again, or call us.',
                ]);
        }

        // The phone number goes in the link so the guest, who has no account to
        // prove themselves with, can still see their own confirmation.
        return redirect()->route('services.booked', [
            'reference' => $booking->reference,
            'phone' => $booking->customer_phone,
        ]);
    }

    /**
     * The confirmation page, reached straight after booking.
     */
    public function booked(Request $request, string $reference): Response
    {
        $booking = ServiceBooking::query()
            ->whereRaw('LOWER(reference) = ?', [strtolower($reference)])
            ->firstOrFail();

        $this->guardAgainstSomeoneElsesBooking($request, $booking);

        return Inertia::render($this->themeResolver->resolve('Services/Booked'), [
            'booking' => $booking->load('service'),
            'settings' => [
                'coverage_note' => $this->settings->coverageNote(),
                'contact_phone' => $this->settings->contactPhone(),
                'contact_whatsapp' => $this->settings->contactWhatsapp(),
            ],
        ]);
    }

    /**
     * Someone who guessed a reference must not read someone else's address.
     */
    protected function guardAgainstSomeoneElsesBooking(Request $request, ServiceBooking $booking): void
    {
        // The signed-in customer who made it.
        if (Auth::check() && $booking->user_id === Auth::id()) {
            return;
        }

        // A guest who can give the phone number they booked with.
        $phone = (string) $request->query('phone', '');

        if ($phone !== '' && $this->phoneMatches($booking->customer_phone, $phone)) {
            return;
        }

        abort(403, 'You can only view a booking made with your own details.');
    }

    protected function phoneMatches(string $bookingPhone, string $given): bool
    {
        $normalise = fn (string $value) => ltrim(preg_replace('/\s+/', '', $value) ?? '', '0');

        return $normalise($bookingPhone) === $normalise($given);
    }
}
