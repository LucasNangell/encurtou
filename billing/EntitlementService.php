<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/PlanService.php';
require_once __DIR__ . '/configs.php';

class EntitlementService {
    private static array $cycleCache = [];

    private static function parseFeaturesJson($featuresJson): array {
        $decoded = json_decode((string)$featuresJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function parseLimitsJson($limitsJson): array {
        $decoded = json_decode((string)$limitsJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function tableExists(string $tableName): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function columnExists(string $tableName, string $columnName): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$tableName, $columnName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function getCycleAnchorDate(int $userId): DateTimeImmutable {
        global $pdo;
        $tz = new DateTimeZone('America/Sao_Paulo');
        $now = new DateTimeImmutable('now', $tz);

        $hasPlanStartedAt = self::columnExists('users', 'plan_started_at');
        $hasCreatedAt = self::columnExists('users', 'created_at');

        $fields = [];
        if ($hasPlanStartedAt) {
            $fields[] = 'plan_started_at';
        }
        if ($hasCreatedAt) {
            $fields[] = 'created_at';
        }

        if (count($fields) === 0) {
            return $now;
        }

        $stmt = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $anchorRaw = '';
        if ($hasPlanStartedAt && !empty($row['plan_started_at'])) {
            $anchorRaw = (string)$row['plan_started_at'];
        } elseif ($hasCreatedAt && !empty($row['created_at'])) {
            $anchorRaw = (string)$row['created_at'];
        }

        if ($anchorRaw === '') {
            return $now;
        }

        try {
            return new DateTimeImmutable($anchorRaw, $tz);
        } catch (Throwable $e) {
            return $now;
        }
    }

    private static function createMonthDate(DateTimeImmutable $baseMonth, int $anchorDay): DateTimeImmutable {
        $tz = $baseMonth->getTimezone();
        $year = (int)$baseMonth->format('Y');
        $month = (int)$baseMonth->format('m');
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = max(1, min($anchorDay, $daysInMonth));
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $tz);
    }

    public static function getUsageCycleWindow(int $userId): array {
        if (isset(self::$cycleCache[$userId])) {
            return self::$cycleCache[$userId];
        }

        $tz = new DateTimeZone('America/Sao_Paulo');
        $now = new DateTimeImmutable('now', $tz);
        $anchorDate = self::getCycleAnchorDate($userId);
        $anchorDay = (int)$anchorDate->format('d');
        if ($anchorDay <= 0) {
            $anchorDay = 1;
        }

        $currentMonthBase = new DateTimeImmutable($now->format('Y-m-01 00:00:00'), $tz);
        $currentCycleStart = self::createMonthDate($currentMonthBase, $anchorDay);
        if ($now < $currentCycleStart) {
            $previousMonthBase = $currentMonthBase->modify('-1 month');
            $currentCycleStart = self::createMonthDate($previousMonthBase, $anchorDay);
        }
        $nextCycleStart = self::createMonthDate($currentCycleStart->modify('+1 month'), $anchorDay);

        $window = [
            'start' => $currentCycleStart->format('Y-m-d H:i:s'),
            'end' => $nextCycleStart->format('Y-m-d H:i:s'),
            'start_date' => $currentCycleStart->format('Y-m-d'),
            'end_date' => $nextCycleStart->modify('-1 day')->format('Y-m-d'),
            'anchor_day' => $anchorDay
        ];

        self::$cycleCache[$userId] = $window;
        return $window;
    }

    private static function countUserRowsInCycle(
        int $userId,
        string $tableName,
        string $userColumn = 'user_id',
        string $timeColumn = 'created_at',
        string $extraWhere = '',
        array $extraParams = []
    ): int {
        global $pdo;
        if (!self::tableExists($tableName)) {
            return 0;
        }

        $window = self::getUsageCycleWindow($userId);
        $params = [$userId];
        $whereSql = "{$userColumn} = ?";
        if ($extraWhere !== '') {
            $whereSql .= " AND {$extraWhere}";
            $params = array_merge($params, $extraParams);
        }

        if (self::columnExists($tableName, $timeColumn)) {
            $whereSql .= " AND {$timeColumn} >= ? AND {$timeColumn} < ?";
            $params[] = $window['start'];
            $params[] = $window['end'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE {$whereSql}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private static function resolvePlanAndOverride(int $userId): array {
        global $pdo;
        $stmtOver = $pdo->prepare("SELECT billing_bypass, custom_plan_id, custom_limits_json, custom_features_json FROM user_account_overrides WHERE user_id = ?");
        $stmtOver->execute([$userId]);
        $override = $stmtOver->fetch(PDO::FETCH_ASSOC);

        $planId = 1;
        if ($override && !empty($override['custom_plan_id'])) {
            $planId = (int)$override['custom_plan_id'];
        } else {
            $stmt = $pdo->prepare("SELECT current_plan_id, plan FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                if (!empty($user['current_plan_id'])) {
                    $planId = (int)$user['current_plan_id'];
                } else {
                    $pName = strtolower((string)($user['plan'] ?? 'free'));
                    $planId = ($pName === 'founder') ? 5 : (($pName === 'business') ? 4 : (($pName === 'pro') ? 3 : (($pName === 'creator') ? 2 : 1)));
                }
            }
        }

        $plan = PlanService::getPlanDetails($planId);
        return [$plan, $override];
    }

    public static function canCreate(int $userId, string $resourceType): bool {
        $decision = self::getCreateAccessDecision($userId, $resourceType);
        return (bool)($decision['allowed'] ?? false);
    }

    public static function getCreateAccessDecision(int $userId, string $resourceType): array {
        global $pdo;

        // 1. Bypass Global Admin
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        // 2. Busca permissões especiais
        $stmtOver = $pdo->prepare("SELECT billing_bypass, custom_plan_id, custom_limits_json, custom_features_json FROM user_account_overrides WHERE user_id = ?");
        $stmtOver->execute([$userId]);
        $override = $stmtOver->fetch(PDO::FETCH_ASSOC);

        if ($override && $override['billing_bypass'] == 1) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        // 3. Determina o Plano
        $planId = 1;
        if ($override && !empty($override['custom_plan_id'])) {
            $planId = (int)$override['custom_plan_id'];
        } else {
            $stmt = $pdo->prepare("SELECT current_plan_id, plan FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (!empty($user['current_plan_id'])) {
                    $planId = (int)$user['current_plan_id'];
                } else {
                    // Fallback para o campo enum 'plan'
                    $pName = $user['plan'] ?? 'free';
                    $planId = ($pName === 'founder') ? 5 : (($pName === 'business') ? 4 : (($pName === 'pro') ? 3 : (($pName === 'creator') ? 2 : 1)));
                }
            }
        }

        $plan = PlanService::getPlanDetails($planId);
        if (!$plan) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        // Bypass para Plano Business (Tier 999)
        if ((int)$plan['tier_level'] >= 999) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        // Garante consistência entre limite numérico e feature booleana quando aplicável
        $features = self::parseFeaturesJson($plan['features_json'] ?? '{}');
        if ($resourceType === 'subdomains') {
            $featureAllowed = isset($features['custom_domain']) ? ($features['custom_domain'] === true) : false;
            if ($override && !empty($override['custom_features_json'])) {
                $custFeat = json_decode((string)$override['custom_features_json'], true) ?? [];
                if (isset($custFeat['custom_domain'])) {
                    $featureAllowed = ($custFeat['custom_domain'] === true);
                }
            }
            if (!$featureAllowed) {
                return [
                    'allowed' => false,
                    'error' => 'feature_locked',
                    'message' => 'Subdomínios personalizados não estão disponíveis no seu plano atual.'
                ];
            }
        }

        $max = 0;
        $currentCount = 0;

        switch ($resourceType) {
            case 'links':
                $max = $plan['max_links'];
                $currentCount = self::countUserRowsInCycle($userId, 'links', 'user_id', 'created_at', "type = 'link'");
                break;
            case 'qr_codes':
                $max = $plan['max_qrs'];
                $currentCount = self::countUserRowsInCycle($userId, 'links', 'user_id', 'created_at', "type = 'qr'");
                break;
            case 'bio_pages':
                $max = $plan['max_bio_pages'];
                $currentCount = self::countUserRowsInCycle($userId, 'bio_pages');
                break;
            case 'subdomains':
                $max = $plan['max_subdomains'];
                $currentCount = self::countUserRowsInCycle($userId, 'user_subdomains');
                break;
        }

        // 4. Overrides específicos de limites
        if ($override && !empty($override['custom_limits_json'])) {
            $limits = json_decode($override['custom_limits_json'], true) ?? [];
            if ($resourceType === 'links' && isset($limits['max_links'])) $max = $limits['max_links'];
            if ($resourceType === 'qr_codes' && isset($limits['max_qrs'])) $max = $limits['max_qrs'];
            if ($resourceType === 'bio_pages' && isset($limits['max_bio_pages'])) $max = $limits['max_bio_pages'];
            if ($resourceType === 'subdomains' && isset($limits['max_subdomains'])) $max = $limits['max_subdomains'];
        }

        $allowed = ($max == 999999) ? true : ($currentCount < $max);

        if (!BILLING_ENFORCEMENT_ENABLED) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        if ($allowed) {
            return ['allowed' => true, 'error' => null, 'message' => null];
        }

        return [
            'allowed' => false,
            'error' => 'quota_exceeded',
            'message' => 'Você atingiu o limite disponível para este recurso no seu plano.'
        ];
    }

    public static function canAccessFeature(int $userId, string $featureKey): bool {
        global $pdo;

        // 1. Bypass Global Admin
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
            return true;
        }

        // 2. Busca permissões especiais (Overrides manuais)
        $stmtOver = $pdo->prepare("SELECT billing_bypass, custom_plan_id, custom_features_json FROM user_account_overrides WHERE user_id = ?");
        $stmtOver->execute([$userId]);
        $override = $stmtOver->fetch(PDO::FETCH_ASSOC);

        if ($override && $override['billing_bypass'] == 1) {
            return true;
        }

        // 3. Determina o Plano e o Tier Level
        $planId = 1;
        $tierLevel = 0;

        if ($override && !empty($override['custom_plan_id'])) {
            $planId = (int)$override['custom_plan_id'];
        } else {
            $stmt = $pdo->prepare("SELECT current_plan_id, plan FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (!empty($user['current_plan_id'])) {
                    $planId = (int)$user['current_plan_id'];
                } else {
                    $pName = strtolower($user['plan'] ?? 'free');
                    $planId = ($pName === 'founder') ? 5 : (($pName === 'business') ? 4 : (($pName === 'pro') ? 3 : (($pName === 'creator') ? 2 : 1)));
                }
            }
        }

        $plan = PlanService::getPlanDetails($planId);
        if (!$plan) return false;
        
        $tierLevel = (int)$plan['tier_level'];

        // 4. BYPASS PARA PLANO BUSINESS (Tier 999) - Acesso total garantido
        if ($tierLevel >= 999) {
            return true;
        }

        // 5. Definição de Nível Mínimo por Funcionalidade (Matriz Inteligente)
        $featureTierMatrix = [
            'analytics'     => 0, // Todos tem acesso básico
            'redirect'      => 0,
            'custom_domain' => 1, // Creator+
            'bio_pages'     => 1,
            'custom_path'   => 1,
            'tracking'      => 1,
            'dwell_monitor' => 1,
            'deep_linking'  => 2, // Pro+
            'bulk_utm'      => 2,
            'ab_testing'    => 2,
            'rules'         => 2,
            'team'          => 2,
            'priority_support' => 2,
            'folders_tags' => 0,
            'social_preview' => 0,
            'premium_bio_templates' => 0,
            'bio_form_exports' => 0,
            'ai_tutor' => 0,
        ];

        // 6. Decisão Final baseada em Hierarquia
        $minTierRequired = $featureTierMatrix[$featureKey] ?? 999; 
        $allowed = ($tierLevel >= $minTierRequired);

        // 7. Overrides Específicos via JSON (Prioridade sobre a hierarquia se definido)
        $features = json_decode($plan['features_json'], true) ?? [];
        if (isset($features[$featureKey])) {
            $allowed = $features[$featureKey] === true;
        }

        if ($override && !empty($override['custom_features_json'])) {
            $custFeat = json_decode($override['custom_features_json'], true) ?? [];
            if (isset($custFeat[$featureKey])) {
                $allowed = $custFeat[$featureKey] === true;
            }
        }

        if (defined('BILLING_ENFORCEMENT_ENABLED') && !BILLING_ENFORCEMENT_ENABLED) {
            return true; 
        }

        return $allowed;
    }

    public static function getEffectiveLimit(int $userId, string $limitKey, int $fallback = 0): int {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1) {
            return 999999999;
        }

        [$plan, $override] = self::resolvePlanAndOverride($userId);
        if (!$plan) {
            return max(0, $fallback);
        }

        if ($override && (int)($override['billing_bypass'] ?? 0) === 1) {
            return 999999999;
        }

        if ((int)($plan['tier_level'] ?? 0) >= 999) {
            return 999999999;
        }

        $limitValue = array_key_exists($limitKey, $plan) ? (int)$plan[$limitKey] : $fallback;
        if ($limitKey === 'max_team_members' && (!array_key_exists($limitKey, $plan) || $limitValue <= 0)) {
            $features = self::parseFeaturesJson($plan['features_json'] ?? '{}');
            if (isset($features['max_team_members'])) {
                $limitValue = (int)$features['max_team_members'];
            }
        }

        if ($override && !empty($override['custom_limits_json'])) {
            $limits = self::parseLimitsJson($override['custom_limits_json']);
            if (array_key_exists($limitKey, $limits)) {
                $limitValue = (int)$limits[$limitKey];
            }
        }

        if (!defined('BILLING_ENFORCEMENT_ENABLED') || !BILLING_ENFORCEMENT_ENABLED) {
            return 999999999;
        }

        return max(0, (int)$limitValue);
    }

    public static function canEditDestination(int $userId, string $editType): bool {
        $fieldName = ($editType === 'qr') ? 'max_qr_destination_edits' : 'max_link_destination_edits';
        $maxEdits = self::getEffectiveLimit($userId, $fieldName, 999999);

        if ($maxEdits >= 999999) {
            return true;
        }
        $used = self::countUserRowsInCycle($userId, 'destination_edit_usage', 'user_id', 'created_at', 'edit_type = ?', [$editType === 'qr' ? 'qr' : 'link']);
        return $used < $maxEdits;
    }

    public static function canActivateDwellMonitor(int $userId, string $resourceType, int $currentActive): bool {
        $limitKey = ($resourceType === 'qr') ? 'max_qr_dwell_monitors' : 'max_link_dwell_monitors';
        $maxAllowed = self::getEffectiveLimit($userId, $limitKey, 0);
        if ($maxAllowed >= 999999) {
            return true;
        }
        if ($maxAllowed <= 0) {
            return false;
        }
        return $currentActive < $maxAllowed;
    }

    public static function registerDestinationEdit(int $userId, string $editType): void {
        if (!self::tableExists('destination_edit_usage')) {
            return;
        }
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO destination_edit_usage (user_id, edit_type) VALUES (?, ?)");
        $stmt->execute([$userId, $editType === 'qr' ? 'qr' : 'link']);
    }

    public static function getUsageSummary(int $userId): array {
        global $pdo;

        // 1. Determina se é Admin ou Bypass
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $isAdmin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);

        $stmtOver = $pdo->prepare("SELECT billing_bypass, custom_plan_id, custom_limits_json FROM user_account_overrides WHERE user_id = ?");
        $stmtOver->execute([$userId]);
        $override = $stmtOver->fetch(PDO::FETCH_ASSOC);

        $isBypass = ($override && $override['billing_bypass'] == 1) || $isAdmin;

        // 2. Determina o Plano
        $planId = 1;
        if ($override && !empty($override['custom_plan_id'])) {
            $planId = (int)$override['custom_plan_id'];
        } else {
            $stmt = $pdo->prepare("SELECT current_plan_id, plan FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (!empty($user['current_plan_id'])) {
                    $planId = (int)$user['current_plan_id'];
                } else {
                    $pName = $user['plan'] ?? 'free';
                    $planId = ($pName === 'founder') ? 5 : (($pName === 'business') ? 4 : (($pName === 'pro') ? 3 : (($pName === 'creator') ? 2 : 1)));
                }
            }
        }

        $plan = PlanService::getPlanDetails($planId);
        $planName = $plan ? $plan['name'] : 'Plano Grátis';

        // 3. Limites (mensais, com overrides)
        $maxLinks = self::getEffectiveLimit($userId, 'max_links', (int)($plan['max_links'] ?? 10));
        $maxQrs = self::getEffectiveLimit($userId, 'max_qrs', (int)($plan['max_qrs'] ?? 5));
        $maxBio = self::getEffectiveLimit($userId, 'max_bio_pages', (int)($plan['max_bio_pages'] ?? 1));

        $maxSub = self::getEffectiveLimit($userId, 'max_subdomains', (int)($plan['max_subdomains'] ?? 0));
        $cycle = self::getUsageCycleWindow($userId);

        // 4. Consumo Atual (mensal, baseado no ciclo do usuário)
        $usedLinks = self::countUserRowsInCycle($userId, 'links', 'user_id', 'created_at', "type = 'link'");
        $usedQrs = self::countUserRowsInCycle($userId, 'links', 'user_id', 'created_at', "type = 'qr'");
        $usedBio = self::countUserRowsInCycle($userId, 'bio_pages');
        $usedSub = self::countUserRowsInCycle($userId, 'user_subdomains');

        return [
            'plan_name' => $planName,
            'is_admin'  => $isBypass,
            'cycle' => [
                'start' => $cycle['start_date'],
                'end' => $cycle['end_date'],
                'anchor_day' => $cycle['anchor_day']
            ],
            'links' => [
                'used' => $usedLinks,
                'total' => (int)$maxLinks,
                'remaining' => ($maxLinks >= 9999) ? 'Ilimitado' : max(0, $maxLinks - $usedLinks),
                'percent' => ($maxLinks >= 9999 || (int)$maxLinks <= 0) ? 0 : min(100, round(($usedLinks / $maxLinks) * 100))
            ],
            'qr_codes' => [
                'used' => $usedQrs,
                'total' => (int)$maxQrs,
                'remaining' => ($maxQrs >= 9999) ? 'Ilimitado' : max(0, $maxQrs - $usedQrs),
                'percent' => ($maxQrs >= 9999 || (int)$maxQrs <= 0) ? 0 : min(100, round(($usedQrs / $maxQrs) * 100))
            ],
            'bio_pages' => [
                'used' => $usedBio,
                'total' => (int)$maxBio,
                'remaining' => ($maxBio >= 9999) ? 'Ilimitado' : max(0, $maxBio - $usedBio),
                'percent' => ($maxBio >= 9999 || (int)$maxBio <= 0) ? 0 : min(100, round(($usedBio / $maxBio) * 100))
            ],
            'subdomains' => [
                'used' => $usedSub,
                'total' => (int)$maxSub,
                'remaining' => ($maxSub >= 50) ? 'Ilimitado' : max(0, $maxSub - $usedSub),
                'percent' => ($maxSub <= 0) ? 100 : min(100, round(($usedSub / $maxSub) * 100))
            ]
        ];
    }
}
