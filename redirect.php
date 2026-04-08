<?php
require_once 'db.php';

$host = strtolower($_SERVER['HTTP_HOST']);
$slug = isset($_GET['slug']) ? strtolower(trim($_GET['slug'])) : '';

$parts = explode('.', $host);
$sub = (count($parts) > 2 && $parts[0] !== 'www') ? $parts[0] : 'ROOT';

if (!empty($slug)) {
    try {
        $stmt = $pdo->prepare("SELECT id, original_url, status FROM links WHERE subdomain = ? AND path = ?");
        $stmt->execute([$sub, $slug]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link) {
            if ($link['status'] === 'paused') {
                die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Link pausado pelo proprietário.</h2>");
            }

            $visitorIp = getRealIP();
            $country = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'Desconhecido';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
            $ref = $_SERVER['HTTP_REFERER'] ?? 'Acesso Direto';

            $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?")->execute([$link['id']]);
            $pdo->prepare("INSERT INTO clicks_log (link_id, visitor_ip, visitor_country, user_agent, referer) VALUES (?, ?, ?, ?, ?)")
                ->execute([$link['id'], $visitorIp, $country, $ua, $ref]);

            header("Location: " . $link['original_url'], true, 301);
            exit;
        }
    } catch (PDOException $e) {}
}

include('index.html');
?>