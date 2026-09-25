<?php

namespace App\Support;

/**
 * The id a property carries in its public URL. Internal ids name their
 * source ("spareroom-18422285", "javier-…", "soreva-…"), which a client must
 * never see, so pages link to a keyed hash of the id instead: stable, short,
 * and saying nothing about where the room came from. Old links with the
 * internal id still open and redirect here.
 */
final class PublicId
{
    public static function for(string $id): string
    {
        return substr(hash_hmac('sha256', 'property:' . $id, (string) config('app.key')), 0, 12);
    }

    public static function looksLikeOne(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{12}$/', $value);
    }
}
