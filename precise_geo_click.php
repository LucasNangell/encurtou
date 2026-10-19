<?php
/**
 * POST: enrich clicks_log with browser-reported coordinates (after user consent).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/precise_geo_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($input)) {
    $input = [];
}

$clickUid = trim((string)($input['click_uid'] ?? ''));
$linkId = (int)($input['link_id'] ?? 0);
$exp = (int)($input['exp'] ?? 0);
$sig = strtolower(trim((string)($input['sig'] ?? '')));
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$accuracy = $input['accuracy'] ?? null;

if ($clickUid === '' || !preg_match('/^[a-f0-9]{32}$/i', $clickUid) || $linkId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_params']);
    exit;
}

if (!preciseGeoVerifySignedPayload($clickUid, $linkId, $exp, $sig)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_token']);
    exit;
}

if (!is_numeric($lat) || !is_numeric($lng)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_coordinates']);
    exit;
}

$latF = (float)$lat;
$lngF = (float)$lng;
if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'coordinates_out_of_range']);
    exit;
}

$accF = null;
if ($accuracy !== null && $accuracy !== '' && is_numeric($accuracy)) {
    $accF = max(0.0, min(1000000.0, (float)$accuracy));
}

$hasGeoLat = pdoColumnExists($pdo, 'clicks_log', 'geo_lat');
if (!$hasGeoLat) {
    echo json_encode(['ok' => true, 'updated' => false, 'reason' => 'schema_not_ready']);
    exit;
}

$hasGeoNeighborhood = pdoColumnExists($pdo, 'clicks_log', 'geo_neighborhood');
$neighborhood = null;
if ($hasGeoNeighborhood) {
    $neighborhood = preciseGeoReverseLookupNeighborhood($latF, $lngF);
}

try {
    if ($hasGeoNeighborhood) {
        $sql = 'UPDATE clicks_log SET geo_lat = ?, geo_lng = ?, geo_accuracy_m = ?, geo_source = ?, geo_neighborhood = ? WHERE click_uid = ? AND link_id = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            round($latF, 7),
            round($lngF, 7),
            $accF,
            'browser_precise',
            $neighborhood,
            $clickUid,
            $linkId,
        ]);
    } else {
        $sql = 'UPDATE clicks_log SET geo_lat = ?, geo_lng = ?, geo_accuracy_m = ?, geo_source = ? WHERE click_uid = ? AND link_id = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            round($latF, 7),
            round($lngF, 7),
            $accF,
            'browser_precise',
            $clickUid,
            $linkId,
        ]);
    }
    echo json_encode(['ok' => true, 'updated' => $stmt->rowCount() > 0]);
} catch (PDOException $e) {
    error_log('precise_geo_click: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
