<?php

namespace Cartxis\Shop\Http\Controllers\Account;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Cartxis\Shop\Http\Controllers\Controller;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Core\Services\SettingService;
use Cartxis\Cart\Models\Cart;
use Cartxis\Shop\Models\Order;
use Cartxis\Customer\Models\Customer;
use Cartxis\Referral\Services\ReferralCreditService;
use Cartxis\Referral\Support\Money;
use Cartxis\Referral\Services\ReferralSettings;

class ProfileController extends Controller
{
    protected ThemeViewResolver $themeResolver;

    public function __construct(ThemeViewResolver $themeResolver)
    {
        $this->themeResolver = $themeResolver;
    }

    /**
     * Display profile page.
     */
    public function show(Request $request)
    {
        return $this->edit($request);
    }

    /**
     * Show the profile edit form.
     */
    public function edit(Request $request)
    {
        return $this->themeResolver->inertia('Account/Profile/Edit', [
            'user' => [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'email_verified_at' => auth()->user()->email_verified_at,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
        ]);

        $user = auth()->user();
        $emailChanged = $user->email !== $validated['email'];

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // If email changed, mark as unverified
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.' . 
            ($emailChanged ? ' Please verify your new email address.' : ''));
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Update email preferences.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'newsletter_subscribed' => ['boolean'],
            'order_notifications' => ['boolean'],
            'promotional_emails' => ['boolean'],
        ]);

        Customer::where('user_id', auth()->id())
            ->update(['newsletter_subscribed' => $validated['newsletter_subscribed'] ?? false]);

        return back()->with('success', 'Email preferences updated successfully.');
    }

    /**
     * Public account-deletion info page (no auth required).
     * Required by Google Play Store / Apple App Store data-safety policies.
     */
    public function deletionInfo()
    {
        $settings = app(SettingService::class);
        $appUrl = rtrim(config('app.url'), '/');

        // Use support_email from admin settings, fall back to admin_email,
        // then fall back to the hardcoded support address.
        $supportEmail = $settings->get('support_email')
            ?: $settings->get('admin_email')
            ?: 'dev@wontonee.com';

        return $this->themeResolver->inertia('Account/DeletionInfo', [
            'appName'      => config('app.name'),
            'supportEmail' => $supportEmail,
            'appUrl'       => $appUrl,
        ]);
    }

    /**
     * Delete the user's account and associated data.
     *
     * Orders are anonymized (kept for business records), all other personal
     * data (cart, addresses, wishlist, customer profile, API tokens) is
     * permanently deleted.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = auth()->user();
        $userId = $user->id;

        // Checked before anything at all is destroyed. Referral credit belongs to
        // this person, so deleting the account would quietly destroy money the
        // shop owes them. Locked credit counts: it is still theirs, just not
        // spendable yet.
        $referralSettings = app(ReferralSettings::class);

        if ($referralSettings->blocksAccountDeletion()) {
            $balances = app(ReferralCreditService::class)->balancesFor($user);

            if ($balances['available'] > 0 || $balances['locked'] > 0) {
                return back()->with('error', $this->referralBalanceMessage($balances));
            }
        }

        // 1. Anonymize orders — keep for accounting/legal records but remove PII
        //    Note: customer_id is auto-nullified by FK nullOnDelete when customer is deleted
        Order::where('user_id', $userId)->update([
            'user_id'        => null,
            'customer_email' => null,
            'customer_phone' => null,
        ]);

        // 2. Delete cart
        Cart::where('user_id', $userId)->delete();

        // 3. Delete customer profile and related data (addresses + wishlist)
        $customer = Customer::where('user_id', $userId)->first();
        if ($customer) {
            $customer->wishlists()->delete();
            $customer->addresses()->delete();
            $customer->delete();
        }

        // 4. Revoke all API tokens (Sanctum)
        $user->tokens()->delete();

        // 5. Log out and destroy session before deleting the user row
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 6. Permanently delete the user
        $user->delete();

        return redirect('/')->with('success', 'Your account has been permanently deleted.');
    }

    /**
     * Why the account cannot be deleted yet, in the customer's own money.
     */
    protected function referralBalanceMessage(array $balances): string
    {
        // Money::inSentence() reads the store's real currency and its real
        // decimal places. It used to be config('currency.symbol', 'AFN'), which
        // had no config file behind it, so this message claimed AFN on a shop
        // priced in any other currency.
        $available = Money::inSentence((float) $balances['available']);
        $locked = Money::inSentence((float) $balances['locked']);

        if ($balances['locked'] > 0 && $balances['available'] <= 0) {
            return "You still have {$locked} of referral credit waiting to unlock. "
                . 'Please use it or contact support before deleting your account.';
        }

        if ($balances['locked'] > 0) {
            return "You still have {$available} of referral credit to spend and "
                . "{$locked} still waiting to unlock. "
                . 'Please use it or contact support before deleting your account.';
        }

        return "You still have {$available} of referral credit to spend. "
            . 'Please use it or contact support before deleting your account.';
    }
}
