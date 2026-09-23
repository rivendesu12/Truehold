<?php

namespace App\Services\Concerns;

use App\Services\PostcodeGeocoder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What the agencies' own Google Sheets readers share: the service account's
 * read-only token, and placing rows on the map from their postcodes.
 */
trait ReadsSupplierSheets
{
    /** Every row has a full postcode, so one bulk lookup places them all. */
    protected function attachCoordinates(Collection $properties): Collection
    {
        if ($properties->isEmpty()) {
            return $properties;
        }

        $geocoder = app(PostcodeGeocoder::class);
        $coords = $geocoder->lookupMany(
            $properties->pluck('postcode')->filter()->map(fn ($p) => $geocoder->normalise($p))->unique()->all()
        );

        return $properties->map(function (array $property) use ($geocoder, $coords) {
            $key = $geocoder->normalise((string) $property['postcode']);

            if (isset($coords[$key])) {
                $property['latitude'] = $coords[$key]['lat'];
                $property['longitude'] = $coords[$key]['lng'];
                $property['geocoded_from_postcode'] = true;
            }

            return $property;
        });
    }

    /** Signed JWT exchange, same service account as the other supplier sheets. */
    protected function accessToken(): ?string
    {
        $path = config('services.supplier_targets.credentials_path');

        if (! $path || ! is_file($path)) {
            return null;
        }

        return Cache::remember('soreva_sheets_token', 3000, function () use ($path) {
            $creds = json_decode((string) file_get_contents($path), true);

            if (empty($creds['client_email']) || empty($creds['private_key'])) {
                return null;
            }

            $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => time(),
                'exp' => time() + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $creds['private_key'], 'sha256')) {
                return null;
            }

            $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }
}
