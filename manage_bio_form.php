<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bio_helpers.php';
require_once __DIR__ . '/bio_form_helpers.php';
require_once __DIR__ . '/billing/EntitlementService.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

requireCsrfToken();

$userId = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function bio_form_assert_page_owner(PDO $pdo, int $userId, int $pageId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM bio_pages WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$pageId, $userId]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    return $page ?: null;
}

function bio_form_load_schema(PDO $pdo, array $page): array
{
    $stmt = $pdo->prepare('SELECT * FROM bio_links WHERE bio_page_id = ? ORDER BY display_order ASC');
    $stmt->execute([(int) $page['id']]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return bio_resolve_schema($page, $links);
}

$hasFormTables = bio_has_table($pdo, 'bio_form_submissions') && bio_has_table($pdo, 'bio_form_answers');
$hasBioViewsTable = bio_has_table($pdo, 'bio_page_views');

$pageId = (int) ($_GET['page_id'] ?? $_POST['page_id'] ?? 0);
if ($pageId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'page_id obrigatório.']);
    exit;
}

$page = bio_form_assert_page_owner($pdo, $userId, $pageId);
if (!$page) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Página não encontrada.']);
    exit;
}

$schema = bio_form_load_schema($pdo, $page);
$fieldsMap = bio_form_collect_fields($schema);

function bio_form_parse_date_ymd(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $s = trim($raw);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $s);
    if (!$dt || $dt->format('Y-m-d') !== $s) {
        return null;
    }

    return $s;
}

function bio_form_resolve_date_range(?string $dateFrom, ?string $dateTo): array
{
    if ($dateFrom !== null && $dateTo !== null && strcmp($dateFrom, $dateTo) > 0) {
        $tmp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $tmp;
    }

    return [$dateFrom, $dateTo];
}

/** @return array{0: string, 1: array<int, mixed>} SQL fragment (starts with AND...), extra params */
function bio_form_submission_date_sql(?string $tableAlias, ?string $dateFrom, ?string $dateTo): array
{
    $col = ($tableAlias === null || $tableAlias === '') ? 'created_at' : $tableAlias . '.created_at';
    $frag = '';
    $params = [];
    if ($dateFrom !== null) {
        $frag .= " AND DATE({$col}) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== null) {
        $frag .= " AND DATE({$col}) <= ?";
        $params[] = $dateTo;
    }

    return [$frag, $params];
}

/** @return array{0: string, 1: array<int, mixed>} */
function bio_page_view_date_sql(?string $tableAlias, ?string $dateFrom, ?string $dateTo): array
{
    $col = ($tableAlias === null || $tableAlias === '') ? 'viewed_at' : $tableAlias . '.viewed_at';
    $frag = '';
    $params = [];
    if ($dateFrom !== null) {
        $frag .= " AND DATE({$col}) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== null) {
        $frag .= " AND DATE({$col}) <= ?";
        $params[] = $dateTo;
    }
    return [$frag, $params];
}

$dateFromIn = bio_form_parse_date_ymd($_GET['date_from'] ?? $_POST['date_from'] ?? null);
$dateToIn = bio_form_parse_date_ymd($_GET['date_to'] ?? $_POST['date_to'] ?? null);
[$dateFrom, $dateTo] = bio_form_resolve_date_range($dateFromIn, $dateToIn);

if ($action === 'text_samples') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$hasFormTables) {
        echo json_encode(['success' => false, 'message' => 'Tabelas de formulário não instaladas. Execute bio_form_migration.sql.']);
        exit;
    }
    $fieldKey = trim((string) ($_GET['field_key'] ?? $_POST['field_key'] ?? ''));
    $pageNum = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
    $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? $_POST['per_page'] ?? 20)));

    if ($fieldKey === '' || !isset($fieldsMap[$fieldKey])) {
        echo json_encode(['success' => false, 'message' => 'Campo inválido.']);
        exit;
    }
    $ft = $fieldsMap[$fieldKey]['field_type'];
    if (!in_array($ft, ['short_text', 'long_text', 'email', 'phone', 'url'], true)) {
        echo json_encode(['success' => false, 'message' => 'Este campo não suporta amostras paginadas.']);
        exit;
    }

    [$dateSql, $dateParams] = bio_form_submission_date_sql('s', $dateFrom, $dateTo);
    $baseWhere = 's.bio_page_id = ?' . $dateSql . " AND a.field_key = ? AND a.value_text IS NOT NULL AND TRIM(a.value_text) <> ''";
    $baseParams = array_merge([$pageId], $dateParams, [$fieldKey]);

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bio_form_answers a INNER JOIN bio_form_submissions s ON s.id = a.submission_id WHERE {$baseWhere}");
    $cntStmt->execute($baseParams);
    $totalRows = (int) $cntStmt->fetchColumn();

    $off = ($pageNum - 1) * $perPage;
    $selStmt = $pdo->prepare("SELECT a.value_text FROM bio_form_answers a INNER JOIN bio_form_submissions s ON s.id = a.submission_id WHERE {$baseWhere} ORDER BY s.id DESC LIMIT {$perPage} OFFSET {$off}");
    $selStmt->execute($baseParams);
    $samples = [];
    while ($r = $selStmt->fetch(PDO::FETCH_ASSOC)) {
        $samples[] = (string) ($r['value_text'] ?? '');
    }

    echo json_encode([
        'success' => true,
        'field_key' => $fieldKey,
        'page' => $pageNum,
        'per_page' => $perPage,
        'total' => $totalRows,
        'samples' => $samples,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (in_array($action, ['export_csv', 'export_xlsx'], true)) {
    try {
        if (!EntitlementService::canAccessFeature($userId, 'bio_form_exports')) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'feature_locked', 'message' => 'Seu plano atual não permite exportar respostas de formulários.']);
            exit;
        }
    } catch (Throwable $e) {
        // Em ambientes sem esquema de billing completo, mantém exportação ativa.
    }
    if (!$hasFormTables) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Tabelas de formulário não instaladas. Execute bio_form_migration.sql.']);
        exit;
    }
    [$dateSql, $dateParams] = bio_form_submission_date_sql(null, $dateFrom, $dateTo);
    $exportWhere = 'bio_page_id = ?' . $dateSql;
    $exportParams = array_merge([$pageId], $dateParams);
    $stmt = $pdo->prepare("SELECT id, created_at FROM bio_form_submissions WHERE {$exportWhere} ORDER BY id ASC");
    $stmt->execute($exportParams);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $keysOrdered = array_keys($fieldsMap);
    $headers = array_merge(['ID envio', 'Data'], array_map(function ($k) use ($fieldsMap) {
        $lab = $fieldsMap[$k]['label'] ?: $k;
        return $lab . ' (' . $k . ')';
    }, $keysOrdered));

    $rows = [];
    foreach ($subs as $sub) {
        $stmtA = $pdo->prepare('SELECT field_key, value_text, value_json FROM bio_form_answers WHERE submission_id = ?');
        $stmtA->execute([(int) $sub['id']]);
        $byKey = [];
        while ($a = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            $txt = (string) ($a['value_text'] ?? '');
            if ($txt === '' && !empty($a['value_json'])) {
                $j = json_decode((string) $a['value_json'], true);
                $txt = is_array($j) ? implode('; ', $j) : (string) $a['value_json'];
            }
            $byKey[$a['field_key']] = $txt;
        }
        $line = [(string) $sub['id'], (string) $sub['created_at']];
        foreach ($keysOrdered as $k) {
            $line[] = $byKey[$k] ?? '';
        }
        $rows[] = $line;
    }

    $fname = 'bio-form-' . preg_replace('/[^a-z0-9_-]+/i', '_', (string) $page['slug']) . '-' . date('Ymd-His');

    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fname . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ';');
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        fclose($out);
        exit;
    }

    require_once __DIR__ . '/bio_form_xlsx.php';
    bio_form_stream_xlsx_download($fname . '.xlsx', $headers, $rows);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($action !== 'analytics') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

function bio_form_analytics_map_option_labels(array $field, array $dist): array
{
    $opts = bio_form_option_values($field);
    $out = [];
    foreach ($dist as $val => $cnt) {
        $k = ($val === '(vazio)') ? '(vazio)' : ($opts[(string) $val] ?? (string) $val);
        $out[$k] = ($out[$k] ?? 0) + (int) $cnt;
    }
    return $out;
}

function bio_form_analytics_numeric_histogram(array $vals): array
{
    if ($vals === []) {
        return [];
    }
    sort($vals);
    $n = count($vals);
    $min = $vals[0];
    $max = $vals[$n - 1];
    if ($min === $max) {
        return [['label' => (string) $min, 'count' => $n]];
    }
    $bins = min(12, max(4, (int) ceil(sqrt($n))));
    $width = ($max - $min) / $bins;
    if ($width <= 0) {
        return [['label' => (string) $min, 'count' => $n]];
    }
    $hist = array_fill(0, $bins, 0);
    foreach ($vals as $v) {
        $i = (int) floor(($v - $min) / $width);
        if ($i >= $bins) {
            $i = $bins - 1;
        }
        if ($i < 0) {
            $i = 0;
        }
        $hist[$i]++;
    }
    $out = [];
    for ($i = 0; $i < $bins; $i++) {
        $lo = $min + $i * $width;
        $hi = $i === $bins - 1 ? $max : $min + ($i + 1) * $width;
        $out[] = [
            'label' => sprintf('%.3g – %.3g', $lo, $hi),
            'count' => $hist[$i],
        ];
    }

    return $out;
}

$recentPage = max(1, (int) ($_GET['recent_page'] ?? 1));
$recentPerPage = min(50, max(5, (int) ($_GET['recent_per_page'] ?? 10)));

if ($fieldsMap === []) {
    if (!$hasBioViewsTable) {
        echo json_encode([
            'success' => true,
            'page_id' => $pageId,
            'page_slug' => $page['slug'] ?? '',
            'has_form' => false,
            'mode' => 'page_views',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'basic_page_analytics' => [
                'total_views' => 0,
                'timeline' => [],
                'recent_views' => [],
                'recent_page' => 1,
                'recent_per_page' => $recentPerPage,
                'recent_total' => 0,
                'message' => 'Tabela de acessos da Bio Page ainda não instalada.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    [$dateSqlViews, $dateParamsViews] = bio_page_view_date_sql(null, $dateFrom, $dateTo);
    $totalViewsStmt = $pdo->prepare("SELECT COUNT(*) FROM bio_page_views WHERE bio_page_id = ?{$dateSqlViews}");
    $totalViewsStmt->execute(array_merge([$pageId], $dateParamsViews));
    $totalViews = (int) $totalViewsStmt->fetchColumn();

    $timeline = [];
    if ($dateFrom !== null || $dateTo !== null) {
        $tlStmt = $pdo->prepare("SELECT DATE(viewed_at) AS d, COUNT(*) AS c FROM bio_page_views WHERE bio_page_id = ?{$dateSqlViews} GROUP BY DATE(viewed_at) ORDER BY d ASC");
        $tlStmt->execute(array_merge([$pageId], $dateParamsViews));
    } else {
        $tlStmt = $pdo->prepare("SELECT DATE(viewed_at) AS d, COUNT(*) AS c FROM bio_page_views WHERE bio_page_id = ? AND viewed_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DATE(viewed_at) ORDER BY d ASC");
        $tlStmt->execute([$pageId]);
    }
    while ($t = $tlStmt->fetch(PDO::FETCH_ASSOC)) {
        $timeline[] = [
            'date' => (string) $t['d'],
            'count' => (int) $t['c'],
        ];
    }

    $recentOffset = ($recentPage - 1) * $recentPerPage;
    $recentTotalStmt = $pdo->prepare("SELECT COUNT(*) FROM bio_page_views WHERE bio_page_id = ?{$dateSqlViews}");
    $recentTotalStmt->execute(array_merge([$pageId], $dateParamsViews));
    $recentTotal = (int) $recentTotalStmt->fetchColumn();

    $recent = [];
    $recentStmt = $pdo->prepare("SELECT id, viewed_at FROM bio_page_views WHERE bio_page_id = ?{$dateSqlViews} ORDER BY id DESC LIMIT " . (int) $recentPerPage . ' OFFSET ' . (int) $recentOffset);
    $recentStmt->execute(array_merge([$pageId], $dateParamsViews));
    foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recent[] = [
            'id' => (int) $row['id'],
            'viewed_at' => (string) $row['viewed_at'],
        ];
    }

    echo json_encode([
        'success' => true,
        'page_id' => $pageId,
        'page_slug' => $page['slug'] ?? '',
        'has_form' => false,
        'mode' => 'page_views',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'basic_page_analytics' => [
            'total_views' => $totalViews,
            'timeline' => $timeline,
            'recent_views' => $recent,
            'recent_page' => $recentPage,
            'recent_per_page' => $recentPerPage,
            'recent_total' => $recentTotal,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$hasFormTables) {
    echo json_encode(['success' => false, 'message' => 'Tabelas de formulário não instaladas. Execute bio_form_migration.sql.']);
    exit;
}

[$dateSqlSub, $dateParamsSub] = bio_form_submission_date_sql(null, $dateFrom, $dateTo);
if ($dateFrom !== null || $dateTo !== null) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bio_form_submissions WHERE bio_page_id = ?{$dateSqlSub}");
    $stmt->execute(array_merge([$pageId], $dateParamsSub));
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bio_form_submissions WHERE bio_page_id = ?');
    $stmt->execute([$pageId]);
}
$total = (int) $stmt->fetchColumn();

$timeline = [];
if ($dateFrom !== null || $dateTo !== null) {
    $tlStmt = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM bio_form_submissions WHERE bio_page_id = ?{$dateSqlSub} GROUP BY DATE(created_at) ORDER BY d ASC");
    $tlStmt->execute(array_merge([$pageId], $dateParamsSub));
} else {
    $tlStmt = $pdo->prepare('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM bio_form_submissions WHERE bio_page_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DATE(created_at) ORDER BY d ASC');
    $tlStmt->execute([$pageId]);
}
while ($t = $tlStmt->fetch(PDO::FETCH_ASSOC)) {
    $timeline[] = [
        'date' => (string) $t['d'],
        'count' => (int) $t['c'],
    ];
}

$fieldStats = [];
foreach ($fieldsMap as $key => $f) {
    $fieldStats[$key] = [
        'key' => $key,
        'label' => $f['label'] ?: $key,
        'field_type' => $f['field_type'],
        'chartable' => bio_form_is_chartable($f['field_type']),
        'distribution' => [],
        'text_total' => 0,
        'response_count' => 0,
        'numeric_values' => [],
        'numeric_summary' => null,
        'numeric_histogram' => [],
    ];
}

[$dateSqlS, $dateParamsS] = bio_form_submission_date_sql('s', $dateFrom, $dateTo);
$sql = "SELECT a.field_key, a.field_type, a.value_text, a.value_json
        FROM bio_form_answers a
        INNER JOIN bio_form_submissions s ON s.id = a.submission_id
        WHERE s.bio_page_id = ?{$dateSqlS}";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$pageId], $dateParamsS));
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fk = $row['field_key'];
    if (!isset($fieldStats[$fk])) {
        continue;
    }
    $type = $row['field_type'];
    $fieldStats[$fk]['response_count']++;

    if (bio_form_is_chartable($type)) {
        if ($type === 'multiselect') {
            $parts = [];
            if (!empty($row['value_json'])) {
                $j = json_decode((string) $row['value_json'], true);
                if (is_array($j)) {
                    $parts = $j;
                }
            }
            if (!$parts && !empty($row['value_text'])) {
                $parts = array_map('trim', explode(';', (string) $row['value_text']));
            }
            foreach ($parts as $p) {
                if ($p === '') {
                    continue;
                }
                if (!isset($fieldStats[$fk]['distribution'][$p])) {
                    $fieldStats[$fk]['distribution'][$p] = 0;
                }
                $fieldStats[$fk]['distribution'][$p]++;
            }
        } else {
            $v = trim((string) ($row['value_text'] ?? ''));
            if ($v === '') {
                $v = '(vazio)';
            }
            if (!isset($fieldStats[$fk]['distribution'][$v])) {
                $fieldStats[$fk]['distribution'][$v] = 0;
            }
            $fieldStats[$fk]['distribution'][$v]++;
        }
        continue;
    }

    if ($type === 'number') {
        $txt = trim((string) ($row['value_text'] ?? ''));
        if ($txt !== '') {
            $norm = str_replace(',', '.', $txt);
            if (is_numeric($norm)) {
                $fieldStats[$fk]['numeric_values'][] = (float) $norm;
            }
        }
        continue;
    }

    if ($type === 'date' || $type === 'time') {
        $txt = trim((string) ($row['value_text'] ?? ''));
        if ($txt !== '') {
            if (!isset($fieldStats[$fk]['distribution'][$txt])) {
                $fieldStats[$fk]['distribution'][$txt] = 0;
            }
            $fieldStats[$fk]['distribution'][$txt]++;
        }
        continue;
    }

    $txt = trim((string) ($row['value_text'] ?? ''));
    if ($txt === '') {
        continue;
    }
    $fieldStats[$fk]['text_total']++;
}

foreach ($fieldStats as $key => &$fs) {
    $fdef = $fieldsMap[$key] ?? null;
    if (!$fdef) {
        continue;
    }
    $ft = $fdef['field_type'];

    if (bio_form_is_chartable($ft) && $fs['distribution'] !== [] && $ft !== 'checkbox') {
        $fs['distribution'] = bio_form_analytics_map_option_labels($fdef, $fs['distribution']);
    }

    if ($ft === 'number' && $fs['numeric_values'] !== []) {
        $vals = $fs['numeric_values'];
        sort($vals);
        $n = count($vals);
        $min = $vals[0];
        $max = $vals[$n - 1];
        $sum = array_sum($vals);
        $avg = $sum / $n;
        $mid = (int) (($n - 1) / 2);
        $median = ($n % 2 === 1) ? $vals[$mid] : ($vals[$mid] + $vals[$mid + 1]) / 2;
        $fs['numeric_summary'] = [
            'count' => $n,
            'min' => $min,
            'max' => $max,
            'sum' => $sum,
            'avg' => round($avg, 6),
            'median' => round($median, 6),
        ];
        $fs['numeric_histogram'] = bio_form_analytics_numeric_histogram($vals);
    }

    if (($ft === 'date' || $ft === 'time') && $fs['distribution'] !== []) {
        arsort($fs['distribution']);
        $fs['distribution'] = array_slice($fs['distribution'], 0, 30, true);
    }

    unset($fs['numeric_values']);
}
unset($fs);

$recentOffset = ($recentPage - 1) * $recentPerPage;
$recentTotalStmt = $pdo->prepare("SELECT COUNT(*) FROM bio_form_submissions WHERE bio_page_id = ?{$dateSqlSub}");
$recentTotalStmt->execute(array_merge([$pageId], $dateParamsSub));
$recentTotal = (int) $recentTotalStmt->fetchColumn();

$recent = [];
$recentStmt = $pdo->prepare("SELECT id, created_at FROM bio_form_submissions WHERE bio_page_id = ?{$dateSqlSub} ORDER BY id DESC LIMIT " . (int) $recentPerPage . ' OFFSET ' . (int) $recentOffset);
$recentStmt->execute(array_merge([$pageId], $dateParamsSub));
foreach ($recentStmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
    $recent[] = [
        'id' => (int) $sub['id'],
        'created_at' => $sub['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'page_id' => $pageId,
    'page_slug' => $page['slug'] ?? '',
    'total_submissions' => $total,
    'timeline' => $timeline,
    'fields' => array_values($fieldStats),
    'recent_submissions' => $recent,
    'recent_page' => $recentPage,
    'recent_per_page' => $recentPerPage,
    'recent_total' => $recentTotal,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'has_form' => $fieldsMap !== [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
