<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Photos of WhatsApp-only rooms, copied out of the groups by a script in
 * WhatsApp Web (docs/whatsapp-photos.js) and kept on our own disk:
 * storage/app/whatsapp-photos/{agency}/{postcode}/{street}/{room}/{sha1}.jpg.
 *
 * WhatsApp gives a photo no link anyone else can open, so this is the only
 * copy the site can show. A room without its own photos borrows the ones
 * posted for its street (Vic's albums name no room).
 */
class WhatsAppPhotoStore
{
    public const MAX_PER_ROOM = 10;

    public const MAX_BYTES = 3_000_000;

    public static function root(): string
    {
        return storage_path('app/whatsapp-photos');
    }

    /** Where a room's photos live, relative to the root. */
    public static function dir(string $agency, string $postcode, ?string $street, ?string $room): string
    {
        $pc = strtolower(preg_replace('/[^A-Z0-9]/i', '', $postcode));

        return implode('/', [
            Str::slug($agency) ?: 'unknown',
            $pc ?: 'nopostcode',
            Str::slug((string) $street) ?: '_',
            Str::slug((string) $room) ?: '_',
        ]);
    }

    /**
     * Keep one photo. Returns false when it is not an image, too big, or the
     * room already has enough.
     */
    public function save(string $agency, string $postcode, ?string $street, ?string $room, string $bytes): bool
    {
        if (strlen($bytes) > self::MAX_BYTES || strlen($bytes) < 2000) {
            return false;
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return false;
        }

        $dir = self::root() . '/' . self::dir($agency, $postcode, $street, $room);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $file = $dir . '/' . sha1($bytes) . '.' . $ext;
        if (is_file($file)) {
            return true; // the same photo, sent again
        }
        if (count(glob($dir . '/*.*') ?: []) >= self::MAX_PER_ROOM) {
            return false;
        }

        return file_put_contents($file, $bytes) !== false;
    }

    /**
     * Photo URLs for a room: its own, else its street's, else the only set
     * posted at that postcode. Disk only, safe in a web request.
     *
     * @return array<int, string>
     */
    public function urlsFor(string $agency, string $postcode, ?string $street, ?string $room): array
    {
        $own = self::dir($agency, $postcode, $street, $room);
        [$a, $pc] = explode('/', $own);
        $streetDir = $a . '/' . $pc . '/' . (Str::slug((string) $street) ?: '_');

        $candidates = [$own, $streetDir . '/_'];
        // Anything at this postcode, when it is all one street's.
        $streets = glob(self::root() . '/' . $a . '/' . $pc . '/*', GLOB_ONLYDIR) ?: [];
        if (count($streets) === 1) {
            foreach (glob($streets[0] . '/*', GLOB_ONLYDIR) ?: [] as $d) {
                $candidates[] = substr($d, strlen(self::root()) + 1);
            }
        }

        foreach (array_unique($candidates) as $rel) {
            $files = glob(self::root() . '/' . $rel . '/*.{jpg,png,webp}', GLOB_BRACE) ?: [];
            if ($files) {
                sort($files);
                return array_map(fn ($f) => route('whatsapp.photo', ['path' => substr($f, strlen(self::root()) + 1)], false), array_slice($files, 0, self::MAX_PER_ROOM));
            }
        }

        return [];
    }
}
