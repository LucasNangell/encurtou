<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'team_permissions.php';
require_once 'auth_guard.php';

$userId = requireAuthenticatedUserNotBanned($pdo, true, false);

$teamContext = teamResolveMembershipContext($pdo, (int)$userId);
if (!teamCanEditContent((string)($teamContext['profile'] ?? 'editor'))) {
    echo json_encode([
        'success' => false,
        'error' => 'permission_denied',
        'message' => 'Seu perfil na equipe é Visualizador e não permite upload de logos.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $file = $_FILES['logo'];
    $maxSize = 512 * 1024; // 512 KB
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'O arquivo é muito grande. Limite de 500KB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!is_string($realMime) || !array_key_exists($realMime, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Formato não suportado. Use JPG, PNG ou WEBP.']);
        exit;
    }

    $ext = $allowedTypes[$realMime];
    $filename = 'logo_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $targetPath = 'uploads/logos/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => true, 'path' => $targetPath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao salvar o arquivo no servidor.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
}
?>
