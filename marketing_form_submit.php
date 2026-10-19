<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

requireCsrfToken(false);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$whatsapp = trim((string)($data['whatsapp'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Preencha nome, e-mail e texto da solicitação.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe um e-mail válido.']);
    exit;
}

if (mb_strlen($name) > 150) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Nome muito longo.']);
    exit;
}

if (mb_strlen($email) > 190) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'E-mail muito longo.']);
    exit;
}

if (mb_strlen($whatsapp) > 40) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'WhatsApp inválido.']);
    exit;
}

if (mb_strlen($message) > 4000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Texto de solicitação excede o limite.']);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketing_form_responses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            whatsapp VARCHAR(40) NULL,
            request_text TEXT NOT NULL,
            source_page VARCHAR(120) NOT NULL DEFAULT 'marketing.php',
            submitter_ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_marketing_form_created_at (created_at),
            INDEX idx_marketing_form_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO marketing_form_responses
            (name, email, whatsapp, request_text, source_page, submitter_ip, user_agent)
        VALUES
            (?, ?, ?, ?, 'marketing.php', ?, ?)
    ");
    $stmt->execute([
        $name,
        $email,
        $whatsapp !== '' ? $whatsapp : null,
        $message,
        getRealIP(),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Obrigado! A equipe da Encurtou.pro já recebeu sua mensagem. Em breve você receberá nosso contato.'
    ]);
} catch (Throwable $e) {
    error_log('marketing_form_submit.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar sua mensagem. Tente novamente.']);
}

