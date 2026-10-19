<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_verify_helpers.php';

$token = trim($_GET['token'] ?? '');
$result = emailVerifyConsumeToken($pdo, $token);
$success = !empty($result['success']);
$message = (string)($result['message'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de e-mail — linkneo.pro</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #1e293b; padding: 2rem; border-radius: 12px; max-width: 420px; text-align: center; }
        a { color: #2BF6D1; }
    </style>
</head>
<body>
<div class="card">
    <h1><?php echo $success ? 'E-mail verificado' : 'Falha na verificação'; ?></h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <p><a href="/">Voltar ao site</a></p>
</div>
</body>
</html>
