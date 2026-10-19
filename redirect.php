<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'db.php';
require_once 'precise_geo_helpers.php';
require_once 'link_pass_through_query.php';
require_once 'affiliate_helpers.php';
require_once 'billing/EntitlementService.php';
require_once 'subdomain_helpers.php';
require_once 'user_ban_helpers.php';
require_once 'AbusePolicyService.php';

function getAnalyticsPolicyForUser(PDO $pdo, int $userId): array {
    // Bypass Global Admin: o dono do link tem acesso total sem limites
    // (mesmo comportamento do EntitlementService para admin). A coluna
    // `is_admin` pode não existir em bancos antigos — por isso o try/catch.
    try {
        $adminStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ? LIMIT 1");
        $adminStmt->execute([$userId]);
        if ((int)$adminStmt->fetchColumn() === 1) {
            return ['enabled' => true, 'retention_days' => 0, 'max_clicks_monthly' => 999999999];
        }
    } catch (PDOException $e) {
        // Coluna is_admin ausente: segue o fluxo padrão abaixo.
    }

    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(op.id, p.id) AS effective_plan_id,
            COALESCE(op.analytics_days, p.analytics_days, 0) AS analytics_days,
            COALESCE(op.features_json, p.features_json, '{}') AS features_json,
            COALESCE(op.max_clicks_monthly, p.max_clicks_monthly, 0) AS max_clicks_monthly,
            o.custom_limits_json
        FROM users u
        LEFT JOIN user_account_overrides o ON o.user_id = u.id
        LEFT JOIN plans p ON p.id = u.current_plan_id
        LEFT JOIN plans op ON op.id = o.custom_plan_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['enabled' => false, 'retention_days' => 0, 'max_clicks_monthly' => 0];
    }

    $features = json_decode((string)($row['features_json'] ?? '{}'), true);
    if (!is_array($features)) $features = [];

    $analyticsDays = (int)($row['analytics_days'] ?? 0);
    if ($analyticsDays <= 0) {
        $analyticsDays = (int)($features['analytics_days'] ?? $features['analytics_retention_days'] ?? 0);
    }

    $maxClicksMonthly = (int)($row['max_clicks_monthly'] ?? 0);
    $customLimits = json_decode((string)($row['custom_limits_json'] ?? '{}'), true);
    if (is_array($customLimits) && array_key_exists('max_clicks_monthly', $customLimits)) {
        $maxClicksMonthly = (int)$customLimits['max_clicks_monthly'];
    }

    // Regra: só registra cliques se houver retenção > 0 e a feature analytics estiver ativa.
    $isAnalyticsEnabled = !empty($features['analytics']) && $analyticsDays > 0;

    return [
        'enabled' => $isAnalyticsEnabled,
        'retention_days' => max(0, $analyticsDays),
        'max_clicks_monthly' => max(0, $maxClicksMonthly)
    ];
}

function getDevicePlatformFromUa(string $ua): string {
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'ios';
    if (preg_match('/Android/i', $ua)) return 'android';
    if (preg_match('/Windows/i', $ua)) return 'windows';
    return 'other';
}

function buildUrlPathQueryFragment(array $parts): string {
    $path = (string)($parts['path'] ?? '/');
    if ($path === '') $path = '/';
    $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? ('#' . $parts['fragment']) : '';
    return $path . $query . $fragment;
}

function getDeepLinkAppProfile(string $host): ?array {
    $host = strtolower($host);
    $host = preg_replace('/^www\./', '', $host);

    $profiles = [
        'youtube.com' => ['label' => 'YouTube', 'android_package' => 'com.google.android.youtube', 'ios_scheme' => 'youtube://www.youtube.com'],
        'm.youtube.com' => ['label' => 'YouTube', 'android_package' => 'com.google.android.youtube', 'ios_scheme' => 'youtube://www.youtube.com'],
        'music.youtube.com' => ['label' => 'YouTube Music', 'android_package' => 'com.google.android.apps.youtube.music', 'ios_scheme' => 'youtubemusic://www.youtube.com'],
        'youtu.be' => ['label' => 'YouTube', 'android_package' => 'com.google.android.youtube', 'ios_scheme' => 'youtube://www.youtube.com'],
        'instagram.com' => ['label' => 'Instagram', 'android_package' => 'com.instagram.android', 'ios_scheme' => 'instagram://'],
        'm.instagram.com' => ['label' => 'Instagram', 'android_package' => 'com.instagram.android', 'ios_scheme' => 'instagram://'],
        'facebook.com' => ['label' => 'Facebook', 'android_package' => 'com.facebook.katana', 'ios_scheme' => 'fb://facewebmodal/f?href='],
        'm.facebook.com' => ['label' => 'Facebook', 'android_package' => 'com.facebook.katana', 'ios_scheme' => 'fb://facewebmodal/f?href='],
        'tiktok.com' => ['label' => 'TikTok', 'android_package' => 'com.zhiliaoapp.musically', 'ios_scheme' => 'tiktok://'],
        'www.tiktok.com' => ['label' => 'TikTok', 'android_package' => 'com.zhiliaoapp.musically', 'ios_scheme' => 'tiktok://'],
        'x.com' => ['label' => 'X', 'android_package' => 'com.twitter.android', 'ios_scheme' => 'twitter://'],
        'twitter.com' => ['label' => 'X', 'android_package' => 'com.twitter.android', 'ios_scheme' => 'twitter://'],
        'linkedin.com' => ['label' => 'LinkedIn', 'android_package' => 'com.linkedin.android', 'ios_scheme' => 'linkedin://'],
        'm.linkedin.com' => ['label' => 'LinkedIn', 'android_package' => 'com.linkedin.android', 'ios_scheme' => 'linkedin://'],
        'spotify.com' => ['label' => 'Spotify', 'android_package' => 'com.spotify.music', 'ios_scheme' => 'spotify://'],
        'open.spotify.com' => ['label' => 'Spotify', 'android_package' => 'com.spotify.music', 'ios_scheme' => 'spotify://'],
        'wa.me' => ['label' => 'WhatsApp', 'android_package' => 'com.whatsapp', 'ios_scheme' => 'whatsapp://'],
        'api.whatsapp.com' => ['label' => 'WhatsApp', 'android_package' => 'com.whatsapp', 'ios_scheme' => 'whatsapp://'],
        'web.whatsapp.com' => ['label' => 'WhatsApp', 'android_package' => 'com.whatsapp', 'ios_scheme' => 'whatsapp://'],
        'telegram.me' => ['label' => 'Telegram', 'android_package' => 'org.telegram.messenger', 'ios_scheme' => 'tg://'],
        't.me' => ['label' => 'Telegram', 'android_package' => 'org.telegram.messenger', 'ios_scheme' => 'tg://'],
    ];

    return $profiles[$host] ?? null;
}

function buildAndroidIntentUrl(array $parts, string $fallbackUrl, ?string $package = null): string {
    $host = (string)($parts['host'] ?? '');
    $pathQuery = buildUrlPathQueryFragment($parts);
    $tail = ltrim($host . $pathQuery, '/');
    $intentUrl = "intent://" . $tail . "#Intent;scheme=https;";
    if (!empty($package)) {
        $intentUrl .= "package=" . $package . ";";
    }
    $intentUrl .= "S.browser_fallback_url=" . rawurlencode($fallbackUrl) . ";end";
    return $intentUrl;
}

function buildIosAppUrl(array $parts, string $destination, array $profile): string {
    $schemeBase = (string)($profile['ios_scheme'] ?? '');
    if ($schemeBase === '') return $destination;

    // Casos em que o app espera a URL web como parâmetro (ex.: Facebook facewebmodal).
    if (substr($schemeBase, -1) === '=') {
        return $schemeBase . rawurlencode($destination);
    }

    $pathQuery = buildUrlPathQueryFragment($parts);
    if (substr($schemeBase, -3) === '://') {
        return $schemeBase . ltrim($pathQuery, '/');
    }

    return rtrim($schemeBase, '/') . $pathQuery;
}

function resolveDeepLinkDispatch(string $destination, string $ua): ?array {
    $parts = parse_url($destination);
    if (!is_array($parts)) return null;

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null; // Já é esquema nativo/app.
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $host = preg_replace('/^www\./', '', $host);
    $platform = getDevicePlatformFromUa($ua);
    $profile = getDeepLinkAppProfile($host);
    $label = (string)($profile['label'] ?? 'Aplicativo');

    // Android: estratégia genérica para qualquer host HTTPS via intent://
    // com fallback obrigatório para web. Quando houver app conhecido, também
    // adicionamos package para reduzir ambiguidade.
    if ($platform === 'android') {
        $intentUrl = buildAndroidIntentUrl($parts, $destination, (string)($profile['android_package'] ?? ''));
        return [
            'strategy' => 'direct',
            'url' => $intentUrl,
            'label' => $label
        ];
    }

    // iOS: usa scheme nativo quando existir mapeamento conhecido; caso não
    // haja profile confiável, mantém fluxo web padrão.
    if ($platform === 'ios' && is_array($profile)) {
        $appUrl = buildIosAppUrl($parts, $destination, $profile);
        return [
            'strategy' => 'bridge',
            'app_url' => $appUrl,
            'fallback_url' => $destination,
            'label' => $label
        ];
    }

    return null;
}

function isSocialCrawlerUserAgent(string $ua): bool {
    if ($ua === '') return false;
    return (bool)preg_match('/facebookexternalhit|facebot|whatsapp|twitterbot|x\.com|linkedinbot|slackbot|discordbot|telegrambot/i', $ua);
}

function renderSocialPreviewPage(
    string $title,
    string $description,
    string $targetUrl,
    string $canonicalUrl,
    ?string $imageUrl = null,
    ?string $faviconUrl = null,
    bool $autoRedirect = false
): void {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $safeTarget = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
    $safeCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
    $safeImage = $imageUrl ? htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') : '';
    $safeFavicon = $faviconUrl ? htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') : '';

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $safeTitle . '</title>';
    echo '<meta name="description" content="' . $safeDescription . '">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:title" content="' . $safeTitle . '">';
    echo '<meta property="og:description" content="' . $safeDescription . '">';
    echo '<meta property="og:url" content="' . $safeCanonical . '">';
    echo '<meta property="og:site_name" content="Encurtou.pro">';
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<meta name="twitter:title" content="' . $safeTitle . '">';
    echo '<meta name="twitter:description" content="' . $safeDescription . '">';
    if ($safeImage !== '') {
        echo '<meta property="og:image" content="' . $safeImage . '">';
        echo '<meta name="twitter:image" content="' . $safeImage . '">';
    }
    if ($safeFavicon !== '') {
        echo '<link rel="icon" href="' . $safeFavicon . '">';
        echo '<link rel="shortcut icon" href="' . $safeFavicon . '">';
        echo '<link rel="apple-touch-icon" href="' . $safeFavicon . '">';
    }
    if ($autoRedirect) {
        echo '<meta http-equiv="refresh" content="0;url=' . $safeTarget . '">';
    }
    echo '<style>:root{--bg:#f3f7fe;--surface:#ffffff;--border:#cbd5e1;--text:#0f172a;--muted:#475569;--link:#0078E8}';
    echo 'html[data-theme="dark"]{--bg:#0b1220;--surface:#111a2e;--border:#1f2a44;--text:#e2e8f0;--muted:#94a3b8;--link:#1A8CFF}';
    echo 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}';
    echo '.card{max-width:560px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px}';
    echo 'h1{font-size:20px;margin:0 0 10px}p{margin:0 0 14px;color:var(--muted);line-height:1.45}a{color:var(--link)}</style>';
    echo '</head><body><div class="card"><h1>' . $safeTitle . '</h1><p>' . $safeDescription . '</p><a href="' . $safeTarget . '">Continuar</a></div>';
    if ($autoRedirect) {
        $targetJson = json_encode($targetUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '<script>(()=>{try{const k="enc_theme_preference";const s=localStorage.getItem(k);document.documentElement.setAttribute("data-theme",s==="light"||s==="dark"?s:"light");}catch(e){document.documentElement.setAttribute("data-theme","light");}})();const __next=' . $targetJson . ';setTimeout(()=>{window.location.replace(__next);},40);</script>';
    } else {
        echo '<script>(()=>{try{const k="enc_theme_preference";const s=localStorage.getItem(k);document.documentElement.setAttribute("data-theme",s==="light"||s==="dark"?s:"light");}catch(e){document.documentElement.setAttribute("data-theme","light");}})();</script>';
    }
    echo '</body></html>';
}

function renderDeepLinkBridge(string $appUrl, string $fallbackUrl, string $label = 'aplicativo', ?string $faviconUrl = null): void {
    $appJson = json_encode($appUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $fallbackJson = json_encode($fallbackUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeFavicon = $faviconUrl ? htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') : '';

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Abrindo aplicativo...</title>';
    if ($safeFavicon !== '') {
        echo '<link rel="icon" href="' . $safeFavicon . '">';
        echo '<link rel="shortcut icon" href="' . $safeFavicon . '">';
        echo '<link rel="apple-touch-icon" href="' . $safeFavicon . '">';
    }
    echo '<style>:root{--bg:#f3f7fe;--surface:#ffffff;--border:#cbd5e1;--text:#0f172a;--muted:#475569;--link:#0078E8;--ghost:#94a3b8}';
    echo 'html[data-theme="dark"]{--bg:#0b1220;--surface:#111a2e;--border:#1f2a44;--text:#e2e8f0;--muted:#94a3b8;--link:#1A8CFF;--ghost:#334155}';
    echo 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}';
    echo '.card{max-width:520px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px}';
    echo 'h1{font-size:20px;margin:0 0 10px}p{margin:0 0 14px;color:var(--muted);line-height:1.45}a{display:inline-block;margin-right:8px;margin-top:8px;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:600}';
    echo '.primary{background:var(--link);color:#041018}.ghost{border:1px solid var(--ghost);color:var(--text)}</style></head><body>';
    echo '<div class="card"><h1>Abrindo ' . $safeLabel . '...</h1><p>Se o app não abrir automaticamente, use os botões abaixo.</p>';
    echo '<a id="openApp" class="primary" href="#">Abrir app</a><a id="openWeb" class="ghost" href="#">Continuar na web</a></div>';
    echo '<script>(()=>{try{const k="enc_theme_preference";const s=localStorage.getItem(k);document.documentElement.setAttribute("data-theme",s==="light"||s==="dark"?s:"light");}catch(e){document.documentElement.setAttribute("data-theme","light");}})();';
    echo 'const appUrl=' . $appJson . ';';
    echo 'const fallbackUrl=' . $fallbackJson . ';';
    echo 'let appOpened=false;';
    echo 'const fallbackTimer=setTimeout(()=>{if(!appOpened){window.location.replace(fallbackUrl);}},1700);';
    echo 'document.addEventListener("visibilitychange",()=>{if(document.hidden){appOpened=true;clearTimeout(fallbackTimer);}});';
    echo 'document.getElementById("openApp").href=appUrl;';
    echo 'document.getElementById("openWeb").href=fallbackUrl;';
    echo 'setTimeout(()=>{window.location.href=appUrl;},60);';
    echo '</script></body></html>';
}

function parseRetargetingPixelsConfig($rawValue): array {
    $default = [
        'facebook' => [],
        'google_ads' => [],
        'linkedin' => [],
        'twitter' => [],
        'pinterest' => [],
        'tiktok' => []
    ];

    if (is_string($rawValue) && trim($rawValue) !== '') {
        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            $rawValue = $decoded;
        }
    }

    if (!is_array($rawValue)) {
        return $default;
    }

    foreach ($default as $platform => $_) {
        $incoming = $rawValue[$platform] ?? [];
        if (!is_array($incoming)) {
            $incoming = [$incoming];
        }
        $clean = [];
        foreach ($incoming as $item) {
            $value = trim((string)$item);
            if ($value === '') {
                continue;
            }
            $value = mb_substr($value, 0, 120, 'UTF-8');
            if (!preg_match('/^[A-Za-z0-9._:\-\/]+$/', $value)) {
                continue;
            }
            $clean[] = $value;
            if (count($clean) >= 10) {
                break;
            }
        }
        $default[$platform] = array_values(array_unique($clean));
    }

    return $default;
}

function hasRetargetingPixelsConfig(array $config): bool {
    foreach ($config as $items) {
        if (is_array($items) && count($items) > 0) {
            return true;
        }
    }
    return false;
}

function renderRetargetingBridgePage(string $targetUrl, array $retargetingConfig): void {
    $safeTarget = preg_replace('/[\r\n]+/', '', $targetUrl);
    if ($safeTarget === '') {
        $safeTarget = '/';
    }

    $targetJson = json_encode($safeTarget, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $configJson = json_encode($retargetingConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>Redirecionando...</title>';
    echo '<style>body{margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0b1220;color:#e2e8f0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}';
    echo '.card{max-width:560px;padding:20px;border-radius:14px;background:#111a2e;border:1px solid #1f2a44}';
    echo 'h1{font-size:20px;margin:0 0 10px}p{margin:0;color:#94a3b8;line-height:1.45}</style>';
    echo '</head><body><div class="card"><h1>Redirecionando...</h1><p>Processando rastreamento de retargeting e enviando você para o destino.</p></div>';
    echo '<script>';
    echo 'const __targetUrl = ' . $targetJson . ';';
    echo 'const __pixels = ' . $configJson . ';';
    echo 'const __timeoutMs = 260;';
    echo 'function __loadScript(src, onload){';
    echo '  var s=document.createElement("script"); s.async=true; s.src=src;';
    echo '  if(onload){s.onload=onload;}';
    echo '  var first=document.getElementsByTagName("script")[0] || document.head.firstChild;';
    echo '  if(first && first.parentNode){first.parentNode.insertBefore(s, first);} else {document.head.appendChild(s);}';
    echo '}';
    echo 'function __asArray(v){ return Array.isArray(v) ? v : []; }';
    echo 'function __safeIds(v){ return __asArray(v).filter(Boolean).map(String).slice(0,10); }';
    echo 'try {';
    echo '  const fbIds=__safeIds(__pixels.facebook);';
    echo '  if(fbIds.length){';
    echo '    !(function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};';
    echo '    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;';
    echo '    s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)})(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");';
    echo '    fbIds.forEach(function(id){ try{ fbq("init", id); fbq("track", "PageView"); }catch(e){} });';
    echo '  }';
    echo '} catch(e) {}';
    echo 'try {';
    echo '  const googleIds=__safeIds(__pixels.google_ads);';
    echo '  if(googleIds.length){';
    echo '    window.dataLayer=window.dataLayer||[];';
    echo '    function gtag(){dataLayer.push(arguments);}';
    echo '    window.gtag=window.gtag||gtag;';
    echo '    gtag("js", new Date());';
    echo '    googleIds.forEach(function(id){ try{ gtag("config", id); }catch(e){} });';
    echo '    __loadScript("https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(googleIds[0]));';
    echo '  }';
    echo '} catch(e) {}';
    echo 'try {';
    echo '  const linkedinIds=__safeIds(__pixels.linkedin);';
    echo '  if(linkedinIds.length){';
    echo '    window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];';
    echo '    linkedinIds.forEach(function(id){ if(window._linkedin_data_partner_ids.indexOf(id)===-1){ window._linkedin_data_partner_ids.push(id); } });';
    echo '    __loadScript("https://snap.licdn.com/li.lms-analytics/insight.min.js");';
    echo '  }';
    echo '} catch(e) {}';
    echo 'try {';
    echo '  const twIds=__safeIds(__pixels.twitter);';
    echo '  if(twIds.length){';
    echo '    !(function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);};';
    echo '    s.version="1.1";s.queue=[];u=t.createElement(n);u.async=!0;u.src="//static.ads-twitter.com/uwt.js";';
    echo '    a=t.getElementsByTagName(n)[0];a.parentNode.insertBefore(u,a);})(window,document,"script");';
    echo '    twIds.forEach(function(id){ try{ twq("init", id); twq("track", "PageView"); }catch(e){} });';
    echo '  }';
    echo '} catch(e) {}';
    echo 'try {';
    echo '  const pinIds=__safeIds(__pixels.pinterest);';
    echo '  if(pinIds.length){';
    echo '    !(function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments));};';
    echo '    var n=window.pintrk;n.queue=[];n.version="3.0";';
    echo '    var t=document.createElement("script");t.async=!0;t.src=e;';
    echo '    var r=document.getElementsByTagName("script")[0];r.parentNode.insertBefore(t,r);}})("https://s.pinimg.com/ct/core.js");';
    echo '    pinIds.forEach(function(id){ try{ pintrk("load", id); pintrk("page"); }catch(e){} });';
    echo '  }';
    echo '} catch(e) {}';
    echo 'try {';
    echo '  const ttIds=__safeIds(__pixels.tiktok);';
    echo '  if(ttIds.length){';
    echo '    !(function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];';
    echo '    ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];';
    echo '    ttq.setAndDefer=function(obj,method){obj[method]=function(){obj.push([method].concat(Array.prototype.slice.call(arguments,0)));};};';
    echo '    for(var i=0;i<ttq.methods.length;i++){ttq.setAndDefer(ttq,ttq.methods[i]);}';
    echo '    ttq.load=function(id){var s=d.createElement("script");s.async=!0;s.src="https://analytics.tiktok.com/i18n/pixel/events.js?sdkid="+id+"&lib="+t;';
    echo '    var f=d.getElementsByTagName("script")[0];f.parentNode.insertBefore(s,f);};';
    echo '    })(window,document,"ttq");';
    echo '    ttIds.forEach(function(id){ try{ ttq.load(id); ttq.page(); }catch(e){} });';
    echo '  }';
    echo '} catch(e) {}';
    echo 'setTimeout(function(){ window.location.replace(__targetUrl); }, __timeoutMs);';
    echo '</script></body></html>';
}

function renderPreciseLocationBridge(
    string $destination,
    string $clickUid,
    int $linkId,
    string $httpHost,
    bool $isHttps,
    ?string $faviconUrl = null
): void {
    $signed = preciseGeoBuildSignedPayload($clickUid, $linkId);
    $exp = (int)$signed['exp'];
    $sig = $signed['sig'];
    $scheme = $isHttps ? 'https' : 'http';
    $postUrl = $scheme . '://' . $httpHost . '/precise_geo_click.php';

    $safeDest = htmlspecialchars($destination, ENT_QUOTES, 'UTF-8');
    $safeFavicon = $faviconUrl ? htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') : '';
    $destJson = json_encode($destination, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $postJson = json_encode($postUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $uidJson = json_encode($clickUid, JSON_UNESCAPED_UNICODE);
    $sigJson = json_encode($sig, JSON_UNESCAPED_UNICODE);

    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>Localização opcional</title>';
    if ($safeFavicon !== '') {
        echo '<link rel="icon" href="' . $safeFavicon . '">';
    }
    echo '<style>:root{--bg:#f3f7fe;--surface:#ffffff;--border:#cbd5e1;--text:#0f172a;--muted:#475569;--link:#0078E8}';
    echo 'html[data-theme="dark"]{--bg:#0b1220;--surface:#111a2e;--border:#1f2a44;--text:#e2e8f0;--muted:#94a3b8;--link:#1A8CFF}';
    echo 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}';
    echo '.card{max-width:520px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px}';
    echo 'h1{font-size:18px;margin:0 0 10px}p{margin:0 0 12px;color:var(--muted);line-height:1.45;font-size:0.9rem}';
    echo 'a{color:var(--link)}.actions{margin-top:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}</style>';
    echo '</head><body><div class="card"><h1>Permitir localização?</h1>';
    echo '<p>O criador deste link solicitou uma localização mais precisa para estatísticas. Isso é <strong>opcional</strong>. Se você negar ou fechar, será redirecionado normalmente.</p>';
    echo '<p id="status" style="font-size:0.85rem;">Aguardando sua escolha no navegador…</p>';
    echo '<div class="actions"><a id="skipLink" href="' . $safeDest . '">Pular e continuar</a></div></div>';
    echo '<script>(()=>{try{const k="enc_theme_preference";const s=localStorage.getItem(k);document.documentElement.setAttribute("data-theme",s==="light"||s==="dark"?s:"light");}catch(e){document.documentElement.setAttribute("data-theme","light");}})();';
    echo 'const __dest=' . $destJson . ';const __post=' . $postJson . ';const __uid=' . $uidJson . ';const __lid=' . (int)$linkId . ';const __exp=' . $exp . ';const __sig=' . $sigJson . ';';
    echo 'const st=document.getElementById("status");const skip=document.getElementById("skipLink");';
    echo 'function go(){try{window.location.replace(__dest);}catch(e){window.location.href=__dest;}}';
    echo 'skip.addEventListener("click",function(e){e.preventDefault();go();});';
    echo 'const tmax=setTimeout(function(){st.textContent="Redirecionando…";go();},12000);';
    echo 'if(!navigator.geolocation){clearTimeout(tmax);st.textContent="Geolocalização não disponível neste dispositivo.";setTimeout(go,400);}';
    echo 'else{navigator.geolocation.getCurrentPosition(function(pos){clearTimeout(tmax);';
    echo 'st.textContent="Enviando localização aproximada…";';
    echo 'const body=JSON.stringify({click_uid:__uid,link_id:__lid,exp:__exp,sig:__sig,lat:pos.coords.latitude,lng:pos.coords.longitude,accuracy:pos.coords.accuracy});';
    echo 'fetch(__post,{method:"POST",headers:{"Content-Type":"application/json"},body:body,credentials:"same-origin"}).catch(function(){}).finally(function(){go();});';
    echo '},function(){clearTimeout(tmax);st.textContent="Permissão negada. Redirecionando…";setTimeout(go,300);},{enableHighAccuracy:false,timeout:8000,maximumAge:600000});}';
    echo '</script></body></html>';
}

// 1. Identificar o Host e capturar Slug/Subdomínio injetados (Via Cloudflare Worker)
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host); // Remover porta se existir

$baseDomain = subdomainGetBaseHost();

// Captura a Slug (Prioridade para o GET, que o Cloudflare Worker usará)
$slug = isset($_GET['slug']) ? strtolower(trim($_GET['slug'], '/')) : '';

// Captura e persiste código de afiliado para atribuição no cadastro.
$affiliateCodeFromRequest = affiliateGetCodeFromRequest();
if ($affiliateCodeFromRequest !== '') {
    affiliateRememberCodeCookie($affiliateCodeFromRequest, 30);
}

// Captura o Subdomínio (Prioridade para o GET injetado pelo Worker)
$sub = (isset($_GET['subdomain']) && $_GET['subdomain'] !== '')
    ? subdomainSanitize((string)$_GET['subdomain'])
    : 'ROOT';
if ($sub === '' || $sub === 'root') {
    $sub = 'ROOT';
}

// Fallback: Se o subdomínio não veio via GET, tenta extrair do Host (Acesso direto)
if ($sub === 'ROOT' && $host !== 'localhost' && $host !== '127.0.0.1') {
    $domainSuffix = '.' . $baseDomain;
    if (substr($host, -strlen($domainSuffix)) === $domainSuffix) {
        $prefix = substr($host, 0, -strlen($domainSuffix));
        $parts = explode('.', $prefix);
        if (isset($parts[0]) && $parts[0] === 'www') {
            array_shift($parts);
        }
        $sub = (!empty($parts) && $parts[0] !== '') ? $parts[0] : 'ROOT';
    }
}

// 3. Se não houver slug: apex mostra dashboard; subdomínio de usuário retorna 404
if (empty($slug)) {
    if ($sub !== 'ROOT') {
        http_response_code(404);
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Ops! Link não encontrado.</h2>");
    }
    include('index.html');
    exit;
}

// 3.1 Rota pública de Bio Page: /bio/{slug}
if (preg_match('#^bio/([a-z0-9-]+)$#', $slug, $matches)) {
    $_GET['slug'] = $matches[1];
    require 'bio.php';
    exit;
}

try {
    // 4. Buscar o link no Banco de Dados
    // Combinamos o subdomínio e o path (slug)
    $stmt = $pdo->prepare("SELECT * FROM links WHERE subdomain = ? AND path = ?");
    $stmt->execute([$sub, $slug]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        // TRAVA: Verifica se o link sofreu downgrade / bloqueio premium
        if (isset($link['is_premium_locked']) && (int)$link['is_premium_locked'] === 1) {
            header("Location: /?erro=link_bloqueado", true, 302);
            exit;
        }

        // Lazy expiry: ban temporário expirado restaura links pausados pelo admin
        if ($link['status'] === 'paused'
            && userBanColumnExists($pdo, 'links', 'admin_ban_paused')
            && (int)($link['admin_ban_paused'] ?? 0) === 1) {
            $banUserId = (int)($link['user_id'] ?? 0);
            if ($banUserId > 0 && resolveExpiredUserBan($pdo, $banUserId)) {
                $stmt->execute([$sub, $slug]);
                $link = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        // TRAVA: Verifica se o link está pausado
        if ($link['status'] === 'paused') {
            $reason = 'paused';
            if (userBanColumnExists($pdo, 'links', 'security_paused') && (int)($link['security_paused'] ?? 0) === 1) {
                $reason = 'security';
            }
            header('Location: /blocked.php?reason=' . urlencode($reason), true, 302);
            exit;
        }

        // TRAVA: Regra de Agendamento (Início futuro)
        if (!empty($link['start_date']) && strtotime($link['start_date']) > time()) {
            header("Location: /?erro=link_agendado", true, 302);
            exit;
        }

        // TRAVA: Regra de Expiração (Data) ou Limite de Cliques
        $isExpiredByDate = (!empty($link['end_date']) && strtotime($link['end_date']) < time());
        $isExpiredByClicks = (!empty($link['expire_clicks']) && $link['clicks'] >= $link['expire_clicks']);
        
        if ($isExpiredByDate || $isExpiredByClicks) {
            if (!empty($link['fallback_url'])) {
                header("Location: " . $link['fallback_url'], true, 302);
                exit;
            } else {
                header("Location: /?erro=link_expirado", true, 302);
                exit;
            }
        }

        $linkId = $link['id'];
        $linkUserId = (int)($link['user_id'] ?? 0);
        $visitorIp = getRealIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'Acesso Direto';
        
        // Captura de Localização (Headers Cloudflare ou API Fallback)
        // Prioridade para headers injetados pelo Cloudflare Worker para garantir precisão no Edge
        $country = $_SERVER["HTTP_X_JS_IPCOUNTRY"] ?? $_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'Desconhecido';
        
        // Tenta capturar a Região/Estado por diversas variações de header
        $region = $_SERVER["HTTP_X_JS_IPREGION"] ?? $_SERVER["HTTP_CF_REGION"] ?? $_SERVER["HTTP_CF_IPREGION"] ?? 'Desconhecido';
        if ($region === 'Desconhecido' || $region === '') {
            $region = $_SERVER["HTTP_CF_REGION_CODE"] ?? $region;
        }

        // Tenta capturar a Cidade
        $city = $_SERVER["HTTP_X_JS_IPCITY"] ?? $_SERVER["HTTP_CF_IPCITY"] ?? $_SERVER["HTTP_CF_CITY"] ?? 'Desconhecido';

        // 5. Lógica de Redirecionamento (Gatilho -> A/B -> Dispositivo)
        $destination = $link['original_url'];
        $deepLinkingEnabled = (int)($link['deep_linking_enabled'] ?? 0) === 1;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $variantId = null;
        $dwellMonitorEnabled = (int)($link['monitor_dwell_enabled'] ?? 0) === 1;
        $customSocialTitle = trim((string)($link['social_title'] ?? ''));
        $customSocialDescription = trim((string)($link['social_description'] ?? ''));
        $customSocialImageUrl = trim((string)($link['social_image_url'] ?? ''));
        $customFaviconUrl = trim((string)($link['favicon_url'] ?? ''));
        $retargetingPixels = parseRetargetingPixelsConfig($link['retargeting_pixels_json'] ?? null);
        $hasRetargetingPixels = hasRetargetingPixelsConfig($retargetingPixels);
        $requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $canonicalUrl = $requestScheme . '://' . $host . '/' . ltrim($slug, '/');
        $isSocialCrawler = isSocialCrawlerUserAgent($ua);

        if (!empty($link['trigger_clicks']) && !empty($link['trigger_url']) && $link['clicks'] >= $link['trigger_clicks']) {
            $destination = $link['trigger_url'];
        } elseif (!empty($link['is_ab_test'])) {
            $vStmt = $pdo->prepare("SELECT id, url, weight FROM link_variants WHERE link_id = ? AND status = 'active' ORDER BY id ASC");
            $vStmt->execute([$linkId]);
            $activeVariants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($activeVariants) > 0) {
                $rand = mt_rand(1, 100);
                $cumulative = 0;
                $selectedVariant = null;
                foreach ($activeVariants as $variant) {
                    $cumulative += (int)$variant['weight'];
                    if ($rand <= $cumulative) {
                        $selectedVariant = $variant;
                        break;
                    }
                }
                if (!$selectedVariant) { $selectedVariant = end($activeVariants); }
                $destination = $selectedVariant['url'];
                $variantId = $selectedVariant['id'];
            }
        } else {
            // Safely check for columns (in case they don't exist yet in the DB)
            if ($deepLinkingEnabled) {
                if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
                    if (!empty($link['url_ios'])) $destination = $link['url_ios'];
                } elseif (preg_match('/Android/i', $ua)) {
                    if (!empty($link['url_android'])) $destination = $link['url_android'];
                } elseif (preg_match('/Windows/i', $ua)) {
                    if (!empty($link['url_windows'])) $destination = $link['url_windows'];
                }
            }
        }

        $passThroughParams = linkPassThroughParamsFromSuperglobalGet($_GET);
        $passThroughQuery = linkPassThroughHttpQueryFromParams($passThroughParams);
        $incomingQueryForLog = $passThroughQuery !== '' ? $passThroughQuery : null;
        if ($passThroughQuery !== '' && strpos($destination, 'BEGIN:VCARD') === false) {
            $destination = linkMergePassThroughQueryOntoUrl($destination, $passThroughQuery);
        }

        // 6. Registrar Estatísticas
        $update = $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
        $update->execute([$linkId]);

        $clickUid = bin2hex(random_bytes(16));
        $source = (isset($_GET['src']) && $_GET['src'] === 'qr') ? 'qr' : 'digital';

        $analyticsPolicy = getAnalyticsPolicyForUser($pdo, $linkUserId);
        $canRegisterClick = !empty($analyticsPolicy['enabled']);
        if ($canRegisterClick) {
            $maxClicksMonthly = (int)($analyticsPolicy['max_clicks_monthly'] ?? 0);
            if ($maxClicksMonthly <= 0) {
                $canRegisterClick = false;
            } elseif ($maxClicksMonthly < 999999999) {
                $cycle = EntitlementService::getUsageCycleWindow($linkUserId);
                $monthCountStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM clicks_log cl
                    INNER JOIN links l_owner ON l_owner.id = cl.link_id
                    WHERE l_owner.user_id = ?
                      AND cl.clicked_at >= ?
                      AND cl.clicked_at < ?
                ");
                $monthCountStmt->execute([$linkUserId, $cycle['start'], $cycle['end']]);
                $monthCount = (int)$monthCountStmt->fetchColumn();
                if ($monthCount >= $maxClicksMonthly) {
                    $canRegisterClick = false;
                }
            }
        }

        if ($canRegisterClick) {
            $clicksLogHasIncoming = pdoColumnExists($pdo, 'clicks_log', 'incoming_query');
            try {
                if ($clicksLogHasIncoming && $incomingQueryForLog !== null) {
                    $log = $pdo->prepare("INSERT INTO clicks_log (click_uid, link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, incoming_query, source, variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $log->execute([$clickUid, $linkId, $visitorIp, $country, $region, $city, $userAgent, $referer, $incomingQueryForLog, $source, $variantId]);
                } else {
                    $log = $pdo->prepare("INSERT INTO clicks_log (click_uid, link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, source, variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $log->execute([$clickUid, $linkId, $visitorIp, $country, $region, $city, $userAgent, $referer, $source, $variantId]);
                }
            } catch(PDOException $e) {
                // Em caso do usuário ou server não terem rodado a migration do click_uid ainda
                try {
                    if ($clicksLogHasIncoming && $incomingQueryForLog !== null) {
                        $log = $pdo->prepare("INSERT INTO clicks_log (link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, incoming_query, source, variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        @$log->execute([$linkId, $visitorIp, $country, $region, $city, $userAgent, $referer, $incomingQueryForLog, $source, $variantId]);
                    } else {
                        $log = $pdo->prepare("INSERT INTO clicks_log (link_id, visitor_ip, visitor_country, visitor_region, visitor_city, user_agent, referer, source, variant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        @$log->execute([$linkId, $visitorIp, $country, $region, $city, $userAgent, $referer, $source, $variantId]);
                    }
                } catch(PDOException $e2) { }
            }

            // Retenção de analytics é executada via job agendado para não degradar o redirecionamento.
        }

        // 6.5. Injetar Tracking ID na URL destino se tracking ativado
        if (!empty($link['tracking_enabled']) || $dwellMonitorEnabled) {
            $parsed = parse_url($destination);
            if (isset($parsed['scheme']) && isset($parsed['host'])) {
                $query = isset($parsed['query']) ? $parsed['query'] . '&ep_cid=' . $clickUid : 'ep_cid=' . $clickUid;
                if ($dwellMonitorEnabled) {
                    $query .= '&ep_dm=1';
                }
                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                $path = isset($parsed['path']) ? $parsed['path'] : '';
                $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
                
                $destination = $parsed['scheme'] . '://' . $parsed['host'] . $port . $path . '?' . $query . $fragment;
            }
        }

        $deepLinkDispatch = null;
        if ($deepLinkingEnabled) {
            $deepLinkDispatch = resolveDeepLinkDispatch($destination, $ua);
            if (is_array($deepLinkDispatch) && ($deepLinkDispatch['strategy'] ?? '') === 'direct') {
                $destination = (string)($deepLinkDispatch['url'] ?? $destination);
            }
        }

        $requestPreciseLocation = false;
        if (pdoColumnExists($pdo, 'links', 'request_precise_location')) {
            $requestPreciseLocation = (int)($link['request_precise_location'] ?? 0) === 1;
        }
        $clicksLogHasClickUidCol = pdoColumnExists($pdo, 'clicks_log', 'click_uid');
        $skipPreciseBridge = !$requestPreciseLocation
            || !$canRegisterClick
            || !$clicksLogHasClickUidCol
            || $isSocialCrawler
            || $hasRetargetingPixels
            || (is_array($deepLinkDispatch ?? null) && ($deepLinkDispatch['strategy'] ?? '') === 'bridge')
            || strpos($destination, 'BEGIN:VCARD') !== false;

        if (!$skipPreciseBridge) {
            $destination = preg_replace('/[\r\n]+/', '', (string)$destination);
            if ($destination !== '') {
                $isHttpsBridge = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                renderPreciseLocationBridge(
                    $destination,
                    $clickUid,
                    $linkId,
                    $host,
                    $isHttpsBridge,
                    $customFaviconUrl !== '' ? $customFaviconUrl : null
                );
                exit;
            }
        }

        // MODO DEBUG: somente local ou admin autenticado
        $isLocalDebug = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
            || strpos(($_SERVER['HTTP_HOST'] ?? ''), 'localhost') !== false;
        $isAdminDebug = isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;
        if (isset($_GET['debug']) && ($isLocalDebug || $isAdminDebug)) {
            echo "<h3>Debug de Redirecionamento</h3>";
            echo "<b>Host:</b> " . htmlspecialchars($host) . "<br>";
            echo "<b>Subdomínio Detectado:</b> " . htmlspecialchars($sub) . "<br>";
            echo "<b>Slug:</b> " . htmlspecialchars($slug) . "<br><br>";
            echo "<b>User Agent:</b> " . htmlspecialchars($ua) . "<br>";
            echo "<b>Destino Final:</b> " . htmlspecialchars($destination) . "<br>";
            if (is_array($deepLinkDispatch ?? null)) {
                echo "<b>Deep Linking Strategy:</b> " . htmlspecialchars((string)($deepLinkDispatch['strategy'] ?? '')) . "<br>";
            }
            exit;
        }

        // 7. Redirecionamento Final ou Entrega de Conteúdo (vCard)
        if (preg_match('#^https?://#i', (string)$destination)) {
            $redirectBlock = AbusePolicyService::checkRedirectDestination($pdo, (int)$linkId, (string)$destination, true);
            if ($redirectBlock !== null) {
                header('Location: /blocked.php?reason=security', true, 302);
                exit;
            }
        }

        if (strpos($destination, 'BEGIN:VCARD') !== false) {
            header('Content-Type: text/vcard; charset=utf-8');
            header('Content-Disposition: inline; filename="contato.vcf"');
            echo $destination;
            exit;
        }

        // Defesa contra CRLF-injection em header Location (já filtrado em qr_helpers
        // e pelo validate_url; esta é uma rede de segurança adicional).
        $destination = preg_replace('/[\r\n]+/', '', (string)$destination);
        if ($destination === '') {
            http_response_code(500);
            die("Destino inválido.");
        }

        if (is_array($deepLinkDispatch ?? null) && ($deepLinkDispatch['strategy'] ?? '') === 'bridge') {
            $appUrl = preg_replace('/[\r\n]+/', '', (string)($deepLinkDispatch['app_url'] ?? ''));
            $fallbackUrl = preg_replace('/[\r\n]+/', '', (string)($deepLinkDispatch['fallback_url'] ?? $destination));
            if ($appUrl !== '' && $fallbackUrl !== '') {
                renderDeepLinkBridge($appUrl, $fallbackUrl, (string)($deepLinkDispatch['label'] ?? 'aplicativo'), $customFaviconUrl !== '' ? $customFaviconUrl : null);
                exit;
            }
        }

        if (
            $hasRetargetingPixels
            && !$isSocialCrawler
            && !(is_array($deepLinkDispatch ?? null) && ($deepLinkDispatch['strategy'] ?? '') === 'bridge')
        ) {
            renderRetargetingBridgePage($destination, $retargetingPixels);
            exit;
        }

        $previewTitle = $customSocialTitle !== '' ? $customSocialTitle : trim((string)($link['title'] ?? 'Link Encurtou.pro'));
        if ($previewTitle === '') {
            $previewTitle = 'Link Encurtou.pro';
        }
        $previewDescription = $customSocialDescription !== '' ? $customSocialDescription : 'Abrir conteúdo do link encurtado.';

        if ($isSocialCrawler) {
            renderSocialPreviewPage(
                $previewTitle,
                $previewDescription,
                $destination,
                $canonicalUrl,
                $customSocialImageUrl !== '' ? $customSocialImageUrl : null,
                $customFaviconUrl !== '' ? $customFaviconUrl : null,
                false
            );
            exit;
        }

        // Para usuários normais, redireciona direto sem página intermediária de preview.
        // O preview social continua ativo apenas para crawlers (bloco acima).

        header('Referrer-Policy: no-referrer');
        header("Location: " . $destination, true, 302);
        exit;
    } else {
        // Caso o link não exista no banco
        http_response_code(404);
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Ops! Link não encontrado.</h2>");
    }

} catch (PDOException $e) {
    // Logar erro e exibir mensagem genérica
    error_log("Erro no Redirect: " . $e->getMessage());
    die("Houve um erro ao processar o redirecionamento.");
}
?>