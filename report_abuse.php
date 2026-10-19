<?php
require_once 'db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denunciar abuso — linkneo.pro</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
        .wrap { max-width: 520px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; }
        label { display: block; margin-top: 1rem; margin-bottom: .35rem; }
        input, textarea { width: 100%; padding: .65rem; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #fff; box-sizing: border-box; }
        button { margin-top: 1rem; background: #2BF6D1; color: #0f172a; border: 0; padding: .75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .msg { margin-top: 1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Denunciar link suspeito</h1>
    <p>Use este formulário para reportar links de phishing, fraude ou conteúdo indevido.</p>
    <form id="reportForm">
        <label for="url">URL encurtada ou slug</label>
        <input type="text" id="url" name="url" required placeholder="https://linkneo.pro/exemplo ou exemplo">

        <label for="email">Seu e-mail (opcional)</label>
        <input type="email" id="email" name="email" placeholder="voce@email.com">

        <label for="reason">Motivo</label>
        <textarea id="reason" name="reason" rows="4" placeholder="Descreva o problema (phishing, impersonação, etc.)"></textarea>

        <button type="submit">Enviar denúncia</button>
        <p class="msg" id="msg"></p>
    </form>
</div>
<script>
document.getElementById('reportForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await fetch('report_abuse.php', { method: 'POST', body: fd });
    const data = await res.json();
    document.getElementById('msg').textContent = data.message || 'Erro ao enviar.';
});
</script>
</body>
</html>
    <?php
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'rate_limit_helpers.php';
require_once 'smtp_mailer.php';

if (!checkRateLimit($pdo, 'report_abuse', 5, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Muitas denúncias. Tente novamente mais tarde.']);
    exit;
}

$inputUrl = trim($_POST['url'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$reporterEmail = strtolower(trim($_POST['email'] ?? ''));

if ($inputUrl === '') {
    echo json_encode(['success' => false, 'message' => 'Informe a URL encurtada ou o slug.']);
    exit;
}

$linkId = null;
$bioPageId = null;
$slug = '';
$subdomain = 'ROOT';

if (preg_match('#^https?://#i', $inputUrl)) {
    $parts = parse_url($inputUrl);
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = trim((string)($parts['path'] ?? ''), '/');
    if (strncmp($path, 'bio/', 4) === 0) {
        $slug = substr($path, 4);
        $stmt = $pdo->prepare('SELECT id FROM bio_pages WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $bioPageId = $stmt->fetchColumn() ?: null;
    } else {
        if (preg_match('/^([a-z0-9-]+)\./i', $host, $m) && $path !== '') {
            $subdomain = strtolower($m[1]);
            $slug = explode('/', $path)[0];
        } else {
            $slug = $path !== '' ? explode('/', $path)[0] : '';
        }
        if ($slug !== '') {
            $stmt = $pdo->prepare('SELECT id FROM links WHERE subdomain = ? AND path = ? LIMIT 1');
            $stmt->execute([$subdomain, $slug]);
            $linkId = $stmt->fetchColumn() ?: null;
        }
    }
} else {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($inputUrl));
    $stmt = $pdo->prepare('SELECT id FROM links WHERE subdomain = ? AND path = ? LIMIT 1');
    $stmt->execute(['ROOT', $slug]);
    $linkId = $stmt->fetchColumn() ?: null;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO abuse_reports (link_id, bio_page_id, reported_slug, reported_subdomain, reporter_ip, reporter_email, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $linkId,
        $bioPageId,
        $slug ?: null,
        $subdomain,
        getRealIP(),
        $reporterEmail !== '' ? $reporterEmail : null,
        $reason !== '' ? $reason : null,
        'open',
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Não foi possível registrar a denúncia.']);
    exit;
}

$adminEmail = envOrDefault('SECURITY_ALERT_EMAIL', envOrDefault('SMTP_USER', ''));
if ($adminEmail !== '') {
    $html = '<p>Nova denúncia de abuso:</p><ul>'
        . '<li>URL/slug: ' . htmlspecialchars($inputUrl) . '</li>'
        . '<li>Link ID: ' . htmlspecialchars((string)$linkId) . '</li>'
        . '<li>Motivo: ' . htmlspecialchars($reason) . '</li></ul>';
    @send_smtp_mail($adminEmail, '[linkneo.pro] Denúncia de abuso', $html);
}

echo json_encode(['success' => true, 'message' => 'Denúncia registrada. Obrigado por ajudar a manter a plataforma segura.']);
