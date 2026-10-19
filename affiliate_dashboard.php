<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
require_once 'affiliate_helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$requiredTables = ['affiliates', 'affiliate_referrals', 'affiliate_commissions'];
foreach ($requiredTables as $tableName) {
    if (!affiliateTableExists($pdo, $tableName)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Programa de afiliados ainda não inicializado no banco de dados.']);
        exit;
    }
}

$affiliate = affiliateFindByUser($pdo, $userId);
if (!$affiliate || (int)($affiliate['is_active'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso permitido apenas para afiliados ativos.']);
    exit;
}

$affiliateId = (int)$affiliate['id'];
$affiliateCode = (string)$affiliate['affiliate_code'];
$affiliateLink = affiliateBuildPublicLink($affiliateCode);

function affiliateDashboardColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    return affiliateColumnExists($pdo, $tableName, $columnName);
}

try {
    $totals = [
        'total_signups' => 0,
        'total_active_paid_users' => 0,
        'pending_commission' => 0.0,
        'released_commission' => 0.0
    ];

    $stmtSignups = $pdo->prepare("SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_id = ?");
    $stmtSignups->execute([$affiliateId]);
    $totals['total_signups'] = (int)$stmtSignups->fetchColumn();

    $hasPlanExpiry = affiliateDashboardColumnExists($pdo, 'users', 'plan_expires_at');
    if ($hasPlanExpiry) {
        $stmtActive = $pdo->prepare("
            SELECT COUNT(*)
            FROM affiliate_referrals r
            JOIN users u ON u.id = r.referred_user_id
            WHERE r.affiliate_id = ?
              AND u.current_plan_id > 1
              AND (u.plan_expires_at IS NULL OR u.plan_expires_at >= NOW())
        ");
    } else {
        $stmtActive = $pdo->prepare("
            SELECT COUNT(*)
            FROM affiliate_referrals r
            JOIN users u ON u.id = r.referred_user_id
            WHERE r.affiliate_id = ?
              AND u.current_plan_id > 1
        ");
    }
    $stmtActive->execute([$affiliateId]);
    $totals['total_active_paid_users'] = (int)$stmtActive->fetchColumn();

    $stmtPending = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE affiliate_id = ? AND payout_status = 'pending'");
    $stmtPending->execute([$affiliateId]);
    $totals['pending_commission'] = (float)$stmtPending->fetchColumn();

    $stmtReleased = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE affiliate_id = ? AND payout_status = 'released'");
    $stmtReleased->execute([$affiliateId]);
    $totals['released_commission'] = (float)$stmtReleased->fetchColumn();

    $activeUsersByPlan = [];
    if ($hasPlanExpiry) {
        $stmtByPlan = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id)) AS plan_name,
                COUNT(*) AS total
            FROM affiliate_referrals r
            JOIN users u ON u.id = r.referred_user_id
            LEFT JOIN plans p ON p.id = u.current_plan_id
            WHERE r.affiliate_id = ?
              AND u.current_plan_id > 1
              AND (u.plan_expires_at IS NULL OR u.plan_expires_at >= NOW())
            GROUP BY COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id))
            ORDER BY total DESC
        ");
    } else {
        $stmtByPlan = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id)) AS plan_name,
                COUNT(*) AS total
            FROM affiliate_referrals r
            JOIN users u ON u.id = r.referred_user_id
            LEFT JOIN plans p ON p.id = u.current_plan_id
            WHERE r.affiliate_id = ?
              AND u.current_plan_id > 1
            GROUP BY COALESCE(NULLIF(p.name, ''), CONCAT('Plano #', u.current_plan_id))
            ORDER BY total DESC
        ");
    }
    $stmtByPlan->execute([$affiliateId]);
    $activeUsersByPlan = $stmtByPlan->fetchAll(PDO::FETCH_ASSOC);

    $geoCountries = [];
    $stmtCountries = $pdo->prepare("
        SELECT COALESCE(NULLIF(captured_country, ''), 'Desconhecido') AS label, COUNT(*) AS total
        FROM affiliate_referrals
        WHERE affiliate_id = ?
        GROUP BY COALESCE(NULLIF(captured_country, ''), 'Desconhecido')
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmtCountries->execute([$affiliateId]);
    $geoCountries = $stmtCountries->fetchAll(PDO::FETCH_ASSOC);

    $geoCities = [];
    $stmtCities = $pdo->prepare("
        SELECT COALESCE(NULLIF(captured_city, ''), 'Desconhecido') AS label, COUNT(*) AS total
        FROM affiliate_referrals
        WHERE affiliate_id = ?
        GROUP BY COALESCE(NULLIF(captured_city, ''), 'Desconhecido')
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmtCities->execute([$affiliateId]);
    $geoCities = $stmtCities->fetchAll(PDO::FETCH_ASSOC);

    $estimatedNextMonths = [];
    $stmtForecast = $pdo->prepare("
        SELECT DATE_FORMAT(expected_release_at, '%Y-%m-01') AS month_key, COALESCE(SUM(commission_amount), 0) AS total
        FROM affiliate_commissions
        WHERE affiliate_id = ?
          AND payout_status = 'pending'
          AND expected_release_at >= NOW()
        GROUP BY DATE_FORMAT(expected_release_at, '%Y-%m-01')
        ORDER BY month_key ASC
        LIMIT 6
    ");
    $stmtForecast->execute([$affiliateId]);
    $estimatedNextMonths = $stmtForecast->fetchAll(PDO::FETCH_ASSOC);

    $recentCommissions = [];
    $stmtRecent = $pdo->prepare("
        SELECT asaas_payment_id, payment_method, installment_number, gross_amount, commission_rate, commission_amount, expected_release_at, payout_status
        FROM affiliate_commissions
        WHERE affiliate_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmtRecent->execute([$affiliateId]);
    $recentCommissions = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'affiliate' => [
            'id' => $affiliateId,
            'code' => $affiliateCode,
            'link' => $affiliateLink
        ],
        'totals' => $totals,
        'active_users_by_plan' => $activeUsersByPlan,
        'geo_countries' => $geoCountries,
        'geo_cities' => $geoCities,
        'estimated_next_months' => $estimatedNextMonths,
        'recent_commissions' => $recentCommissions
    ]);
} catch (Throwable $e) {
    error_log('affiliate_dashboard.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar painel de afiliado.']);
}

?>
