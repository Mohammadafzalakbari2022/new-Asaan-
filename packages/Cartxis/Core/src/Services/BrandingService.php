<?php

namespace Cartxis\Core\Services;

use Illuminate\Support\Facades\Storage;

/**
 * BrandingService
 *
 * THE one shared resolver for brand identity across every layer — storefront,
 * admin dashboard and the mobile API. Each layer already injects SettingService,
 * so this thin wrapper is the single chokepoint for "customer value, or the
 * Akbari Development Group fallback".
 *
 * Layer rules:
 *   - Storefront (web + mobile): customer's configured branding when set,
 *     otherwise the Akbari Development Group default from config/branding.php.
 *   - Admin dashboard: always reads its own admin_logo / site_name, falling back
 *     to the same shared defaults when empty.
 *
 * The dashboard Settings screen remains the ONLY mechanism for configuring
 * customer branding; nothing here hard-codes customer information. Akbari
 * Development Group appears only when the customer has not configured their
 * own branding values.
 */
class BrandingService
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Branded store/site name.
     */
    public function name(): string
    {
        return (string) ($this->settingService->get('site_name') ?: config('branding.name', 'Akbari Development Group'));
    }

    /**
     * Branded store tagline.
     */
    public function tagline(): string
    {
        return (string) ($this->settingService->get('site_tagline') ?: config('branding.tagline', ''));
    }

    /**
     * Branded short description / meta description.
     */
    public function description(): string
    {
        return (string) ($this->settingService->get('site_tagline') ?: config('branding.description', config('branding.tagline', '')));
    }

    /**
     * Branded storefront logo — the storage-relative path as used across
     * the theme (rendered as /storage/<path>), or null if the customer
     * explicitly cleared their logo.
     */
    public function logo(): ?string
    {
        return $this->storagePath('site_logo', 'logos/akbari-devlopment-group.jpeg');
    }

    /**
     * Branded site favicon — storage-relative path.
     */
    public function favicon(): ?string
    {
        return $this->storagePath('site_favicon', 'logos/akbari-favicon.png');
    }

    /**
     * Branded admin-dashboard logo — storage-relative path.
     */
    public function adminLogo(): ?string
    {
        return $this->storagePath('admin_logo', 'logos/akbari-devlopment-group.jpeg');
    }

    /**
     * Branded mobile auth-screen logo — storage-relative path.
     */
    public function mobileAuthLogo(): ?string
    {
        return $this->storagePath('mobile_auth_logo', 'logos/akbari-devlopment-group.jpeg');
    }

    /**
     * Full public URL for a storage-relative brand asset.
     */
    public function assetUrl(string $storagePath): string
    {
        return Storage::disk('public')->url(ltrim($storagePath, '/'));
    }

    /**
     * Resolve a branded asset: customer's stored value when set and
     * non-empty, otherwise the shared fallback file from config/branding.php.
     */
    protected function storagePath(string $settingKey, string $fallback): ?string
    {
        $value = (string) $this->settingService->get($settingKey, '');

        if ($value !== '') {
            return $value;
        }

        return $fallback;
    }
}
