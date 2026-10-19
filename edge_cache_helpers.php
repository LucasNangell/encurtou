<?php
declare(strict_types=1);

if (!function_exists('edgeCacheNormalizeSubdomain')) {
    function edgeCacheNormalizeSubdomain(string $subdomain): string
    {
        $sub = strtolower(trim($subdomain));
        $sub = preg_replace('/[^a-z0-9\-]/', '', $sub) ?? '';
        if ($sub === '' || $sub === 'root') {
            return 'ROOT';
        }
        return $sub;
    }
}

if (!function_exists('edgeCacheNormalizeSlug')) {
    function edgeCacheNormalizeSlug(string $slug): string
    {
        $value = strtolower(trim($slug));
        $value = trim($value, '/');
        return preg_replace('/[^a-z0-9\-\/]/', '', $value) ?? '';
    }
}

if (!function_exists('edgeCacheInvalidateSlug')) {
    function edgeCacheInvalidateSlug(string $subdomain, string $slug, string $reason, string $requestedBy): array
    {
        $endpoint = envOrDefault('EDGE_WORKER_INVALIDATE_URL', '');
        $secret = envOrDefault('EDGE_WORKER_INVALIDATE_SECRET', '');

        if ($endpoint === '' || $secret === '') {
            return [
                'ok' => false,
                'status' => 'not_configured',
                'status_code' => 0,
                'response' => 'EDGE_WORKER_INVALIDATE_URL/EDGE_WORKER_INVALIDATE_SECRET missing',
            ];
        }

        $payload = [
            'scope' => 'slug',
            'subdomain' => edgeCacheNormalizeSubdomain($subdomain),
            'slug' => edgeCacheNormalizeSlug($slug),
            'reason' => trim($reason) !== '' ? trim($reason) : 'link_updated',
            'requested_by' => trim($requestedBy) !== '' ? trim($requestedBy) : 'backend',
            'requested_at' => gmdate('c'),
        ];

        if ($payload['slug'] === '') {
            return [
                'ok' => false,
                'status' => 'invalid_slug',
                'status_code' => 0,
                'response' => 'slug is empty after normalization',
            ];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return [
                'ok' => false,
                'status' => 'serialization_error',
                'status_code' => 0,
                'response' => 'json_encode failed',
            ];
        }

        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $json, $secret);
        $opts = [
            'http' => [
                'method' => 'POST',
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-Edge-Invalidate-Signature: ' . $signature,
                    'X-Edge-Invalidate-Timestamp: ' . $timestamp,
                ]),
                'content' => $json,
            ],
        ];

        $ctx = stream_context_create($opts);
        $result = @file_get_contents($endpoint, false, $ctx);
        $statusCode = 0;
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', (string)$statusLine, $m)) {
            $statusCode = (int)$m[1];
        }

        $ok = $statusCode >= 200 && $statusCode < 300;
        return [
            'ok' => $ok,
            'status' => $ok ? 'forwarded' : 'forward_failed',
            'status_code' => $statusCode,
            'response' => is_string($result) ? mb_substr($result, 0, 1200) : null,
        ];
    }
}

if (!function_exists('edgeCacheInvalidateLinkById')) {
    function edgeCacheInvalidateLinkById(PDO $pdo, int $linkId, string $reason, string $requestedBy): array
    {
        if ($linkId <= 0) {
            return [
                'ok' => false,
                'status' => 'invalid_link_id',
                'status_code' => 0,
                'response' => 'link id must be > 0',
            ];
        }

        $stmt = $pdo->prepare("SELECT subdomain, path FROM links WHERE id = ? LIMIT 1");
        $stmt->execute([$linkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'ok' => false,
                'status' => 'link_not_found',
                'status_code' => 0,
                'response' => 'link row not found for invalidation',
            ];
        }

        return edgeCacheInvalidateSlug((string)$row['subdomain'], (string)$row['path'], $reason, $requestedBy);
    }
}

