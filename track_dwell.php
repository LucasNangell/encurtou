<?php
require_once 'db.php';
header('Content-Type: application/json');

function dwellApplyCors(string $origin): void {
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
dwellApplyCors($origin);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$epCid = trim((string)($payload['ep_cid'] ?? ''));
$durationMs = (int)($payload['duration_ms'] ?? 0);
if ($epCid === '' || !preg_match('/^[a-f0-9-]{16,64}$/i', $epCid)) {
    echo json_encode(['success' => false, 'message' => 'ep_cid inválido']);
    exit;
}
if ($durationMs < 0) {
    $durationMs = 0;
}
if ($durationMs > 24 * 60 * 60 * 1000) {
    $durationMs = 24 * 60 * 60 * 1000;
}

try {
    $ensure = "
        CREATE TABLE IF NOT EXISTS dwell_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            click_uid VARCHAR(64) NOT NULL,
            click_id INT(11) DEFAULT NULL,
            link_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL,
            ended_at DATETIME NOT NULL,
            is_valid TINYINT(1) NOT NULL DEFAULT 0,
            discard_reason VARCHAR(120) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dwell_click_uid (click_uid),
            KEY idx_dwell_link_started (link_id, started_at),
            KEY idx_dwell_user_started (user_id, started_at),
            KEY idx_dwell_valid_started (is_valid, started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($ensure);

    $stmt = $pdo->prepare("
        SELECT cl.id AS click_id, cl.link_id, cl.clicked_at, l.user_id
        FROM clicks_log cl
        INNER JOIN links l ON l.id = cl.link_id
        WHERE cl.click_uid = ?
        LIMIT 1
    ");
    $stmt->execute([$epCid]);
    $click = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$click) {
        // Fallback resiliente para cenários Edge onde click_uid existe em
        // edge_click_ingest, mas o clicks_log não foi persistido por política
        // de analytics/limites do plano.
        $hasEdgeIngest = false;
        try {
            $checkEdgeTbl = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'edge_click_ingest'");
            $checkEdgeTbl->execute();
            $hasEdgeIngest = ((int)$checkEdgeTbl->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $hasEdgeIngest = false;
        }

        if ($hasEdgeIngest) {
            $stmtEdge = $pdo->prepare("
                SELECT
                    NULL AS click_id,
                    eci.link_id AS link_id,
                    eci.received_at AS clicked_at,
                    l.user_id AS user_id
                FROM edge_click_ingest eci
                INNER JOIN links l ON l.id = eci.link_id
                WHERE eci.click_id = ?
                LIMIT 1
            ");
            $stmtEdge->execute([$epCid]);
            $click = $stmtEdge->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$click) {
        echo json_encode(['success' => true, 'status' => 'ignored_click_not_found']);
        exit;
    }

    $isValid = 1;
    $discardReason = null;
    if ($durationMs < 1000) {
        $isValid = 0;
        $discardReason = 'below_1s';
    } elseif ($durationMs > 30 * 60 * 1000) {
        $isValid = 0;
        $discardReason = 'hard_cap_30m';
    }

    $upsert = $pdo->prepare("
        INSERT INTO dwell_sessions (
            click_uid, click_id, link_id, user_id, duration_ms, started_at, ended_at, is_valid, discard_reason
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            duration_ms = GREATEST(duration_ms, VALUES(duration_ms)),
            ended_at = GREATEST(ended_at, VALUES(ended_at)),
            is_valid = CASE
                WHEN VALUES(is_valid) = 1 THEN 1
                ELSE is_valid
            END,
            discard_reason = CASE
                WHEN VALUES(is_valid) = 1 THEN NULL
                ELSE COALESCE(discard_reason, VALUES(discard_reason))
            END
    ");
    $upsert->execute([
        $epCid,
        (int)$click['click_id'],
        (int)$click['link_id'],
        (int)$click['user_id'],
        $durationMs,
        (string)$click['clicked_at'],
        $isValid,
        $discardReason
    ]);

    echo json_encode(['success' => true, 'status' => 'recorded']);
} catch (Throwable $e) {
    error_log('track_dwell_error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'status' => 'error_db']);
}
?>
