<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

const EDGE_MAX_SIGNATURE_SKEW_SECONDS = 300;

function edgeJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function edgeRequestHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';
    return is_string($value) ? trim($value) : '';
}

function edgeReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        edgeJson(400, ['ok' => false, 'error' => 'empty_body']);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        edgeJson(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    return [$decoded, $raw];
}

function edgeEnv(string $key, string $default = ''): string
{
    $value = $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function edgeVerifyRequestSignature(string $rawBody): void
{
    $apiKeyExpected = edgeEnv('EDGE_API_KEY', '');
    $secret = edgeEnv('EDGE_SHARED_SECRET', '');

    if ($apiKeyExpected === '' || $secret === '') {
        edgeJson(500, ['ok' => false, 'error' => 'edge_auth_not_configured']);
    }

    $apiKey = edgeRequestHeader('X-Edge-Key');
    $signature = edgeRequestHeader('X-Edge-Signature');
    $timestamp = edgeRequestHeader('X-Edge-Timestamp');

    if ($apiKey === '' || $signature === '' || $timestamp === '') {
        edgeJson(401, ['ok' => false, 'error' => 'missing_auth_headers']);
    }

    if (!hash_equals($apiKeyExpected, $apiKey)) {
        edgeJson(401, ['ok' => false, 'error' => 'invalid_edge_key']);
    }

    if (!ctype_digit($timestamp)) {
        edgeJson(401, ['ok' => false, 'error' => 'invalid_timestamp']);
    }

    $ts = (int)$timestamp;
    if (abs(time() - $ts) > EDGE_MAX_SIGNATURE_SKEW_SECONDS) {
        edgeJson(401, ['ok' => false, 'error' => 'timestamp_out_of_window']);
    }

    $expected = hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($expected, strtolower($signature))) {
        edgeJson(401, ['ok' => false, 'error' => 'invalid_signature']);
    }
}

function edgeNormalizeSlug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = trim($slug, '/');
    return preg_replace('/[^a-z0-9\-\/]/', '', $slug) ?? '';
}

function edgeNormalizeSubdomain(string $subdomain): string
{
    $sub = strtolower(trim($subdomain));
    $sub = preg_replace('/[^a-z0-9\-]/', '', $sub) ?? '';
    if ($sub === '' || $sub === 'root') {
        return 'ROOT';
    }
    return $sub;
}

function edgeSafePublicBaseUrl(): string
{
    $base = edgeEnv('PUBLIC_BASE_URL', 'https://encurtou.pro');
    $base = rtrim($base, '/');
    if ($base === '') {
        return 'https://encurtou.pro';
    }
    return $base;
}

function edgeSanitizeDestination(string $destination): string
{
    $destination = preg_replace('/[\r\n]+/', '', $destination) ?? '';
    return trim($destination);
}

function edgeIsAllowedDestination(string $destination): bool
{
    if ($destination === '') {
        return false;
    }

    if (stripos($destination, 'BEGIN:VCARD') !== false) {
        return true;
    }

    $parts = parse_url($destination);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    return !empty($parts['host']);
}

function edgeBuildCacheVersion(array $link, array $variants = []): string
{
    $seed = [
        'id' => (int)($link['id'] ?? 0),
        'status' => (string)($link['status'] ?? ''),
        'is_premium_locked' => (int)($link['is_premium_locked'] ?? 0),
        'original_url' => (string)($link['original_url'] ?? ''),
        'url_ios' => (string)($link['url_ios'] ?? ''),
        'url_android' => (string)($link['url_android'] ?? ''),
        'url_windows' => (string)($link['url_windows'] ?? ''),
        'fallback_url' => (string)($link['fallback_url'] ?? ''),
        'start_date' => (string)($link['start_date'] ?? ''),
        'end_date' => (string)($link['end_date'] ?? ''),
        'expire_clicks' => (int)($link['expire_clicks'] ?? 0),
        'trigger_clicks' => (int)($link['trigger_clicks'] ?? 0),
        'trigger_url' => (string)($link['trigger_url'] ?? ''),
        'is_ab_test' => (int)($link['is_ab_test'] ?? 0),
        'ab_edge_safe' => (int)($link['ab_edge_safe'] ?? 0),
        'tracking_enabled' => (int)($link['tracking_enabled'] ?? 0),
        'deep_linking_enabled' => (int)($link['deep_linking_enabled'] ?? 0),
        'retargeting_pixels_json' => (string)($link['retargeting_pixels_json'] ?? ''),
        'social_title' => (string)($link['social_title'] ?? ''),
        'social_description' => (string)($link['social_description'] ?? ''),
        'social_image_url' => (string)($link['social_image_url'] ?? ''),
        'favicon_url' => (string)($link['favicon_url'] ?? ''),
        'destination_type' => (string)($link['destination_type'] ?? ''),
        'destination_metadata' => (string)($link['destination_metadata'] ?? ''),
        'request_precise_location' => (int)($link['request_precise_location'] ?? 0),
        'updated_hint' => (string)($link['updated_at'] ?? $link['created_at'] ?? ''),
        'variants' => $variants,
    ];

    return substr(hash('sha256', json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 20);
}

function edgeSourceFromQuery(?string $source): string
{
    return $source === 'qr' ? 'qr' : 'digital';
}

function edgeSafeReferer(string $referer): string
{
    if ($referer === '') {
        return 'Acesso Direto';
    }
    return mb_substr($referer, 0, 2000);
}

