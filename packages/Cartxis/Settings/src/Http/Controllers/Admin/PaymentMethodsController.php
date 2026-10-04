<?php

namespace Cartxis\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Cartxis\Core\Models\PaymentMethod;
use Cartxis\Settings\Http\Requests\SavePaymentMethodRequest;

class PaymentMethodsController extends Controller
{
    /**
     * Display a listing of payment methods.
     */
    public function index(): Response
    {
        $paymentMethods = PaymentMethod::supported()->orderBy('sort_order')->get();

        // Filter out payment methods whose extensions are inactive
        $paymentMethods = $paymentMethods->filter(function ($method) {
            // COD and bank_transfer don't require extensions
            if (in_array($method->code, ['cod', 'bank_transfer'])) {
                return true;
            }

            // For extension-based payment methods, check if extension is active
            $extensionCode = 'cartxis-' . $method->code;
            $extension = \Cartxis\Core\Models\Extension::where('code', $extensionCode)->first();
            
            return $extension && $extension->active;
        });

        return Inertia::render('Admin/Settings/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods->values(),
        ]);
    }

    /**
     * Get configuration form for a specific payment method type.
     */
    public function getConfigure(string $type): Response
    {
        // Try to find by code first (for custom gateways like razorpay), then by type
        $method = PaymentMethod::where('code', $type)
            ->orWhere('type', $type)
            ->first();

        if (!$method) {
            abort(404, 'Payment method not found');
        }

        if (! in_array($method->code, PaymentMethod::SUPPORTED_CODES, true)) {
            abort(404, 'Payment method not found');
        }

        return Inertia::render("Admin/Settings/PaymentMethods/Configure{$this->getComponentName($method->code)}", [
            'method' => $method,
            'gatewayMeta' => $this->getGatewayMeta($method->code),
        ]);
    }

    /**
     * Extra setup information a specific gateway's screen needs.
     *
     * HesabPay needs the owner to copy its webhook URL into the HesabPay
     * dashboard, so the page shows the exact URL. Guarded on the route
     * existing, because the extension may be switched off.
     */
    private function getGatewayMeta(string $code): ?array
    {
        if ($code !== 'hesabpay') {
            return null;
        }

        if (!Route::has('hesabpay.webhook')) {
            return null;
        }

        return [
            'webhookUrl' => route('hesabpay.webhook'),
            'sandboxUrl' => 'https://developers-sandbox.hesab.com/',
            'productionUrl' => 'https://developers.hesab.com/',
            'docsUrl' => 'https://docs.hesab.com/',
        ];
    }

    /**
     * Save payment method configuration.
     */
    public function save(SavePaymentMethodRequest $request, string $type)
    {
        // Try to find by code first (for custom gateways), then by type
        $method = PaymentMethod::where('code', $type)
            ->orWhere('type', $type)
            ->firstOrFail();

        // Update method with validated data
        $method->update($request->validated());

        return back()->with('success', "Payment method '{$method->name}' updated successfully.");
    }

    /**
     * Toggle payment method active status.
     */
    public function toggle(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active,
        ]);

        return back()->with('success', 'Payment method status updated.');
    }

    /**
     * Set payment method as default.
     */
    public function setDefault(PaymentMethod $paymentMethod)
    {
        PaymentMethod::where('is_default', true)->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        return back()->with('success', 'Default payment method updated.');
    }

    /**
     * Update sort order.
     */
    public function sort(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'sort_order' => 'required|integer|min:0',
        ]);

        $paymentMethod->update($validated);

        return back()->with('success', 'Sort order updated.');
    }

    /**
     * Get component name from type.
     */
    private function getComponentName(string $code): string
    {
        return match ($code) {
            'cod' => 'COD',
            'bank_transfer' => 'BankTransfer',
            'stripe' => 'Stripe',
            'razorpay' => 'Razorpay',
            'hesabpay' => 'HesabPay',
            'paypal' => 'PayPal',
            'payumoney' => 'PayUMoney',
            'phonepe' => 'PhonePe',
            default => 'Generic',
        };
    }
}
