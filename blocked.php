<?php
http_response_code(403);
header('X-Robots-Tag: noindex, nofollow');
$reason = trim($_GET['reason'] ?? 'blocked');
$messages = [
    'blocked' => 'Este link foi desativado por violar nossos termos de uso.',
    'security' => 'Este link foi desativado por motivos de segurança.',
    'threat_detected' => 'Este link foi desativado após verificação de segurança.',
    'paused' => 'Este link está temporariamente pausado.',
];
$message = $messages[$reason] ?? $messages['blocked'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link indisponível — linkneo.pro</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
        .card { background: #1e293b; padding: 2rem; border-radius: 12px; max-width: 480px; text-align: center; line-height: 1.6; }
        a { color: #2BF6D1; }
    </style>
</head>
<body>
<div class="card">
    <h1>Link indisponível</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <p>Se você acredita que isto é um erro, <a href="/report_abuse.php">denuncie aqui</a>.</p>
    <p><a href="https://linkneo.pro">Ir para linkneo.pro</a></p>
</div>
</body>
</html>
