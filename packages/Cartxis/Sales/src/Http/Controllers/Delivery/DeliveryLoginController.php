<?php

namespace Cartxis\Sales\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Cartxis\Sales\Http\Requests\DeliveryLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryLoginController extends Controller
{
    /**
     * Display the delivery login view (rendered from the shared
     * Admin/Auth/Login page via its "Delivery Person" tab).
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'tab' => 'delivery',
        ]);
    }

    /**
     * Handle an incoming delivery authentication request.
     */
    public function store(DeliveryLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        if (Auth::guard('delivery')->attempt(
            $request->only('email', 'password')
        )) {
            $driver = Auth::guard('delivery')->user();

            $isDelivery = (string) ($driver->role ?? '') === 'delivery';
            $isActive = (bool) $driver->is_active;

            if (!$isDelivery) {
                Auth::guard('delivery')->logout();
                $request->hitRateLimiter();
                throw ValidationException::withMessages([
                    'email' => __('You do not have a delivery account.'),
                ]);
            }

            if (!$isActive) {
                Auth::guard('delivery')->logout();
                $request->hitRateLimiter();
                throw ValidationException::withMessages([
                    'email' => __('Your delivery account has been deactivated.'),
                ]);
            }

            $request->clearRateLimiter();
            $request->session()->regenerate();

            return redirect()->intended(route('delivery.dashboard'));
        }

        $request->hitRateLimiter();

        throw ValidationException::withMessages([
            'email' => __('The provided credentials do not match our records.'),
        ]);
    }

    /**
     * Destroy an authenticated delivery session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('delivery')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/delivery/login');
    }
}