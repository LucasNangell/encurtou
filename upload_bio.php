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
        'message' => 'Seu perfil na equipe é Visualizador e não permite upload de mídia.'
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$fileKey = null;
if (isset($_FILES['bio_photo']) && is_array($_FILES['bio_photo'])) {
    $fileKey = 'bio_photo';
} elseif (isset($_FILES['bio_cover']) && is_array($_FILES['bio_cover'])) {
    $fileKey = 'bio_cover';
}

if ($fileKey === null) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado ou campo inválido (use bio_photo ou bio_cover).']);
    exit;
}

$file = $_FILES[$fileKey];
$maxSize = 1024 * 1024; // 1 MB
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo. Código: ' . $file['error']]);
    exit;
}

if (!is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido enviado.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'O arquivo é muito grande. Limite de 1MB.']);
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

$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    echo json_encode(['success' => false, 'message' => 'Arquivo de imagem inválido.']);
    exit;
}

$uploadDir = 'uploads/bio';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Erro: Não foi possível criar a pasta de upload.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'message' => 'Erro: Pasta de upload sem permissão de escrita.']);
    exit;
}

$ext = $allowedTypes[$realMime];
$filename = 'bio_' . (int)$_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    @chmod($targetPath, 0644);
    echo json_encode(['success' => true, 'path' => $targetPath]);
} else {
    echo json_encode(['success' => false, 'message' => 'Falha ao salvar o arquivo no servidor.']);
}
?>
