<?php

namespace Cartxis\Identity\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The only place a national ID is turned into something storable.
 *
 * Two values come out of a number, and they are used for two different jobs:
 *
 *  - an encrypted copy, so a reviewer can read the number and nobody else can;
 *  - a keyed fingerprint, so the database can answer "has this Tazkira been
 *    seen before?" without ever being able to answer "what is this Tazkira?".
 *
 * The fingerprint is an HMAC, not a plain hash. A plain hash of a number this
 * short is trivially reversed: there are only so many Afghan ID numbers, so an
 * attacker with a database dump could hash a list and find them all. An HMAC
 * with a key nobody in the database has cannot be attacked that way.
 */
class IdentityCrypto
{
    /**
     * Encrypt for storage. Never logged, never returned in a response.
     */
    public function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    /**
     * Read a stored value back.
     *
     * Returns null rather than throwing when the ciphertext cannot be read,
     * which happens if APP_KEY was rotated or a row was truncated. A reviewer
     * seeing "unreadable" can re-request the document; a reviewer seeing a stack
     * trace learns nothing useful and cannot act on it.
     */
    public function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (DecryptException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The unique fingerprint of a national ID.
     *
     * Deterministic, so the same number always produces the same fingerprint and
     * can be looked up, and keyed, so the value cannot be reversed from the
     * database alone.
     */
    public function fingerprint(string $nationalId): string
    {
        return hash_hmac('sha256', $this->domain().$this->normalise($nationalId), $this->key());
    }

    /**
     * Compare a number against a stored fingerprint without decrypting it.
     *
     * Uses hash_equals so that a caller cannot learn the fingerprint a character
     * at a time by timing.
     */
    public function matches(?string $storedFingerprint, string $nationalId): bool
    {
        if ($storedFingerprint === null || $storedFingerprint === '') {
            return false;
        }

        return hash_equals($storedFingerprint, $this->fingerprint($nationalId));
    }

    /**
     * Fold away the ways people write the same number.
     *
     * "123 456 789", "123-456-789" and "123456789" are one person's document,
     * and treating them as three would let a customer open three accounts by
     * typing spaces. Case is folded for the same reason on the document types
     * that carry letters.
     */
    public function normalise(string $nationalId): string
    {
        $collapsed = preg_replace('/[^A-Za-z0-9]+/', '', $nationalId) ?? '';

        return mb_strtoupper($collapsed, 'UTF-8');
    }

    /**
     * A fingerprint of something that is not a national ID, for logs and error
     * messages that need to say which document was involved.
     *
     * Safe to write to a log: it identifies the record without carrying the
     * number it belongs to.
     */
    public function shortFingerprint(?string $storedFingerprint): string
    {
        if ($storedFingerprint === null || $storedFingerprint === '') {
            return 'none';
        }

        return substr($storedFingerprint, 0, 8);
    }

    /**
     * Part of the key derivation, so an ID fingerprint can never collide with
     * another keyed value elsewhere in the app.
     */
    private function domain(): string
    {
        return 'identity.national_id.v1|';
    }

    private function key(): string
    {
        $configured = (string) config('identity.fingerprint_key', '');

        if ($configured !== '') {
            return $configured;
        }

        $appKey = (string) config('app.key', '');

        // A key derived from APP_KEY, so the fingerprint uses a different key
        // material to anything else in the app without needing a second secret.
        return hash_hmac('sha256', 'identity.fingerprint.v1', $appKey, true);
    }
}