<?php

function affiliateTableExists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$tableName]);
    $cache[$tableName] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$tableName];
}

function affiliateColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tableName, $columnName]);
    $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function affiliateNormalizeCode(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return '';
    }
    return preg_replace('/[^A-Z0-9\-_]/', '', $code) ?? '';
}

function affiliateCookieName(): string
{
    return 'enc_aff_code';
}

function affiliateGetCodeFromRequest(): string
{
    $raw = (string)($_GET['aff'] ?? '');
    if ($raw === '') {
        $raw = (string)($_GET['affiliate'] ?? '');
    }
    return affiliateNormalizeCode($raw);
}

function affiliateRememberCodeCookie(string $code, int $days = 30): void
{
    $code = affiliateNormalizeCode($code);
    if ($code === '') {
        return;
    }
    $secure = function_exists('isHttpsRequest') ? isHttpsRequest() : false;
    $lifetimeSeconds = max(1, $days) * 86400;
    setcookie(affiliateCookieName(), $code, [
        'expires' => time() + $lifetimeSeconds,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[affiliateCookieName()] = $code;
}

function affiliateGetCodeFromCookie(): string
{
    return affiliateNormalizeCode((string)($_COOKIE[affiliateCookieName()] ?? ''));
}

function affiliateConsumeTrackingCode(): string
{
    $queryCode = affiliateGetCodeFromRequest();
    if ($queryCode !== '') {
        affiliateRememberCodeCookie($queryCode, 30);
        return $queryCode;
    }
    return affiliateGetCodeFromCookie();
}

function affiliateFindByCode(PDO $pdo, string $code): ?array
{
    if (!affiliateTableExists($pdo, 'affiliates')) {
        return null;
    }
    $normalized = affiliateNormalizeCode($code);
    if ($normalized === '') {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, user_id, affiliate_code, is_active FROM affiliates WHERE affiliate_code = ? LIMIT 1");
    $stmt->execute([$normalized]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if ((int)($row['is_active'] ?? 0) !== 1) {
        return null;
    }
    return $row;
}

function affiliateFindByUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !affiliateTableExists($pdo, 'affiliates')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, user_id, affiliate_code, is_active FROM affiliates WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return $row;
}

function affiliateBuildPublicLink(string $code): string
{
    $normalized = affiliateNormalizeCode($code);
    if ($normalized === '') {
        return '';
    }
    $scheme = (function_exists('isHttpsRequest') && isHttpsRequest()) ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host);
    return $scheme . '://' . $host . '/?aff=' . rawurlencode($normalized);
}

function affiliateCaptureGeoFromRequest(): array
{
    $country = (string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Desconhecido');
    $region = (string)($_SERVER['HTTP_CF_REGION'] ?? 'Desconhecido');
    $city = (string)($_SERVER['HTTP_CF_IPCITY'] ?? 'Desconhecido');
    return [
        'country' => $country !== '' ? $country : 'Desconhecido',
        'region' => $region !== '' ? $region : 'Desconhecido',
        'city' => $city !== '' ? $city : 'Desconhecido',
    ];
}

function affiliateRegisterReferral(PDO $pdo, int $referredUserId): bool
{
    if ($referredUserId <= 0) {
        return false;
    }
    if (!affiliateTableExists($pdo, 'affiliate_referrals') || !affiliateTableExists($pdo, 'affiliates')) {
        return false;
    }

    $code = affiliateConsumeTrackingCode();
    if ($code === '') {
        return false;
    }

    $affiliate = affiliateFindByCode($pdo, $code);
    if (!$affiliate) {
        return false;
    }

    $affiliateUserId = (int)($affiliate['user_id'] ?? 0);
    if ($affiliateUserId <= 0 || $affiliateUserId === $referredUserId) {
        return false;
    }

    $geo = affiliateCaptureGeoFromRequest();
    $ip = function_exists('getRealIP') ? (string)getRealIP() : (string)($_SERVER['REMOTE_ADDR'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO affiliate_referrals (
            affiliate_id, referred_user_id, referral_code, source,
            captured_ip, captured_country, captured_region, captured_city
        )
        VALUES (?, ?, ?, 'web', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            affiliate_id = VALUES(affiliate_id),
            referral_code = VALUES(referral_code),
            source = VALUES(source)
    ");
    $stmt->execute([
        (int)$affiliate['id'],
        $referredUserId,
        (string)$affiliate['affiliate_code'],
        $ip,
        $geo['country'],
        $geo['region'],
        $geo['city'],
    ]);
    return true;
}

function affiliateExpectedReleaseAt(string $paymentMethod, ?DateTimeImmutable $confirmedAt = null): DateTimeImmutable
{
    $base = $confirmedAt ?? new DateTimeImmutable('now');
    $method = strtoupper(trim($paymentMethod));
    if ($method === 'PIX') {
        $days = 0;
    } elseif ($method === 'BOLETO') {
        $days = 2;
    } elseif ($method === 'DEBIT_CARD') {
        $days = 3;
    } else {
        $days = 32;
    }
    $days += 10;
    return $base->modify('+' . $days . ' day');
}

function affiliateCommissionRateForInstallment(int $installmentNumber): float
{
    return ($installmentNumber <= 1) ? 50.0 : 35.0;
}

?>
