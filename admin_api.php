<?php
// admin_api.php
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
require_once 'affiliate_helpers.php';

// Proteção global da rota administrativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem acessar esta rota.']);
    exit;
}

// CSRF (enforcement controlado por env CSRF_ENFORCE=1)
requireCsrfToken();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function adminAffiliateTablesReady(PDO $pdo): bool {
    return affiliateTableExists($pdo, 'affiliates')
        && affiliateTableExists($pdo, 'affiliate_referrals')
        && affiliateTableExists($pdo, 'affiliate_commissions');
}

function adminAffiliateColumnsReady(PDO $pdo): bool {
    return affiliateColumnExists($pdo, 'users', 'current_plan_id')
        && affiliateTableExists($pdo, 'plans');
}

function adminColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function adminTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    // ---- 1. GESTÃO DE PLANOS ----
    if ($action === 'get_plans') {
        $stmt = $pdo->query("SELECT * FROM plans ORDER BY tier_level ASC");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $plans]);
        exit;
    }

    if ($action === 'update_plan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Plano inválido.']);
            exit;
        }

        $stmtCurrent = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
        $stmtCurrent->execute([$id]);
        $currentPlan = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

        if (!$currentPlan) {
            echo json_encode(['success' => false, 'message' => 'Plano não encontrado.']);
            exit;
        }

        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : (string)$currentPlan['name'];
        $monthly_price = isset($_POST['monthly_price']) ? (float)$_POST['monthly_price'] : (float)$currentPlan['monthly_price'];
        $annual_price = isset($_POST['annual_price']) ? (float)$_POST['annual_price'] : (float)$currentPlan['annual_price'];
        $max_links = isset($_POST['max_links']) ? (int)$_POST['max_links'] : (int)$currentPlan['max_links'];
        $max_qrs = isset($_POST['max_qrs']) ? (int)$_POST['max_qrs'] : (int)$currentPlan['max_qrs'];
        $max_bio_pages = isset($_POST['max_bio_pages']) ? (int)$_POST['max_bio_pages'] : (int)$currentPlan['max_bio_pages'];
        $max_campaigns = isset($_POST['max_campaigns']) ? (int)$_POST['max_campaigns'] : (int)$currentPlan['max_campaigns'];
        $max_clicks_monthly = isset($_POST['max_clicks_monthly']) ? (int)$_POST['max_clicks_monthly'] : (int)$currentPlan['max_clicks_monthly'];
        $analytics_days = isset($_POST['analytics_days']) ? (int)$_POST['analytics_days'] : (int)$currentPlan['analytics_days'];
        $max_subdomains = isset($_POST['max_subdomains']) ? (int)$_POST['max_subdomains'] : (int)$currentPlan['max_subdomains'];
        $max_link_destination_edits = isset($_POST['max_link_destination_edits']) ? (int)$_POST['max_link_destination_edits'] : (int)($currentPlan['max_link_destination_edits'] ?? 999999);
        $max_qr_destination_edits = isset($_POST['max_qr_destination_edits']) ? (int)$_POST['max_qr_destination_edits'] : (int)($currentPlan['max_qr_destination_edits'] ?? 999999);
        $max_link_dwell_monitors = isset($_POST['max_link_dwell_monitors']) ? (int)$_POST['max_link_dwell_monitors'] : (int)($currentPlan['max_link_dwell_monitors'] ?? 0);
        $max_qr_dwell_monitors = isset($_POST['max_qr_dwell_monitors']) ? (int)$_POST['max_qr_dwell_monitors'] : (int)($currentPlan['max_qr_dwell_monitors'] ?? 0);
        $max_team_members = isset($_POST['max_team_members']) ? (int)$_POST['max_team_members'] : (int)($currentPlan['max_team_members'] ?? 1);
        $tier_level = isset($_POST['tier_level']) ? (int)$_POST['tier_level'] : (int)$currentPlan['tier_level'];

        $features_json = array_key_exists('features_json', $_POST) ? (string)$_POST['features_json'] : (string)($currentPlan['features_json'] ?? '{}');
        if (trim($features_json) === '') {
            $features_json = '{}';
        }
        if (json_decode($features_json, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'features_json inválido.']);
            exit;
        }

        $setFields = [
            'name = ?',
            'monthly_price = ?',
            'annual_price = ?',
            'max_links = ?',
            'max_qrs = ?',
            'max_bio_pages = ?',
            'max_campaigns = ?',
            'max_clicks_monthly = ?',
            'analytics_days = ?',
            'max_subdomains = ?',
            'max_link_destination_edits = ?',
            'max_qr_destination_edits = ?',
            'tier_level = ?',
            'features_json = ?'
        ];
        $params = [$name, $monthly_price, $annual_price, $max_links, $max_qrs, $max_bio_pages, $max_campaigns, $max_clicks_monthly, $analytics_days, $max_subdomains, $max_link_destination_edits, $max_qr_destination_edits, $tier_level, $features_json];

        if (adminColumnExists($pdo, 'plans', 'max_team_members')) {
            $setFields[] = 'max_team_members = ?';
            $params[] = max(1, $max_team_members);
        }
        if (adminColumnExists($pdo, 'plans', 'max_link_dwell_monitors')) {
            $setFields[] = 'max_link_dwell_monitors = ?';
            $params[] = max(0, $max_link_dwell_monitors);
        }
        if (adminColumnExists($pdo, 'plans', 'max_qr_dwell_monitors')) {
            $setFields[] = 'max_qr_dwell_monitors = ?';
            $params[] = max(0, $max_qr_dwell_monitors);
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE plans SET " . implode(', ', $setFields) . " WHERE id = ?");
        $stmt->execute($params);

        echo json_encode(['success' => true]);
        exit;
    }

    // ---- 2. GESTÃO DE CONFIGURAÇÕES GLOBAIS ----
    if ($action === 'get_config') {
        $stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode(['success' => true, 'data' => $configs]);
        exit;
    }

    if ($action === 'update_config') {
        $configs = $_POST['configs'] ?? []; // Passado como Array associativo
        $allowedKeys = ['founder_edition_active', 'founder_max_seats', 'grace_period_days'];
        if (is_array($configs)) {
            $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
            foreach ($configs as $key => $value) {
                if (!in_array((string)$key, $allowedKeys, true)) {
                    continue;
                }
                $stmt->execute([$key, $value, $value]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // ---- 3. GESTÃO DE USUÁRIOS E CONTAS ESPECIAIS ----
    if ($action === 'search_users') {
        $term = $_GET['term'] ?? '';
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.current_plan_id, o.special_account_type, o.billing_bypass, o.custom_plan_id FROM users u LEFT JOIN user_account_overrides o ON u.id = o.user_id WHERE u.email LIKE ? OR u.name LIKE ? LIMIT 20");
        $likeTerm = "%$term%";
        $stmt->execute([$likeTerm, $likeTerm]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }

    if ($action === 'save_user_override') {
        $userId = (int)$_POST['user_id'];
        $type = $_POST['special_account_type'] ?? 'interna';
        $bypass = (int)($_POST['billing_bypass'] ?? 0);
        $customPlan = !empty($_POST['custom_plan_id']) ? (int)$_POST['custom_plan_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO user_account_overrides (user_id, special_account_type, billing_bypass, custom_plan_id, granted_by) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE special_account_type = ?, billing_bypass = ?, custom_plan_id = ?, granted_by = ?
        ");
        
        $adminId = $_SESSION['user_id'];
        $stmt->execute([$userId, $type, $bypass, $customPlan, $adminId, $type, $bypass, $customPlan, $adminId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'remove_user_override') {
        $userId = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM user_account_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ---- 4. GESTÃO E DASHBOARD DE AFILIADOS ----
    if ($action === 'search_users_for_affiliate') {
        if (!adminAffiliateTablesReady($pdo)) {
            echo json_encode(['success' => false, 'message' => 'Tabelas de afiliados não encontradas. Execute a migration de afiliados antes.']);
            exit;
        }
        $term = trim((string)($_GET['term'] ?? ''));
        if ($term === '') {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.email,
                a.id AS affiliate_id,
                a.affiliate_code,
                a.is_active
            FROM users u
            LEFT JOIN affiliates a ON a.user_id = u.id
            WHERE u.email LIKE ? OR u.name LIKE ?
            ORDER BY u.id DESC
            LIMIT 30
        ");
        $like = "%{$term}%";
        $stmt->execute([$like, $like]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save_affiliate') {
        if (!adminAffiliateTablesReady($pdo)) {
            echo json_encode(['success' => false, 'message' => 'Tabelas de afiliados não encontradas. Execute a migration de afiliados antes.']);
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $code = affiliateNormalizeCode((string)($_POST['affiliate_code'] ?? ''));
        $isActive = ((int)($_POST['is_active'] ?? 1) === 1) ? 1 : 0;
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido.']);
            exit;
        }
        if ($code === '' || strlen($code) < 4) {
            echo json_encode(['success' => false, 'message' => 'Código de afiliado inválido.']);
            exit;
        }
        $stmtCode = $pdo->prepare("SELECT user_id FROM affiliates WHERE affiliate_code = ? LIMIT 1");
        $stmtCode->execute([$code]);
        $codeOwner = (int)$stmtCode->fetchColumn();
        if ($codeOwner > 0 && $codeOwner !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Código de afiliado já está em uso por outro usuário.']);
            exit;
        }
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        if (!$stmtUser->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO affiliates (user_id, affiliate_code, is_active)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE affiliate_code = VALUES(affiliate_code), is_active = VALUES(is_active)
        ");
        try {
            $stmt->execute([$userId, $code, $isActive]);
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                echo json_encode(['success' => false, 'message' => 'Código de afiliado já está em uso por outro usuário.']);
                exit;
            }
            throw $e;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'remove_affiliate') {
        if (!adminAffiliateTablesReady($pdo)) {
            echo json_encode(['success' => false, 'message' => 'Tabelas de afiliados não encontradas. Execute a migration de afiliados antes.']);
            exit;
        }
        $affiliateId = (int)($_POST['affiliate_id'] ?? 0);
        if ($affiliateId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Afiliado inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'affiliate_dashboard_overview') {
        if (!adminAffiliateTablesReady($pdo)) {
            echo json_encode(['success' => false, 'message' => 'Tabelas de afiliados não encontradas. Execute a migration de afiliados antes.']);
            exit;
        }

        $kpis = [
            'total_affiliates' => 0,
            'active_affiliates' => 0,
            'total_referrals' => 0,
            'pending_commissions' => 0.0,
            'released_commissions' => 0.0
        ];

        $kpis['total_affiliates'] = (int)$pdo->query("SELECT COUNT(*) FROM affiliates")->fetchColumn();
        $kpis['active_affiliates'] = (int)$pdo->query("SELECT COUNT(*) FROM affiliates WHERE is_active = 1")->fetchColumn();
        $kpis['total_referrals'] = (int)$pdo->query("SELECT COUNT(*) FROM affiliate_referrals")->fetchColumn();
        $kpis['pending_commissions'] = (float)$pdo->query("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE payout_status = 'pending'")->fetchColumn();
        $kpis['released_commissions'] = (float)$pdo->query("SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE payout_status = 'released'")->fetchColumn();

        $stmtAff = $pdo->query("
            SELECT
                a.id,
                a.user_id,
                a.affiliate_code,
                a.is_active,
                u.name AS user_name,
                u.email AS user_email,
                COALESCE(ref.total_signups, 0) AS total_signups,
                COALESCE(pay.pending_commission, 0) AS pending_commission,
                COALESCE(pay.released_commission, 0) AS released_commission
            FROM affiliates a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN (
                SELECT affiliate_id, COUNT(*) AS total_signups
                FROM affiliate_referrals
                GROUP BY affiliate_id
            ) ref ON ref.affiliate_id = a.id
            LEFT JOIN (
                SELECT
                    affiliate_id,
                    SUM(CASE WHEN payout_status = 'pending' THEN commission_amount ELSE 0 END) AS pending_commission,
                    SUM(CASE WHEN payout_status = 'released' THEN commission_amount ELSE 0 END) AS released_commission
                FROM affiliate_commissions
                GROUP BY affiliate_id
            ) pay ON pay.affiliate_id = a.id
            ORDER BY total_signups DESC, a.id DESC
        ");
        $affiliates = $stmtAff->fetchAll(PDO::FETCH_ASSOC);

        $activeByAffiliate = [];
        if (adminAffiliateColumnsReady($pdo)) {
            if (affiliateColumnExists($pdo, 'users', 'plan_expires_at')) {
                $stmtActiveAff = $pdo->query("
                    SELECT r.affiliate_id, COUNT(*) AS active_total
                    FROM affiliate_referrals r
                    JOIN users u ON u.id = r.referred_user_id
                    WHERE u.current_plan_id > 1
                      AND (u.plan_expires_at IS NULL OR u.plan_expires_at >= NOW())
                    GROUP BY r.affiliate_id
                ");
            } else {
                $stmtActiveAff = $pdo->query("
                    SELECT r.affiliate_id, COUNT(*) AS active_total
                    FROM affiliate_referrals r
                    JOIN users u ON u.id = r.referred_user_id
                    WHERE u.current_plan_id > 1
                    GROUP BY r.affiliate_id
                ");
            }
            $rows = $stmtActiveAff->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $activeByAffiliate[(int)$row['affiliate_id']] = (int)$row['active_total'];
            }
        }

        foreach ($affiliates as &$a) {
            $a['active_paid_users'] = (int)($activeByAffiliate[(int)$a['id']] ?? 0);
        }
        unset($a);

        $referralsByAffiliate = [];
        foreach ($affiliates as $a) {
            $referralsByAffiliate[] = [
                'label' => (string)$a['user_name'],
                'total' => (int)$a['total_signups']
            ];
        }
        $referralsByAffiliate = array_slice($referralsByAffiliate, 0, 10);

        $commissionsByAffiliate = [];
        foreach ($affiliates as $a) {
            $commissionsByAffiliate[] = [
                'label' => (string)$a['user_name'],
                'total' => round((float)$a['pending_commission'] + (float)$a['released_commission'], 2)
            ];
        }
        usort($commissionsByAffiliate, static function ($x, $y) {
            return ((float)$y['total']) <=> ((float)$x['total']);
        });
        $commissionsByAffiliate = array_slice($commissionsByAffiliate, 0, 10);

        $selectedAffiliateId = (int)($_GET['affiliate_id'] ?? 0);
        if ($selectedAffiliateId <= 0 && !empty($affiliates)) {
            $selectedAffiliateId = (int)$affiliates[0]['id'];
        }

        $detail = [
            'affiliate_id' => $selectedAffiliateId,
            'plans_distribution' => [],
            'geo_countries' => [],
            'geo_cities' => [],
            'forecast_months' => []
        ];

        if ($selectedAffiliateId > 0) {
            if (adminAffiliateColumnsReady($pdo)) {
                if (affiliateColumnExists($pdo, 'users', 'plan_expires_at')) {
                    $stmtPlans = $pdo->prepare("
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
                    $stmtPlans = $pdo->prepare("
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
                $stmtPlans->execute([$selectedAffiliateId]);
                $detail['plans_distribution'] = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);
            }

            $stmtCountries = $pdo->prepare("
                SELECT COALESCE(NULLIF(captured_country, ''), 'Desconhecido') AS label, COUNT(*) AS total
                FROM affiliate_referrals
                WHERE affiliate_id = ?
                GROUP BY COALESCE(NULLIF(captured_country, ''), 'Desconhecido')
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmtCountries->execute([$selectedAffiliateId]);
            $detail['geo_countries'] = $stmtCountries->fetchAll(PDO::FETCH_ASSOC);

            $stmtCities = $pdo->prepare("
                SELECT COALESCE(NULLIF(captured_city, ''), 'Desconhecido') AS label, COUNT(*) AS total
                FROM affiliate_referrals
                WHERE affiliate_id = ?
                GROUP BY COALESCE(NULLIF(captured_city, ''), 'Desconhecido')
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmtCities->execute([$selectedAffiliateId]);
            $detail['geo_cities'] = $stmtCities->fetchAll(PDO::FETCH_ASSOC);

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
            $stmtForecast->execute([$selectedAffiliateId]);
            $detail['forecast_months'] = $stmtForecast->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'kpis' => $kpis,
            'affiliates' => $affiliates,
            'referrals_by_affiliate' => $referralsByAffiliate,
            'commissions_by_affiliate' => $commissionsByAffiliate,
            'detail' => $detail
        ]);
        exit;
    }

    if ($action === 'marketing_form_responses') {
        if (!adminTableExists($pdo, 'marketing_form_responses')) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                COALESCE(whatsapp, '') AS whatsapp,
                request_text,
                source_page,
                submitter_ip,
                created_at
            FROM marketing_form_responses
            ORDER BY created_at DESC
            LIMIT 1000
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação não reconhecida.']);

} catch (Exception $e) {
    error_log('admin_api.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno na administração.']);
}
?>
