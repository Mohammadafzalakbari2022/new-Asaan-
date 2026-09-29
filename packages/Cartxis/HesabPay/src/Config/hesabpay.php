<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HesabPay API Base URL
    |--------------------------------------------------------------------------
    |
    | HesabPay runs two environments. Sandbox is for development, production
    | takes real money. The value is derived from the payment method's
    | "mode" configuration key, so an admin switching modes cannot end up
    | pointing live keys at the sandbox.
    |
    | Docs: https://docs.hesab.com/
    |
    */

    'base_url' => [
        'sandbox' => 'https://api-sandbox.hesab.com',
        'production' => 'https://api.hesab.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Fallback API Key
    |--------------------------------------------------------------------------
    |
    | Optional. Most stores configure the key in the admin panel instead. This
    | is only consulted when the admin has not saved one, which keeps the
    | gateway usable in a deployment that manages secrets through env vars.
    |
    */

    'env_api_key' => env('HESABPAY_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Environment
    |--------------------------------------------------------------------------
    |
    | Used until the admin picks one on the configuration screen. "sandbox" is
    | the safe default: it cannot charge a real customer by accident.
    |
    */

    'default_mode' => env('HESABPAY_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for a HesabPay API response. Kept short so a slow
    | gateway shows an error to the customer instead of hanging checkout.
    |
    */

    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Webhook Timestamp Tolerance
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    |
    | HesabPay does not document a maximum webhook age. The signature endpoint
    | is the authority on authenticity, so this value is informational only
    | and is not used to reject requests.
    |
    */

    'webhook_timestamp_tolerance' => 900,

];
