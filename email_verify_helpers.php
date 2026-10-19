<?php

require_once __DIR__ . '/smtp_mailer.php';

function disposableEmailDomains(): array
{
    static $domains = null;
    if ($domains !== null) {
        return $domains;
    }
    $domains = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
        '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'throwaway.email',
        'yopmail.com', 'sharklasers.com', 'trashmail.com', 'getnada.com', 'maildrop.cc',
        'dispostable.com', 'fakeinbox.com', 'mailnesia.com', 'mintemail.com',
        'mytemp.email', 'spamgourmet.com', 'mailcatch.com', 'mailnull.com',
        'emailondeck.com', 'tempail.com', 'moakt.com', 'inboxkitten.com',
    ];
    return $domains;
}

function isDisposableEmail(string $email): bool
{
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2) {
        return false;
    }
    $domain = $parts[1];
    foreach (disposableEmailDomains() as $blocked) {
        if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
            return true;
        }
    }
    return false;
}

function emailVerifyColumnsReady(PDO $pdo): bool
{
    return function_exists('pdoColumnExists')
        ? pdoColumnExists($pdo, 'users', 'email_verified_at')
        : (function () use ($pdo) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $stmt->execute(['users', 'email_verified_at']);
            return (int)$stmt->fetchColumn() > 0;
        })();
}

function emailVerifyBuildLink(string $token): string
{
    $base = rtrim(envOrDefault('PUBLIC_BASE_URL', 'https://linkneo.pro'), '/');
    return $base . '/verify_email.php?token=' . urlencode($token);
}

function emailVerifySend(PDO $pdo, int $userId, string $email, string $name): bool
{
    if (!emailVerifyColumnsReady($pdo)) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $pdo->prepare('UPDATE users SET email_verify_token_hash = ?, email_verify_sent_at = NOW() WHERE id = ?')
        ->execute([$hash, $userId]);

    $link = emailVerifyBuildLink($token);
    $html = '<p>Olá ' . htmlspecialchars($name) . ',</p>'
        . '<p>Confirme seu e-mail para criar links no linkneo.pro:</p>'
        . '<p><a href="' . htmlspecialchars($link) . '">Verificar e-mail</a></p>'
        . '<p>Se você não criou esta conta, ignore esta mensagem.</p>';

    return send_smtp_mail($email, 'Confirme seu e-mail — linkneo.pro', $html);
}

function emailVerifyConsumeToken(PDO $pdo, string $token): array
{
    if (!emailVerifyColumnsReady($pdo) || $token === '') {
        return ['success' => false, 'message' => 'Link inválido.'];
    }
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT id, email FROM users WHERE email_verify_token_hash = ? AND email_verified_at IS NULL LIMIT 1'
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['success' => false, 'message' => 'Link inválido ou já utilizado.'];
    }

    $pdo->prepare(
        'UPDATE users SET email_verified_at = NOW(), email_verify_token_hash = NULL WHERE id = ?'
    )->execute([(int)$user['id']]);

    return ['success' => true, 'message' => 'E-mail verificado com sucesso!', 'user_id' => (int)$user['id']];
}

function emailVerifyMarkVerified(PDO $pdo, int $userId): void
{
    if (!emailVerifyColumnsReady($pdo)) {
        return;
    }
    $pdo->prepare(
        'UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), email_verify_token_hash = NULL WHERE id = ?'
    )->execute([$userId]);
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
