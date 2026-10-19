<?php

function userBanColumnExists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function userBanColumnsReady(PDO $pdo): bool {
    return userBanColumnExists($pdo, 'users', 'is_banned')
        && userBanColumnExists($pdo, 'users', 'ban_expires_at')
        && userBanColumnExists($pdo, 'links', 'admin_ban_paused');
}

function userBanBioColumnReady(PDO $pdo): bool {
    return userBanColumnExists($pdo, 'bio_pages', 'admin_ban_paused');
}

function invalidateUserSessions(PDO $pdo, int $userId): void {
    if (!userBanColumnExists($pdo, 'users', 'session_invalidated_at')) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE users SET session_invalidated_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);
}

function fetchUserBanRow(PDO $pdo, int $userId): ?array {
    if (!userBanColumnsReady($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, is_banned, ban_expires_at, ban_reason FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function isUserCurrentlyBanned(array $userRow): bool {
    if ((int)($userRow['is_banned'] ?? 0) !== 1) {
        return false;
    }
    $expiresAt = $userRow['ban_expires_at'] ?? null;
    if ($expiresAt === null || $expiresAt === '') {
        return true;
    }
    return strtotime((string)$expiresAt) > time();
}

function formatUserBanMessage(array $userRow): string {
    $expiresAt = $userRow['ban_expires_at'] ?? null;
    if ($expiresAt === null || $expiresAt === '') {
        return 'Conta suspensa permanentemente.';
    }
    $formatted = date('d/m/Y H:i', strtotime((string)$expiresAt));
    return 'Conta suspensa até ' . $formatted . '.';
}

function restoreLinksPausedByBan(PDO $pdo, int $userId): void {
    if (!userBanColumnExists($pdo, 'links', 'admin_ban_paused')) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE links SET status = 'active', admin_ban_paused = 0 WHERE user_id = ? AND admin_ban_paused = 1");
    $stmt->execute([$userId]);
    restoreBioPagesPausedByBan($pdo, $userId);
}

function pauseBioPagesForBan(PDO $pdo, int $userId): void {
    if (!userBanBioColumnReady($pdo)) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE bio_pages SET page_status = 'paused', admin_ban_paused = 1 WHERE user_id = ? AND page_status = 'published'");
    $stmt->execute([$userId]);
}

function restoreBioPagesPausedByBan(PDO $pdo, int $userId): void {
    if (!userBanBioColumnReady($pdo)) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE bio_pages SET page_status = 'published', admin_ban_paused = 0 WHERE user_id = ? AND admin_ban_paused = 1");
    $stmt->execute([$userId]);
}

function pauseActiveLinksForBan(PDO $pdo, int $userId): void {
    if (!userBanColumnExists($pdo, 'links', 'admin_ban_paused')) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE links SET status = 'paused', admin_ban_paused = 1 WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    pauseBioPagesForBan($pdo, $userId);
}

function clearUserBanFlags(PDO $pdo, int $userId): void {
    if (!userBanColumnsReady($pdo)) {
        return;
    }
    $stmt = $pdo->prepare("
        UPDATE users
        SET is_banned = 0, ban_expires_at = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
}

function resolveExpiredUserBan(PDO $pdo, int $userId): bool {
    if (!userBanColumnsReady($pdo)) {
        return false;
    }
    $row = fetchUserBanRow($pdo, $userId);
    if (!$row || (int)($row['is_banned'] ?? 0) !== 1) {
        return false;
    }
    $expiresAt = $row['ban_expires_at'] ?? null;
    if ($expiresAt === null || $expiresAt === '') {
        return false;
    }
    if (strtotime((string)$expiresAt) > time()) {
        return false;
    }
    clearUserBanFlags($pdo, $userId);
    restoreLinksPausedByBan($pdo, $userId);
    return true;
}

function liftUserBan(PDO $pdo, int $userId): bool {
    if (!userBanColumnsReady($pdo)) {
        return false;
    }
    $row = fetchUserBanRow($pdo, $userId);
    if (!$row || (int)($row['is_banned'] ?? 0) !== 1) {
        return false;
    }
    clearUserBanFlags($pdo, $userId);
    restoreLinksPausedByBan($pdo, $userId);
    return true;
}

function applyUserBan(PDO $pdo, int $userId, ?DateTime $expiresAt, ?string $reason, int $adminId): array {
    if (!userBanColumnsReady($pdo)) {
        return ['success' => false, 'message' => 'Migração de banimento não aplicada.'];
    }

    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Usuário inválido.'];
    }

    if ($adminId > 0 && $userId === $adminId) {
        return ['success' => false, 'message' => 'Você não pode banir a própria conta.'];
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    if (userBanColumnExists($pdo, 'users', 'is_admin')) {
        $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ? LIMIT 1");
    }
    $stmt->execute([$userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        return ['success' => false, 'message' => 'Usuário não encontrado.'];
    }
    if (userBanColumnExists($pdo, 'users', 'is_admin') && (int)($target['is_admin'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Não é possível banir contas administrativas.'];
    }

    if ($expiresAt !== null) {
        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $minExpiry = (clone $now)->modify('+1 hour');
        $maxExpiry = (clone $now)->modify('+365 days');
        if ($expiresAt < $minExpiry) {
            return ['success' => false, 'message' => 'Ban temporário deve durar pelo menos 1 hora.'];
        }
        if ($expiresAt > $maxExpiry) {
            return ['success' => false, 'message' => 'Ban temporário não pode exceder 365 dias.'];
        }
    }

    $reasonClean = $reason !== null ? trim($reason) : '';
    if (strlen($reasonClean) > 500) {
        $reasonClean = substr($reasonClean, 0, 500);
    }
    if ($reasonClean === '') {
        $reasonClean = null;
    }

    $expiresSql = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("
        UPDATE users
        SET is_banned = 1,
            ban_expires_at = ?,
            ban_reason = ?,
            banned_by = ?,
            banned_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$expiresSql, $reasonClean, $adminId > 0 ? $adminId : null, $userId]);

    pauseActiveLinksForBan($pdo, $userId);
    invalidateUserSessions($pdo, $userId);

    return ['success' => true, 'message' => 'Usuário banido com sucesso.'];
}

function getUserBanBlockResponse(PDO $pdo, int $userId): ?array {
    if (!userBanColumnsReady($pdo)) {
        return null;
    }
    resolveExpiredUserBan($pdo, $userId);
    $row = fetchUserBanRow($pdo, $userId);
    if (!$row || !isUserCurrentlyBanned($row)) {
        return null;
    }
    return [
        'success' => false,
        'message' => formatUserBanMessage($row),
        'banned' => true
    ];
}

function parseBanDurationPreset(string $preset): ?DateTime {
    $preset = strtolower(trim($preset));
    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $map = [
        '1h' => '+1 hour',
        '24h' => '+24 hours',
        '7d' => '+7 days',
        '30d' => '+30 days',
    ];
    if (!isset($map[$preset])) {
        return null;
    }
    return $now->modify($map[$preset]);
}

function parseBanUntilDatetime(string $raw): ?DateTime {
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('America/Sao_Paulo'));
    if ($dt instanceof DateTime) {
        return $dt;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw, new DateTimeZone('America/Sao_Paulo'));
    if ($dt instanceof DateTime) {
        return $dt;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
}
