<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT id, subdomain, path, original_url, clicks, status FROM links WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'links' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$_POST['id'], $userId]);
        echo json_encode(['success' => true]);
    }

    if ($action === 'toggle_status') {
        $status = $_POST['status'] === 'paused' ? 'paused' : 'active';
        $stmt = $pdo->prepare("UPDATE links SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, (int)$_POST['id'], $userId]);
        echo json_encode(['success' => true]);
    }

    if ($action === 'edit_url') {
        $url = filter_var($_POST['url'], FILTER_VALIDATE_URL);
        if (!$url) { echo json_encode(['success' => false, 'message' => 'URL inválida']); exit; }
        $stmt = $pdo->prepare("UPDATE links SET original_url = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$url, (int)$_POST['id'], $userId]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) { echo json_encode(['success' => false]); }
?>