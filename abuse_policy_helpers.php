<?php

function abusePolicyColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function abusePolicyTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    $cache[$table] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$table];
}

function isPrivateOrReservedIp(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1') {
            return true;
        }
        if (stripos($ip, 'fe80:') === 0) {
            return true;
        }
        if (stripos($ip, 'fc') === 0 || stripos($ip, 'fd') === 0) {
            return true;
        }
        return false;
    }
    return true;
}

function isSafePublicHttpUrl(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $host = (string)($parts['host'] ?? '');
    if ($host === '') {
        return false;
    }
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true)) {
        return false;
    }

    $resolved = @gethostbynamel($host);
    if (!is_array($resolved) || empty($resolved)) {
        return false;
    }
    foreach ($resolved as $ip) {
        if (isPrivateOrReservedIp($ip)) {
            return false;
        }
    }
    return true;
}

function abuseNormalizeToken(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
    return $value;
}

function abuseContainsPunycode(string $value): bool
{
    return (bool)preg_match('/(^|\.)xn--/i', $value);
}

function abuseNormalizeUrl(string $url): string
{
    $url = trim($url);
    if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '/');
    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    return $scheme . '://' . $host . $path . $query;
}

function abuseUrlHash(string $url): string
{
    return hash('sha256', abuseNormalizeUrl($url));
}

function abuseBrandOfficialDomains(): array
{
    return [
        'netflix' => ['netflix.com', 'www.netflix.com'],
        'paypal' => ['paypal.com', 'www.paypal.com', 'paypal.me'],
        'nubank' => ['nubank.com.br', 'www.nubank.com.br', 'app.nubank.com.br'],
        'itau' => ['itau.com.br', 'www.itau.com.br'],
        'bradesco' => ['bradesco.com.br', 'www.bradesco.com.br'],
        'santander' => ['santander.com.br', 'www.santander.com.br'],
        'bb' => ['bb.com.br', 'www.bb.com.br'],
        'caixa' => ['caixa.gov.br', 'www.caixa.gov.br'],
        'amazon' => ['amazon.com', 'www.amazon.com', 'amazon.com.br'],
        'google' => ['google.com', 'www.google.com', 'accounts.google.com'],
        'apple' => ['apple.com', 'www.apple.com'],
        'microsoft' => ['microsoft.com', 'www.microsoft.com', 'login.microsoftonline.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
        'whatsapp' => ['whatsapp.com', 'www.whatsapp.com', 'wa.me', 'api.whatsapp.com'],
        'starling' => ['starlingbank.com', 'www.starlingbank.com'],
        'hsbc' => ['hsbc.com', 'www.hsbc.com', 'hsbc.com.br'],
        'barclays' => ['barclays.com', 'www.barclays.co.uk'],
        'revolut' => ['revolut.com', 'www.revolut.com'],
        'mercadolivre' => ['mercadolivre.com.br', 'www.mercadolivre.com.br'],
        'mercadopago' => ['mercadopago.com.br', 'www.mercadopago.com.br'],
        'govbr' => ['gov.br', 'www.gov.br', 'servicos.gov.br'],
    ];
}

function abuseSuspiciousPathTerms(): array
{
    return [
        'login', 'signin', 'sign-in', 'logon', 'auth', 'authenticate', 'verify',
        'verification', 'secure', 'security', 'account', 'update', 'confirm',
        'password', 'reset', 'unlock', 'banking', 'wallet', 'payment', 'billing',
    ];
}

function abuseHostMatchesOfficial(string $host, array $officialHosts): bool
{
    $host = strtolower(preg_replace('/^www\./', '', $host));
    foreach ($officialHosts as $official) {
        $official = strtolower(preg_replace('/^www\./', '', $official));
        if ($host === $official || str_ends_with($host, '.' . $official)) {
            return true;
        }
    }
    return false;
}

function abuseLevenshteinClose(string $needle, string $haystack, int $maxDistance = 2): bool
{
    if ($needle === '' || $haystack === '') {
        return false;
    }
    if (strpos($haystack, $needle) !== false) {
        return true;
    }
    $len = strlen($needle);
    if ($len < 4) {
        return false;
    }
    $limit = max(0, strlen($haystack) - $len + 1);
    for ($i = 0; $i <= $limit; $i++) {
        $slice = substr($haystack, $i, $len);
        if ($slice !== '' && levenshtein($needle, $slice) <= $maxDistance) {
            return true;
        }
    }
    return false;
}

function abuseFetchKeywordRules(PDO $pdo, string $ruleType): array
{
    if (!abusePolicyTableExists($pdo, 'abuse_keyword_rules')) {
        return abuseDefaultKeywordRules($ruleType);
    }
    $stmt = $pdo->prepare(
        "SELECT term FROM abuse_keyword_rules WHERE is_active = 1 AND severity = 'block'
         AND (rule_type = ? OR rule_type = 'any') ORDER BY id ASC"
    );
    $stmt->execute([$ruleType]);
    $terms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$terms) {
        return abuseDefaultKeywordRules($ruleType);
    }
    return array_values(array_unique(array_map('strval', $terms)));
}

function abuseDefaultKeywordRules(string $ruleType): array
{
    $brands = [
        'netflix', 'paypal', 'nubank', 'itau', 'bradesco', 'santander', 'bb', 'caixa',
        'amazon', 'google', 'apple', 'microsoft', 'instagram', 'facebook', 'whatsapp',
        'starling', 'hsbc', 'barclays', 'revolut', 'mercadolivre', 'mercadopago',
        'govbr', 'gov', 'receita', 'serasa', 'banco', 'bank', 'binance', 'coinbase',
        'spotify', 'disney', 'hbo', 'prime', 'telegram', 'tiktok', 'twitter', 'x',
    ];
    $loginTerms = [
        'login', 'signin', 'sign-in', 'logon', 'secure', 'verify', 'verification',
        'account', 'password', 'auth', 'authenticate', 'banking', 'wallet', 'payment',
    ];

    if ($ruleType === 'slug' || $ruleType === 'subdomain') {
        return array_merge($brands, $loginTerms);
    }
    if ($ruleType === 'destination') {
        return $loginTerms;
    }
    return array_merge($brands, $loginTerms);
}

function abuseCheckKeywordRules(PDO $pdo, string $value, string $ruleType): ?string
{
    $normalized = abuseNormalizeToken($value);
    if ($normalized === '') {
        return null;
    }
    if (abuseContainsPunycode($value)) {
        return 'Uso de caracteres especiais (punycode) não permitido.';
    }

    foreach (abuseFetchKeywordRules($pdo, $ruleType) as $term) {
        $termNorm = abuseNormalizeToken((string)$term);
        if ($termNorm === '') {
            continue;
        }
        if (strpos($normalized, $termNorm) !== false || abuseLevenshteinClose($termNorm, $normalized)) {
            return 'Termo não permitido detectado: "' . $term . '".';
        }
    }
    return null;
}

function abuseFetchDomainBlocklist(PDO $pdo): array
{
    if (!abusePolicyTableExists($pdo, 'abuse_domain_blocklist')) {
        return abuseDefaultDomainBlocklist();
    }
    $stmt = $pdo->query(
        "SELECT pattern, match_type, reason FROM abuse_domain_blocklist WHERE is_active = 1 ORDER BY id ASC"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!$rows) {
        return abuseDefaultDomainBlocklist();
    }
    return $rows;
}

function abuseDefaultDomainBlocklist(): array
{
    $suffixes = ['.tk', '.ml', '.ga', '.cf', '.gq', '.bond', '.top', '.xyz', '.cam', '.rest'];
    $rows = [];
    foreach ($suffixes as $suffix) {
        $rows[] = ['pattern' => $suffix, 'match_type' => 'suffix', 'reason' => 'TLD de alto risco'];
    }
    return $rows;
}

function abuseCheckDomainBlocklist(PDO $pdo, string $url, string $context = 'destination'): ?string
{
    $parts = parse_url(abuseNormalizeUrl($url));
    if (!is_array($parts)) {
        return 'URL inválida.';
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = strtolower((string)($parts['path'] ?? ''));

    foreach (abuseFetchDomainBlocklist($pdo) as $row) {
        $pattern = strtolower((string)($row['pattern'] ?? ''));
        $matchType = (string)($row['match_type'] ?? 'exact');
        if ($pattern === '') {
            continue;
        }
        if ($matchType === 'suffix' && str_ends_with($host, $pattern)) {
            return (string)($row['reason'] ?? 'Domínio bloqueado por política de segurança.');
        }
        if ($matchType === 'exact' && ($host === $pattern || str_ends_with($host, '.' . $pattern))) {
            return (string)($row['reason'] ?? 'Domínio bloqueado por política de segurança.');
        }
        if ($matchType === 'destination_path' && $context === 'destination') {
            foreach (abuseSuspiciousPathTerms() as $term) {
                if (strpos($path, $term) !== false && strpos($host, $pattern) !== false) {
                    return 'Combinação de domínio e caminho suspeita bloqueada.';
                }
            }
        }
    }
    return null;
}

function abuseCheckBrandImpersonation(PDO $pdo, string $slug, string $subdomain, string $destinationUrl): ?string
{
    $brands = abuseBrandOfficialDomains();
    $slugNorm = abuseNormalizeToken($slug);
    $subNorm = abuseNormalizeToken($subdomain === 'ROOT' ? '' : $subdomain);
    $destParts = parse_url(abuseNormalizeUrl($destinationUrl));
    $destHost = strtolower((string)($destParts['host'] ?? ''));
    $destHost = preg_replace('/^www\./', '', $destHost);

    foreach ($brands as $brand => $officialHosts) {
        $brandNorm = abuseNormalizeToken($brand);
        $slugHit = ($slugNorm !== '' && (strpos($slugNorm, $brandNorm) !== false || abuseLevenshteinClose($brandNorm, $slugNorm)));
        $subHit = ($subNorm !== '' && (strpos($subNorm, $brandNorm) !== false || abuseLevenshteinClose($brandNorm, $subNorm)));
        if (!$slugHit && !$subHit) {
            continue;
        }
        if ($destHost !== '' && !abuseHostMatchesOfficial($destHost, $officialHosts)) {
            return 'Não é permitido usar nomes de marcas em slugs/subdomínios apontando para sites não oficiais.';
        }
    }
    return null;
}

function abuseValidateHttpUrl(string $url): ?string
{
    if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return 'URL inválida. Use um endereço HTTP/HTTPS válido.';
    }
    if (!isSafePublicHttpUrl($url)) {
        return 'URL de destino não permitida (endereço interno ou reservado).';
    }
    return null;
}

function securityAuditLog(PDO $pdo, string $eventType, ?int $userId, ?int $linkId, array $details = []): void
{
    if (!abusePolicyTableExists($pdo, 'security_audit_log')) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO security_audit_log (event_type, user_id, link_id, details_json) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventType,
            $userId,
            $linkId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        error_log('security_audit_log: ' . $e->getMessage());
    }
}

function abuseNotifyAdmin(PDO $pdo, string $subject, string $htmlBody): void
{
    if (!function_exists('send_smtp_mail')) {
        require_once __DIR__ . '/smtp_mailer.php';
    }
    $adminEmail = envOrDefault('SECURITY_ALERT_EMAIL', envOrDefault('SMTP_USER', ''));
    if ($adminEmail === '') {
        return;
    }
    @send_smtp_mail($adminEmail, $subject, $htmlBody);
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
