<?php
/**
 * HMAC tokens for optional browser geolocation enrichment on clicks_log.
 */
declare(strict_types=1);

if (!function_exists('preciseGeoGetSecret')) {
    function preciseGeoGetSecret(): string
    {
        $direct = envOrDefault('PRECISE_GEO_SECRET', '');
        if ($direct !== '') {
            return $direct;
        }
        return envOrDefault('EDGE_SHARED_SECRET', '');
    }
}

if (!function_exists('preciseGeoBuildSignedPayload')) {
    /**
     * @return array{exp:int, sig:string}
     */
    function preciseGeoBuildSignedPayload(string $clickUid, int $linkId): array
    {
        $secret = preciseGeoGetSecret();
        $exp = time() + 900;
        $base = $clickUid . '|' . $linkId . '|' . $exp;
        $sig = hash_hmac('sha256', $base, $secret !== '' ? $secret : 'encurtou-precise-geo-dev-only');
        return ['exp' => $exp, 'sig' => $sig];
    }
}

if (!function_exists('preciseGeoVerifySignedPayload')) {
    function preciseGeoVerifySignedPayload(string $clickUid, int $linkId, int $exp, string $sig): bool
    {
        if ($sig === '' || $exp < time() - 60 || $exp > time() + 960) {
            return false;
        }
        $secret = preciseGeoGetSecret();
        $base = $clickUid . '|' . $linkId . '|' . $exp;
        $expected = hash_hmac('sha256', $base, $secret !== '' ? $secret : 'encurtou-precise-geo-dev-only');
        return hash_equals($expected, strtolower($sig));
    }
}

if (!function_exists('preciseGeoReverseLookupNeighborhood')) {
    /**
     * Reverse geocode (OpenStreetMap Nominatim) to a neighbourhood-level label.
     * Best-effort: returns null on failure, timeout, or when disabled via PRECISE_GEO_REVERSE_LOOKUP=0.
     * Set OSM_NOMINATIM_CONTACT to a URL or email for the required User-Agent contact (see Nominatim usage policy).
     */
    function preciseGeoReverseLookupNeighborhood(float $lat, float $lng): ?string
    {
        if (envOrDefault('PRECISE_GEO_REVERSE_LOOKUP', '1') === '0') {
            return null;
        }

        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=%.7F&lon=%.7F&zoom=18&accept-language=pt,en',
            $lat,
            $lng
        );

        $contact = trim(envOrDefault('OSM_NOMINATIM_CONTACT', ''));
        if ($contact === '') {
            $contact = trim(envOrDefault('PUBLIC_BASE_URL', 'https://encurtou.pro'));
        }
        $ua = 'Encurtou/1.0 (+' . $contact . ')';

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2.5,
                'header' => 'User-Agent: ' . $ua . "\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }

        $addr = $json['address'] ?? null;
        if (!is_array($addr)) {
            return null;
        }

        $candidates = [
            $addr['neighbourhood'] ?? '',
            $addr['suburb'] ?? '',
            $addr['quarter'] ?? '',
            $addr['city_district'] ?? '',
        ];

        foreach ($candidates as $c) {
            $c = trim((string)$c);
            if ($c !== '') {
                if (function_exists('mb_substr')) {
                    return mb_substr($c, 0, 128, 'UTF-8');
                }
                return substr($c, 0, 128);
            }
        }

        return null;
    }
}
