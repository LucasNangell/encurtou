<?php
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
require_once 'user_ban_helpers.php';
require_once 'subdomain_helpers.php';
require_once 'abuse_policy_helpers.php';
$pdo->exec("SET time_zone = '-03:00';");

function tableExists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$table];
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$key];
}

function getCurrentUserEmail(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return strtolower(trim((string)$stmt->fetchColumn()));
}

function buildPeriodFilter(string $period): array {
    $normalized = strtolower(trim($period));
    $allowed = ['1h', '4h', '24h', '7d', '30d', '90d', 'all'];
    if (!in_array($normalized, $allowed, true)) {
        $normalized = '24h';
    }
    if ($normalized === 'all') {
        return ['period' => $normalized, 'where' => '', 'label' => 'Todo o período'];
    }

    $intervalMap = [
        '1h' => '1 HOUR',
        '4h' => '4 HOUR',
        '24h' => '24 HOUR',
        '7d' => '7 DAY',
        '30d' => '30 DAY',
        '90d' => '90 DAY'
    ];
    return [
        'period' => $normalized,
        'where' => " WHERE clicked_at >= NOW() - INTERVAL " . $intervalMap[$normalized] . " ",
        'label' => $normalized
    ];
}

function adminUserCanModerate(PDO $pdo, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }
    $fields = ['email'];
    if (columnExists($pdo, 'users', 'can_moderate')) {
        $fields[] = 'can_moderate';
    }
    if (columnExists($pdo, 'users', 'is_admin')) {
        $fields[] = 'is_admin';
    }
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    if ((int)($row['can_moderate'] ?? 0) === 1 || (int)($row['is_admin'] ?? 0) === 1) {
        return true;
    }
    return strtolower(trim((string)($row['email'] ?? ''))) === 'nangellbusiness@gmail.com';
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
if (!adminUserCanModerate($pdo, $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

function buildAdminShortUrl(string $subdomain, string $path): string {
    $baseHost = subdomainGetBaseHost();
    $sub = strtoupper(trim($subdomain));
    if ($sub === '' || $sub === 'ROOT') {
        return 'https://' . $baseHost . '/' . ltrim($path, '/');
    }
    return 'https://' . strtolower($subdomain) . '.' . $baseHost . '/' . ltrim($path, '/');
}

if ($action === 'list_user_links') {
    try {
        $targetUserId = (int)($_GET['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido.']);
            exit;
        }
        if (!tableExists($pdo, 'links')) {
            echo json_encode(['success' => true, 'links' => [], 'total' => 0]);
            exit;
        }

        $term = trim((string)($_GET['term'] ?? ''));
        $hasBanPausedCol = columnExists($pdo, 'links', 'admin_ban_paused');
        $banPausedField = $hasBanPausedCol ? ', l.admin_ban_paused' : ', 0 AS admin_ban_paused';
        $where = ['l.user_id = :uid'];
        $params = [':uid' => $targetUserId];
        if ($term !== '') {
            $where[] = '(l.path LIKE :term OR l.title LIKE :term OR l.original_url LIKE :term OR l.subdomain LIKE :term)';
            $params[':term'] = '%' . $term . '%';
        }

        $sql = "
            SELECT l.id, l.type, l.path, l.subdomain, l.title, l.original_url, l.status, l.clicks, l.created_at
                   {$banPausedField}
            FROM links l
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.created_at DESC
            LIMIT 200
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($links as &$linkRow) {
            $linkRow['short_url'] = buildAdminShortUrl((string)($linkRow['subdomain'] ?? 'ROOT'), (string)($linkRow['path'] ?? ''));
        }
        unset($linkRow);

        echo json_encode(['success' => true, 'links' => $links, 'total' => count($links)]);
    } catch (Throwable $e) {
        error_log('admin_dashboard.php list_user_links error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar links do usuário.']);
    }
    exit;
}

if ($action === 'ban_user') {
    requireCsrfToken();
    try {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $banType = strtolower(trim((string)($_POST['ban_type'] ?? 'permanent')));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $expiresAt = null;

        if ($banType === 'temporary') {
            $preset = trim((string)($_POST['duration_preset'] ?? ''));
            $banUntil = trim((string)($_POST['ban_until'] ?? ''));
            if ($preset !== '') {
                $expiresAt = parseBanDurationPreset($preset);
            } elseif ($banUntil !== '') {
                $expiresAt = parseBanUntilDatetime($banUntil);
            }
            if (!$expiresAt) {
                echo json_encode(['success' => false, 'message' => 'Informe uma duração válida para o ban temporário.']);
                exit;
            }
        }

        $result = applyUserBan($pdo, $targetUserId, $expiresAt, $reason, $userId);
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('admin_dashboard.php ban_user error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao banir usuário.']);
    }
    exit;
}

if ($action === 'unban_user') {
    requireCsrfToken();
    try {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido.']);
            exit;
        }
        if (!liftUserBan($pdo, $targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Usuário não está banido.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Banimento removido com sucesso.']);
    } catch (Throwable $e) {
        error_log('admin_dashboard.php unban_user error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao remover banimento.']);
    }
    exit;
}

if ($action === 'list_abuse_reports') {
    try {
        if (!tableExists($pdo, 'abuse_reports')) {
            echo json_encode(['success' => true, 'reports' => []]);
            exit;
        }
        $stmt = $pdo->query("SELECT * FROM abuse_reports WHERE status = 'open' ORDER BY created_at DESC LIMIT 100");
        echo json_encode(['success' => true, 'reports' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar denúncias.']);
    }
    exit;
}

if ($action === 'list_security_paused_links') {
    try {
        if (!tableExists($pdo, 'links') || !columnExists($pdo, 'links', 'security_paused')) {
            echo json_encode(['success' => true, 'links' => []]);
            exit;
        }
        $stmt = $pdo->query("SELECT id, user_id, subdomain, path, original_url, security_verdict, security_scanned_at FROM links WHERE security_paused = 1 ORDER BY security_scanned_at DESC LIMIT 200");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($links as &$row) {
            $row['short_url'] = buildAdminShortUrl((string)($row['subdomain'] ?? 'ROOT'), (string)($row['path'] ?? ''));
        }
        unset($row);
        echo json_encode(['success' => true, 'links' => $links]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar links pausados.']);
    }
    exit;
}

if ($action === 'list_audit_log') {
    try {
        if (!tableExists($pdo, 'security_audit_log')) {
            echo json_encode(['success' => true, 'events' => []]);
            exit;
        }
        $stmt = $pdo->query('SELECT * FROM security_audit_log ORDER BY created_at DESC LIMIT 200');
        echo json_encode(['success' => true, 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao listar auditoria.']);
    }
    exit;
}

if ($action === 'add_keyword_rule') {
    requireCsrfToken();
    $term = trim((string)($_POST['term'] ?? ''));
    $ruleType = trim((string)($_POST['rule_type'] ?? 'any'));
    if ($term === '' || !tableExists($pdo, 'abuse_keyword_rules')) {
        echo json_encode(['success' => false, 'message' => 'Termo inválido.']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO abuse_keyword_rules (term, rule_type, severity, is_active) VALUES (?, ?, ?, 1)');
    $stmt->execute([$term, $ruleType, 'block']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'trigger_security_rescan') {
    requireCsrfToken();
    echo json_encode([
        'success' => true,
        'message' => 'Execute run_abuse_rescan.php via cron ou CLI para varrer links existentes.',
    ]);
    exit;
}

try {
    $periodInput = (string)($_GET['period'] ?? '24h');
    $periodData = buildPeriodFilter($periodInput);
    $periodWhere = $periodData['where'];
    $periodValue = $periodData['period'];

    $totals = [
        'total_users' => 0,
        'total_links' => 0,
        'total_qrcodes' => 0,
        'total_biopages' => 0,
        'total_clicks' => 0,
        'clicks_24h' => 0
    ];

    if (tableExists($pdo, 'users')) {
        $totals['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
    if (tableExists($pdo, 'links')) {
        $totals['total_links'] = (int)$pdo->query("SELECT COUNT(*) FROM links WHERE type = 'link'")->fetchColumn();
        $totals['total_qrcodes'] = (int)$pdo->query("SELECT COUNT(*) FROM links WHERE type = 'qr'")->fetchColumn();
    }
    if (tableExists($pdo, 'bio_pages')) {
        $totals['total_biopages'] = (int)$pdo->query("SELECT COUNT(*) FROM bio_pages")->fetchColumn();
    }
    if (tableExists($pdo, 'clicks_log')) {
        $totals['total_clicks'] = (int)$pdo->query("SELECT COUNT(*) FROM clicks_log " . $periodWhere)->fetchColumn();
        $totals['clicks_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM clicks_log WHERE clicked_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
    }

    $usersByPlan = [];
    $activeUsersByPlan = [];
    if (tableExists($pdo, 'users') && columnExists($pdo, 'users', 'current_plan_id') && tableExists($pdo, 'plans')) {
        $stmtPlan = $pdo->query("
            SELECT COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id)) AS plan_name, COUNT(*) AS total
            FROM users u
            LEFT JOIN plans p ON p.id = u.current_plan_id
            GROUP BY COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id))
            ORDER BY total DESC
        ");
        $usersByPlan = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

        $hasExpires = columnExists($pdo, 'users', 'plan_expires_at');
        if ($hasExpires) {
            $stmtActive = $pdo->query("
                SELECT COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id)) AS plan_name, COUNT(*) AS total
                FROM users u
                LEFT JOIN plans p ON p.id = u.current_plan_id
                WHERE u.current_plan_id > 1
                  AND (u.plan_expires_at IS NULL OR u.plan_expires_at >= NOW())
                GROUP BY COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id))
                ORDER BY total DESC
            ");
        } else {
            $stmtActive = $pdo->query("
                SELECT COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id)) AS plan_name, COUNT(*) AS total
                FROM users u
                LEFT JOIN plans p ON p.id = u.current_plan_id
                WHERE u.current_plan_id > 1
                GROUP BY COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id))
                ORDER BY total DESC
            ");
        }
        $activeUsersByPlan = $stmtActive->fetchAll(PDO::FETCH_ASSOC);
    }

    $topCountries = [];
    $topCities = [];
    $trafficSourceType = [];
    $topReferrers = [];
    $clicksTrend = [];
    if (tableExists($pdo, 'clicks_log')) {
        $stmtCountries = $pdo->query("
            SELECT COALESCE(NULLIF(visitor_country, ''), 'Desconhecido') AS label, COUNT(*) AS total
            FROM clicks_log
            " . $periodWhere . "
            GROUP BY COALESCE(NULLIF(visitor_country, ''), 'Desconhecido')
            ORDER BY total DESC
            LIMIT 10
        ");
        $topCountries = $stmtCountries->fetchAll(PDO::FETCH_ASSOC);

        $stmtCities = $pdo->query("
            SELECT COALESCE(NULLIF(visitor_city, ''), 'Desconhecido') AS label, COUNT(*) AS total
            FROM clicks_log
            " . $periodWhere . "
            GROUP BY COALESCE(NULLIF(visitor_city, ''), 'Desconhecido')
            ORDER BY total DESC
            LIMIT 10
        ");
        $topCities = $stmtCities->fetchAll(PDO::FETCH_ASSOC);

        $stmtSourceType = $pdo->query("
            SELECT COALESCE(NULLIF(source, ''), 'desconhecido') AS label, COUNT(*) AS total
            FROM clicks_log
            " . $periodWhere . "
            GROUP BY COALESCE(NULLIF(source, ''), 'desconhecido')
            ORDER BY total DESC
        ");
        $trafficSourceType = $stmtSourceType->fetchAll(PDO::FETCH_ASSOC);

        $stmtReferrers = $pdo->query("
            SELECT
                CASE
                    WHEN referer IS NULL OR referer = '' THEN 'Direto / Orgânico'
                    ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '//', -1)
                END AS label,
                COUNT(*) AS total
            FROM clicks_log
            " . $periodWhere . "
            GROUP BY
                CASE
                    WHEN referer IS NULL OR referer = '' THEN 'Direto / Orgânico'
                    ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '//', -1)
                END
            ORDER BY total DESC
            LIMIT 10
        ");
        $topReferrers = $stmtReferrers->fetchAll(PDO::FETCH_ASSOC);

        $trendWhere = '';
        if ($periodValue === 'all') {
            $trendWhere = " WHERE clicked_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) ";
        } elseif ($periodValue === '1h' || $periodValue === '4h' || $periodValue === '24h') {
            $trendWhere = $periodWhere;
        } else {
            $trendWhere = $periodWhere;
        }
        $stmtTrend = $pdo->query("
            SELECT
                CASE
                    WHEN '$periodValue' IN ('1h', '4h', '24h') THEN DATE_FORMAT(clicked_at, '%Y-%m-%d %H:00:00')
                    ELSE DATE(clicked_at)
                END AS day,
                COUNT(*) AS total
            FROM clicks_log
            " . $trendWhere . "
            GROUP BY
                CASE
                    WHEN '$periodValue' IN ('1h', '4h', '24h') THEN DATE_FORMAT(clicked_at, '%Y-%m-%d %H:00:00')
                    ELSE DATE(clicked_at)
                END
            ORDER BY day ASC
        ");
        $clicksTrend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);
    }

    $allUsers = [];
    if (tableExists($pdo, 'users')) {
        $hasBanCols = userBanColumnsReady($pdo);
        $banSelect = $hasBanCols
            ? 'u.id, u.is_banned, u.ban_expires_at, u.ban_reason,'
            : 'u.id, 0 AS is_banned, NULL AS ban_expires_at, NULL AS ban_reason,';
        $linkCountSelect = tableExists($pdo, 'links')
            ? '(SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) AS link_count'
            : '0 AS link_count';
        $stmtAllUsers = $pdo->query("
            SELECT {$banSelect} u.name, u.email, u.phone, u.created_at,
                   COALESCE(p.name, 'Start (Free)') AS plan_name,
                   {$linkCountSelect}
            FROM users u
            LEFT JOIN plans p ON u.current_plan_id = p.id
            ORDER BY u.created_at DESC
        ");
        $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
        if ($hasBanCols) {
            foreach ($allUsers as &$userRow) {
                resolveExpiredUserBan($pdo, (int)$userRow['id']);
            }
            unset($userRow);
            $stmtAllUsers = $pdo->query("
                SELECT {$banSelect} u.name, u.email, u.phone, u.created_at,
                       COALESCE(p.name, 'Start (Free)') AS plan_name,
                       {$linkCountSelect}
                FROM users u
                LEFT JOIN plans p ON u.current_plan_id = p.id
                ORDER BY u.created_at DESC
            ");
            $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'period' => $periodValue,
        'totals' => $totals,
        'users_by_plan' => $usersByPlan,
        'active_users_by_plan' => $activeUsersByPlan,
        'top_countries' => $topCountries,
        'top_cities' => $topCities,
        'traffic_source_type' => $trafficSourceType,
        'top_referrers' => $topReferrers,
        'clicks_trend' => $clicksTrend,
        'all_users' => $allUsers
    ]);
} catch (Throwable $e) {
    error_log('admin_dashboard.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar dashboard administrativo.']);
}
?>
