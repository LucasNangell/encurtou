<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json');
require_once 'db.php';
$pdo->exec("SET time_zone = '-03:00';");
require_once 'billing/EntitlementService.php';

function buildGeminiEndpoint(string $model): string
{
    return 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
}

function callGemini(string $prompt): ?string
{
    $apiKey = envOrDefault('GEMINI_API_KEY', '');
    $model = envOrDefault('GEMINI_MODEL', 'gemini-1.5-flash');
    if ($apiKey === '' || $model === '') {
        return null;
    }

    $endpoint = buildGeminiEndpoint($model) . '?key=' . rawurlencode($apiKey);
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.35,
            'maxOutputTokens' => 180
        ]
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return null;
    }

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 12
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 12
            ]
        ]);
        $raw = @file_get_contents($endpoint, false, $ctx);
    }

    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim((string)$text);
    return $text !== '' ? $text : null;
}

function getAIStatus(string $summaryJson): array
{
    $basePrompt = 'Aja como um analista de dados sênior. Resuma em 2 frases quais são os pontos mais fortes deste relatório de cliques e dê uma sugestão acionável para o usuário. Dados: ' . $summaryJson;
    $alertsPrompt = 'Você é um monitor de risco de performance. Em até 3 bullets curtos (sem introdução), liste alertas críticos e objetivos com base nos dados, priorizando anomalias de queda, baixa conversão e concentração de tráfego. Dados: ' . $summaryJson;
    $recommendationsPrompt = 'Você é um consultor de growth. Gere até 3 recomendações acionáveis, priorizadas e de baixo esforço para melhorar conversão e cliques nos próximos 7 dias. Responda somente em bullets curtos. Dados: ' . $summaryJson;

    $summary = callGemini($basePrompt);
    $alertsText = callGemini($alertsPrompt);
    $recommendationsText = callGemini($recommendationsPrompt);

    $toItems = static function (?string $text): array {
        if (!is_string($text) || trim($text) === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $items = [];
        foreach ($lines as $line) {
            $clean = trim((string)$line);
            $clean = preg_replace('/^[-*•\d\.\)\s]+/u', '', $clean);
            if ($clean !== '') {
                $items[] = $clean;
            }
        }
        return array_slice($items, 0, 3);
    };

    return [
        'summary' => $summary ?? '',
        'alerts' => $toItems($alertsText),
        'recommendations' => $toItems($recommendationsText)
    ];
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$version = $_GET['version'] ?? '1';
$period = (int)($_GET['period'] ?? 14);
if (!in_array($period, [7, 14, 30], true)) {
    $period = 14;
}
$periodSql = (string)$period;
$previousWindowSql = (string)($period * 2);

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $kpiActivity = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(clicks), 0) FROM links WHERE user_id = ?");
    $stmt->execute([$userId]);
    $kpiReach = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN cl.clicked_at >= NOW() - INTERVAL 24 HOUR THEN 1 END) AS last_24h,
            COUNT(CASE WHEN cl.clicked_at >= NOW() - INTERVAL 1 HOUR THEN 1 END) AS last_1h
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
    ");
    $stmt->execute([$userId]);
    $engagement = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['last_24h' => 0, 'last_1h' => 0];

    $stmtTeam = $pdo->prepare("SELECT id FROM teams WHERE owner_id = ?");
    $stmtTeam->execute([$userId]);
    $teamId = $stmtTeam->fetchColumn();
    $membersCount = 1;
    if ($teamId) {
        $stmtMembers = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
        $stmtMembers->execute([$teamId]);
        $membersCount += (int)$stmtMembers->fetchColumn();
    }

    $stmtPlan = $pdo->prepare("
        SELECT u.plan, p.name AS plan_name
        FROM users u
        LEFT JOIN plans p ON p.id = u.current_plan_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmtPlan->execute([$userId]);
    $planRow = $stmtPlan->fetch(PDO::FETCH_ASSOC) ?: [];
    $userPlan = strtolower((string)($planRow['plan'] ?? 'free'));
    if ($userPlan === '') $userPlan = 'free';
    $planName = trim((string)($planRow['plan_name'] ?? ''));
    if ($planName === '') {
        $planName = ucfirst($userPlan);
    }

    $maxMembers = EntitlementService::getEffectiveLimit($userId, 'max_team_members', 1);
    if ($maxMembers >= 999999) {
        $maxMembers = 999999;
    }
    if ($maxMembers <= 1) {
        if ($userPlan === 'pro') $maxMembers = 2;
        if ($userPlan === 'business') $maxMembers = 10;
    }

    $stmtTrend = $pdo->prepare("
        SELECT DATE(cl.clicked_at) AS day, COUNT(*) AS clicks
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ? AND cl.clicked_at >= DATE_SUB(CURDATE(), INTERVAL $periodSql DAY)
        GROUP BY DATE(cl.clicked_at)
        ORDER BY DATE(cl.clicked_at) ASC
    ");
    $stmtTrend->execute([$userId]);
    $trendData = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    $stmtTop = $pdo->prepare("
        SELECT title, subdomain, path, clicks
        FROM links
        WHERE user_id = ?
        ORDER BY clicks DESC
        LIMIT 5
    ");
    $stmtTop->execute([$userId]);
    $topLinks = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    $stmtMix = $pdo->prepare("
        SELECT cl.source, COUNT(*) AS total
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
        GROUP BY cl.source
    ");
    $stmtMix->execute([$userId]);
    $mixData = $stmtMix->fetchAll(PDO::FETCH_ASSOC);

    $stmtRecent = $pdo->prepare("
        SELECT cl.visitor_country, cl.visitor_region, cl.visitor_city, cl.clicked_at, l.path, l.subdomain
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
        ORDER BY cl.clicked_at DESC
        LIMIT 7
    ");
    $stmtRecent->execute([$userId]);
    $recentActivity = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    if ($version !== '2') {
        echo json_encode([
            'success' => true,
            'kpis' => [
                'activity' => $kpiActivity,
                'reach' => $kpiReach,
                'engagement_24h' => (int)$engagement['last_24h'],
                'engagement_1h' => (int)$engagement['last_1h'],
                'team_count' => $membersCount,
                'team_limit' => $maxMembers,
                'plan_name' => $planName
            ],
            'charts' => [
                'trend' => $trendData,
                'top_links' => $topLinks,
                'mix_origin' => $mixData
            ],
            'recent_activity' => $recentActivity
        ]);
        exit;
    }

    $stmtCurrent = $pdo->prepare("
        SELECT COUNT(*) AS clicks
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ? AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
    ");
    $stmtCurrent->execute([$userId]);
    $currentClicks = (int)$stmtCurrent->fetchColumn();

    $stmtTopCities = $pdo->prepare("
        SELECT cl.visitor_city AS city, COUNT(*) AS total
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
          AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
          AND cl.visitor_city IS NOT NULL
          AND cl.visitor_city <> ''
          AND cl.visitor_city <> 'Desconhecido'
        GROUP BY cl.visitor_city
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmtTopCities->execute([$userId]);
    $topCities = $stmtTopCities->fetchAll(PDO::FETCH_ASSOC);

    $stmtDevices = $pdo->prepare("
        SELECT
            CASE
                WHEN cl.user_agent IS NULL OR cl.user_agent = '' THEN 'unknown'
                WHEN LOWER(cl.user_agent) REGEXP 'mobile|android|iphone' THEN 'mobile'
                WHEN LOWER(cl.user_agent) REGEXP 'ipad|tablet' THEN 'tablet'
                ELSE 'desktop'
            END AS device,
            COUNT(*) AS total
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
          AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        GROUP BY device
        ORDER BY total DESC
    ");
    $stmtDevices->execute([$userId]);
    $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

    $stmtPrevious = $pdo->prepare("
        SELECT COUNT(*) AS clicks
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ?
          AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $previousWindowSql DAY)
          AND cl.clicked_at < DATE_SUB(NOW(), INTERVAL $periodSql DAY)
    ");
    $stmtPrevious->execute([$userId]);
    $previousClicks = (int)$stmtPrevious->fetchColumn();

    $growth = 0.0;
    if ($previousClicks > 0) {
        $growth = (($currentClicks - $previousClicks) / $previousClicks) * 100;
    }

    $stmtTotals = $pdo->prepare("
        SELECT
            COUNT(cl.id) AS total_clicks,
            COUNT(cv.id) AS total_conversions
        FROM links l
        LEFT JOIN clicks_log cl ON cl.link_id = l.id AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        LEFT JOIN conversions_log cv ON cv.click_id = cl.id
        WHERE l.user_id = ?
    ");
    $stmtTotals->execute([$userId]);
    $totals = $stmtTotals->fetch(PDO::FETCH_ASSOC) ?: ['total_clicks' => 0, 'total_conversions' => 0];
    $totalClicksPeriod = (int)$totals['total_clicks'];
    $totalConversionsPeriod = (int)$totals['total_conversions'];

    $stmtAvgTime = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, cl.clicked_at, cv.converted_at)) AS avg_minutes
        FROM conversions_log cv
        JOIN clicks_log cl ON cl.id = cv.click_id
        JOIN links l ON l.id = cl.link_id
        WHERE l.user_id = ? AND cv.converted_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
    ");
    $stmtAvgTime->execute([$userId]);
    $avgMinutes = (float)($stmtAvgTime->fetchColumn() ?: 0);

    $stmtConversionsDaily = $pdo->prepare("
        SELECT DATE(cv.converted_at) AS day, COUNT(*) AS total
        FROM conversions_log cv
        JOIN clicks_log cl ON cv.click_id = cl.id
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ? AND cv.converted_at >= DATE_SUB(CURDATE(), INTERVAL $periodSql DAY)
        GROUP BY DATE(cv.converted_at)
        ORDER BY DATE(cv.converted_at) ASC
    ");
    $stmtConversionsDaily->execute([$userId]);
    $conversionsDaily = $stmtConversionsDaily->fetchAll(PDO::FETCH_ASSOC);

    $stmtHourly = $pdo->prepare("
        SELECT HOUR(cl.clicked_at) AS hour, COUNT(*) AS clicks
        FROM clicks_log cl
        JOIN links l ON cl.link_id = l.id
        WHERE l.user_id = ? AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        GROUP BY HOUR(cl.clicked_at)
        ORDER BY HOUR(cl.clicked_at) ASC
    ");
    $stmtHourly->execute([$userId]);
    $hourlyHeatmap = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

    $stmtCampaignRows = $pdo->prepare("
        SELECT
            l.original_url,
            COUNT(cl.id) AS clicks,
            COUNT(cv.id) AS conversions
        FROM links l
        LEFT JOIN clicks_log cl ON cl.link_id = l.id AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL $periodSql DAY)
        LEFT JOIN conversions_log cv ON cv.click_id = cl.id
        WHERE l.user_id = ?
        GROUP BY l.id
    ");
    $stmtCampaignRows->execute([$userId]);
    $campaignRows = $stmtCampaignRows->fetchAll(PDO::FETCH_ASSOC);
    $campaignMap = [];

    foreach ($campaignRows as $row) {
        $query = parse_url((string)$row['original_url'], PHP_URL_QUERY);
        $campaign = 'Sem campanha';
        if ($query) {
            parse_str($query, $params);
            if (!empty($params['utm_campaign'])) {
                $campaign = (string)$params['utm_campaign'];
            }
        }
        if (!isset($campaignMap[$campaign])) {
            $campaignMap[$campaign] = ['campaign' => $campaign, 'clicks' => 0, 'conversions' => 0, 'efficiency' => 0];
        }
        $campaignMap[$campaign]['clicks'] += (int)$row['clicks'];
        $campaignMap[$campaign]['conversions'] += (int)$row['conversions'];
    }

    $campaignEfficiency = [];
    foreach ($campaignMap as $c) {
        $c['efficiency'] = $c['clicks'] > 0 ? round(($c['conversions'] / $c['clicks']) * 100, 2) : 0.0;
        $campaignEfficiency[] = $c;
    }
    usort($campaignEfficiency, static function($a, $b) {
        return $b['efficiency'] <=> $a['efficiency'];
    });
    $campaignEfficiency = array_slice($campaignEfficiency, 0, 6);

    $ctr = $kpiActivity > 0 ? ($totalClicksPeriod / $kpiActivity) * 100 : 0.0;
    $conversionRate = $totalClicksPeriod > 0 ? ($totalConversionsPeriod / $totalClicksPeriod) * 100 : 0.0;

    $alerts = [];
    if ($growth < -20) $alerts[] = 'Queda superior a 20% no volume de cliques versus período anterior.';
    if ($totalClicksPeriod > 30 && $conversionRate < 1) $alerts[] = 'Volume de cliques alto com baixa taxa de conversão.';
    if ((int)$engagement['last_1h'] > 30) $alerts[] = 'Pico recente de tráfego detectado na última hora.';

    $recommendations = [];
    if (!empty($campaignEfficiency)) {
        $topCampaign = $campaignEfficiency[0];
        $recommendations[] = 'Priorize orçamento em "' . $topCampaign['campaign'] . '" com eficiência de ' . number_format((float)$topCampaign['efficiency'], 1, ',', '.') . '%.';
    }
    if ($conversionRate < 1.5) $recommendations[] = 'Revise CTA e página de destino para melhorar conversão nas próximas campanhas.';
    if ($ctr < 30) $recommendations[] = 'Experimente variações de copy para elevar o CTR médio por link ativo.';
    if (empty($recommendations)) $recommendations[] = 'Seu desempenho está consistente. Foque em escalar as campanhas com melhor eficiência.';

    $summaryPayload = [
        'period_days' => $period,
        'clicks_period' => $totalClicksPeriod,
        'conversions_period' => $totalConversionsPeriod,
        'engagement_24h' => (int)$engagement['last_24h'],
        'engagement_1h' => (int)$engagement['last_1h'],
        'growth_percent' => round($growth, 2),
        'conversion_rate_percent' => round($conversionRate, 2),
        'ctr_percent' => round($ctr, 2),
        'top_cities' => $topCities,
        'devices' => $devices,
        'top_links' => array_map(static function ($row) {
            return [
                'title' => (string)($row['title'] ?? ''),
                'clicks' => (int)($row['clicks'] ?? 0)
            ];
        }, $topLinks)
    ];
    $summaryJson = json_encode($summaryPayload, JSON_UNESCAPED_UNICODE);
    if (!is_string($summaryJson)) {
        $summaryJson = '{}';
    }

    $manualAIRequest = ($_GET['ai'] ?? '0') === '1';
    $isAdmin = isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;
    $hasAutoAI = in_array($userPlan, ['business', 'empresarial'], true) || $isAdmin;
    $canUseAI = $hasAutoAI || $manualAIRequest;
    $aiInsights = ['summary' => '', 'alerts' => [], 'recommendations' => []];
    if ($canUseAI) {
        $aiInsights = getAIStatus($summaryJson);
    }

    echo json_encode([
        'success' => true,
        'kpis' => [
            'activity' => $kpiActivity,
            'reach' => $kpiReach,
            'engagement_24h' => (int)$engagement['last_24h'],
            'engagement_1h' => (int)$engagement['last_1h'],
            'team_count' => $membersCount,
            'team_limit' => $maxMembers,
            'plan_name' => ucfirst($userPlan)
        ],
        'insights' => [
            'funnel_health' => [
                'ctr' => round($ctr, 2),
                'conversion_rate' => round($conversionRate, 2),
                'avg_time_to_convert_minutes' => round($avgMinutes, 2),
                'period_growth' => round($growth, 2)
            ],
            'alerts' => !empty($aiInsights['alerts']) ? $aiInsights['alerts'] : $alerts,
            'recommendations' => !empty($aiInsights['recommendations']) ? $aiInsights['recommendations'] : $recommendations,
            'ai_summary' => $aiInsights['summary'],
            'ai_allowed' => $canUseAI,
            'ai_auto' => $hasAutoAI
        ],
        'charts' => [
            'trend' => $trendData,
            'top_links' => $topLinks,
            'mix_origin' => $mixData,
            'conversion_funnel' => [
                ['label' => 'Links ativos', 'value' => $kpiActivity],
                ['label' => 'Cliques período', 'value' => $totalClicksPeriod],
                ['label' => 'Conversões período', 'value' => $totalConversionsPeriod]
            ],
            'conversions_daily' => $conversionsDaily,
            'hourly_heatmap' => $hourlyHeatmap,
            'campaign_efficiency' => $campaignEfficiency
        ],
        'recent_activity' => $recentActivity
    ]);
} catch (PDOException $e) {
    error_log('dashboard.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao carregar dashboard.']);
}
