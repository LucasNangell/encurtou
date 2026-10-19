<?php
// Evita HTML quebrando o JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'db.php';
require_once 'billing/FounderService.php';
require_once 'billing/AsaasRefundService.php';
require_once 'affiliate_helpers.php';
require_once 'smtp_mailer.php';
require_once 'team_permissions.php';
require_once 'user_ban_helpers.php';
require_once 'rate_limit_helpers.php';
require_once 'auth_guard.php';
require_once 'captcha_helpers.php';
require_once 'email_verify_helpers.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function tableExists(PDO $pdo, string $tableName): bool {
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$tableName]);
    $cache[$tableName] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$tableName];
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tableName, $columnName]);
    $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$cacheKey];
}

function getGoogleClientId(): string
{
    $fallback = '4942397873-238uvchi00mv3i59annesaj1kpa0o9ed.apps.googleusercontent.com';
    return envOrDefault('GOOGLE_CLIENT_ID', $fallback);
}

function supportsOAuthColumns(PDO $pdo): bool
{
    return columnExists($pdo, 'users', 'oauth_provider') && columnExists($pdo, 'users', 'oauth_sub');
}

function passwordResetTableExists(PDO $pdo): bool
{
    return tableExists($pdo, 'password_resets');
}

function getTeamUserIds(PDO $pdo, int $userId): array {
    $ids = [$userId];
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.owner_id 
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.owner_id = ? OR tm.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($team) {
            $teamId = $team['id'];
            $ownerId = $team['owner_id'];
            if (!in_array((int)$ownerId, $ids)) $ids[] = (int)$ownerId;

            $stmtM = $pdo->prepare("SELECT user_id FROM team_members WHERE team_id = ?");
            $stmtM->execute([$teamId]);
            $members = $stmtM->fetchAll(PDO::FETCH_COLUMN);
            foreach ($members as $mId) {
                if (!in_array((int)$mId, $ids)) $ids[] = (int)$mId;
            }
        }
    } catch (Throwable $e) {}
    return array_unique($ids);
}

function getFallbackFeatures(): array {
    return [
        'analytics'     => false,
        'custom_domain' => false,
        'deep_linking'  => false,
        'bulk_utm'      => false,
        'tracking'      => false,
        'ab_testing'    => false,
        'rules'         => false,
        'bio_pages'     => false,
        'custom_path'   => false,
        'redirect'      => false,
        'team'          => false,
        'priority_support' => false,
        'folders_tags' => false,
        'social_preview' => false,
        'premium_bio_templates' => false,
        'bio_form_exports' => false,
        'ai_tutor' => false,
    ];
}

function getFallbackUsageSummary(PDO $pdo, int $userId): array {
    $usedLinks = 0;
    $usedQrs = 0;
    $usedBio = 0;
    $usedSub = 0;

    if (tableExists($pdo, 'links')) {
        $stmtL = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND type='link'");
        $stmtL->execute([$userId]);
        $usedLinks = (int)$stmtL->fetchColumn();

        $stmtQ = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND type='qr'");
        $stmtQ->execute([$userId]);
        $usedQrs = (int)$stmtQ->fetchColumn();
    }

    if (tableExists($pdo, 'bio_pages')) {
        $stmtB = $pdo->prepare("SELECT COUNT(*) FROM bio_pages WHERE user_id = ?");
        $stmtB->execute([$userId]);
        $usedBio = (int)$stmtB->fetchColumn();
    }

    if (tableExists($pdo, 'user_subdomains')) {
        $stmtS = $pdo->prepare("SELECT COUNT(*) FROM user_subdomains WHERE user_id = ?");
        $stmtS->execute([$userId]);
        $usedSub = (int)$stmtS->fetchColumn();
    }

    return [
        'plan_name' => 'Start (Free)',
        'is_admin'  => false,
        'links' => [
            'used' => $usedLinks,
            'total' => 50,
            'remaining' => max(0, 50 - $usedLinks),
            'percent' => min(100, round(($usedLinks / 50) * 100))
        ],
        'qr_codes' => [
            'used' => $usedQrs,
            'total' => 2,
            'remaining' => max(0, 2 - $usedQrs),
            'percent' => min(100, round(($usedQrs / 2) * 100))
        ],
        'bio_pages' => [
            'used' => $usedBio,
            'total' => 1,
            'remaining' => max(0, 1 - $usedBio),
            'percent' => min(100, round(($usedBio / 1) * 100))
        ],
        'subdomains' => [
            'used' => $usedSub,
            'total' => 0,
            'remaining' => 0,
            'percent' => 100
        ]
    ];
}

function hasBillingSchema(PDO $pdo): bool {
    return tableExists($pdo, 'plans')
        && tableExists($pdo, 'user_account_overrides')
        && columnExists($pdo, 'users', 'current_plan_id');
}

function isFounderMember(int $userId): bool {
    return FounderService::isActiveFounder($userId);
}

function isMasterAdminDashboardEmail(PDO $pdo, int $userId): bool {
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
    $email = strtolower(trim((string)($row['email'] ?? '')));
    return $email === 'nangellbusiness@gmail.com';
}

function getMostRecentConfirmedPayment(PDO $pdo, int $userId): ?array
{
    if (!tableExists($pdo, 'payments_history')) {
        return null;
    }

    $hasCreatedAt = columnExists($pdo, 'payments_history', 'created_at');
    $hasPaidAt = columnExists($pdo, 'payments_history', 'paid_at');
    $hasConfirmedAt = columnExists($pdo, 'payments_history', 'confirmed_at');
    $timeExpr = $hasConfirmedAt ? 'confirmed_at' : ($hasPaidAt ? 'paid_at' : ($hasCreatedAt ? 'created_at' : 'NULL'));
    $timeOrder = ($timeExpr !== 'NULL') ? "{$timeExpr} DESC" : 'id DESC';

    $sql = "SELECT asaas_payment_id, amount, {$timeExpr} AS paid_ts
            FROM payments_history
            WHERE user_id = ? AND status = 'CONFIRMED' AND asaas_payment_id IS NOT NULL AND asaas_payment_id <> ''
            ORDER BY {$timeOrder}, id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'asaas_payment_id' => (string)($row['asaas_payment_id'] ?? ''),
        'amount' => (float)($row['amount'] ?? 0),
        'paid_ts' => $row['paid_ts'] ?? null,
    ];
}

function getAsaasCustomerIdByUser(PDO $pdo, int $userId): string
{
    if (!columnExists($pdo, 'users', 'asaas_customer_id')) {
        return '';
    }
    $stmt = $pdo->prepare("SELECT asaas_customer_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return trim((string)$stmt->fetchColumn());
}

function getFirstConfirmedPaymentTimestamp(PDO $pdo, int $userId): ?int
{
    if (!tableExists($pdo, 'payments_history')) {
        return null;
    }

    $hasCreatedAt = columnExists($pdo, 'payments_history', 'created_at');
    $hasPaidAt = columnExists($pdo, 'payments_history', 'paid_at');
    $hasConfirmedAt = columnExists($pdo, 'payments_history', 'confirmed_at');
    $timeExpr = $hasConfirmedAt ? 'confirmed_at' : ($hasPaidAt ? 'paid_at' : ($hasCreatedAt ? 'created_at' : 'NULL'));
    $timeOrder = ($timeExpr !== 'NULL') ? "{$timeExpr} ASC" : 'id ASC';

    $sql = "SELECT {$timeExpr} AS paid_ts
            FROM payments_history
            WHERE user_id = ? AND status = 'CONFIRMED'
            ORDER BY {$timeOrder}, id ASC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $value = $stmt->fetchColumn();
    if (!$value) {
        return null;
    }
    $ts = strtotime((string)$value);
    return $ts !== false ? $ts : null;
}

function userEligibleForSevenDayRefund(PDO $pdo, int $userId): bool
{
    $firstConfirmedTs = getFirstConfirmedPaymentTimestamp($pdo, $userId);
    if (is_int($firstConfirmedTs)) {
        return (time() - $firstConfirmedTs) <= (7 * 86400);
    }

    if (columnExists($pdo, 'users', 'plan_started_at')) {
        $stmt = $pdo->prepare("SELECT plan_started_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $startedAt = $stmt->fetchColumn();
        if (!empty($startedAt)) {
            $startedTs = strtotime((string)$startedAt);
            if ($startedTs !== false) {
                return (time() - $startedTs) <= (7 * 86400);
            }
        }
    }

    return false;
}

function isTechnicalCancellationReason(string $reasonCode): bool
{
    return in_array($reasonCode, ['contains_errors', 'missing_feature'], true);
}

function applyFifteenDayGrace(PDO $pdo, int $userId): void
{
    if (columnExists($pdo, 'users', 'plan_expires_at')) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET plan_expires_at = DATE_ADD(
                CASE
                    WHEN plan_expires_at IS NULL OR plan_expires_at < NOW() THEN NOW()
                    ELSE plan_expires_at
                END,
                INTERVAL 15 DAY
            )
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
}

try {
    // Verifica Status
    if ($action === 'status') {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $banBlock = getUserBanBlockResponse($pdo, (int)$userId);
            if ($banBlock !== null) {
                authGuardDestroySession();
                echo json_encode([
                    'logged_in' => false,
                    'message' => $banBlock['message'],
                    'banned' => true,
                    'is_affiliate' => false,
                    'affiliate_code' => null,
                    'affiliate_link' => null,
                    'csrf_token' => getCsrfToken(),
                    'csrf_enforced' => csrfEnforced(),
                    'google_client_id' => getGoogleClientId(),
                    'captcha_site_key' => captchaSiteKey(),
                ]);
                exit;
            }
            if (authGuardSessionInvalidated($pdo, (int)$userId)) {
                authGuardDestroySession();
                echo json_encode([
                    'logged_in' => false,
                    'session_expired' => true,
                    'csrf_token' => getCsrfToken(),
                    'csrf_enforced' => csrfEnforced(),
                    'google_client_id' => getGoogleClientId(),
                    'captcha_site_key' => captchaSiteKey(),
                ]);
                exit;
            }
            $features = getFallbackFeatures();
            $isMasterAdminDashboard = false;
            $isAffiliate = false;
            $affiliateCode = null;
            $affiliateLink = null;

            if (hasBillingSchema($pdo)) {
                try {
                    $features = [
                        'analytics'     => EntitlementService::canAccessFeature($userId, 'analytics'),
                        'custom_domain' => EntitlementService::canAccessFeature($userId, 'custom_domain'),
                        'deep_linking'  => EntitlementService::canAccessFeature($userId, 'deep_linking'),
                        'bulk_utm'      => EntitlementService::canAccessFeature($userId, 'bulk_utm'),
                        'tracking'      => EntitlementService::canAccessFeature($userId, 'tracking'),
                        'ab_testing'    => EntitlementService::canAccessFeature($userId, 'ab_testing'),
                        'rules'         => EntitlementService::canAccessFeature($userId, 'rules'),
                        'bio_pages'     => EntitlementService::canAccessFeature($userId, 'bio_pages'),
                        'custom_path'   => EntitlementService::canAccessFeature($userId, 'custom_path'),
                        'redirect'      => EntitlementService::canAccessFeature($userId, 'redirect'),
                        'team'          => EntitlementService::canAccessFeature($userId, 'team'),
                        'priority_support' => EntitlementService::canAccessFeature($userId, 'priority_support'),
                        'folders_tags'  => EntitlementService::canAccessFeature($userId, 'folders_tags'),
                        'social_preview' => EntitlementService::canAccessFeature($userId, 'social_preview'),
                        'premium_bio_templates' => EntitlementService::canAccessFeature($userId, 'premium_bio_templates'),
                        'bio_form_exports' => EntitlementService::canAccessFeature($userId, 'bio_form_exports'),
                        'ai_tutor' => EntitlementService::canAccessFeature($userId, 'ai_tutor'),
                    ];
                } catch (Throwable $e) {
                    $features = getFallbackFeatures();
                }
            }
            
            
            // Popula IDs da equipe na sessão
            $teamCtx = teamResolveMembershipContext($pdo, (int)$userId);
            if (!empty($teamCtx['has_team'])) {
                $_SESSION['team_users'] = teamGetMemberUserIds($pdo, (int)$teamCtx['team_id']);
            } else {
                $_SESSION['team_users'] = [(int)$userId];
            }
            $isMasterAdminDashboard = isMasterAdminDashboardEmail($pdo, (int)$userId);
            try {
                $affiliate = affiliateFindByUser($pdo, (int)$userId);
                if ($affiliate && (int)($affiliate['is_active'] ?? 0) === 1) {
                    $isAffiliate = true;
                    $affiliateCode = (string)($affiliate['affiliate_code'] ?? '');
                    $affiliateLink = affiliateBuildPublicLink($affiliateCode);
                }
            } catch (Throwable $e) {
                $isAffiliate = false;
                $affiliateCode = null;
                $affiliateLink = null;
            }

            echo json_encode([
                'logged_in' => true, 
                'name' => $_SESSION['user_name'], 
                'is_admin' => (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1),
                'is_master_admin_dashboard' => $isMasterAdminDashboard,
                'email_verified' => authGuardEmailVerified($pdo, (int)$userId),
                'is_affiliate' => $isAffiliate,
                'affiliate_code' => $affiliateCode,
                'affiliate_link' => $affiliateLink,
                'features' => $features,
                'team_users' => $_SESSION['team_users'],
                'team_profile' => (string)($teamCtx['profile'] ?? 'editor'),
                'team_profile_label' => teamProfileLabel((string)($teamCtx['profile'] ?? 'editor')),
                'team_can_manage_members' => (bool)($teamCtx['can_manage_members'] ?? false),
                'team_can_edit_content' => (bool)($teamCtx['can_edit_content'] ?? true),
                'csrf_token' => getCsrfToken(),
                'csrf_enforced' => csrfEnforced(),
                'google_client_id' => getGoogleClientId(),
                'captcha_site_key' => captchaSiteKey(),
            ]);
        } else {
            echo json_encode([
                'logged_in' => false,
                'is_affiliate' => false,
                'affiliate_code' => null,
                'affiliate_link' => null,
                'csrf_token' => getCsrfToken(),
                'csrf_enforced' => csrfEnforced(),
                'google_client_id' => getGoogleClientId(),
                'captcha_site_key' => captchaSiteKey(),
            ]);
        }
        exit;
    }

    if ($action === 'usage_summary') {
        if (isset($_SESSION['user_id'])) {
            if (hasBillingSchema($pdo)) {
                try {
                    $summary = EntitlementService::getUsageSummary($_SESSION['user_id']);
                } catch (Throwable $e) {
                    $summary = getFallbackUsageSummary($pdo, (int)$_SESSION['user_id']);
                }
            } else {
                $summary = getFallbackUsageSummary($pdo, (int)$_SESSION['user_id']);
            }
            echo json_encode(['success' => true, 'usage' => $summary]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
        }
        exit;
    }

    if ($action === 'account_settings') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            exit;
        }
        $userId = (int)$_SESSION['user_id'];
        $planNameJoin = null;
        $userRow = null;

        if (hasBillingSchema($pdo) && tableExists($pdo, 'plans')) {
            $autoRenewSelect = columnExists($pdo, 'users', 'plan_auto_renew') ? ', u.plan_auto_renew' : '';
            $stmt = $pdo->prepare("SELECT u.name, u.nickname, u.email, u.phone, u.plan, u.current_plan_id, u.plan_expires_at, u.plan_cycle{$autoRenewSelect}, p.name AS plan_name_join FROM users u LEFT JOIN plans p ON u.current_plan_id = p.id WHERE u.id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $planNameJoin = $userRow['plan_name_join'] ?? null;
            }
        } else {
            $autoRenewSelect = columnExists($pdo, 'users', 'plan_auto_renew') ? ', plan_auto_renew' : '';
            $stmt = $pdo->prepare("SELECT name, nickname, email, phone, plan, current_plan_id, plan_expires_at, plan_cycle{$autoRenewSelect} FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$userRow) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        $planAutoRenew = true;
        if (columnExists($pdo, 'users', 'plan_auto_renew') && array_key_exists('plan_auto_renew', $userRow)) {
            $planAutoRenew = ((int)$userRow['plan_auto_renew'] === 1);
        }

        $currentPlanId = (int)($userRow['current_plan_id'] ?? 0);
        $canManageRenewal = ($currentPlanId > 1);
        $isFounder = isFounderMember($userId);
        $founderLocked = $isFounder ? FounderService::getLockedPrices($userId) : null;

        echo json_encode([
            'success' => true,
            'user' => [
                'name' => (string)($userRow['name'] ?? ''),
                'nickname' => (string)($userRow['nickname'] ?? ''),
                'email' => (string)($userRow['email'] ?? ''),
                'phone' => (string)($userRow['phone'] ?? ''),
                'plan' => (string)($userRow['plan'] ?? 'free'),
                'current_plan_id' => $currentPlanId,
                'plan_name' => $planNameJoin ?: null,
                'plan_expires_at' => $userRow['plan_expires_at'] ?? null,
                'plan_cycle' => $userRow['plan_cycle'] ?? null,
                'plan_auto_renew' => $planAutoRenew,
                'is_founder' => $isFounder,
                'founder_locked_monthly' => $founderLocked['monthly'] ?? null,
                'founder_locked_annual' => $founderLocked['annual'] ?? null,
                'can_manage_renewal' => $canManageRenewal,
            ],
        ]);
        exit;
    }

    // Sair
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $publicAuthActions = ['signup', 'login', 'google_login', 'forgot', 'reset'];
        $privateAuthActions = ['update_profile', 'change_password', 'cancel_plan_renewal', 'resume_plan_renewal', 'submit_cancellation_request'];
        if (in_array($action, $publicAuthActions, true)) {
            requireCsrfToken(false);
        } elseif (in_array($action, $privateAuthActions, true)) {
            requireCsrfToken(true);
        }

        if ($action === 'update_profile') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Não autorizado']);
                exit;
            }
            $userId = (int)$_SESSION['user_id'];
            $name = trim((string)($_POST['name'] ?? ''));
            $nickname = trim((string)($_POST['nickname'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));

            if ($name === '' || $email === '') {
                echo json_encode(['success' => false, 'message' => 'Nome e e-mail são obrigatórios.']);
                exit;
            }

            $stmtDup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmtDup->execute([$email, $userId]);
            if ($stmtDup->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este e-mail já está em uso por outra conta.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET name = ?, nickname = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $nickname, $email, $phone, $userId]);

            $display = ($nickname !== '') ? $nickname : $name;
            $_SESSION['user_name'] = $display;

            echo json_encode(['success' => true, 'name' => $display]);
            exit;
        }

        if ($action === 'change_password') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Não autorizado']);
                exit;
            }
            $userId = (int)$_SESSION['user_id'];
            $current = (string)($_POST['current_password'] ?? '');
            $newPass = (string)($_POST['new_password'] ?? '');
            $newConfirm = (string)($_POST['new_password_confirm'] ?? '');

            if ($current === '' || $newPass === '') {
                echo json_encode(['success' => false, 'message' => 'Preencha a senha atual e a nova senha.']);
                exit;
            }
            if ($newPass !== $newConfirm) {
                echo json_encode(['success' => false, 'message' => 'A confirmação da nova senha não confere.']);
                exit;
            }
            if (strlen($newPass) < 8) {
                echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 8 caracteres.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($current, (string)$row['password'])) {
                echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
                exit;
            }

            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'cancel_plan_renewal') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Não autorizado']);
                exit;
            }
            if (!columnExists($pdo, 'users', 'plan_auto_renew')) {
                echo json_encode(['success' => false, 'message' => 'Recurso indisponível: atualize o banco de dados (coluna plan_auto_renew).']);
                exit;
            }
            $userId = (int)$_SESSION['user_id'];
            $planNameJoin = null;
            $userRow = null;
            if (hasBillingSchema($pdo) && tableExists($pdo, 'plans')) {
                $stmt = $pdo->prepare("SELECT u.plan, u.current_plan_id, p.name AS plan_name_join FROM users u LEFT JOIN plans p ON u.current_plan_id = p.id WHERE u.id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow) {
                    $planNameJoin = $userRow['plan_name_join'] ?? null;
                }
            } else {
                $stmt = $pdo->prepare("SELECT plan, current_plan_id FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$userRow) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
                exit;
            }
            if ((int)($userRow['current_plan_id'] ?? 0) <= 1) {
                echo json_encode(['success' => false, 'message' => 'Você não está em um plano pago ativo.']);
                exit;
            }
            $pdo->prepare("UPDATE users SET plan_auto_renew = 0 WHERE id = ?")->execute([$userId]);
            echo json_encode(['success' => true, 'message' => 'Renovação automática desativada. Você mantém o acesso até o fim do período já pago.']);
            exit;
        }

        if ($action === 'resume_plan_renewal') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Não autorizado']);
                exit;
            }
            if (!columnExists($pdo, 'users', 'plan_auto_renew')) {
                echo json_encode(['success' => false, 'message' => 'Recurso indisponível: atualize o banco de dados (coluna plan_auto_renew).']);
                exit;
            }
            $userId = (int)$_SESSION['user_id'];
            $planNameJoin = null;
            $userRow = null;
            if (hasBillingSchema($pdo) && tableExists($pdo, 'plans')) {
                $stmt = $pdo->prepare("SELECT u.plan, u.current_plan_id, p.name AS plan_name_join FROM users u LEFT JOIN plans p ON u.current_plan_id = p.id WHERE u.id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow) {
                    $planNameJoin = $userRow['plan_name_join'] ?? null;
                }
            } else {
                $stmt = $pdo->prepare("SELECT plan, current_plan_id FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$userRow) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
                exit;
            }
            if ((int)($userRow['current_plan_id'] ?? 0) <= 1) {
                echo json_encode(['success' => false, 'message' => 'Você não está em um plano pago ativo.']);
                exit;
            }
            $pdo->prepare("UPDATE users SET plan_auto_renew = 1 WHERE id = ?")->execute([$userId]);
            echo json_encode(['success' => true, 'message' => 'Renovação automática reativada.']);
            exit;
        }

        if ($action === 'submit_cancellation_request') {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Não autorizado']);
                exit;
            }

            $userId = (int)$_SESSION['user_id'];
            $reasonCode = trim((string)($_POST['reason_code'] ?? ''));
            $reasonText = trim((string)($_POST['reason_text'] ?? ''));
            $decision = trim((string)($_POST['decision'] ?? ''));
            $allowedReasons = [
                'expectations',
                'contains_errors',
                'missing_feature',
                'high_price',
                'hard_to_use',
                'support_issues',
                'others',
            ];

            if (!in_array($reasonCode, $allowedReasons, true)) {
                echo json_encode(['success' => false, 'message' => 'Selecione um motivo válido para o cancelamento.']);
                exit;
            }
            if ($reasonText === '' || strlen($reasonText) < 5) {
                echo json_encode(['success' => false, 'message' => 'Descreva o motivo com um pouco mais de detalhes.']);
                exit;
            }
            if (!in_array($decision, ['cancel_now', 'test_more'], true)) {
                echo json_encode(['success' => false, 'message' => 'Selecione como deseja prosseguir.']);
                exit;
            }

            $isTechnical = isTechnicalCancellationReason($reasonCode);
            $eligibleRefund = userEligibleForSevenDayRefund($pdo, $userId);
            $statusMessage = '';

            if ($decision === 'test_more' && $isTechnical) {
                applyFifteenDayGrace($pdo, $userId);
                if (columnExists($pdo, 'users', 'plan_auto_renew')) {
                    $pdo->prepare("UPDATE users SET plan_auto_renew = 0 WHERE id = ?")->execute([$userId]);
                }
                $statusMessage = 'Concedemos 15 dias adicionais para você testar. Seu relato será analisado e vamos atuar nas correções em até 72h. Se após 15 dias você quiser cancelar, garantimos o reembolso integral desta assinatura.';
            } else if ($decision === 'cancel_now') {
                if (columnExists($pdo, 'users', 'plan_auto_renew')) {
                    $pdo->prepare("UPDATE users SET plan_auto_renew = 0 WHERE id = ?")->execute([$userId]);
                }

                $refundResponse = null;
                if ($eligibleRefund) {
                    $payment = getMostRecentConfirmedPayment($pdo, $userId);
                    if (!$payment || (string)($payment['asaas_payment_id'] ?? '') === '') {
                        $customerId = getAsaasCustomerIdByUser($pdo, $userId);
                        if ($customerId !== '') {
                            $payment = AsaasRefundService::getLatestRefundablePaymentByCustomer($customerId);
                        }
                    }
                    if (!$payment || (string)($payment['asaas_payment_id'] ?? '') === '') {
                        error_log('refund_missing_payment_user_' . $userId . ': eligible=1');
                    }
                    if ($payment && $payment['asaas_payment_id'] !== '' && (float)$payment['amount'] > 0) {
                        $refundReason = 'Cancelamento em até 7 dias. Motivo: ' . $reasonText;
                        $refundResponse = AsaasRefundService::refundPayment(
                            (string)$payment['asaas_payment_id'],
                            (float)$payment['amount'],
                            $refundReason
                        );
                    }
                }

                $hasRefundSuccess = is_array($refundResponse) && !empty($refundResponse['success']);
                if ($hasRefundSuccess) {
                    $updates = [];
                    if (columnExists($pdo, 'users', 'plan')) {
                        $updates[] = "plan = 'free'";
                    }
                    if (columnExists($pdo, 'users', 'current_plan_id')) {
                        $updates[] = "current_plan_id = 1";
                    }
                    if (columnExists($pdo, 'users', 'billing_status')) {
                        $updates[] = "billing_status = 'freemium'";
                    }
                    if (columnExists($pdo, 'users', 'plan_expires_at')) {
                        $updates[] = "plan_expires_at = NOW()";
                    }
                    if (!empty($updates)) {
                        $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute([$userId]);
                    }
                    $statusMessage = 'Cancelamento concluído com solicitação de reembolso automático no Asaas dentro do prazo de 7 dias.';
                } else if ($eligibleRefund) {
                    if (!$payment || (string)($payment['asaas_payment_id'] ?? '') === '') {
                        $statusMessage = 'Recebemos seu pedido, mas não localizamos uma cobrança elegível para estorno automático neste momento.';
                    } else {
                        $statusMessage = 'Recebemos seu pedido, mas não foi possível concluir o estorno automático agora. Nossa equipe foi acionada para tratar isso com prioridade.';
                    }
                } else {
                    $statusMessage = 'Cancelamento realizado. Sua renovação automática foi desativada.';
                }

                if (is_array($refundResponse) && empty($refundResponse['success'])) {
                    error_log('refund_error_user_' . $userId . ': ' . json_encode($refundResponse, JSON_UNESCAPED_UNICODE));
                }
            } else {
                if (columnExists($pdo, 'users', 'plan_auto_renew')) {
                    $pdo->prepare("UPDATE users SET plan_auto_renew = 0 WHERE id = ?")->execute([$userId]);
                }
                $statusMessage = 'Solicitação registrada. Sua renovação automática foi desativada.';
            }

            error_log('cancel_request_user_' . $userId . ': ' . json_encode([
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'decision' => $decision,
                'eligible_refund_7d' => $eligibleRefund ? 1 : 0,
                'is_technical' => $isTechnical ? 1 : 0,
            ], JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => $statusMessage,
                'eligible_refund_7d' => $eligibleRefund,
                'technical_reason' => $isTechnical,
            ]);
            exit;
        }
        
        // CRIAR CONTA
        if ($action === 'signup') {
            if (!checkRateLimit($pdo, 'signup', 5, 30)) {
                echo json_encode(['success' => false, 'message' => 'Muitas tentativas. Tente novamente mais tarde.']);
                exit;
            }

            $captchaToken = (string)($_POST['captcha_token'] ?? '');
            if (!verifyCaptchaToken($captchaToken)) {
                echo json_encode(['success' => false, 'message' => 'Confirme o captcha para continuar.']);
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $pass = $_POST['password'] ?? '';
            $referralCode = affiliateNormalizeCode((string)($_POST['referral_code'] ?? ''));
            if ($referralCode !== '') {
                affiliateRememberCodeCookie($referralCode, 30);
            }

            if (!$name || !$nickname || !$email || !$pass || !$phone) {
                echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
                exit;
            }

            if (strlen($pass) < 10) {
                echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 10 caracteres.']);
                exit;
            }

            if (isDisposableEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'Use um e-mail permanente. E-mails descartáveis não são permitidos.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado.']);
                exit;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $signupIp = getRealIP();
            $signupUa = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

            $insertCols = ['name', 'nickname', 'email', 'phone', 'password'];
            $insertVals = ['?', '?', '?', '?', '?'];
            $params = [$name, $nickname, $email, $phone, $hash];

            if (columnExists($pdo, 'users', 'is_admin')) {
                $insertCols[] = 'is_admin';
                $insertVals[] = '0';
            }
            if (columnExists($pdo, 'users', 'signup_ip')) {
                $insertCols[] = 'signup_ip';
                $insertVals[] = '?';
                $params[] = $signupIp;
            }
            if (columnExists($pdo, 'users', 'signup_user_agent')) {
                $insertCols[] = 'signup_user_agent';
                $insertVals[] = '?';
                $params[] = $signupUa;
            }

            $stmt = $pdo->prepare('INSERT INTO users (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')');
            
            if ($stmt->execute($params)) {
                session_regenerate_id(true);
                $lastId = (int)$pdo->lastInsertId();
                $_SESSION['user_id'] = $lastId;
                $_SESSION['user_name'] = $nickname; 
                $_SESSION['is_admin'] = 0;
                $_SESSION['plan'] = 'free';
                authGuardSetSessionIssuedAt();
                emailVerifySend($pdo, $lastId, $email, $nickname);
                try {
                    affiliateRegisterReferral($pdo, $lastId);
                } catch (Throwable $e) {}
                echo json_encode([
                    'success' => true,
                    'name' => $nickname,
                    'email_verification_required' => emailVerifyColumnsReady($pdo),
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar no banco.']);
            }
            exit;
        }

        // LOGIN
        if ($action === 'login') {
            if (!checkRateLimit($pdo, 'login', 6, 15)) {
                echo json_encode(['success' => false, 'message' => 'Conta bloqueada temporariamente por excesso de tentativas.']);
                exit;
            }

            $captchaToken = (string)($_POST['captcha_token'] ?? '');
            if (captchaConfigured() && !verifyCaptchaToken($captchaToken)) {
                echo json_encode(['success' => false, 'message' => 'Confirme o captcha para continuar.']);
                exit;
            }

            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass = $_POST['password'] ?? '';

            if (columnExists($pdo, 'users', 'is_admin')) {
                $stmt = $pdo->prepare("SELECT id, name, nickname, password, is_admin, plan FROM users WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("SELECT id, name, nickname, password, plan FROM users WHERE email = ?");
            }
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($pass, $user['password'])) {
                $banBlock = getUserBanBlockResponse($pdo, (int)$user['id']);
                if ($banBlock !== null) {
                    echo json_encode($banBlock);
                    exit;
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $nomeExibicao = !empty($user['nickname']) ? $user['nickname'] : $user['name'];
                $_SESSION['user_name'] = $nomeExibicao;
                $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
                $_SESSION['plan'] = $user['plan'] ?? 'free';
                authGuardSetSessionIssuedAt();
                
                // Popula equipe no login
                $_SESSION['team_users'] = getTeamUserIds($pdo, $user['id']);
                
                // Retorna o nome para o Javascript
                echo json_encode(['success' => true, 'name' => $nomeExibicao, 'team_users' => $_SESSION['team_users'], 'plan' => $_SESSION['plan'], 'email_verified' => authGuardEmailVerified($pdo, (int)$user['id'])]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Email ou senha incorretos.']);
            }
            exit;
        }

        // GOOGLE LOGIN
        if ($action === 'google_login') {
            if (!checkRateLimit($pdo, 'google_login', 10, 15)) {
                echo json_encode(['success' => false, 'message' => 'Muitas tentativas. Aguarde e tente novamente.']);
                exit;
            }

            $id_token = $_POST['id_token'] ?? '';
            $referralCode = affiliateNormalizeCode((string)($_POST['referral_code'] ?? ''));
            if ($referralCode !== '') {
                affiliateRememberCodeCookie($referralCode, 30);
            }
            if (!$id_token) {
                echo json_encode(['success' => false, 'message' => 'Token não fornecido.']);
                exit;
            }

            // Validação com Google
            $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
            $response = @file_get_contents($verify_url);
            $token_info = json_decode($response, true);

            if (!$token_info || isset($token_info['error'])) {
                echo json_encode(['success' => false, 'message' => 'Token inválido.']);
                exit;
            }

            // Verifica Client ID (aud)
            $client_id = getGoogleClientId();
            if ($token_info['aud'] !== $client_id) {
                echo json_encode(['success' => false, 'message' => 'Autenticação inválida.']);
                exit;
            }

            if (!$token_info['email_verified']) {
                echo json_encode(['success' => false, 'message' => 'Email não verificado no Google.']);
                exit;
            }

            $googleSub = (string)($token_info['sub'] ?? '');
            if ($googleSub === '') {
                echo json_encode(['success' => false, 'message' => 'Token Google sem identificador único.']);
                exit;
            }

            $email = strtolower($token_info['email']);
            $name = $token_info['name'] ?? 'Usuário Google';
            $nickname = $token_info['given_name'] ?? $name;

            // Busca usuário
            if (supportsOAuthColumns($pdo)) {
                if (columnExists($pdo, 'users', 'is_admin')) {
                    $stmt = $pdo->prepare("
                        SELECT id, name, nickname, is_admin, plan, oauth_provider, oauth_sub
                        FROM users
                        WHERE (oauth_provider = 'google' AND oauth_sub = ?)
                           OR email = ?
                        ORDER BY (oauth_sub = ?) DESC, id ASC
                        LIMIT 1
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id, name, nickname, plan, oauth_provider, oauth_sub
                        FROM users
                        WHERE (oauth_provider = 'google' AND oauth_sub = ?)
                           OR email = ?
                        ORDER BY (oauth_sub = ?) DESC, id ASC
                        LIMIT 1
                    ");
                }
                $stmt->execute([$googleSub, $email, $googleSub]);
            } else {
                if (columnExists($pdo, 'users', 'is_admin')) {
                    $stmt = $pdo->prepare("SELECT id, name, nickname, is_admin, plan FROM users WHERE email = ?");
                } else {
                    $stmt = $pdo->prepare("SELECT id, name, nickname, plan FROM users WHERE email = ?");
                }
                $stmt->execute([$email]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (supportsOAuthColumns($pdo)) {
                    $existingSub = (string)($user['oauth_sub'] ?? '');
                    $existingProvider = (string)($user['oauth_provider'] ?? '');
                    if ($existingSub !== '' && $existingSub !== $googleSub) {
                        echo json_encode(['success' => false, 'message' => 'Conta já vinculada a outro perfil Google.']);
                        exit;
                    }
                    if ($existingSub === '') {
                        $pdo->prepare("UPDATE users SET oauth_provider = 'google', oauth_sub = ?, oauth_linked_at = NOW() WHERE id = ?")
                            ->execute([$googleSub, (int)$user['id']]);
                    } elseif ($existingProvider !== 'google') {
                        $pdo->prepare("UPDATE users SET oauth_provider = 'google' WHERE id = ?")->execute([(int)$user['id']]);
                    }
                }

                $banBlock = getUserBanBlockResponse($pdo, (int)$user['id']);
                if ($banBlock !== null) {
                    echo json_encode($banBlock);
                    exit;
                }

                // Login existente
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $nomeExibicao = !empty($user['nickname']) ? $user['nickname'] : $user['name'];
                $_SESSION['user_name'] = $nomeExibicao;
                $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
                $_SESSION['plan'] = $user['plan'] ?? 'free';
                $_SESSION['team_users'] = getTeamUserIds($pdo, (int)$user['id']);
                authGuardSetSessionIssuedAt();
                emailVerifyMarkVerified($pdo, (int)$user['id']);
                echo json_encode(['success' => true, 'name' => $nomeExibicao, 'plan' => $_SESSION['plan'], 'team_users' => $_SESSION['team_users'], 'email_verified' => true]);
            } else {
                // Cadastro automático
                $random_pass = bin2hex(random_bytes(16));
                $hash = password_hash($random_pass, PASSWORD_DEFAULT);
                if (supportsOAuthColumns($pdo)) {
                    if (columnExists($pdo, 'users', 'is_admin')) {
                        $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password, is_admin, oauth_provider, oauth_sub, oauth_linked_at) VALUES (?, ?, ?, ?, ?, 0, 'google', ?, NOW())");
                        $params = [$name, $nickname, $email, 'Google', $hash, $googleSub];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password, oauth_provider, oauth_sub, oauth_linked_at) VALUES (?, ?, ?, ?, ?, 'google', ?, NOW())");
                        $params = [$name, $nickname, $email, 'Google', $hash, $googleSub];
                    }
                } else {
                    if (columnExists($pdo, 'users', 'is_admin')) {
                        $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password, is_admin) VALUES (?, ?, ?, ?, ?, 0)");
                        $params = [$name, $nickname, $email, 'Google', $hash];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (name, nickname, email, phone, password) VALUES (?, ?, ?, ?, ?)");
                        $params = [$name, $nickname, $email, 'Google', $hash];
                    }
                }
                
                if ($stmt->execute($params)) {
                    session_regenerate_id(true);
                    $newUserId = (int)$pdo->lastInsertId();
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $nickname;
                    $_SESSION['is_admin'] = 0;
                    $_SESSION['plan'] = 'free';
                    $_SESSION['team_users'] = getTeamUserIds($pdo, $newUserId);
                    authGuardSetSessionIssuedAt();
                    emailVerifyMarkVerified($pdo, $newUserId);
                    try {
                        affiliateRegisterReferral($pdo, $newUserId);
                    } catch (Throwable $e) {}
                    echo json_encode(['success' => true, 'name' => $nickname, 'plan' => 'free', 'team_users' => $_SESSION['team_users'], 'email_verified' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao criar conta via Google.']);
                }
            }
            exit;
        }

        if ($action === 'resend_verify_email') {
            $uid = (int)($_SESSION['user_id'] ?? 0);
            if ($uid <= 0) {
                echo json_encode(['success' => false, 'message' => 'Faça login para reenviar a verificação.']);
                exit;
            }
            if (!checkRateLimit($pdo, 'resend_verify_' . $uid, 3, 60)) {
                echo json_encode(['success' => false, 'message' => 'Aguarde antes de reenviar o e-mail.']);
                exit;
            }
            if (authGuardEmailVerified($pdo, $uid)) {
                echo json_encode(['success' => true, 'message' => 'Seu e-mail já está verificado.']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT email, nickname, name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
                exit;
            }
            $sent = emailVerifySend($pdo, $uid, (string)$row['email'], (string)($row['nickname'] ?: $row['name']));
            echo json_encode([
                'success' => $sent,
                'message' => $sent ? 'E-mail de verificação reenviado.' : 'Não foi possível enviar o e-mail agora.',
            ]);
            exit;
        }

        // ESQUECEU A SENHA
        if ($action === 'forgot') {
            if (!checkRateLimit($pdo, 'forgot', 1, 2)) {
                echo json_encode(['success' => false, 'message' => 'Aguarde 2 minutos para solicitar novo código.']);
                exit;
            }

            $email = strtolower(trim($_POST['email'] ?? ''));
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $tokenPlain = strtoupper(bin2hex(random_bytes(4)));
                $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                if (passwordResetTableExists($pdo)) {
                    $tokenHash = hash('sha256', $tokenPlain);
                    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                        ->execute([(int)$user['id']]);
                    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at, attempts, used_at, created_at) VALUES (?, ?, ?, ?, 0, NULL, NOW())");
                    $stmt->execute([(int)$user['id'], $email, $tokenHash, $expires]);
                } else {
                    // Fallback legado para ambientes ainda sem migration.
                    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                    $stmt->execute([$tokenPlain, $expires, $user['id']]);
                }

                $to = $email;
                $subject = "Redefinir Senha - Encurtou.pro";
                $message = "Olá " . $user['name'] . ",<br><br>Seu código para redefinir a senha é: <b style='font-size:20px; color:#04449E;'>{$tokenPlain}</b><br><br>Este código expira em 30 minutos e só pode ser usado uma vez.";
                
                $mailSent = send_smtp_mail($to, $subject, $message);
                if (!$mailSent) {
                    error_log('forgot_mail_failed email=' . $email . ' reason=' . smtp_last_error());
                }
            }
            echo json_encode(['success' => true]);
            exit;
        }

        // REDEFINIR SENHA
        if ($action === 'reset') {
            if (!checkRateLimit($pdo, 'reset', 8, 30)) {
                echo json_encode(['success' => false, 'message' => 'Muitas tentativas de redefinição. Aguarde 30 minutos.']);
                exit;
            }
            $email = strtolower(trim($_POST['email'] ?? ''));
            $code = trim($_POST['code'] ?? '');
            $newpass = $_POST['new_password'] ?? '';

            if (strlen($newpass) < 8) {
                echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 8 caracteres.']);
                exit;
            }

            $resetSuccess = false;
            $targetUserId = 0;

            if (passwordResetTableExists($pdo)) {
                $tokenHash = hash('sha256', strtoupper($code));
                $stmt = $pdo->prepare("
                    SELECT pr.id, pr.user_id
                    FROM password_resets pr
                    WHERE pr.email = ?
                      AND pr.token_hash = ?
                      AND pr.used_at IS NULL
                      AND pr.expires_at > NOW()
                      AND pr.attempts < 10
                    ORDER BY pr.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $tokenHash]);
                $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($resetRow) {
                    $targetUserId = (int)$resetRow['user_id'];
                    $hash = password_hash($newpass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
                        ->execute([$hash, $targetUserId]);
                    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                        ->execute([(int)$resetRow['id']]);
                    $resetSuccess = true;
                } else {
                    $pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE email = ? AND used_at IS NULL AND expires_at > NOW()")
                        ->execute([$email]);
                }
            } else {
                // Fallback legado
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW()");
                $stmt->execute([$email, $code]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $targetUserId = (int)$user['id'];
                    $hash = password_hash($newpass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
                        ->execute([$hash, $targetUserId]);
                    $resetSuccess = true;
                }
            }

            if ($resetSuccess) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Código inválido ou expirado.']);
            }
            exit;
        }
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro fatal no servidor.']);
}
?>