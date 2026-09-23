<?php

/*
|--------------------------------------------------------------------------
| Akbari Development Group — Default / Fallback Brand
|--------------------------------------------------------------------------
|
| This is the ONE shared source of truth for the Akbari Development Group
| brand. It is used ONLY as the fallback: whenever a setting is empty on a
| layer, the brand shown is Akbari Development Group (name, logo, favicon,
| admin logo, mobile auth logo, OG image).
|
| A customer who configures their own branding in the dashboard — via the
| General Settings screen, stored through SettingService — completely
| overrides these defaults on every layer (storefront, admin, mobile).
|
| Rule: no customer information is hard-coded anywhere. What you configure
| as a customer is what appears; when nothing is configured the store shows
| the Akbari Development Group brand below.
|
| All paths here are storage-relative (served under /storage/), matching how
| customer-configured logo/favicon settings are stored, so the same frontend
| resolvers (/storage/<path>) work identically for both customer and
| fallback brand.
*/

return [
    // Default store/site name shown across storefront + mobile when the
    // customer has not configured their own site_name.
    'name' => 'Akbari Development Group',

    // Default storefront tagline / footer description.
    'tagline' => 'Building e-commerce, point-of-sale and custom software you can rely on.',

    // Default meta/OG description.
    'description' => 'Akbari Development Group — software solutions that work for your business.',

    // Default storefront + mobile logo (storage-relative). Renders at
    // /storage/logos/akbari-devlopment-group.jpeg.
    'logo' => 'logos/akbari-devlopment-group.jpeg',

    // Default admin dashboard logo (storage-relative).
    'admin_logo' => 'logos/akbari-devlopment-group.jpeg',

    // Default mobile auth-screen logo (storage-relative).
    'mobile_auth_logo' => 'logos/akbari-devlopment-group.jpeg',

    // Default site favicon (storage-relative).
    'favicon' => 'logos/akbari-favicon.png',

    // Default favicon served directly by the appearance middleware (public
    // asset, outside /storage/, for the blade <link rel="icon"> tag).
    'favicon_asset' => '/logos/asaan-favicon.png',

    // Default Open Graph / social share image (storage-relative).
    'og_image' => 'logos/akbari-devlopment-group.jpeg',
];
