<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) $payload = [];

    $event = substr((string)($payload['event'] ?? 'dashboard_view'), 0, 80);
    $details = json_encode($payload['payload'] ?? [], JSON_UNESCAPED_UNICODE);
    $userId = (int)$_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dashboard_usage_events (
            id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id int(11) NOT NULL,
            event_name varchar(80) NOT NULL,
            payload_json text NULL,
            visitor_ip varchar(45) NULL,
            user_agent varchar(255) NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            KEY idx_dashboard_usage_user_date (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("INSERT INTO dashboard_usage_events (user_id, event_name, payload_json, visitor_ip, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $event, $details, $ip, $ua]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false]);
}
