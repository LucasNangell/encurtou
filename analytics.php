<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
// Recebe o ID do link específico (se houver)
$linkId = isset($_GET['link_id']) && $_GET['link_id'] !== 'all' ? (int)$_GET['link_id'] : null;

try {
    // Busca a lista de links do usuário para preencher o dropdown
    $linksQuery = $pdo->prepare("SELECT id, path, subdomain FROM links WHERE user_id = ? ORDER BY created_at DESC");
    $linksQuery->execute([$userId]);
    $userLinks = $linksQuery->fetchAll(PDO::FETCH_ASSOC);

    // Constrói a condição extra se um link específico for selecionado
    $linkFilter = $linkId ? " AND links.id = :linkId" : "";

    // 1. Cliques por País
    $qCountries = "SELECT visitor_country as label, COUNT(*) as value FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter . " GROUP BY visitor_country ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($qCountries);
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Origem (Referral)
    $qReferrers = "SELECT SUBSTRING_INDEX(REPLACE(REPLACE(referer, 'https://', ''), 'http://', ''), '/', 1) as label, COUNT(*) as value FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter . " GROUP BY label ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($qReferrers);
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Navegador e Dispositivo
    $qLogs = "SELECT user_agent FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter;
    $stmt = $pdo->prepare($qLogs);
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $browsers = ['Chrome' => 0, 'Firefox' => 0, 'Safari' => 0, 'Edge' => 0, 'Outros' => 0];
    $devices = ['Mobile' => 0, 'Desktop' => 0];

    foreach ($logs as $ua) {
        if (preg_match('/(android|iphone|ipad|mobile)/i', $ua)) $devices['Mobile']++;
        else $devices['Desktop']++;

        if (strpos($ua, 'Edg') !== false) $browsers['Edge']++;
        elseif (strpos($ua, 'Chrome') !== false) $browsers['Chrome']++;
        elseif (strpos($ua, 'Firefox') !== false) $browsers['Firefox']++;
        elseif (strpos($ua, 'Safari') !== false) $browsers['Safari']++;
        else $browsers['Outros']++;
    }
    
    // 4. Origem do Clique (Digital vs QR)
    $qSources = "SELECT source as label, COUNT(*) as value FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter . " GROUP BY source";
    $stmt = $pdo->prepare($qSources);
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'links' => $userLinks,
        'countries' => $countries,
        'referrers' => $referrers,
        'browsers' => $browsers,
        'devices' => $devices,
        'sources' => $sources
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
?>