<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    edgeJson(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

[$input, $rawBody] = edgeReadJsonBody();

$tokenExpected = edgeEnv('EDGE_INVALIDATE_TOKEN', '');
$tokenSent = edgeRequestHeader('X-Invalidate-Token');
if ($tokenExpected === '' || $tokenSent === '' || !hash_equals($tokenExpected, $tokenSent)) {
    edgeJson(401, ['ok' => false, 'error' => 'invalid_invalidate_token']);
}

$scope = strtolower(trim((string)($input['scope'] ?? 'slug')));
$subdomain = edgeNormalizeSubdomain((string)($input['subdomain'] ?? 'ROOT'));
$slug = edgeNormalizeSlug((string)($input['slug'] ?? ''));
$reason = trim((string)($input['reason'] ?? 'manual'));
$requestedBy = trim((string)($input['requested_by'] ?? 'system'));

if ($scope !== 'slug') {
    edgeJson(400, ['ok' => false, 'error' => 'unsupported_scope', 'supported_scopes' => ['slug']]);
}

if ($slug === '') {
    edgeJson(400, ['ok' => false, 'error' => 'slug_required']);
}

$keys = ["slug:{$subdomain}:{$slug}"];

$invalidateEndpoint = edgeEnv('EDGE_WORKER_INVALIDATE_URL', '');
$invalidateSecret = edgeEnv('EDGE_WORKER_INVALIDATE_SECRET', '');
$forwarded = null;

if ($invalidateEndpoint === '' || $invalidateSecret === '') {
    edgeJson(503, ['ok' => false, 'error' => 'edge_worker_invalidate_not_configured']);
}

$forwarded = edgeForwardInvalidateToWorker($invalidateEndpoint, $invalidateSecret, [
    'scope' => $scope,
    'subdomain' => $subdomain,
    'slug' => $slug,
    'reason' => $reason,
    'requested_by' => $requestedBy,
    'requested_at' => gmdate('c'),
]);

$auditPayload = [
    'event' => 'edge_cache_invalidate',
    'scope' => $scope,
    'subdomain' => $subdomain,
    'slug' => $slug,
    'reason' => $reason,
    'requested_by' => $requestedBy,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
    'forwarded_status' => $forwarded['status'] ?? 'not_forwarded',
];
error_log(json_encode($auditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if (!($forwarded['ok'] ?? false)) {
    edgeJson(502, [
        'ok' => false,
        'error' => 'edge_worker_invalidate_failed',
        'scope' => $scope,
        'keys' => $keys,
        'forwarded' => $forwarded,
    ]);
}

edgeJson(200, [
    'ok' => true,
    'scope' => $scope,
    'keys' => $keys,
    'forwarded' => $forwarded,
    'message' => 'Invalidate request accepted',
]);

function edgeForwardInvalidateToWorker(string $endpoint, string $secret, array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return ['status' => 'serialization_error'];
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
        'status' => $ok ? 'forwarded' : ($statusCode > 0 ? 'forward_error' : 'unreachable'),
        'status_code' => $statusCode,
        'response' => is_string($result) ? mb_substr($result, 0, 1200) : null,
    ];
}

