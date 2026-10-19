<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bio_helpers.php';
require_once __DIR__ . '/bio_form_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (!bio_has_table($pdo, 'bio_form_submissions') || !bio_has_table($pdo, 'bio_form_answers')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Formulários ainda não configurados no servidor.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$slug = trim((string) ($data['slug'] ?? ''));
$csrf = (string) ($data['csrf_token'] ?? '');
$answersIn = $data['answers'] ?? [];
$honeypot = trim((string) ($data['website'] ?? ''));

if ($honeypot !== '') {
    echo json_encode(['success' => false, 'message' => 'Envio rejeitado.']);
    exit;
}

if ($slug === '' || !is_array($answersIn)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

if (!is_string($csrf) || $csrf === '' || !hash_equals(getCsrfToken(), $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Recarregue a página e tente novamente.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM bio_pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$page) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Página não encontrada.']);
    exit;
}

$status = bio_normalize_text($page['page_status'] ?? 'published', 'published');
if ($status !== 'published') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Esta página não aceita envios no momento.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM bio_links WHERE bio_page_id = ? ORDER BY display_order ASC');
$stmt->execute([(int) $page['id']]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
$schema = bio_resolve_schema($page, $links);

$fieldsMap = bio_form_collect_fields($schema);
if (empty($fieldsMap)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum formulário configurado nesta página.']);
    exit;
}

$ip = getRealIP();
if (bio_form_rate_limit_hit($pdo, (int) $page['id'], $ip)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Muitas tentativas. Aguarde um momento.']);
    exit;
}

$validated = bio_form_validate_submission($fieldsMap, $answersIn);
if (!$validated['ok']) {
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $validated['errors']),
        'errors' => $validated['errors'],
    ]);
    exit;
}

$ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO bio_form_submissions (bio_page_id, submitter_ip, user_agent) VALUES (?, ?, ?)');
    $ins->execute([(int) $page['id'], $ip, $ua]);
    $sid = (int) $pdo->lastInsertId();

    $ans = $pdo->prepare('INSERT INTO bio_form_answers (submission_id, block_id, field_id, field_key, field_type, value_text, value_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($validated['rows'] as $row) {
        $ans->execute([
            $sid,
            $row['block_id'],
            $row['field_id'],
            $row['field_key'],
            $row['field_type'],
            $row['value_text'],
            $row['value_json'],
        ]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Resposta enviada com sucesso. Obrigado!']);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('bio_form_submit: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar. Tente novamente.']);
}
