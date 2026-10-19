<?php

require_once __DIR__ . '/abuse_policy_helpers.php';
require_once __DIR__ . '/user_ban_helpers.php';

class AbusePolicyService
{
    public static function validateLinkCreation(
        PDO $pdo,
        int $userId,
        string $destinationUrl,
        string $slug,
        string $subdomain = 'ROOT',
        bool $runExternalScan = true
    ): ?array {
        $formatError = abuseValidateHttpUrl($destinationUrl);
        if ($formatError !== null) {
            self::recordStrike($pdo, $userId, 'invalid_url', ['url' => $destinationUrl]);
            return self::blocked($formatError, 'invalid_url');
        }

        $url = abuseNormalizeUrl($destinationUrl);

        foreach (['slug' => $slug, 'subdomain' => ($subdomain === 'ROOT' ? '' : $subdomain)] as $type => $value) {
            if ($value === '') {
                continue;
            }
            $keywordError = abuseCheckKeywordRules($pdo, $value, $type);
            if ($keywordError !== null) {
                self::recordStrike($pdo, $userId, 'keyword_' . $type, ['value' => $value]);
                return self::blocked($keywordError, 'keyword_blocked');
            }
        }

        $domainError = abuseCheckDomainBlocklist($pdo, $url, 'destination');
        if ($domainError !== null) {
            self::recordStrike($pdo, $userId, 'domain_blocklist', ['url' => $url]);
            return self::blocked($domainError, 'domain_blocked');
        }

        $destKeywordError = abuseCheckKeywordRules($pdo, parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH), 'destination');
        if ($destKeywordError !== null) {
            self::recordStrike($pdo, $userId, 'destination_keyword', ['url' => $url]);
            return self::blocked('URL de destino contém termos não permitidos.', 'destination_keyword');
        }

        $impersonationError = abuseCheckBrandImpersonation($pdo, $slug, $subdomain, $url);
        if ($impersonationError !== null) {
            self::recordStrike($pdo, $userId, 'brand_impersonation', compact('slug', 'subdomain', 'url'));
            return self::blocked($impersonationError, 'brand_impersonation');
        }

        if ($runExternalScan) {
            $scan = self::scanUrlWithCache($pdo, $url, false);
            if (($scan['verdict'] ?? '') === 'blocked') {
                self::recordStrike($pdo, $userId, 'external_scan', $scan);
                return self::blocked(
                    'Esta URL foi bloqueada por nossa verificação de segurança.',
                    'threat_detected',
                    $scan
                );
            }
        }

        return null;
    }

    public static function validateUrlEdit(PDO $pdo, int $userId, string $destinationUrl, bool $runExternalScan = true): ?array
    {
        return self::validateLinkCreation($pdo, $userId, $destinationUrl, '', 'ROOT', $runExternalScan);
    }

    public static function scanUrlWithCache(PDO $pdo, string $url, bool $quickOnly = false): array
    {
        $normalized = abuseNormalizeUrl($url);
        $hash = abuseUrlHash($normalized);

        if (abusePolicyTableExists($pdo, 'url_scan_cache')) {
            $stmt = $pdo->prepare(
                'SELECT verdict, sources, scanned_at, expires_at FROM url_scan_cache WHERE url_hash = ? LIMIT 1'
            );
            $stmt->execute([$hash]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cached && strtotime((string)$cached['expires_at']) > time()) {
                return [
                    'verdict' => (string)$cached['verdict'],
                    'sources' => json_decode((string)($cached['sources'] ?? '[]'), true) ?: [],
                    'cached' => true,
                ];
            }
        }

        $sources = [];
        $blocked = false;

        if (self::feedContainsUrl($pdo, $hash)) {
            $blocked = true;
            $sources[] = 'abuse_feed';
        }

        if (!$blocked && !$quickOnly) {
            $webRisk = self::queryWebRiskApi($normalized);
            if ($webRisk['blocked']) {
                $blocked = true;
                $sources[] = 'web_risk';
            } elseif ($webRisk['error'] && self::scanFailClosed()) {
                return ['verdict' => 'blocked', 'sources' => ['scan_unavailable'], 'error' => true];
            }
        }

        $verdict = $blocked ? 'blocked' : 'safe';
        self::storeScanCache($pdo, $hash, $normalized, $verdict, $sources);

        return ['verdict' => $verdict, 'sources' => $sources, 'cached' => false];
    }

    public static function scanFailClosed(): bool
    {
        return envOrDefault('ABUSE_SCAN_FAIL_OPEN', '0') !== '1';
    }

    public static function queryWebRiskApi(string $url): array
    {
        $apiKey = envOrDefault('WEB_RISK_API_KEY', '');
        if ($apiKey === '') {
            return ['blocked' => false, 'error' => false];
        }

        $endpoint = 'https://webrisk.googleapis.com/v1/uris:search?'
            . 'threatTypes=MALWARE&threatTypes=SOCIAL_ENGINEERING&threatTypes=UNWANTED_SOFTWARE'
            . '&uri=' . rawurlencode($url)
            . '&key=' . rawurlencode($apiKey);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($body)) {
            error_log('web_risk_api_error: HTTP ' . $httpCode);
            return ['blocked' => false, 'error' => true];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['blocked' => false, 'error' => true];
        }

        $threat = $json['threat'] ?? null;
        if (is_array($threat) && !empty($threat['threatTypes'])) {
            return ['blocked' => true, 'error' => false, 'threatTypes' => $threat['threatTypes']];
        }

        return ['blocked' => false, 'error' => false];
    }

    public static function feedContainsUrl(PDO $pdo, string $urlHash): bool
    {
        if (!abusePolicyTableExists($pdo, 'abuse_feed_entries')) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT id FROM abuse_feed_entries WHERE url_hash = ? LIMIT 1');
        $stmt->execute([$urlHash]);
        return (bool)$stmt->fetchColumn();
    }

    public static function storeScanCache(PDO $pdo, string $hash, string $url, string $verdict, array $sources): void
    {
        if (!abusePolicyTableExists($pdo, 'url_scan_cache')) {
            return;
        }
        $ttlHours = ($verdict === 'blocked') ? 168 : 24;
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlHours * 3600));
        $stmt = $pdo->prepare(
            'INSERT INTO url_scan_cache (url_hash, url_normalized, verdict, sources, scanned_at, expires_at)
             VALUES (?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE verdict = VALUES(verdict), sources = VALUES(sources),
             scanned_at = NOW(), expires_at = VALUES(expires_at)'
        );
        $stmt->execute([$hash, $url, $verdict, json_encode($sources, JSON_UNESCAPED_UNICODE), $expiresAt]);
    }

    public static function pauseLinkForSecurity(PDO $pdo, int $linkId, string $verdict, array $details = []): void
    {
        if (!abusePolicyColumnExists($pdo, 'links', 'security_paused')) {
            $pdo->prepare("UPDATE links SET status = 'paused' WHERE id = ?")->execute([$linkId]);
            return;
        }
        $sets = ["status = 'paused'", 'security_paused = 1', 'security_verdict = ?', 'security_scanned_at = NOW()'];
        $params = [$verdict, $linkId];
        $pdo->prepare('UPDATE links SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        securityAuditLog($pdo, 'link_security_paused', null, $linkId, $details);
    }

    public static function recordStrike(PDO $pdo, int $userId, string $reason, array $details = []): void
    {
        if ($userId <= 0 || !abusePolicyColumnExists($pdo, 'users', 'abuse_strike_count')) {
            securityAuditLog($pdo, 'abuse_attempt', $userId, null, array_merge(['reason' => $reason], $details));
            return;
        }

        $stmt = $pdo->prepare('SELECT abuse_strike_count, abuse_strike_window_start FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['abuse_strike_count' => 0, 'abuse_strike_window_start' => null];

        $windowStart = $row['abuse_strike_window_start'] ?? null;
        $count = (int)($row['abuse_strike_count'] ?? 0);
        if ($windowStart === null || strtotime((string)$windowStart) < strtotime('-30 days')) {
            $count = 0;
            $windowStart = date('Y-m-d H:i:s');
        }
        $count++;

        $pdo->prepare('UPDATE users SET abuse_strike_count = ?, abuse_strike_window_start = ? WHERE id = ?')
            ->execute([$count, $windowStart, $userId]);

        securityAuditLog($pdo, 'abuse_strike', $userId, null, array_merge(['reason' => $reason, 'strike' => $count], $details));

        if ($count >= 3) {
            self::autoBanUser($pdo, $userId, 'Ban automático: 3 violações de política de segurança em 30 dias.');
        }
    }

    public static function autoBanUser(PDO $pdo, int $userId, string $reason): void
    {
        if (!userBanColumnsReady($pdo)) {
            return;
        }
        $expiresAt = parseBanDurationPreset('30d');
        applyUserBan($pdo, $userId, $expiresAt, $reason, 0);
        if (abusePolicyColumnExists($pdo, 'users', 'session_invalidated_at')) {
            $pdo->prepare('UPDATE users SET session_invalidated_at = NOW() WHERE id = ?')->execute([$userId]);
        }
        abuseNotifyAdmin($pdo, '[linkneo.pro] Ban automático por abuso', '<p>Usuário #' . $userId . ' banido automaticamente.</p><p>' . htmlspecialchars($reason) . '</p>');
        securityAuditLog($pdo, 'auto_ban', $userId, null, ['reason' => $reason]);
    }

    public static function checkRedirectDestination(PDO $pdo, int $linkId, string $destinationUrl, bool $quickOnly = true): ?array
    {
        $formatError = abuseValidateHttpUrl($destinationUrl);
        if ($formatError !== null) {
            self::pauseLinkForSecurity($pdo, $linkId, 'invalid_url');
            return self::blocked('Link desativado por segurança.', 'invalid_url');
        }

        $scan = self::scanUrlWithCache($pdo, $destinationUrl, $quickOnly);
        if (($scan['verdict'] ?? '') === 'blocked') {
            self::pauseLinkForSecurity($pdo, $linkId, 'threat_detected', $scan);
            return self::blocked('Link desativado por segurança.', 'threat_detected', $scan);
        }

        return null;
    }

    private static function blocked(string $message, string $code, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'error' => $code,
            'message' => $message,
            'blocked' => true,
        ], $extra);
    }
}
