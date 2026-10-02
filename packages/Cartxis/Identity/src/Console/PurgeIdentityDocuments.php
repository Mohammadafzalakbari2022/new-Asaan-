<?php

namespace Cartxis\Identity\Console;

use Cartxis\Identity\Services\IdentityRetention;
use Cartxis\Identity\Services\IdentitySettings;
use Illuminate\Console\Command;

/**
 * Deletes identity document images that are past their retention window.
 *
 * Schedule it daily. The rows stay, because the fingerprint on them is what
 * stops the same Tazkira opening a second account years later; only the pictures
 * of documents nobody is going to look at again are removed.
 *
 *   php artisan identity:purge-documents
 *   php artisan identity:purge-documents --days=7
 *   php artisan identity:purge-documents --verification=42
 */
class PurgeIdentityDocuments extends Command
{
    protected $signature = 'identity:purge-documents
                            {--days= : Override the retention window in days}
                            {--verification= : Purge one document by id and stop}';

    protected $description = 'Delete identity document images that are past the retention window';

    public function handle(IdentityRetention $retention, IdentitySettings $settings): int
    {
        if ($id = $this->option('verification')) {
            $deleted = $retention->purgeVerification((int) $id);

            $this->info($deleted
                ? 'Document image deleted. The record was kept.'
                : 'Nothing to delete: no image, or it had already been purged.');

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null ? (int) $this->option('days') : $settings->retentionDays();

        if ($days <= 0) {
            $this->warn('Retention is set to zero days, so nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = $retention->purgeExpired($days);

        $this->info("Deleted {$deleted} identity document image(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}