<?php

require_once __DIR__ . '/user_ban_helpers.php';
require_once __DIR__ . '/rate_limit_helpers.php';

function authGuardColumnExists(PDO $pdo, string $table, string $column): bool
{
    return function_exists('pdoColumnExists')
        ? pdoColumnExists($pdo, $table, $column)
        : userBanColumnExists($pdo, $table, $column);
}

function authGuardJsonExit(array $payload, int $httpCode = 403): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
    }
    echo json_encode($payload);
    exit;
}

function authGuardDestroySession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function authGuardSessionInvalidated(PDO $pdo, int $userId): bool
{
    if (!authGuardColumnExists($pdo, 'users', 'session_invalidated_at')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT session_invalidated_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $invalidatedAt = $stmt->fetchColumn();
    if (!$invalidatedAt) {
        return false;
    }
    $issuedAt = (string)($_SESSION['auth_issued_at'] ?? '');
    if ($issuedAt === '') {
        return true;
    }
    return strtotime($issuedAt) < strtotime((string)$invalidatedAt);
}

function authGuardEmailVerified(PDO $pdo, int $userId): bool
{
    if (!authGuardColumnExists($pdo, 'users', 'email_verified_at')) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT email_verified_at, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    if (!empty($row['email_verified_at'])) {
        return true;
    }
    $graceDays = (int)envOrDefault('EMAIL_VERIFY_GRACE_DAYS', '7');
    if ($graceDays <= 0) {
        return false;
    }
    $createdAt = strtotime((string)($row['created_at'] ?? ''));
    if ($createdAt === false) {
        return false;
    }
    return (time() - $createdAt) <= ($graceDays * 86400);
}

function requireAuthenticatedUser(PDO $pdo, bool $requireCsrf = true): int
{
    if ($requireCsrf) {
        requireCsrfToken();
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        authGuardJsonExit(['success' => false, 'message' => 'Faça login para continuar.'], 401);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        authGuardDestroySession();
        authGuardJsonExit(['success' => false, 'message' => 'Sua sessão expirou. Faça login novamente.'], 401);
    }

    if (authGuardSessionInvalidated($pdo, $userId)) {
        authGuardDestroySession();
        authGuardJsonExit(['success' => false, 'message' => 'Sua sessão foi encerrada. Faça login novamente.', 'session_expired' => true], 401);
    }

    return $userId;
}

function requireAuthenticatedUserNotBanned(PDO $pdo, bool $requireCsrf = true, bool $requireEmailVerified = false): int
{
    $userId = requireAuthenticatedUser($pdo, $requireCsrf);

    $banBlock = getUserBanBlockResponse($pdo, $userId);
    if ($banBlock !== null) {
        authGuardDestroySession();
        authGuardJsonExit($banBlock, 403);
    }

    if ($requireEmailVerified && !authGuardEmailVerified($pdo, $userId)) {
        authGuardJsonExit([
            'success' => false,
            'error' => 'email_not_verified',
            'message' => 'Verifique seu e-mail antes de criar links. Reenvie o link de confirmação nas configurações da conta.',
        ], 403);
    }

    return $userId;
}

function authGuardSetSessionIssuedAt(): void
{
    $_SESSION['auth_issued_at'] = date('Y-m-d H:i:s');
}
