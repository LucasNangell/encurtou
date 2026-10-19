<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
require_once 'db.php';
require_once 'qr_helpers.php';
require_once 'edge_cache_helpers.php';
require_once 'team_permissions.php';
require_once 'subdomain_helpers.php';
require_once 'auth_guard.php';
require_once 'abuse_policy_helpers.php';
require_once 'AbusePolicyService.php';
require_once 'rate_limit_helpers.php';

// CSRF (enforcement controlado por env CSRF_ENFORCE=1)
requireCsrfToken();

function normalize_page_title($rawTitle) {
    if (!is_string($rawTitle)) return '';
    $title = trim($rawTitle);
    if ($title === '') return '';
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = preg_replace('/\s+/u', ' ', $title);
    return trim($title);
}

function get_page_title($url) {
    if (!$url) return 'Título não disponível';
    if (!isSafePublicHttpUrl((string)$url)) {
        return 'Título não disponível';
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ?: 'Título não disponível';
    }

    // Tenta capturar a tag <title>
    if (preg_match("/<title[^>]*>(.*?)<\/title>/is", $html, $matches)) {
        $title = mb_convert_encoding($matches[1], 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
        $title = normalize_page_title($title);
        if ($title !== '') {
            return $title;
        }
    }

    // Fallback para metatags comuns de SEO/social
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/is', $html, $matchesOg)
        || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']og:title["\']/is', $html, $matchesOg)) {
        $ogTitle = normalize_page_title(mb_convert_encoding($matchesOg[1], 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252'));
        if ($ogTitle !== '') {
            return $ogTitle;
        }
    }
    if (preg_match('/<meta[^>]+name=["\']twitter:title["\'][^>]+content=["\'](.*?)["\']/is', $html, $matchesTw)
        || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']twitter:title["\']/is', $html, $matchesTw)) {
        $twTitle = normalize_page_title(mb_convert_encoding($matchesTw[1], 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252'));
        if ($twTitle !== '') {
            return $twTitle;
        }
    }

    $host = parse_url($url, PHP_URL_HOST);
    return $host ?: 'Título não disponível';
}

function generateAutomaticSlug(PDO $pdo, string $subdomain, int $length = 6): string {
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $maxIndex = strlen($alphabet) - 1;
    $attempts = 0;
    $length = max(4, min(10, $length));

    while ($attempts < 30) {
        $attempts++;
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $alphabet[random_int(0, $maxIndex)];
        }

        $stmt = $pdo->prepare("SELECT id FROM links WHERE subdomain = ? AND path = ? LIMIT 1");
        $stmt->execute([$subdomain, $slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
    }

    return strtolower(bin2hex(random_bytes(4)));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_slug') {
    $path = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_GET['slug'] ?? '')));
    $subValue = subdomainNormalizeForLink((string)($_GET['subdomain'] ?? ''));

    if (!$path) {
        echo json_encode(['available' => true]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM links WHERE subdomain = ? AND path = ?");
    $stmt->execute([$subValue, $path]);
    $exists = $stmt->fetch();

    echo json_encode(['available' => !$exists]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = requireAuthenticatedUserNotBanned($pdo, false, true);

    if (!checkRateLimit($pdo, 'create_link', 30, 60)) {
        authGuardJsonExit(['success' => false, 'message' => 'Muitas tentativas de criação. Aguarde e tente novamente.'], 429);
    }
    if (!checkUserRateLimit($pdo, $userId, 'create_link', 60, 60)) {
        authGuardJsonExit(['success' => false, 'message' => 'Limite de criação por hora atingido.'], 429);
    }

    $teamContext = teamResolveMembershipContext($pdo, (int)$userId);
    if (!teamCanEditContent((string)($teamContext['profile'] ?? 'editor'))) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode([
            'success' => false,
            'error' => 'permission_denied',
            'message' => 'Seu perfil na equipe é Visualizador e não permite criar ou editar links.'
        ]);
        exit;
    }
    require_once 'billing/EntitlementService.php';

    $url = $_POST['url'] ?? '';
    $subValue = subdomainNormalizeForLink((string)($_POST['subdomain'] ?? ''));
    $path = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['path'] ?? '')));

    $type = (isset($_POST['type']) && $_POST['type'] === 'qr') ? 'qr' : 'link';

    // Destinos dinâmicos de QR Code (tabs): o backend é a fonte da verdade.
    // A UI envia `destination_type` + `destination_metadata` (JSON) e o
    // servidor re-calcula o `original_url` a partir dos metadados
    // sanitizados, ignorando qualquer URL enviada pelo cliente.
    $destinationType = null;
    $destinationMetadata = null;
    if ($type === 'qr' && isset($_POST['destination_type'])) {
        $errMsg = null;
        $processed = qr_process_destination(
            $_POST['destination_type'],
            $_POST['destination_metadata'] ?? null,
            $errMsg
        );
        if (!$processed) {
            echo json_encode(['success' => false, 'message' => $errMsg ?: 'Dados do QR Code inválidos.']);
            exit;
        }
        $destinationType = $processed['type'];
        $destinationMetadata = json_encode($processed['metadata'], JSON_UNESCAPED_UNICODE);
        $url = $processed['url'];
    }

    $isAbTest = (isset($_POST['is_ab_test']) && $_POST['is_ab_test'] == '1') ? 1 : 0;
    $variantsData = [];

    if ($isAbTest) {
        if (!EntitlementService::canAccessFeature($userId, 'ab_testing')) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'feature_locked', 'message' => 'Seu plano não permite testes A/B.']);
            exit;
        }
        $variantsJson = $_POST['variants'] ?? '[]';
        $variantsData = json_decode($variantsJson, true);
        if (!is_array($variantsData) || count($variantsData) < 2) {
            echo json_encode(['success' => false, 'message' => 'Um Teste A/B requer pelo menos 2 variantes válidas.']);
            exit;
        }

        $totalWeight = 0;
        $activeCount = 0;
        foreach ($variantsData as $variant) {
            if ($variant['status'] === 'active') {
                $totalWeight += (int)$variant['weight'];
                $activeCount++;
            }
        }
        
        if ($activeCount < 2) {
            echo json_encode(['success' => false, 'message' => 'Você deve ter pelo menos 2 variantes ativas.']);
            exit;
        }
        if ($totalWeight !== 100) {
            echo json_encode(['success' => false, 'message' => 'A soma dos pesos das variantes ativas deve ser exatamente 100%.']);
            exit;
        }

        // Selecionar a primeira variante ativa como fallback e título padrão
        foreach ($variantsData as $variant) {
            if ($variant['status'] === 'active') {
                $url = $variant['url'];
                break;
            }
        }
    }

    if (!EntitlementService::canAccessFeature($userId, 'custom_path')) {
        $path = generateAutomaticSlug($pdo, $subValue, 6);
    }

    if (!$url || !$path) {
        $msg = (!$url) ? 'O conteúdo do QR Code está vazio.' : 'A terminação (slug) é obrigatória.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $creatorIP = getRealIP();

    if ($type === 'link') {
        if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'URL inválida. Use um endereço HTTP/HTTPS válido.']);
            exit;
        }
    }

    if ($type === 'link' || ($type === 'qr' && preg_match('#^https?://#i', (string)$url))) {
        $policyBlock = AbusePolicyService::validateLinkCreation($pdo, (int)$userId, (string)$url, (string)$path, (string)$subValue);
        if ($policyBlock !== null) {
            echo json_encode($policyBlock);
            exit;
        }
    }

    if ($isAbTest) {
        foreach ($variantsData as $variant) {
            if (($variant['status'] ?? '') !== 'active') {
                continue;
            }
            $variantBlock = AbusePolicyService::validateUrlEdit($pdo, (int)$userId, (string)($variant['url'] ?? ''));
            if ($variantBlock !== null) {
                echo json_encode($variantBlock);
                exit;
            }
        }
    }

    // Captura título apenas se for um link HTTP/HTTPS padrão
    $isStandardUrl = preg_match('/^https?:\/\//i', $url);
    $pageTitle = $isStandardUrl ? get_page_title($url) : 'Conteúdo QR Code';

    $resourceType = ($type === 'qr') ? 'qr_codes' : 'links';
    if (!EntitlementService::canCreate($userId, $resourceType)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'quota_exceeded', 'message' => "Você atingiu o limite de $resourceType do seu plano ativo. Faça upgrade no seu painel."]);
        exit;
    }

    if ($subValue !== 'ROOT') {
        if (!EntitlementService::canAccessFeature($userId, 'custom_domain')) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'feature_locked', 'message' => 'Subdomínios personalizados não estão permitidos no seu plano.']);
            exit;
        }

        // Verifica se o subdomínio pertence ao usuário
        $checkSub = $pdo->prepare("SELECT id FROM user_subdomains WHERE user_id = ? AND subdomain = ?");
        $checkSub->execute([$userId, $subValue]);
        if (!$checkSub->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para usar este subdomínio ou ele não está cadastrado.']);
            exit;
        }
    }

    // Validação de Tracking (Pixels) - Caso o usuário tente enviar via POST direto
    if (isset($_POST['tracking_enabled']) && $_POST['tracking_enabled'] == '1') {
        if (!EntitlementService::canAccessFeature($userId, 'tracking')) {
             echo json_encode(['success' => false, 'message' => 'Seu plano não permite o uso de Tracking/Pixels.']);
             exit;
        }
    }

    try {
        $hasReqPrecCol = pdoColumnExists($pdo, 'links', 'request_precise_location');
        if ($hasReqPrecCol) {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, subdomain, path, original_url, title, creator_ip, type, destination_type, destination_metadata, is_ab_test, request_precise_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$userId, $subValue, $path, $url, $pageTitle, $creatorIP, $type, $destinationType, $destinationMetadata, $isAbTest]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, subdomain, path, original_url, title, creator_ip, type, destination_type, destination_metadata, is_ab_test) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $subValue, $path, $url, $pageTitle, $creatorIP, $type, $destinationType, $destinationMetadata, $isAbTest]);
        }
        $linkId = $pdo->lastInsertId();

        if ($isAbTest) {
            $varStmt = $pdo->prepare("INSERT INTO link_variants (link_id, name, url, weight, status) VALUES (?, ?, ?, ?, ?)");
            foreach ($variantsData as $var) {
                $rawVarUrl = trim((string)($var['url'] ?? ''));
                if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $rawVarUrl)) {
                    $rawVarUrl = 'https://' . $rawVarUrl;
                }
                $varUrl = filter_var($rawVarUrl, FILTER_VALIDATE_URL);
                if (!$varUrl || !preg_match('#^https?://#i', $varUrl)) {
                    throw new PDOException('variant_url_invalid');
                }
                $varStmt->execute([$linkId, $var['name'], $varUrl, (int)$var['weight'], $var['status']]);
            }
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'encurtou.pro';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $shortHost = ($subValue !== 'ROOT' ? $subValue . '.' : '') . $host;
        $shortUrl = $scheme . '://' . $shortHost . '/' . $path;

        $edgeSync = edgeCacheInvalidateSlug((string)$subValue, (string)$path, 'create_link', 'api_create');
        if (!($edgeSync['ok'] ?? false)) {
            error_log('edge_cache_sync_warning: ' . json_encode([
                'reason' => 'create_link',
                'subdomain' => $subValue,
                'slug' => $path,
                'sync' => $edgeSync,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        echo json_encode([
            'success' => true,
            'title' => $pageTitle,
            'original_url' => $url,
            'short_url' => $shortUrl,
            'edge_cache_sync_ok' => (bool)($edgeSync['ok'] ?? false)
        ]);
    } catch (PDOException $e) {
        $errorCode = $e->errorInfo[1] ?? 0;
        
        if ($errorCode == 1062) { // Duplicate Entry
            echo json_encode(['success' => false, 'message' => 'Esta terminação já está em uso neste subdomínio.']);
        } elseif ($errorCode == 1452) { // Foreign Key Constraint Fail
            echo json_encode(['success' => false, 'message' => 'Usuário inválido ou inexistente. Por favor, saia e entre novamente.']);
        } elseif (strpos((string)$e->getMessage(), 'variant_url_invalid') !== false) {
            echo json_encode(['success' => false, 'message' => 'Uma ou mais URLs de variante são inválidas.']);
        } else {
            error_log('api.php save error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar o link.']);
        }
    }
}
?>