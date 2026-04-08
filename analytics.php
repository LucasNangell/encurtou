<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$linkId = isset($_GET['link_id']) && $_GET['link_id'] !== 'all' ? (int)$_GET['link_id'] : null;

try {
    $linksQuery = $pdo->prepare("SELECT id, path, subdomain FROM links WHERE user_id = ? ORDER BY created_at DESC");
    $linksQuery->execute([$userId]);
    $userLinks = $linksQuery->fetchAll(PDO::FETCH_ASSOC);

    $linkFilter = $linkId ? " AND links.id = :linkId" : "";

    // Cliques por País
    $stmt = $pdo->prepare("SELECT visitor_country as label, COUNT(*) as value FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter . " GROUP BY visitor_country ORDER BY value DESC LIMIT 5");
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Origem
    $stmt = $pdo->prepare("SELECT SUBSTRING_INDEX(REPLACE(REPLACE(referer, 'https://', ''), 'http://', ''), '/', 1) as label, COUNT(*) as value FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter . " GROUP BY label ORDER BY value DESC LIMIT 5");
    $stmt->bindValue(':userId', $userId);
    if($linkId) $stmt->bindValue(':linkId', $linkId);
    $stmt->execute();
    $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dispositivos e Navegadores
    $stmt = $pdo->prepare("SELECT user_agent FROM clicks_log JOIN links ON clicks_log.link_id = links.id WHERE links.user_id = :userId" . $linkFilter);
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

    echo json_encode([
        'success' => true,
        'links' => $userLinks,
        'countries' => $countries,
        'referrers' => $referrers,
        'browsers' => $browsers,
        'devices' => $devices
    ]);
} catch (PDOException $e) { echo json_encode(['success' => false]); }
?>