<?php

namespace Cartxis\Identity\Services;

/**
 * Typed access to the package config, so no other class reads config('identity')
 * directly and every default lives in one file.
 */
class IdentityConfig
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = config('identity.'.$key, $default);

        return $value ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimes(): array
    {
        $mimes = $this->get('allowed_mimes', ['image/jpeg', 'image/png', 'image/webp']);

        return is_array($mimes) ? array_values(array_map('strval', $mimes)) : [];
    }

    /**
     * Disk the documents are stored on.
     */
    public function disk(): string
    {
        return (string) $this->get('disk', 'identity_private');
    }

    /**
     * Longest edge an upload may keep after re-encoding.
     */
    public function maxEdge(): int
    {
        return $this->int('max_edge', 2000);
    }

    /**
     * Largest pixel dimension accepted at all.
     */
    public function maxDimension(): int
    {
        return $this->int('max_dimension', 6000);
    }

    public function maxUploadKb(): int
    {
        return $this->int('max_upload_kb', 4096);
    }

    /**
     * Pending submissions allowed per account at once.
     */
    public function maxPendingPerUser(): int
    {
        return max(1, $this->int('max_pending_per_user', 1));
    }
}