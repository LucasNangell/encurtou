<?php
/**
 * Verifica e garante admin master para nangellbusiness@gmail.com.
 * Uso: php run_verify_master_admin.php
 */
require_once __DIR__ . '/db.php';

$masterEmail = 'nangellbusiness@gmail.com';

$hasIsAdmin = false;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin'");
    $stmt->execute();
    $hasIsAdmin = ((int)$stmt->fetchColumn()) > 0;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar schema: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$selectCols = $hasIsAdmin ? 'id, email, is_admin' : 'id, email';
$stmt = $pdo->prepare("SELECT {$selectCols} FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$masterEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    if ($hasIsAdmin) {
        $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $ins = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password, is_admin) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->execute(['Administrador Master', 'Nangell Admin', $masterEmail, 'Admin', $hash]);
        $userId = (int)$pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'action' => 'created',
            'user_id' => $userId,
            'email' => $masterEmail,
            'is_admin' => 1,
            'is_master_admin_dashboard' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit(0);
    }

    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado e coluna is_admin ausente.'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$userId = (int)$user['id'];
$isAdmin = $hasIsAdmin ? (int)($user['is_admin'] ?? 0) : 0;
$action = 'verified';

if ($hasIsAdmin && $isAdmin !== 1) {
    $upd = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
    $upd->execute([$userId]);
    $isAdmin = 1;
    $action = 'promoted';
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'user_id' => $userId,
    'email' => $masterEmail,
    'is_admin' => $isAdmin,
    'is_master_admin_dashboard' => true,
    'note' => 'Dashboard global liberado por e-mail hardcoded em auth.php e admin_dashboard.php.',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
