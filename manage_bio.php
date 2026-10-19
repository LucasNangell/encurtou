<?php
require_once 'db.php';
require_once 'bio_helpers.php';
require_once 'billing/EntitlementService.php';
require_once 'team_permissions.php';
require_once 'auth_guard.php';
require_once 'abuse_policy_helpers.php';
require_once 'AbusePolicyService.php';

header('Content-Type: application/json');

$user_id = requireAuthenticatedUserNotBanned($pdo, true, true);
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$teamContext = teamResolveMembershipContext($pdo, (int)$user_id);

$bioWriteActions = ['save', 'duplicate', 'delete'];
if (in_array($action, $bioWriteActions, true) && !teamCanEditContent((string)($teamContext['profile'] ?? 'editor'))) {
    echo json_encode([
        'success' => false,
        'error' => 'permission_denied',
        'message' => 'Seu perfil na equipe é Visualizador e não permite editar Bio Pages.'
    ]);
    exit;
}

function bio_sanitize_slug(string $value): string
{
    return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($value)));
}

function bio_generate_random_slug(int $size = 6): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $size; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function bio_resolve_unique_slug(PDO $pdo, string $slug, ?int $currentId = null): string
{
    $slug = bio_sanitize_slug($slug);
    $attempts = 0;

    while (true) {
        if ($slug === '') {
            $slug = bio_generate_random_slug(6);
        }
        $attempts++;

        $stmt = $pdo->prepare('SELECT id FROM bio_pages WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $existingId = $stmt->fetchColumn();

        if (!$existingId || ($currentId !== null && (int) $existingId === $currentId)) {
            return $slug;
        }

        $slug = '';
        if ($attempts >= 20) {
            throw new RuntimeException('Nao foi possivel gerar um slug unico.');
        }
    }
}

function bio_template_is_premium(int $templateId): bool
{
    static $premiumIds = null;
    if (!is_array($premiumIds)) {
        $premiumIds = [];
        $baseDir = __DIR__ . '/templates/bio';
        if (is_dir($baseDir)) {
            $folders = scandir($baseDir);
            if (is_array($folders)) {
                foreach ($folders as $folder) {
                    if ($folder === '.' || $folder === '..') {
                        continue;
                    }
                    $configPath = $baseDir . '/' . $folder . '/config.json';
                    if (!is_file($configPath)) {
                        continue;
                    }
                    $raw = @file_get_contents($configPath);
                    $decoded = json_decode((string)$raw, true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    $id = (int)($decoded['id'] ?? 0);
                    if ($id > 0 && !empty($decoded['premium'])) {
                        $premiumIds[$id] = true;
                    }
                }
            }
        }
    }
    return isset($premiumIds[$templateId]);
}

if ($action === 'save') {
    $id = $_POST['id'] ?? null;
    $currentId = $id ? (int) $id : null;
    $slug = trim($_POST['slug'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $theme_bg = $_POST['theme_bg_color'] ?? '#0f172a';
    $theme_btn = $_POST['theme_btn_color'] ?? '#1A8CFF';
    $theme_style = $_POST['theme_btn_style'] ?? 'solid';
    $theme_text = $_POST['theme_text_color'] ?? '#ffffff';
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $pix = trim($_POST['pix_key'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 1);
    $page_type = trim($_POST['page_type'] ?? 'bio');
    $page_status = trim($_POST['page_status'] ?? 'published');
    $selected_goal_key = trim($_POST['selected_goal_key'] ?? '');

    $links = isset($_POST['links']) ? json_decode($_POST['links'], true) : [];
    $incoming_schema = isset($_POST['builder_schema_json']) ? json_decode($_POST['builder_schema_json'], true) : [];

    if (!is_array($links)) {
        $links = [];
    }

    if (!is_array($incoming_schema)) {
        $incoming_schema = [];
    }

    if (empty($links) && !empty($incoming_schema)) {
        $links = bio_flatten_action_links_from_schema($incoming_schema);
    }

    $builder_schema_json = !empty($incoming_schema) ? json_encode($incoming_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    try {
        $slug = bio_resolve_unique_slug($pdo, $slug, $currentId);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Não foi possível gerar um slug único.']);
        exit;
    }

    // Verifica se o slug já existe em outra página.
    $stmt = $pdo->prepare("SELECT id FROM bio_pages WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $existingSlugId = $stmt->fetchColumn();
    if ($existingSlugId && (!$currentId || (int) $existingSlugId !== $currentId)) {
        echo json_encode(['success' => false, 'message' => 'Este slug já está sendo usado por outro usuário.']);
        exit;
    }

    if (!$id) {
        if (!EntitlementService::canCreate($user_id, 'bio_pages')) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'quota_exceeded', 'message' => 'Você atingiu o limite de Bio Pages do seu plano ativo.']);
            exit;
        }
    }

    if ($template_id > 0 && bio_template_is_premium($template_id) && !EntitlementService::canAccessFeature((int)$user_id, 'premium_bio_templates')) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'feature_locked', 'message' => 'Seu plano atual não permite templates premium de Bio Page.']);
        exit;
    }

    $slugPolicy = abuseCheckKeywordRules($pdo, $slug, 'slug');
    if ($slugPolicy !== null) {
        echo json_encode(['success' => false, 'message' => $slugPolicy, 'blocked' => true]);
        exit;
    }

    foreach ($links as $linkItem) {
        if (!is_array($linkItem)) {
            continue;
        }
        $linkUrl = trim((string)($linkItem['url'] ?? ''));
        if ($linkUrl === '' || !preg_match('#^https?://#i', $linkUrl)) {
            continue;
        }
        $policyBlock = AbusePolicyService::validateUrlEdit($pdo, (int)$user_id, $linkUrl);
        if ($policyBlock !== null) {
            echo json_encode($policyBlock);
            exit;
        }
    }

    if (is_array($incoming_schema)) {
        bio_validate_schema_urls($pdo, (int)$user_id, $incoming_schema);
    }

    try {
        $pdo->beginTransaction();

        $bioPageColumns = bio_page_columns($pdo, 'bio_pages');
        $pageData = [
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'avatar_url' => $avatar_url,
            'theme_bg_color' => $theme_bg,
            'theme_btn_color' => $theme_btn,
            'theme_btn_style' => $theme_style,
            'theme_text_color' => $theme_text,
            'whatsapp_number' => $whatsapp,
            'pix_key' => $pix,
            'template_id' => $template_id,
        ];

        if (isset($bioPageColumns['page_type'])) {
            $pageData['page_type'] = $page_type ?: 'bio';
        }
        if (isset($bioPageColumns['page_status'])) {
            $pageData['page_status'] = $page_status ?: 'published';
        }
        if (isset($bioPageColumns['selected_goal_key'])) {
            $pageData['selected_goal_key'] = $selected_goal_key ?: null;
        }
        if (isset($bioPageColumns['builder_schema_json'])) {
            $pageData['builder_schema_json'] = $builder_schema_json;
        }

        if ($id) {
            $setParts = [];
            $values = [];
            foreach ($pageData as $column => $value) {
                $setParts[] = "{$column} = ?";
                $values[] = $value;
            }
            $values[] = $id;
            $values[] = $user_id;

            $stmt = $pdo->prepare("UPDATE bio_pages SET " . implode(', ', $setParts) . " WHERE id = ? AND user_id = ?");
            $stmt->execute($values);
            $bio_page_id = $id;
        } else {
            $insertData = array_merge(['user_id' => $user_id], $pageData);
            $columns = array_keys($insertData);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare("INSERT INTO bio_pages (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
            $stmt->execute(array_values($insertData));
            $bio_page_id = $pdo->lastInsertId();
        }

        // Limpa links antigos e reinsere (mais simples para ordenação dinâmica)
        $stmt = $pdo->prepare("DELETE FROM bio_links WHERE bio_page_id = ?");
        $stmt->execute([$bio_page_id]);

        $stmt = $pdo->prepare("INSERT INTO bio_links (bio_page_id, url, title, icon_class, is_featured, display_order, link_type, social_network, social_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($links as $index => $link) {
            $stmt->execute([
                $bio_page_id,
                $link['url'],
                $link['title'],
                $link['icon_class'] ?? '',
                $link['is_featured'] ?? 0,
                $index,
                $link['link_type'] ?? 'link',
                $link['social_network'] ?? null,
                $link['social_value'] ?? null
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Página de Bio salva com sucesso!', 'id' => $bio_page_id, 'slug' => $slug]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('manage_bio save error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar a página.']);
    }
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM bio_pages WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pages as &$page) {
        $page['page_type'] = $page['page_type'] ?? 'bio';
        $page['page_status'] = $page['page_status'] ?? 'published';
        $page['selected_goal_key'] = $page['selected_goal_key'] ?? '';
        $page['has_form'] = false;
        $rawSchema = $page['builder_schema_json'] ?? '';
        if (is_string($rawSchema) && $rawSchema !== '') {
            $decoded = json_decode($rawSchema, true);
            if (is_array($decoded) && !empty($decoded['blocks']) && is_array($decoded['blocks'])) {
                foreach ($decoded['blocks'] as $b) {
                    if (is_array($b) && ($b['type'] ?? '') === 'form') {
                        $page['has_form'] = true;
                        break;
                    }
                }
            }
        }
    }
    unset($page);

    echo json_encode(['success' => true, 'pages' => $pages]);
    exit;
}

if ($action === 'get') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM bio_pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        echo json_encode(['success' => false, 'message' => 'Página não encontrada.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM bio_links WHERE bio_page_id = ? ORDER BY display_order ASC");
    $stmt->execute([$id]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page['links'] = $links;
    $page['page_type'] = $page['page_type'] ?? 'bio';
    $page['page_status'] = $page['page_status'] ?? 'published';
    $page['selected_goal_key'] = $page['selected_goal_key'] ?? '';
    $page['resolved_schema'] = bio_resolve_schema($page, $links);
    echo json_encode(['success' => true, 'page' => $page]);
    exit;
}

if ($action === 'duplicate') {
    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM bio_pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        echo json_encode(['success' => false, 'message' => 'Página não encontrada.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM bio_links WHERE bio_page_id = ? ORDER BY display_order ASC");
    $stmt->execute([$id]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once 'billing/EntitlementService.php';
    if (!EntitlementService::canCreate($user_id, 'bio_pages')) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode([
            'success' => false,
            'error' => 'quota_exceeded',
            'message' => 'Você atingiu o limite de Bio Pages do seu plano ativo.'
        ]);
        exit;
    }

    $baseSlug = preg_replace('/-copy-\d+$/', '', $page['slug']) . '-copy';
    $newSlug = $baseSlug;
    $suffix = 2;
    $checkStmt = $pdo->prepare("SELECT id FROM bio_pages WHERE slug = ?");
    while (true) {
        $checkStmt->execute([$newSlug]);
        if (!$checkStmt->fetch()) {
            break;
        }
        $newSlug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    try {
        $pdo->beginTransaction();

        $columns = bio_page_columns($pdo, 'bio_pages');
        $insertData = [
            'user_id' => $user_id,
            'slug' => $newSlug,
            'title' => trim(($page['title'] ?? 'Página') . ' (Cópia)'),
            'description' => $page['description'] ?? '',
            'avatar_url' => $page['avatar_url'] ?? '',
            'theme_bg_color' => $page['theme_bg_color'] ?? '#0f172a',
            'theme_btn_color' => $page['theme_btn_color'] ?? '#1A8CFF',
            'theme_btn_style' => $page['theme_btn_style'] ?? 'solid',
            'theme_text_color' => $page['theme_text_color'] ?? '#ffffff',
            'whatsapp_number' => $page['whatsapp_number'] ?? '',
            'pix_key' => $page['pix_key'] ?? '',
            'template_id' => $page['template_id'] ?? 1,
        ];

        foreach (['page_type', 'page_status', 'selected_goal_key', 'builder_schema_json'] as $optionalColumn) {
            if (isset($columns[$optionalColumn])) {
                $insertData[$optionalColumn] = $page[$optionalColumn] ?? null;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO bio_pages (" . implode(', ', array_keys($insertData)) . ") VALUES (" . implode(', ', array_fill(0, count($insertData), '?')) . ")");
        $stmt->execute(array_values($insertData));
        $newPageId = $pdo->lastInsertId();

        $linkStmt = $pdo->prepare("INSERT INTO bio_links (bio_page_id, url, title, icon_class, is_featured, display_order, link_type, social_network, social_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($links as $index => $link) {
            $linkStmt->execute([
                $newPageId,
                $link['url'],
                $link['title'],
                $link['icon_class'] ?? '',
                $link['is_featured'] ?? 0,
                $index,
                $link['link_type'] ?? 'link',
                $link['social_network'] ?? null,
                $link['social_value'] ?? null,
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Página duplicada com sucesso.', 'id' => $newPageId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('manage_bio duplicate error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao duplicar a página.']);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    $stmt = $pdo->prepare("DELETE FROM bio_pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Página excluída com sucesso.']);
    exit;
}
