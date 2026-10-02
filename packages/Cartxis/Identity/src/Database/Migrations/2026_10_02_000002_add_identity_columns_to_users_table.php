<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns on the account, and nothing more.
 *
 * The account is the fastest possible read of "is this person verified", and it
 * is the column the referral programme checks on every paid order, so it is
 * denormalised on purpose rather than counted from the verification table on
 * every order event.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'identity_verified_at')) {
                // Null means "not verified". Nothing has to be back-filled for an
                // existing account: until an admin approves a document, the
                // account has not proved who it is.
                $table->timestamp('identity_verified_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'identity_verification_id')) {
                // The single approved identity of this account.
                //
                // This is a plain indexed column rather than a foreign key on
                // purpose: SQLite silently ignores a foreign key added to a
                // table that already exists (SQLiteGrammar::compileForeign is a
                // no-op outside CREATE TABLE), so a constraint written this way
                // would hold in production and vanish in tests. The unique index
                // below is what actually enforces the rule we care about: a
                // users row can point at exactly one verification, and the
                // referenced row can only ever be pointed at by one account.
                $table->unsignedBigInteger('identity_verification_id')
                    ->nullable()
                    ->unique('users_identity_verification_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // Index first: on MySQL the index belongs to the table, but dropping it
        // by name keeps the statement safe on PostgreSQL too.
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'identity_verification_id')) {
                $table->dropUnique('users_identity_verification_unique');
                $table->dropColumn('identity_verification_id');
            }

            if (Schema::hasColumn('users', 'identity_verified_at')) {
                $table->dropColumn('identity_verified_at');
            }
        });
    }
};