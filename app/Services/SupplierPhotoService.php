<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Photos for supplier rooms, read from Drive with the service account.
 *
 * The Drive folders are deliberately never shared publicly, so the browser can
 * never fetch them directly. Instead we resolve a folder to an ordered list of
 * file ids here, and stream the bytes through our own proxy route. Same approach
 * as the supplier intake in Sourcing-x: no Drive sharing setting is needed.
 */
class SupplierPhotoService
{
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /** Files that are never listings photos. */
    private const SKIP = '/(key\s*box|keybox|\bold\b|\bvideo\b|floor\s*plan|\bepc\b|inventory|deleted)/i';

    private const TOTAL_CAP = 10;

    /** Allowed file ids, so the proxy cannot be used to fetch arbitrary Drive files. */
    private const ALLOWED_KEY = 'supplier_photos_allowed_ids';

    protected ?GoogleDrive $drive = null;

    protected function drive(): ?GoogleDrive
    {
        if ($this->drive) {
            return $this->drive;
        }

        $credentials = config('services.supplier_targets.credentials_path');
        if (! $credentials) {
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setScopes([GoogleDrive::DRIVE_READONLY]);
            $client->setAuthConfig($credentials);
            return $this->drive = new GoogleDrive($client);
        } catch (\Throwable $e) {
            Log::error('Drive client init failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Ordered image file ids for a room, room photos first so the card cover is
     * the actual room rather than a communal area.
     *
     * @return array<int, string>
     */
    public function photosForRoom(?string $folderUrl, ?string $roomCode): array
    {
        $folderId = $this->folderIdFromUrl($folderUrl);
        if (! $folderId) {
            return [];
        }

        $cacheKey = 'supplier_photos_' . sha1($folderId . '|' . (string) $roomCode);

        // A week, not twelve hours. Folder contents barely change, and every
        // expiry used to land the next page request with a hundred Drive calls
        // to make while someone waited for the listings to load.
        $ids = Cache::remember($cacheKey, now()->addDays(7), function () use ($folderId, $roomCode) {
            try {
                return $this->resolve($folderId, $roomCode);
            } catch (\Throwable $e) {
                Log::warning('Drive photo lookup failed', ['folder' => $folderId, 'error' => $e->getMessage()]);
                return [];
            }
        });

        // Re-affirm on a cache hit too: resolve() is what normally allow-lists
        // these ids, and it does not run when the ids come from the cache.
        $this->allowIds($ids);

        return $ids;
    }

    /**
     * The same lookup, but never touching the network: null on a cache miss.
     *
     * Serving a page must not depend on someone else's API. A listing whose
     * folder has not been resolved yet simply shows without photographs until
     * photos:warm fills it in, which is a far better failure than a blank page.
     */
    public function cachedPhotosForRoom(?string $folderUrl, ?string $roomCode): ?array
    {
        $folderId = $this->folderIdFromUrl($folderUrl);

        if (! $folderId) {
            return [];
        }

        return Cache::get('supplier_photos_' . sha1($folderId . '|' . (string) $roomCode));
    }

    protected function resolve(string $folderId, ?string $roomCode): array
    {
        $children = $this->listChildren($folderId);

        $images = [];
        $subfolders = [];

        foreach ($children as $child) {
            if ($child->getMimeType() === self::FOLDER_MIME) {
                $subfolders[] = $child;
            } elseif (in_array($child->getMimeType(), self::IMAGE_MIMES, true)
                && ! preg_match(self::SKIP, (string) $child->getName())) {
                $images[] = $child;
            }
        }

        // Room subfolder first, matched on the trailing/leading number or letter code.
        $roomImages = [];
        if ($roomCode !== null && $roomCode !== '') {
            foreach ($subfolders as $folder) {
                if ($this->folderMatchesRoom((string) $folder->getName(), $roomCode)) {
                    foreach ($this->listChildren($folder->getId()) as $f) {
                        if (in_array($f->getMimeType(), self::IMAGE_MIMES, true)
                            && ! preg_match(self::SKIP, (string) $f->getName())) {
                            $roomImages[] = $f;
                        }
                    }
                    break;
                }
            }
        }

        // Then loose images in the property folder, then any other subfolder.
        $rest = $images;
        if (count($roomImages) + count($rest) < self::TOTAL_CAP) {
            foreach ($subfolders as $folder) {
                if (count($roomImages) + count($rest) >= self::TOTAL_CAP) {
                    break;
                }
                if ($roomCode && $this->folderMatchesRoom((string) $folder->getName(), $roomCode)) {
                    continue;
                }
                foreach ($this->listChildren($folder->getId()) as $f) {
                    if (in_array($f->getMimeType(), self::IMAGE_MIMES, true)
                        && ! preg_match(self::SKIP, (string) $f->getName())) {
                        $rest[] = $f;
                    }
                }
            }
        }

        $ordered = array_slice(array_merge($roomImages, $rest), 0, self::TOTAL_CAP);
        $ids = array_map(fn ($f) => $f->getId(), $ordered);

        $this->allowIds($ids);

        return $ids;
    }

    /** @return array<int, \Google\Service\Drive\DriveFile> */
    protected function listChildren(string $folderId): array
    {
        $drive = $this->drive();
        if (! $drive) {
            return [];
        }

        $out = [];
        $pageToken = null;

        do {
            $response = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false", $folderId),
                'fields' => 'nextPageToken, files(id, name, mimeType)',
                'pageSize' => 200,
                'orderBy' => 'name_natural',
                'pageToken' => $pageToken,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
            foreach ($response->getFiles() as $f) {
                $out[] = $f;
            }
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $out;
    }

    /**
     * AP uses D2 / M1 / C / Studio, Javier uses 1M / 4S / bare numbers.
     * Match on the letter code or on the trailing number, whichever is present.
     */
    protected function folderMatchesRoom(string $folderName, string $roomCode): bool
    {
        $name = strtolower(trim($folderName));
        $code = strtolower(trim($roomCode));

        if ($name === $code) {
            return true;
        }

        if (preg_match('/(\d+)/', $code, $cm) && preg_match('/(\d+)/', $name, $nm)) {
            if (ltrim($cm[1], '0') === ltrim($nm[1], '0')) {
                return true;
            }
        }

        // Bare letter codes: "C" should match "ROOM C" but not "Communal".
        if (preg_match('/^[a-z]$/', $code)) {
            return (bool) preg_match('/\b' . preg_quote($code, '/') . '\b/', $name);
        }

        if ($code === 'studio' || $code === 'flat') {
            return str_contains($name, $code);
        }

        return false;
    }

    protected function folderIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Record ids the proxy is permitted to serve. */
    protected function allowIds(array $ids): void
    {
        if (! $ids) {
            return;
        }
        $allowed = Cache::get(self::ALLOWED_KEY, []);
        foreach ($ids as $id) {
            $allowed[$id] = true;
        }
        // Longer than the photo-id cache (7 days), or the allowlist expires
        // first and every image starts 404ing while the ids are still cached.
        Cache::put(self::ALLOWED_KEY, $allowed, now()->addDays(30));
    }

    public function isAllowed(string $fileId): bool
    {
        if (isset(Cache::get(self::ALLOWED_KEY, [])[$fileId])) {
            return true;
        }

        // The allowlist lives in the cache, and a deploy clears the cache —
        // which made every supplier photograph 404 until the next warm-up.
        // A file in our own cache directory is proof we resolved and
        // downloaded it from a folder we were indexing, so it stays servable.
        if (! preg_match('/^[A-Za-z0-9_-]{10,}$/', $fileId)) {
            return false;
        }

        return is_file(storage_path('app/supplier-photos/' . $fileId));
    }

    /**
     * Where an already-downloaded photo sits on disk, so nginx can serve it
     * rather than a PHP worker reading it into memory and echoing it back.
     *
     * @return array{path: string, relative: string, mime: string}|null
     */
    public function cachedFile(string $fileId): ?array
    {
        $path = storage_path('app/supplier-photos/' . $fileId);

        if (! is_file($path) || filemtime($path) <= time() - 2592000) {
            return null;
        }

        $metaPath = $path . '.mime';

        return [
            'path' => $path,
            'relative' => $fileId,
            'mime' => is_file($metaPath) ? trim((string) file_get_contents($metaPath)) : 'image/jpeg',
        ];
    }

    /**
     * Raw bytes plus content type, or null.
     *
     * Cached on local disk, not through the cache store: the cache store is the
     * database here, and binary image data cannot be written to its text column.
     */
    public function download(string $fileId): ?array
    {
        $dir = storage_path('app/supplier-photos');
        $path = $dir . '/' . $fileId;
        $metaPath = $path . '.mime';

        // A month, not twelve hours: the file is addressed by its Drive id, so
        // the bytes cannot change under us, and each expiry used to mean a
        // fresh download and resize holding a PHP worker while someone waited.
        if (is_file($path) && filemtime($path) > time() - 2592000) {
            return [
                'body' => (string) file_get_contents($path),
                'mime' => is_file($metaPath) ? trim((string) file_get_contents($metaPath)) : 'image/jpeg',
            ];
        }

        $drive = $this->drive();
        if (! $drive) {
            return null;
        }

        try {
            $meta = $drive->files->get($fileId, ['fields' => 'mimeType', 'supportsAllDrives' => true]);
            $response = $drive->files->get($fileId, ['alt' => 'media', 'supportsAllDrives' => true]);
            $body = (string) $response->getBody();
            $mime = $meta->getMimeType() ?: 'image/jpeg';

            // Originals straight off a phone are routinely 4-6MB; a listing card
            // does not need that. Downscale to fit 1920x1080 and re-encode.
            [$body, $mime] = $this->downscale($body, $mime);

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($path, $body);
            @file_put_contents($metaPath, $mime);

            return ['body' => $body, 'mime' => $mime];
        } catch (\Throwable $e) {
            Log::warning('Drive photo download failed', ['file' => $fileId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fit within 1920x1080 and re-encode as JPEG. Returns the input untouched
     * if GD is unavailable or the image cannot be decoded.
     *
     * @return array{0: string, 1: string}
     */
    protected function downscale(string $body, string $mime, int $maxW = 1920, int $maxH = 1080): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [$body, $mime];
        }

        try {
            $image = @imagecreatefromstring($body);
            if ($image === false) {
                return [$body, $mime];
            }

            $w = imagesx($image);
            $h = imagesy($image);
            $scale = min($maxW / max($w, 1), $maxH / max($h, 1), 1);

            if ($scale >= 1) {
                // Already small enough; still re-encode a non-JPEG to keep the
                // proxy's output type predictable.
                if ($mime === 'image/jpeg') {
                    imagedestroy($image);
                    return [$body, $mime];
                }
                $out = $image;
            } else {
                $out = imagescale($image, (int) round($w * $scale), (int) round($h * $scale));
                imagedestroy($image);
                if ($out === false) {
                    return [$body, $mime];
                }
            }

            ob_start();
            imagejpeg($out, null, 85);
            $encoded = (string) ob_get_clean();
            imagedestroy($out);

            return $encoded !== '' ? [$encoded, 'image/jpeg'] : [$body, $mime];
        } catch (\Throwable $e) {
            return [$body, $mime];
        }
    }
}
