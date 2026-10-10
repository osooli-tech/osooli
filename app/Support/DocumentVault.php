<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ParcelPhoto;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deeds, sketches and photos encrypted at rest (AES-256-GCM).
 *
 * A sealed file starts with a marker, so sealed and not-yet-sealed files can
 * sit side by side: every read goes through open(), which returns a plain
 * file unchanged. That keeps the site working while existing files are being
 * encrypted, and makes sealing safe to repeat.
 *
 * Nothing outside this class should read a document's bytes from disk.
 */
final class DocumentVault
{
    /** No real PDF or image starts with these four bytes. */
    private const MARKER = 'SKV1';

    private const CIPHER = 'aes-256-gcm';

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    /** Separates this key from every other use of the application key. */
    private const KEY_CONTEXT = 'documents.vault';

    /** @var array<string, string> */
    private const MIME_TYPES = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp',
    ];

    public static function isSealed(string $bytes): bool
    {
        return str_starts_with($bytes, self::MARKER);
    }

    public static function seal(string $plain): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_BYTES);

        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt a document.');
        }

        return self::MARKER.$nonce.$tag.$cipher;
    }

    /** The file's real bytes; a file that was never sealed comes back as it is. */
    public static function open(string $stored): string
    {
        if (! self::isSealed($stored)) {
            return $stored;
        }

        $offset = strlen(self::MARKER);
        $nonce = substr($stored, $offset, self::NONCE_BYTES);
        $tag = substr($stored, $offset + self::NONCE_BYTES, self::TAG_BYTES);
        $cipher = substr($stored, $offset + self::NONCE_BYTES + self::TAG_BYTES);

        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plain === false) {
            // Wrong key or a tampered file — never hand back garbage as a deed.
            throw new RuntimeException('A stored document could not be decrypted.');
        }

        return $plain;
    }

    /** A document's real bytes, or null when its file is gone. */
    public static function read(ParcelPhoto $photo): ?string
    {
        $location = $photo->storageLocation();
        $disk = Storage::disk($location['disk']);

        return $disk->exists($location['path']) ? self::open((string) $disk->get($location['path'])) : null;
    }

    /**
     * Encrypts a stored file where it lies. Returns false when it already was.
     * The sealed copy is checked to open back to the same bytes before it
     * replaces the original, so a failure never costs a document.
     */
    public static function sealOnDisk(string $diskName, string $path): bool
    {
        $disk = Storage::disk($diskName);
        $plain = (string) $disk->get($path);

        if (self::isSealed($plain)) {
            return false;
        }

        $sealed = self::seal($plain);

        if (! hash_equals(hash('sha256', $plain), hash('sha256', self::open($sealed)))) {
            throw new RuntimeException("Sealing {$path} did not round-trip; the file was left as it is.");
        }

        $disk->put($path, $sealed);

        return true;
    }

    /**
     * The decrypted file as an HTTP response: a download under its own name,
     * or shown inline (a site photo in a page). 404 when the file is gone.
     *
     * @param  array<string, string>  $headers
     */
    public static function response(ParcelPhoto $photo, bool $download, array $headers = []): Response
    {
        $bytes = self::read($photo);
        abort_if($bytes === null, 404);

        $extension = strtolower(pathinfo($photo->storageLocation()['path'], PATHINFO_EXTENSION));
        $headers += ['Content-Type' => self::MIME_TYPES[$extension] ?? 'application/octet-stream'];

        return $download
            ? response()->streamDownload(static function () use ($bytes): void {
                echo $bytes;
            }, $photo->downloadName(), $headers)
            : response($bytes, 200, $headers);
    }

    private static function key(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7), true) : $appKey;

        return hash_hkdf('sha256', $raw, 32, self::KEY_CONTEXT);
    }
}
