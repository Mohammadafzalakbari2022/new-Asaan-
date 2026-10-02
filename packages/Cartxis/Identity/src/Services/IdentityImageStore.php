<?php

namespace Cartxis\Identity\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use RuntimeException;

/**
 * Stores the copy of the national ID, and nothing else.
 *
 * Three rules, all of them load-bearing:
 *
 *  1. It goes on the private disk. That disk has no url and no "serve" flag, so
 *     Laravel never registers a route that could hand the file to a stranger,
 *     and it is outside the directory storage:link exposes.
 *  2. The bytes are decoded and re-encoded rather than copied. That removes
 *     EXIF, which on a phone photo includes the GPS coordinates of the person's
 *     home, along with the camera model and anything else the file was carrying.
 *  3. The name on disk is generated here. The name the browser sent is never
 *     used for anything, so "shell.php" or "../../config/app.php" in the
 *     filename field changes nothing.
 */
class IdentityImageStore
{
    protected ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver());
    }

    /**
     * Disk name. Read from config so the archive can be moved without a code
     * change, but validated on the way in.
     */
    public function disk(): string
    {
        $disk = (string) config('identity.disk', 'identity_private');

        // A disk that serves files, or that lives behind a URL, would put a
        // national ID on the open internet. Refuse to use one rather than trust
        // that nobody has misconfigured it.
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'local') {
            throw new RuntimeException("Identity documents need a private local disk; [{$disk}] is not one.");
        }

        if (($config['serve'] ?? false) || ! empty($config['url'])) {
            throw new RuntimeException("Disk [{$disk}] is web accessible. Identity documents must not be stored on it.");
        }

        return $disk;
    }

    /**
     * Take an upload and put it on the private disk.
     *
     * @return array{path: string, disk: string, sha256: string}
     *
     * @throws RuntimeException when the file is not a real image, or is larger
     *                          than the pixel ceiling allows.
     */
    public function store(UploadedFile $file, int $userId): array
    {
        $this->assertLooksLikeAnImage($file);

        $extension = $this->extensionFor($this->detectMime($file));

        // Sharded by user so one account's documents are all in one folder and a
        // cleanup pass can find them without a database query per file.
        $directory = 'identity/'.$userId.'/'.now()->format('Y-m');

        // Two random components: the directory is guessable from the user id, so
        // the file name itself has to carry no information at all.
        $name = Str::random(40).'-'.Str::lower(Str::random(16)).'.'.$extension;

        $image = $this->images->read($file->getRealPath());

        $maxEdge = (int) config('identity.max_edge', 2000);

        if ($maxEdge > 0 && max($image->width(), $image->height()) > $maxEdge) {
            $image = $image->scaleDown(width: $maxEdge);
        }

        // Re-encoding is what strips the metadata: the returned bytes are a new
        // file built by the encoder, not the upload with fields edited out.
        $contents = $image->encodeByExtension($extension);

        $disk = $this->disk();

        Storage::disk($disk)->put($directory.'/'.$name, (string) $contents);

        return [
            'disk' => $disk,
            'path' => $directory.'/'.$name,
            'sha256' => hash('sha256', (string) $contents),
        ];
    }

    /**
     * Read a stored document back, for a reviewer.
     *
     * Returns null when the file has already been purged by the retention policy.
     */
    public function get(string $disk, string $path): ?string
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            return null;
        }

        return $filesystem->get($path);
    }

    /**
     * Delete a stored document. Used when a submission is superseded or when the
     * retention policy runs; the row itself is kept.
     */
    public function delete(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Absolute path, for tests and for the cleanup command. Never handed out in
     * a response.
     */
    public function absolutePath(string $disk, string $path): string
    {
        return Storage::disk($disk)->path($path);
    }

    /**
     * The mime type, read from the bytes rather than from the client's header or
     * its file name.
     */
    protected function detectMime(UploadedFile $file): string
    {
        $mime = (string) ($file->getMimeType() ?: '');

        if (! in_array($mime, (array) config('identity.allowed_mimes', []), true)) {
            throw new RuntimeException('Unsupported identity document type.');
        }

        return $mime;
    }

    protected function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Cheap structural checks before the expensive decode.
     *
     * The pixel ceiling matters as much as the byte ceiling: a few hundred
     * kilobytes can decode into a hundred megapixel image and take the worker
     * out with it.
     */
    protected function assertLooksLikeAnImage(UploadedFile $file): void
    {
        $maxDimension = (int) config('identity.max_dimension', 6000);

        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            throw new RuntimeException('The identity document is not a readable image.');
        }

        if ($maxDimension > 0 && (int) max($size[0], $size[1]) > $maxDimension) {
            throw new RuntimeException('The identity document is too large to process.');
        }
    }
}