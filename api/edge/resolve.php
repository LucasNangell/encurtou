<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../AbusePolicyService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    edgeJson(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

[$input, $rawBody] = edgeReadJsonBody();
edgeVerifyRequestSignature($rawBody);

$slug = edgeNormalizeSlug((string)($input['slug'] ?? ''));
$subdomain = edgeNormalizeSubdomain((string)($input['subdomain'] ?? 'ROOT'));
$ua = (string)($input['ua'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));

if ($slug === '') {
    edgeJson(400, ['ok' => false, 'error' => 'invalid_slug']);
}

$stmt = $pdo->prepare("SELECT * FROM links WHERE subdomain = ? AND path = ? LIMIT 1");
$stmt->execute([$subdomain, $slug]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) {
    edgeJson(404, [
        'ok' => false,
        'action' => 'not_found',
        'slug' => $slug,
        'subdomain' => $subdomain,
        'cacheable' => true,
        'ttl_seconds' => 20,
    ]);
}

$publicBaseUrl = edgeSafePublicBaseUrl();
$linkId = (int)$link['id'];
$nowTs = time();
$ttlSeconds = 60;
$variantId = null;
$variants = [];

if (isset($link['is_premium_locked']) && (int)$link['is_premium_locked'] === 1) {
    edgeJson(200, edgeBuildBlockedPayload($link, $slug, $subdomain, $publicBaseUrl . '/?erro=link_bloqueado', 'premium_locked', 15));
}

if (($link['status'] ?? 'active') === 'paused') {
    edgeJson(200, edgeBuildBlockedPayload($link, $slug, $subdomain, $publicBaseUrl . '/?erro=link_pausado', 'paused', 15));
}

if (!empty($link['start_date']) && strtotime((string)$link['start_date']) > $nowTs) {
    edgeJson(200, edgeBuildBlockedPayload($link, $slug, $subdomain, $publicBaseUrl . '/?erro=link_agendado', 'scheduled', 15));
}

$isExpiredByDate = (!empty($link['end_date']) && strtotime((string)$link['end_date']) < $nowTs);
$isExpiredByClicks = (!empty($link['expire_clicks']) && (int)$link['clicks'] >= (int)$link['expire_clicks']);
if ($isExpiredByDate || $isExpiredByClicks) {
    $fallback = !empty($link['fallback_url']) ? (string)$link['fallback_url'] : ($publicBaseUrl . '/?erro=link_expirado');
    $fallback = edgeSanitizeDestination($fallback);
    edgeJson(200, edgeBuildBlockedPayload($link, $slug, $subdomain, $fallback, 'expired', 10));
}

$destination = (string)($link['original_url'] ?? '');
$destination = edgeSanitizeDestination($destination);
$abBackendStrict = false;
$forceOrigin = false;

if (!empty($link['trigger_clicks']) && !empty($link['trigger_url']) && (int)$link['clicks'] >= (int)$link['trigger_clicks']) {
    $destination = edgeSanitizeDestination((string)$link['trigger_url']);
    $ttlSeconds = 30;
} elseif (!empty($link['is_ab_test'])) {
    $vStmt = $pdo->prepare("SELECT id, url, weight, status FROM link_variants WHERE link_id = ? AND status = 'active' ORDER BY id ASC");
    $vStmt->execute([$linkId]);
    $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($variants) > 0) {
        $selected = edgeSelectWeightedVariant($variants);
        if (is_array($selected)) {
            $destination = edgeSanitizeDestination((string)($selected['url'] ?? $destination));
            $variantId = (int)($selected['id'] ?? 0) ?: null;
            // Modo seguro inicial: backend decide A/B, a menos que o link esteja
            // explicitamente marcado como seguro para edge (ab_edge_safe = 1).
            $abBackendStrict = ((int)($link['ab_edge_safe'] ?? 0) !== 1);
        }
    }
    $ttlSeconds = 15;
} else {
    $deepLinkingEnabled = (int)($link['deep_linking_enabled'] ?? 0) === 1;
    if ($deepLinkingEnabled) {
        if (preg_match('/iPhone|iPad|iPod/i', $ua) && !empty($link['url_ios'])) {
            $destination = edgeSanitizeDestination((string)$link['url_ios']);
        } elseif (preg_match('/Android/i', $ua) && !empty($link['url_android'])) {
            $destination = edgeSanitizeDestination((string)$link['url_android']);
        } elseif (preg_match('/Windows/i', $ua) && !empty($link['url_windows'])) {
            $destination = edgeSanitizeDestination((string)$link['url_windows']);
        }
        // Deep linking completo hoje depende de bridge HTML no redirect legado.
        $forceOrigin = true;
        $ttlSeconds = 20;
    }
}

if (!edgeIsAllowedDestination($destination)) {
    edgeJson(200, [
        'ok' => false,
        'action' => 'blocked',
        'reason' => 'invalid_destination',
        'cacheable' => false,
        'force_origin' => true,
    ]);
}

$securityBlock = AbusePolicyService::checkRedirectDestination($pdo, $linkId, $destination, true);
if ($securityBlock !== null) {
    edgeJson(200, edgeBuildBlockedPayload($link, $slug, $subdomain, $publicBaseUrl . '/blocked.php?reason=security', 'security', 15));
}

$retargetingPixels = trim((string)($link['retargeting_pixels_json'] ?? ''));
$hasCustomPreview = trim((string)($link['social_title'] ?? '')) !== ''
    || trim((string)($link['social_description'] ?? '')) !== ''
    || trim((string)($link['social_image_url'] ?? '')) !== ''
    || trim((string)($link['favicon_url'] ?? '')) !== '';

if ($retargetingPixels !== '' && $retargetingPixels !== '[]' && $retargetingPixels !== '{}') {
    $forceOrigin = true;
}
if ($hasCustomPreview) {
    $forceOrigin = true;
}
if (stripos($destination, 'BEGIN:VCARD') !== false) {
    $forceOrigin = true;
}

if ((int)($link['request_precise_location'] ?? 0) === 1) {
    $forceOrigin = true;
    $ttlSeconds = min($ttlSeconds, 20);
}

$cacheVersion = edgeBuildCacheVersion($link, $variants);
$ttlSeconds = edgeResolveTtl($ttlSeconds, $link, $abBackendStrict, $forceOrigin);

edgeJson(200, [
    'ok' => true,
    'action' => 'redirect',
    'reason' => 'ok',
    'slug' => $slug,
    'subdomain' => $subdomain,
    'link_id' => $linkId,
    'user_id' => (int)($link['user_id'] ?? 0),
    'status' => (string)($link['status'] ?? 'active'),
    'destination_url' => $destination,
    'redirect_type' => '302',
    'tracking_enabled' => ((int)($link['tracking_enabled'] ?? 0) === 1),
    'monitor_dwell_enabled' => ((int)($link['monitor_dwell_enabled'] ?? 0) === 1),
    'variant_id' => $variantId,
    'ab_test_enabled' => ((int)($link['is_ab_test'] ?? 0) === 1),
    'ab_backend_strict' => $abBackendStrict,
    'cacheable' => !$forceOrigin,
    'force_origin' => $forceOrigin,
    'ttl_seconds' => $ttlSeconds,
    'cache_version' => $cacheVersion,
    'updated_at' => (string)($link['updated_at'] ?? $link['created_at'] ?? gmdate('Y-m-d H:i:s')),
    'expires_at' => !empty($link['end_date']) ? gmdate('c', strtotime((string)$link['end_date'])) : null,
]);

function edgeBuildBlockedPayload(array $link, string $slug, string $subdomain, string $targetUrl, string $reason, int $ttl): array
{
    $destination = edgeSanitizeDestination($targetUrl);
    return [
        'ok' => true,
        'action' => 'redirect',
        'reason' => $reason,
        'slug' => $slug,
        'subdomain' => $subdomain,
        'link_id' => (int)($link['id'] ?? 0),
        'user_id' => (int)($link['user_id'] ?? 0),
        'status' => (string)($link['status'] ?? 'active'),
        'destination_url' => $destination,
        'redirect_type' => '302',
        'tracking_enabled' => false,
        'variant_id' => null,
        'ab_test_enabled' => false,
        'ab_backend_strict' => false,
        'cacheable' => true,
        'force_origin' => false,
        'ttl_seconds' => max(10, $ttl),
        'cache_version' => edgeBuildCacheVersion($link, []),
        'updated_at' => (string)($link['updated_at'] ?? $link['created_at'] ?? gmdate('Y-m-d H:i:s')),
        'expires_at' => null,
    ];
}

function edgeResolveTtl(int $proposed, array $link, bool $abStrict, bool $forceOrigin): int
{
    if ($forceOrigin) {
        return 15;
    }
    if ($abStrict) {
        return 10;
    }
    if (!empty($link['start_date']) || !empty($link['end_date']) || !empty($link['expire_clicks']) || !empty($link['trigger_clicks'])) {
        return min(30, max(10, $proposed));
    }
    return min(300, max(20, $proposed));
}

function edgeSelectWeightedVariant(array $activeVariants): ?array
{
    $total = 0;
    foreach ($activeVariants as $variant) {
        $weight = (int)($variant['weight'] ?? 0);
        if ($weight > 0) {
            $total += $weight;
        }
    }

    if ($total <= 0) {
        return $activeVariants[0] ?? null;
    }

    $rand = mt_rand(1, $total);
    $cumulative = 0;
    foreach ($activeVariants as $variant) {
        $weight = max(0, (int)($variant['weight'] ?? 0));
        $cumulative += $weight;
        if ($rand <= $cumulative) {
            return $variant;
        }
    }

    return end($activeVariants) ?: null;
}

