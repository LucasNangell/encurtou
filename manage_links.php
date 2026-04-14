<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // LISTAR LINKS (Filtrado por tipo: link, qr ou all)
    if ($action === 'list') {
        $type = $_GET['type'] ?? 'link';
        
        $sql = "SELECT l.*, 
            DATE_FORMAT(l.created_at, '%d/%m/%Y') as data_criacao,
            (SELECT COUNT(*) FROM clicks_log cl WHERE cl.link_id = l.id AND cl.source = 'digital') as clicks_digital,
            (SELECT COUNT(*) FROM clicks_log cl WHERE cl.link_id = l.id AND cl.source = 'qr') as clicks_qr
            FROM links l 
            WHERE l.user_id = :userId";
        
        if ($type !== 'all') {
            $sql .= " AND l.type = :type";
        }
        
        $sql .= " ORDER BY l.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':userId', $userId);
        if ($type !== 'all') {
            $stmt->bindValue(':type', $type);
        }
        
        $stmt->execute();
        echo json_encode(['success' => true, 'links' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // EXCLUIR LINK
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // PAUSAR / ATIVAR LINK
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] === 'paused' ? 'paused' : 'active';
        $stmt = $pdo->prepare("UPDATE links SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // EDITAR LINK (BÁSICO + AVANÇADO)
    if ($action === 'edit_url') {
        $id = (int)$_POST['id'];
        $newUrl = filter_var($_POST['url'], FILTER_VALIDATE_URL);
        
        $url_ios = !empty($_POST['url_ios']) ? filter_var($_POST['url_ios'], FILTER_VALIDATE_URL) : null;
        $url_android = !empty($_POST['url_android']) ? filter_var($_POST['url_android'], FILTER_VALIDATE_URL) : null;
        $url_windows = !empty($_POST['url_windows']) ? filter_var($_POST['url_windows'], FILTER_VALIDATE_URL) : null;

        if (!$newUrl) {
            echo json_encode(['success' => false, 'message' => 'URL padrão inválida.']);
            exit;
        }

        // Se informou URL de dispositivo e ela é inválida
        if (($_POST['url_ios'] && !$url_ios) || ($_POST['url_android'] && !$url_android) || ($_POST['url_windows'] && !$url_windows)) {
            echo json_encode(['success' => false, 'message' => 'Uma ou mais URLs de dispositivos são inválidas.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE links SET original_url = ?, url_ios = ?, url_android = ?, url_windows = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newUrl, $url_ios, $url_android, $url_windows, $id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ALTERAR TIPO DO LINK (link <=> qr)
    if ($action === 'update_type') {
        $id = (int)$_POST['id'];
        $type = ($_POST['type'] === 'qr') ? 'qr' : 'link';

        $stmt = $pdo->prepare("UPDATE links SET type = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$type, $id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>