<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralLinkService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * The optional field a customer types into when the link did not survive
     * (it was pasted as plain text, or the app stripped the query string).
     */
    public const REFERRAL_FIELD = 'referral_code';

    protected ThemeViewResolver $themeResolver;

    public function __construct(ThemeViewResolver $themeResolver)
    {
        $this->themeResolver = $themeResolver;
    }

    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render($this->themeResolver->resolve('Auth/Register'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // A referral code is a bonus, never a condition. It is only checked
            // for being text, so a typo or an invented code cannot stop someone
            // creating an account.
            self::REFERRAL_FIELD => ['nullable', 'string', 'max:64'],
        ]);

        // Remembered before the account is created, because the referral is
        // written by the model observer as the row is inserted.
        $typedCode = $this->acceptTypedReferralCode($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->discardUnusableTypedCode($user, $typedCode);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('shop.account.dashboard');
    }

    /**
     * Take the code somebody typed and hand it to the link service, which is
     * where every other code comes from.
     *
     * An unknown or unusable code is quietly ignored: losing a referral is a
     * small problem, refusing a sale over a mistyped code is a bigger one.
     */
    protected function acceptTypedReferralCode(Request $request): ?string
    {
        $typed = trim((string) $request->input(self::REFERRAL_FIELD, ''));

        if ($typed === '') {
            return null;
        }

        try {
            $codes = app(ReferralCodeService::class);

            // Finds nothing for an unknown, disabled or self-entered code.
            if (! $codes->findByCode($typed)) {
                return null;
            }

            app(ReferralLinkService::class)->rememberPendingCode($typed);

            return $codes->normalise($typed);
        } catch (\Throwable $e) {
            // Nothing here is worth failing a signup over.
            Log::warning('Typed referral code could not be read', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Drop a typed code that produced no referral, so it cannot be silently
     * applied to a later, unrelated signup on the same browser.
     */
    protected function discardUnusableTypedCode(User $user, ?string $typedCode): void
    {
        if ($typedCode === null) {
            return;
        }

        try {
            if (! app(ReferralLinkService::class)->referrerFor($user->id)) {
                app(ReferralLinkService::class)->forgetPendingCode();
            }
        } catch (\Throwable $e) {
            Log::warning('Typed referral code could not be cleared', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
