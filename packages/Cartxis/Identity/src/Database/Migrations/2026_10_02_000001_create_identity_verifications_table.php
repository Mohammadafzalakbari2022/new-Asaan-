<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per submission of a national ID (Tazkira) for review.
 *
 * Rows are kept forever even after the image is deleted, because the row is
 * the anti-fraud record: the fingerprint is what stops one Tazkira number being
 * used to open a second account, and that has to survive the image cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // The national ID is never stored, logged or returned in the clear.
            $table->text('national_id_encrypted');

            // Keyed HMAC of the same number. Unique, so a single Tazkira can
            // back exactly one account for the lifetime of the install. This is
            // the constraint the whole feature exists to hold, and it is in the
            // database rather than in PHP so that two simultaneous submissions
            // cannot both slip past an application-level check.
            $table->string('national_id_fingerprint', 64)
                ->unique('identity_verifications_fingerprint_unique');

            $table->text('full_name_encrypted');
            $table->text('father_name_encrypted')->nullable();
            $table->text('date_of_birth_encrypted')->nullable();

            // Only "tazkira" today. Kept as a column because Afghan ID documents
            // also exist as passports and voting cards, and rewriting history
            // is harder than adding a value.
            $table->string('document_type', 32)->default('tazkira');

            // Private disk, never a URL. The disk name is stored per row so that
            // a future move of the whole archive is a config change.
            $table->string('image_disk', 64);
            $table->string('image_path', 512);

            // Digest of the decoded pixels. Catches the same photograph being
            // re-uploaded to a second account under a different number, and
            // proves later that the file an admin reviewed is the file stored.
            $table->string('image_sha256', 64)->nullable();

            // Set when the image has been deleted by the retention policy. The
            // row stays; only the picture goes.
            $table->timestamp('image_purged_at')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Shown to the customer, so it has to say something useful.
            $table->text('rejection_reason')->nullable();

            // Staff-only note. Never rendered on a customer page.
            $table->string('review_note', 255)->nullable();

            // Reserved for face matching, which is OFF. Present so that turning
            // it on later is a setting change and not a migration on a table
            // that already holds personal documents.
            $table->string('face_match_status', 32)->nullable();
            $table->decimal('face_match_score', 5, 4)->nullable();
            $table->string('face_match_reference_path', 512)->nullable();

            $table->timestamps();

            // The review queue: oldest pending first.
            $table->index(['status', 'created_at'], 'identity_verifications_queue_index');

            // One person, many submissions (a rejection can be resubmitted), so
            // this is deliberately NOT unique.
            $table->index('user_id');

            $table->index('image_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};