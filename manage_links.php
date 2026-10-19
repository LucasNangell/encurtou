<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json');
require_once 'db.php';
require_once 'qr_helpers.php';
require_once 'subdomain_helpers.php';
require_once 'edge_cache_helpers.php';
require_once 'team_permissions.php';
require_once 'auth_guard.php';
require_once 'AbusePolicyService.php';
require_once 'rate_limit_helpers.php';

function tableExistsForCampaign(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExistsForCampaign(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function normalizeCampaignName(string $name): string {
    $value = trim(mb_strtolower($name, 'UTF-8'));
    $value = preg_replace('/\s+/', ' ', $value);
    return (string)$value;
}

function parseUtmParamsFromUrl(string $url): array {
    $query = parse_url($url, PHP_URL_QUERY);
    if (!$query) return [];
    $params = [];
    parse_str($query, $params);
    return [
        'utm_source' => trim((string)($params['utm_source'] ?? '')),
        'utm_medium' => trim((string)($params['utm_medium'] ?? '')),
        'utm_campaign' => trim((string)($params['utm_campaign'] ?? '')),
        'utm_term' => trim((string)($params['utm_term'] ?? '')),
        'utm_content' => trim((string)($params['utm_content'] ?? ''))
    ];
}

function rebuildUrlWithQueryParams(string $url, array $query): string {
    $parts = parse_url($url);
    $newQuery = http_build_query($query);
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host     = isset($parts['host']) ? $parts['host'] : '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user     = isset($parts['user']) ? $parts['user'] : '';
    $pass     = isset($parts['pass']) ? ':' . $parts['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parts['path']) ? $parts['path'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return "$scheme$user$pass$host$port$path" . ($newQuery ? "?$newQuery" : "") . "$fragment";
}

function normalizeRetargetingPixelsInput($rawValue): array {
    $default = [
        'facebook' => [],
        'google_ads' => [],
        'linkedin' => [],
        'twitter' => [],
        'pinterest' => [],
        'tiktok' => []
    ];

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

$userId = requireAuthenticatedUserNotBanned($pdo, true, true);
$teamContext = teamResolveMembershipContext($pdo, (int)$userId);
$teamUserIds = !empty($teamContext['has_team'])
    ? teamGetMemberUserIds($pdo, (int)$teamContext['team_id'])
    : [$userId];
if (count($teamUserIds) === 0) {
    $teamUserIds = [$userId];
}
$teamUserIdsStr = implode(',', array_map('intval', $teamUserIds));
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$hasCampaignsTable = tableExistsForCampaign($pdo, 'campaigns');
$hasLinksCampaignId = columnExistsForCampaign($pdo, 'links', 'campaign_id');
$hasDeepLinkingColumn = columnExistsForCampaign($pdo, 'links', 'deep_linking_enabled');
$hasFolderColumn = columnExistsForCampaign($pdo, 'links', 'folder_id');
$hasFoldersTable = tableExistsForCampaign($pdo, 'link_folders');
$hasTagsTable = tableExistsForCampaign($pdo, 'link_tags');
$hasTagMapTable = tableExistsForCampaign($pdo, 'link_tag_map');
$hasSocialTitleColumn = columnExistsForCampaign($pdo, 'links', 'social_title');
$hasSocialDescriptionColumn = columnExistsForCampaign($pdo, 'links', 'social_description');
$hasSocialImageUrlColumn = columnExistsForCampaign($pdo, 'links', 'social_image_url');
$hasFaviconUrlColumn = columnExistsForCampaign($pdo, 'links', 'favicon_url');
$hasRetargetingPixelsColumn = columnExistsForCampaign($pdo, 'links', 'retargeting_pixels_json');
$hasMonitorDwellColumn = columnExistsForCampaign($pdo, 'links', 'monitor_dwell_enabled');
$hasLinksCreatedAtColumn = columnExistsForCampaign($pdo, 'links', 'created_at');
$hasCampaignCreatedAtColumn = columnExistsForCampaign($pdo, 'campaigns', 'created_at');
$hasRequestPreciseLocationColumn = columnExistsForCampaign($pdo, 'links', 'request_precise_location');

try {
    require_once 'billing/EntitlementService.php';

    $readOnlyActions = ['list', 'list_campaign_links', 'get_actions', 'list_subdomains'];
    if ($action !== '' && !in_array($action, $readOnlyActions, true)) {
        if (!teamCanEditContent((string)($teamContext['profile'] ?? 'editor'))) {
            echo json_encode([
                'success' => false,
                'error' => 'permission_denied',
                'message' => 'Seu perfil na equipe é Visualizador e não permite editar conteúdos.'
            ]);
            exit;
        }
    }

    $featureMessages = [
        'bulk_utm' => 'Seu plano atual não permite gerenciamento de campanhas e UTMs em massa.',
        'tracking' => 'Seu plano atual não permite rastreamento e eventos de conversão.',
        'rules' => 'Seu plano atual não permite regras avançadas de automação.',
        'ab_testing' => 'Seu plano atual não permite testes A/B.',
        'folders_tags' => 'Seu plano atual não permite organização por pastas e tags.',
        'social_preview' => 'Seu plano atual não permite personalização de preview social.'
    ];

    $requireFeature = function (string $featureKey) use ($userId, $featureMessages): void {
        if (!EntitlementService::canAccessFeature($userId, $featureKey)) {
            $message = $featureMessages[$featureKey] ?? 'Este recurso não está disponível no seu plano atual.';
            echo json_encode([
                'success' => false,
                'error' => 'feature_locked',
                'message' => $message
            ]);
            exit;
        }
    };

    $enforceCampaignCreationLimit = function () use ($pdo, $userId, $hasCampaignCreatedAtColumn): void {
        $maxCampaigns = EntitlementService::getEffectiveLimit($userId, 'max_campaigns', 0);
        if ($maxCampaigns >= 999999) {
            return;
        }
        if ($maxCampaigns <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'quota_exceeded',
                'message' => 'Seu plano atual não permite criar campanhas.'
            ]);
            exit;
        }

        if ($hasCampaignCreatedAtColumn) {
            $cycle = EntitlementService::getUsageCycleWindow($userId);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ? AND created_at >= ? AND created_at < ?");
            $countStmt->execute([$userId, $cycle['start'], $cycle['end']]);
        } else {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE user_id = ?");
            $countStmt->execute([$userId]);
        }
        $currentCampaigns = (int)$countStmt->fetchColumn();
        if ($currentCampaigns >= $maxCampaigns) {
            echo json_encode([
                'success' => false,
                'error' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de campanhas do seu plano.'
            ]);
            exit;
        }
    };

    $resolveDeepLinkingFlag = function () use ($userId, $hasDeepLinkingColumn): ?int {
        if (!$hasDeepLinkingColumn) {
            return null;
        }
        if (!array_key_exists('deep_linking_enabled', $_POST)) {
            return null;
        }

        $requested = (int)$_POST['deep_linking_enabled'] === 1 ? 1 : 0;
        if ($requested === 1 && !EntitlementService::canAccessFeature($userId, 'deep_linking')) {
            echo json_encode([
                'success' => false,
                'error' => 'feature_locked',
                'message' => 'Seu plano atual não permite Deep Linking (Apps).'
            ]);
            exit;
        }
        return $requested;
    };

    $syncEdgeCacheForLink = function (int $linkId, string $reason) use ($pdo): void {
        $sync = edgeCacheInvalidateLinkById($pdo, $linkId, $reason, 'manage_links');
        if (!($sync['ok'] ?? false)) {
            error_log('edge_cache_sync_warning: ' . json_encode([
                'reason' => $reason,
                'link_id' => $linkId,
                'sync' => $sync,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    };

    $syncEdgeCacheForSlug = function (string $subdomain, string $slug, string $reason): void {
        $sync = edgeCacheInvalidateSlug($subdomain, $slug, $reason, 'manage_links');
        if (!($sync['ok'] ?? false)) {
            error_log('edge_cache_sync_warning: ' . json_encode([
                'reason' => $reason,
                'subdomain' => $subdomain,
                'slug' => $slug,
                'sync' => $sync,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    };

    // LISTAR LINKS (Filtrado por tipo: link, qr ou all)
    if ($action === 'list') {
        $type = $_GET['type'] ?? 'link';
        $createdFrom = trim((string)($_GET['created_from'] ?? ''));
        $createdTo = trim((string)($_GET['created_to'] ?? ''));
        $statusFilter = trim((string)($_GET['status'] ?? 'all'));
        $tagIdsRaw = trim((string)($_GET['tag_ids'] ?? ''));
        $tagIds = [];
        if ($tagIdsRaw !== '') {
            foreach (explode(',', $tagIdsRaw) as $rawTagId) {
                $parsedId = (int)trim($rawTagId);
                if ($parsedId > 0) {
                    $tagIds[] = $parsedId;
                }
            }
            $tagIds = array_values(array_unique($tagIds));
        }

        $campaignJoinSql = '';
        $campaignFieldSql = '';
        if ($hasCampaignsTable && $hasLinksCampaignId) {
            $campaignJoinSql = " LEFT JOIN campaigns c ON c.id = l.campaign_id ";
            $campaignFieldSql = ", c.name AS campaign_name ";
        }

        $folderJoinSql = '';
        $folderFieldSql = ", NULL AS folder_id, NULL AS folder_name, NULL AS folder_type";
        if ($hasFolderColumn && $hasFoldersTable) {
            $folderJoinSql = " LEFT JOIN link_folders lf ON lf.id = l.folder_id ";
            $folderFieldSql = ", l.folder_id, lf.name AS folder_name, lf.type AS folder_type";
        } elseif ($hasFolderColumn) {
            $folderFieldSql = ", l.folder_id, NULL AS folder_name, NULL AS folder_type";
        }

        $expiredSql = "((l.end_date IS NOT NULL AND l.end_date < NOW()) OR (l.expire_clicks IS NOT NULL AND l.expire_clicks > 0 AND l.clicks >= l.expire_clicks))";
        $whereSql = [];
        $whereSql[] = "l.user_id IN ($teamUserIdsStr)";
        $sqlParams = [];
        if ($hasTagMapTable && count($tagIds) > 0) {
            $tagPlaceholders = [];
            foreach ($tagIds as $idx => $tagId) {
                $placeholder = ':tag_filter_' . $idx;
                $tagPlaceholders[] = $placeholder;
                $sqlParams[$placeholder] = $tagId;
            }
            $whereSql[] = "EXISTS (SELECT 1 FROM link_tag_map ltm_filter WHERE ltm_filter.link_id = l.id AND ltm_filter.tag_id IN (" . implode(',', $tagPlaceholders) . "))";
        }

        $sql = "SELECT l.*, 
            DATE_FORMAT(l.created_at, '%d/%m/%Y') as data_criacao,
            (SELECT COUNT(*) FROM clicks_log cl WHERE cl.link_id = l.id AND cl.source = 'digital') as clicks_digital,
            (SELECT COUNT(*) FROM clicks_log cl WHERE cl.link_id = l.id AND cl.source = 'qr') as clicks_qr
            $folderFieldSql
            $campaignFieldSql
            FROM links l 
            $campaignJoinSql
            $folderJoinSql ";

        if ($type !== 'all') {
            $whereSql[] = "l.type = :type";
            $sqlParams[':type'] = $type;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdFrom)) {
            $whereSql[] = "DATE(l.created_at) >= :created_from";
            $sqlParams[':created_from'] = $createdFrom;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdTo)) {
            $whereSql[] = "DATE(l.created_at) <= :created_to";
            $sqlParams[':created_to'] = $createdTo;
        }

        if ($statusFilter === 'active') {
            $whereSql[] = "l.status = 'active' AND NOT $expiredSql";
        } elseif ($statusFilter === 'paused') {
            $whereSql[] = "l.status = 'paused'";
        } elseif ($statusFilter === 'expired') {
            $whereSql[] = $expiredSql;
        }

        $sql .= " WHERE " . implode(' AND ', $whereSql);
        if ($hasFolderColumn) {
            $sql .= " ORDER BY (l.folder_id IS NULL) ASC, COALESCE(folder_name, ''), l.created_at DESC";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        $stmt = $pdo->prepare($sql);
        foreach ($sqlParams as $paramKey => $paramValue) {
            if (is_int($paramValue)) {
                $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($paramKey, $paramValue);
            }
        }
        $stmt->execute();
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $variantsStmt = $pdo->prepare("SELECT id, name, url, weight, status FROM link_variants WHERE link_id = ? ORDER BY id ASC");
        foreach ($links as &$link) {
            $link['tags'] = [];
            if (!empty($link['is_ab_test'])) {
                $variantsStmt->execute([$link['id']]);
                $link['variants'] = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        unset($link);

        $folders = [];
        if ($hasFoldersTable) {
            $foldersSql = "SELECT f.id, f.name, f.type FROM link_folders f WHERE f.user_id IN ($teamUserIdsStr)";
            $folderParams = [];
            if ($type !== 'all') {
                $foldersSql .= " AND f.type = :folder_type";
                $folderParams[':folder_type'] = $type;
            }
            $foldersSql .= " ORDER BY f.name ASC";
            $foldersStmt = $pdo->prepare($foldersSql);
            foreach ($folderParams as $folderParamKey => $folderParamValue) {
                $foldersStmt->bindValue($folderParamKey, $folderParamValue);
            }
            $foldersStmt->execute();
            $folders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($hasTagsTable && $hasTagMapTable && count($links) > 0) {
            $linkIds = array_map(static function (array $row): int {
                return (int)$row['id'];
            }, $links);
            $linkPlaceholders = implode(',', array_fill(0, count($linkIds), '?'));
            $tagsByLinkStmt = $pdo->prepare("
                SELECT m.link_id, t.id AS tag_id, t.name AS tag_name
                FROM link_tag_map m
                INNER JOIN link_tags t ON t.id = m.tag_id
                WHERE m.link_id IN ($linkPlaceholders)
                ORDER BY t.name ASC
            ");
            $tagsByLinkStmt->execute($linkIds);
            $tagRows = $tagsByLinkStmt->fetchAll(PDO::FETCH_ASSOC);

            $linkIndexById = [];
            foreach ($links as $idx => $row) {
                $linkIndexById[(int)$row['id']] = $idx;
            }
            foreach ($tagRows as $tagRow) {
                $rowLinkId = (int)$tagRow['link_id'];
                if (!array_key_exists($rowLinkId, $linkIndexById)) {
                    continue;
                }
                $targetIdx = $linkIndexById[$rowLinkId];
                $links[$targetIdx]['tags'][] = [
                    'id' => (int)$tagRow['tag_id'],
                    'name' => (string)$tagRow['tag_name']
                ];
            }
        }

        $availableTags = [];
        if ($hasTagsTable && $hasTagMapTable) {
            $availableTagsSql = "
                SELECT t.id, t.name, COUNT(DISTINCT l.id) AS usage_count
                FROM link_tags t
                LEFT JOIN link_tag_map m ON m.tag_id = t.id
                LEFT JOIN links l ON l.id = m.link_id AND l.user_id IN ($teamUserIdsStr)
                WHERE t.user_id IN ($teamUserIdsStr)
            ";
            $availableTagsParams = [];
            if ($type !== 'all') {
                $availableTagsSql .= " AND (l.id IS NULL OR l.type = :tag_type)";
                $availableTagsParams[':tag_type'] = $type;
            }
            $availableTagsSql .= " GROUP BY t.id, t.name ORDER BY t.name ASC";
            $availableTagsStmt = $pdo->prepare($availableTagsSql);
            foreach ($availableTagsParams as $tagParamKey => $tagParamValue) {
                $availableTagsStmt->bindValue($tagParamKey, $tagParamValue);
            }
            $availableTagsStmt->execute();
            $availableTags = $availableTagsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $folderItemCount = [];
        foreach ($links as $row) {
            $folderId = isset($row['folder_id']) ? (int)$row['folder_id'] : 0;
            if ($folderId > 0) {
                if (!isset($folderItemCount[$folderId])) {
                    $folderItemCount[$folderId] = 0;
                }
                $folderItemCount[$folderId]++;
            }
        }
        foreach ($folders as &$folder) {
            $fId = (int)($folder['id'] ?? 0);
            $folder['item_count'] = (int)($folderItemCount[$fId] ?? 0);
        }
        unset($folder);

        echo json_encode([
            'success' => true,
            'links' => $links,
            'folders' => $folders,
            'available_tags' => $availableTags
        ]);
        exit;
    }

    if ($action === 'create_folder') {
        $requireFeature('folders_tags');
        if (!$hasFoldersTable) {
            echo json_encode(['success' => false, 'message' => 'Estrutura de pastas não encontrada. Rode a migration.']);
            exit;
        }

        $folderType = (string)($_POST['type'] ?? 'link');
        $folderType = ($folderType === 'qr') ? 'qr' : 'link';
        $folderName = trim((string)($_POST['name'] ?? ''));
        $folderName = preg_replace('/\s+/', ' ', $folderName);

        if ($folderName === '' || mb_strlen($folderName, 'UTF-8') > 120) {
            echo json_encode(['success' => false, 'message' => 'Informe um nome de pasta entre 1 e 120 caracteres.']);
            exit;
        }

        try {
            $insFolder = $pdo->prepare("INSERT INTO link_folders (user_id, type, name) VALUES (?, ?, ?)");
            $insFolder->execute([$userId, $folderType, $folderName]);
            echo json_encode([
                'success' => true,
                'folder' => [
                    'id' => (int)$pdo->lastInsertId(),
                    'name' => $folderName,
                    'type' => $folderType,
                    'item_count' => 0
                ]
            ]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                echo json_encode(['success' => false, 'message' => 'Já existe uma pasta com este nome para este tipo.']);
                exit;
            }
            throw $e;
        }
        exit;
    }

    if ($action === 'move_to_folder') {
        $requireFeature('folders_tags');
        if (!$hasFolderColumn) {
            echo json_encode(['success' => false, 'message' => 'Coluna folder_id não encontrada. Rode a migration.']);
            exit;
        }

        $linkId = (int)($_POST['id'] ?? 0);
        $folderRaw = isset($_POST['folder_id']) ? trim((string)$_POST['folder_id']) : '';
        $folderId = ($folderRaw === '' || $folderRaw === '0') ? null : (int)$folderRaw;

        if ($linkId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Item inválido para mover.']);
            exit;
        }

        $linkStmt = $pdo->prepare("SELECT id, type FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $linkStmt->execute([$linkId]);
        $linkRow = $linkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$linkRow) {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado ou sem permissão.']);
            exit;
        }

        if ($folderId !== null) {
            if (!$hasFoldersTable) {
                echo json_encode(['success' => false, 'message' => 'Tabela de pastas não encontrada. Rode a migration.']);
                exit;
            }
            $folderStmt = $pdo->prepare("SELECT id, type FROM link_folders WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
            $folderStmt->execute([$folderId]);
            $folderRow = $folderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$folderRow) {
                echo json_encode(['success' => false, 'message' => 'Pasta não encontrada ou sem permissão.']);
                exit;
            }
            if ((string)$folderRow['type'] !== (string)$linkRow['type']) {
                echo json_encode(['success' => false, 'message' => 'Tipo da pasta incompatível com o item selecionado.']);
                exit;
            }
        }

        $moveStmt = $pdo->prepare("UPDATE links SET folder_id = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $moveStmt->execute([$folderId, $linkId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_item_tags') {
        $requireFeature('folders_tags');
        if (!($hasTagsTable && $hasTagMapTable)) {
            echo json_encode(['success' => false, 'message' => 'Estrutura de tags não encontrada. Rode a migration.']);
            exit;
        }

        $linkId = (int)($_POST['id'] ?? 0);
        if ($linkId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Item inválido para salvar tags.']);
            exit;
        }

        $ownerStmt = $pdo->prepare("SELECT id, user_id FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $ownerStmt->execute([$linkId]);
        $ownerRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ownerRow) {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado ou sem permissão.']);
            exit;
        }

        $tagsInput = trim((string)($_POST['tags'] ?? ''));
        $parsedTags = [];
        if ($tagsInput !== '') {
            $chunks = preg_split('/[,;\n]+/', $tagsInput);
            foreach ($chunks as $chunk) {
                $tagName = trim((string)$chunk);
                $tagName = preg_replace('/\s+/', ' ', $tagName);
                if ($tagName !== '') {
                    $parsedTags[] = mb_substr($tagName, 0, 80, 'UTF-8');
                }
            }
            $parsedTags = array_values(array_unique($parsedTags));
        }

        if (count($parsedTags) > 20) {
            echo json_encode(['success' => false, 'message' => 'Limite máximo de 20 tags por item.']);
            exit;
        }

        $tagOwnerId = (int)$ownerRow['user_id'];

        $pdo->beginTransaction();
        try {
            $deleteMapStmt = $pdo->prepare("DELETE FROM link_tag_map WHERE link_id = ?");
            $deleteMapStmt->execute([$linkId]);

            if (count($parsedTags) > 0) {
                $findTagStmt = $pdo->prepare("SELECT id FROM link_tags WHERE user_id = ? AND name = ? LIMIT 1");
                $insertTagStmt = $pdo->prepare("INSERT INTO link_tags (user_id, name) VALUES (?, ?)");
                $insertMapStmt = $pdo->prepare("INSERT INTO link_tag_map (link_id, tag_id) VALUES (?, ?)");

                foreach ($parsedTags as $tagName) {
                    $findTagStmt->execute([$tagOwnerId, $tagName]);
                    $tagId = (int)($findTagStmt->fetchColumn() ?: 0);
                    if ($tagId <= 0) {
                        $insertTagStmt->execute([$tagOwnerId, $tagName]);
                        $tagId = (int)$pdo->lastInsertId();
                    }
                    $insertMapStmt->execute([$linkId, $tagId]);
                }
            }

            $pdo->commit();
        } catch (Throwable $th) {
            $pdo->rollBack();
            throw $th;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'list_campaign_links') {
        $campaignName = trim((string)($_GET['campaign'] ?? ''));
        if ($campaignName === '') {
            echo json_encode(['success' => true, 'links' => []]);
            exit;
        }

        $campaignJoinSql = '';
        $campaignFieldSql = '';
        if ($hasCampaignsTable && $hasLinksCampaignId) {
            $campaignJoinSql = " LEFT JOIN campaigns c ON c.id = l.campaign_id ";
            $campaignFieldSql = ", c.name AS campaign_name ";
        }

        $stmt = $pdo->prepare("
            SELECT l.id, l.path, l.subdomain, l.original_url, l.tracking_enabled $campaignFieldSql
            FROM links l
            $campaignJoinSql
            WHERE l.user_id IN ($teamUserIdsStr)
            ORDER BY l.created_at DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $normalizedCampaign = normalizeCampaignName($campaignName);
        $result = [];
        foreach ($rows as $row) {
            $utm = parseUtmParamsFromUrl((string)($row['original_url'] ?? ''));
            $rowCampaign = trim((string)($row['campaign_name'] ?? ''));
            if ($rowCampaign === '' && $utm['utm_campaign'] !== '') {
                $rowCampaign = $utm['utm_campaign'];
            }
            if (normalizeCampaignName($rowCampaign) !== $normalizedCampaign) {
                continue;
            }
            $result[] = [
                'id' => (int)$row['id'],
                'path' => (string)($row['path'] ?? ''),
                'subdomain' => (string)($row['subdomain'] ?? ''),
                'tracking_enabled' => (int)($row['tracking_enabled'] ?? 0),
                'campaign_name' => $rowCampaign,
                'utm_source' => $utm['utm_source'],
                'utm_medium' => $utm['utm_medium'],
                'utm_campaign' => $utm['utm_campaign'],
                'utm_term' => $utm['utm_term'],
                'utm_content' => $utm['utm_content'],
                'original_url' => (string)($row['original_url'] ?? '')
            ];
        }

        echo json_encode(['success' => true, 'links' => $result]);
        exit;
    }

    // EXCLUIR LINK
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $lookup = $pdo->prepare("SELECT subdomain, path FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $lookup->execute([$id]);
        $linkRow = $lookup->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $stmt->execute([$id]);
        if ($linkRow) {
            $syncEdgeCacheForSlug((string)$linkRow['subdomain'], (string)$linkRow['path'], 'delete_link');
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // PAUSAR / ATIVAR LINK
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] === 'paused' ? 'paused' : 'active';
        $stmt = $pdo->prepare("UPDATE links SET status = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $stmt->execute([$status, $id]);
        $syncEdgeCacheForLink($id, 'toggle_status');
        echo json_encode(['success' => true]);
        exit;
    }

    // EDITAR LINK (BÁSICO + AVANÇADO)
    if ($action === 'edit_url') {
        if (!EntitlementService::canAccessFeature($userId, 'redirect')) {
             echo json_encode(['success' => false, 'message' => 'Seu plano atual não permite o redirecionamento de links. Faça upgrade para liberar.']);
             exit;
        }
        if (!EntitlementService::canEditDestination($userId, 'link')) {
            echo json_encode(['success' => false, 'message' => 'Você atingiu o limite de edições de destino para Links do seu plano.']);
            exit;
        }

        $id = (int)$_POST['id'];
        $newUrl = filter_var($_POST['url'], FILTER_VALIDATE_URL);
        
        $url_ios = !empty($_POST['url_ios']) ? filter_var($_POST['url_ios'], FILTER_VALIDATE_URL) : null;
        $url_android = !empty($_POST['url_android']) ? filter_var($_POST['url_android'], FILTER_VALIDATE_URL) : null;
        $url_windows = !empty($_POST['url_windows']) ? filter_var($_POST['url_windows'], FILTER_VALIDATE_URL) : null;
        $socialTitleRaw = trim((string)($_POST['social_title'] ?? ''));
        $socialDescriptionRaw = trim((string)($_POST['social_description'] ?? ''));
        $socialImageRaw = trim((string)($_POST['social_image_url'] ?? ''));
        $faviconRaw = trim((string)($_POST['favicon_url'] ?? ''));

        $wantsSocialPreviewEdit = ($socialTitleRaw !== '' || $socialDescriptionRaw !== '' || $socialImageRaw !== '' || $faviconRaw !== '');
        if ($wantsSocialPreviewEdit) {
            $requireFeature('social_preview');
        }

        if (!$newUrl) {
            echo json_encode(['success' => false, 'message' => 'URL padrão inválida.']);
            exit;
        }

        if (!checkUserRateLimit($pdo, (int)$userId, 'edit_link', 60, 60)) {
            echo json_encode(['success' => false, 'message' => 'Limite de edições por hora atingido.']);
            exit;
        }

        foreach (array_filter([$newUrl, $url_ios, $url_android, $url_windows]) as $candidateUrl) {
            $policyBlock = AbusePolicyService::validateUrlEdit($pdo, (int)$userId, (string)$candidateUrl);
            if ($policyBlock !== null) {
                echo json_encode($policyBlock);
                exit;
            }
        }

        // Se informou URL de dispositivo e ela é inválida
        if (($_POST['url_ios'] && !$url_ios) || ($_POST['url_android'] && !$url_android) || ($_POST['url_windows'] && !$url_windows)) {
            echo json_encode(['success' => false, 'message' => 'Uma ou mais URLs de dispositivos são inválidas.']);
            exit;
        }

        $socialImageUrl = $socialImageRaw !== '' ? filter_var($socialImageRaw, FILTER_VALIDATE_URL) : null;
        if ($socialImageRaw !== '' && !$socialImageUrl) {
            echo json_encode(['success' => false, 'message' => 'A URL da imagem de preview é inválida.']);
            exit;
        }

        $faviconUrl = $faviconRaw !== '' ? filter_var($faviconRaw, FILTER_VALIDATE_URL) : null;
        if ($faviconRaw !== '' && !$faviconUrl) {
            echo json_encode(['success' => false, 'message' => 'A URL do favicon é inválida.']);
            exit;
        }

        $socialTitle = $socialTitleRaw !== '' ? mb_substr($socialTitleRaw, 0, 255, 'UTF-8') : null;
        $socialDescription = $socialDescriptionRaw !== '' ? mb_substr($socialDescriptionRaw, 0, 500, 'UTF-8') : null;

        $deepLinkingEnabled = $resolveDeepLinkingFlag();

        $setSql = [
            'original_url = ?',
            'url_ios = ?',
            'url_android = ?',
            'url_windows = ?',
            'is_ab_test = 0'
        ];
        $params = [$newUrl, $url_ios, $url_android, $url_windows];

        if ($hasDeepLinkingColumn) {
            $setSql[] = 'deep_linking_enabled = COALESCE(?, deep_linking_enabled)';
            $params[] = $deepLinkingEnabled;
        }
        if ($hasSocialTitleColumn) {
            $setSql[] = 'social_title = ?';
            $params[] = $socialTitle;
        }
        if ($hasSocialDescriptionColumn) {
            $setSql[] = 'social_description = ?';
            $params[] = $socialDescription;
        }
        if ($hasSocialImageUrlColumn) {
            $setSql[] = 'social_image_url = ?';
            $params[] = $socialImageUrl;
        }
        if ($hasFaviconUrlColumn) {
            $setSql[] = 'favicon_url = ?';
            $params[] = $faviconUrl;
        }
        if ($hasRequestPreciseLocationColumn) {
            $setSql[] = 'request_precise_location = ?';
            $params[] = isset($_POST['request_precise_location']) && (int)$_POST['request_precise_location'] === 1 ? 1 : 0;
        }

        $params[] = $id;
        $params[] = $userId;
        $stmt = $pdo->prepare("UPDATE links SET " . implode(', ', $setSql) . " WHERE id = ? AND user_id = ?");
        $stmt->execute($params);
        EntitlementService::registerDestinationEdit($userId, 'link');
        $syncEdgeCacheForLink($id, 'edit_url');
        echo json_encode(['success' => true]);
        exit;
    }

    // EDITAR DESTINO DE QR CODE DINÂMICO (tipo + metadados)
    // O backend re-gera o `original_url` a partir dos metadados, mantendo
    // o slug/subdomínio originais intactos para não quebrar QR impressos.
    if ($action === 'edit_qr_destination') {
        if (!EntitlementService::canAccessFeature($userId, 'redirect')) {
            echo json_encode(['success' => false, 'message' => 'Seu plano atual não permite o redirecionamento de QR Codes. Faça upgrade para liberar.']);
            exit;
        }
        if (!EntitlementService::canEditDestination($userId, 'qr')) {
            echo json_encode(['success' => false, 'message' => 'Você atingiu o limite de edições de destino para QR Codes do seu plano.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID do QR inválido.']);
            exit;
        }

        // Ownership: usuário só edita os próprios QR Codes (ou do time)
        $ownCheck = $pdo->prepare("SELECT id, type FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $ownCheck->execute([$id]);
        $linkRow = $ownCheck->fetch(PDO::FETCH_ASSOC);
        if (!$linkRow) {
            echo json_encode(['success' => false, 'message' => 'QR Code não encontrado ou permissão negada.']);
            exit;
        }
        if ($linkRow['type'] !== 'qr') {
            echo json_encode(['success' => false, 'message' => 'Este item não é um QR Code dinâmico.']);
            exit;
        }

        $errMsg = null;
        $processed = qr_process_destination(
            $_POST['destination_type'] ?? null,
            $_POST['destination_metadata'] ?? null,
            $errMsg
        );
        if (!$processed) {
            echo json_encode(['success' => false, 'message' => $errMsg ?: 'Dados do QR Code inválidos.']);
            exit;
        }

        $metaJson = json_encode($processed['metadata'], JSON_UNESCAPED_UNICODE);
        $newUrl   = $processed['url'];
        $newType  = $processed['type'];

        // Título amigável e sincronizado com o novo destino
        $titleMap = [
            'link'   => 'QR Code – Link',
            'social' => 'QR Code – Rede Social',
            'vcard'  => 'QR Code – Contato (vCard)',
            'phone'  => 'QR Code – Telefone',
            'email'  => 'QR Code – E-mail',
        ];
        $newTitle = $titleMap[$newType] ?? 'QR Code Dinâmico';

        $deepLinkingEnabled = $resolveDeepLinkingFlag();

        if ($hasDeepLinkingColumn) {
            $upd = $pdo->prepare("UPDATE links SET
                    destination_type = ?,
                    destination_metadata = ?,
                    original_url = ?,
                    title = ?,
                    deep_linking_enabled = COALESCE(?, deep_linking_enabled),
                    is_ab_test = 0
                WHERE id = ? AND user_id IN ($teamUserIdsStr)");
            $upd->execute([$newType, $metaJson, $newUrl, $newTitle, $deepLinkingEnabled, $id]);
        } else {
            $upd = $pdo->prepare("UPDATE links SET
                    destination_type = ?,
                    destination_metadata = ?,
                    original_url = ?,
                    title = ?,
                    is_ab_test = 0
                WHERE id = ? AND user_id IN ($teamUserIdsStr)");
            $upd->execute([$newType, $metaJson, $newUrl, $newTitle, $id]);
        }
        if ($hasRequestPreciseLocationColumn) {
            $preciseQr = isset($_POST['request_precise_location']) && (int)$_POST['request_precise_location'] === 1 ? 1 : 0;
            $pStmt = $pdo->prepare("UPDATE links SET request_precise_location = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
            $pStmt->execute([$preciseQr, $id]);
        }
        EntitlementService::registerDestinationEdit($userId, 'qr');
        $syncEdgeCacheForLink($id, 'edit_qr_destination');

        echo json_encode([
            'success' => true,
            'destination_type' => $newType,
            'destination_metadata' => $processed['metadata'],
            'original_url' => $newUrl
        ]);
        exit;
    }

    if ($action === 'toggle_deep_linking') {
        $id = (int)($_POST['id'] ?? 0);
        $enabled = (int)($_POST['deep_linking_enabled'] ?? 0) === 1 ? 1 : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        if (!$hasDeepLinkingColumn) {
            echo json_encode(['success' => false, 'message' => 'Coluna deep_linking_enabled não encontrada. Rode a migration.']);
            exit;
        }

        if ($enabled === 1 && !EntitlementService::canAccessFeature($userId, 'deep_linking')) {
            echo json_encode([
                'success' => false,
                'error' => 'feature_locked',
                'message' => 'Seu plano atual não permite Deep Linking (Apps).'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE links SET deep_linking_enabled = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $stmt->execute([$enabled, $id]);
        $syncEdgeCacheForLink($id, 'toggle_deep_linking');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- SUBDOMAINS MANAGEMENT ---
    if ($action === 'list_subdomains') {
        $stmt = $pdo->prepare("SELECT subdomain FROM user_subdomains WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'subdomains' => $subs]);
        exit;
    }

    if ($action === 'add_subdomain') {
        $sub = subdomainSanitize((string)($_POST['subdomain'] ?? ''));
        $validationError = subdomainValidateForRegistration($sub);
        if ($validationError !== null) {
            echo json_encode(['success' => false, 'message' => $validationError]);
            exit;
        }

        $subdomainDecision = EntitlementService::getCreateAccessDecision($userId, 'subdomains');
        if (!(bool)($subdomainDecision['allowed'] ?? false)) {
            $errorCode = (string)($subdomainDecision['error'] ?? 'quota_exceeded');
            $errorMessage = (string)($subdomainDecision['message'] ?? 'Subdomínios não disponíveis para seu plano atual.');
            echo json_encode(['success' => false, 'error' => $errorCode, 'message' => $errorMessage]);
            exit;
        }

        try {
            $ins = $pdo->prepare("INSERT INTO user_subdomains (user_id, subdomain) VALUES (?, ?)");
            $ins->execute([$userId, $sub]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                echo json_encode(['success' => false, 'message' => 'Este subdomínio já está em uso por outro usuário.']);
                exit;
            }
            throw $e;
        }
        exit;
    }

    // EDITAR REGRAS E AUTOMAÇÕES (Agendamento, Expiração, Gatilhos)
    if ($action === 'edit_rules') {
        $requireFeature('rules');

        $id = (int)$_POST['id'];
        
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $expire_clicks = !empty($_POST['expire_clicks']) ? (int)$_POST['expire_clicks'] : null;
        $fallback_url = !empty($_POST['fallback_url']) ? filter_var($_POST['fallback_url'], FILTER_VALIDATE_URL) : null;
        $trigger_clicks = !empty($_POST['trigger_clicks']) ? (int)$_POST['trigger_clicks'] : null;
        $trigger_url = !empty($_POST['trigger_url']) ? filter_var($_POST['trigger_url'], FILTER_VALIDATE_URL) : null;

        if (!empty($_POST['fallback_url']) && !$fallback_url) {
            echo json_encode(['success' => false, 'message' => 'URL para redirecionamento após a expiração é inválida.']);
            exit;
        }
        if (!empty($_POST['trigger_url']) && !$trigger_url) {
            echo json_encode(['success' => false, 'message' => 'URL de destino alternativo para o gatilho é inválida.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE links SET 
            start_date = ?, 
            end_date = ?, 
            expire_clicks = ?, 
            fallback_url = ?, 
            trigger_clicks = ?, 
            trigger_url = ? 
            WHERE id = ? AND user_id = ?");
            
        $stmt->execute([
            $start_date, 
            $end_date, 
            $expire_clicks, 
            $fallback_url, 
            $trigger_clicks, 
            $trigger_url, 
            $id, 
            $userId
        ]);
        $syncEdgeCacheForLink($id, 'edit_rules');
        echo json_encode(['success' => true]);
        exit;
    }

    // EDITAR LINK A/B TEST
    if ($action === 'edit_ab_test') {
        $requireFeature('ab_testing');

        $id = (int)$_POST['id'];
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

        // Verifica dono do link
        $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Link não encontrado ou permissão negada.']);
            exit;
        }

        // Atualiza as variantes
        $del = $pdo->prepare("DELETE FROM link_variants WHERE link_id = ?");
        $del->execute([$id]);

        $varStmt = $pdo->prepare("INSERT INTO link_variants (link_id, name, url, weight, status) VALUES (?, ?, ?, ?, ?)");
        $primaryUrl = '';
        foreach ($variantsData as $var) {
            $varUrl = filter_var($var['url'], FILTER_VALIDATE_URL) ? $var['url'] : 'http://' . $var['url'];
            $varStmt->execute([$id, $var['name'], $varUrl, (int)$var['weight'], $var['status']]);
            if ($var['status'] === 'active' && empty($primaryUrl)) {
                $primaryUrl = $varUrl;
            }
        }

        // Atualiza original_url para fallback e marca como ab test
        if ($hasRequestPreciseLocationColumn) {
            $preciseAb = isset($_POST['request_precise_location']) && (int)$_POST['request_precise_location'] === 1 ? 1 : 0;
            $upd = $pdo->prepare("UPDATE links SET original_url = ?, is_ab_test = 1, request_precise_location = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$primaryUrl, $preciseAb, $id, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE links SET original_url = ?, is_ab_test = 1 WHERE id = ? AND user_id = ?");
            $upd->execute([$primaryUrl, $id, $userId]);
        }
        $syncEdgeCacheForLink($id, 'edit_ab_test');

        echo json_encode(['success' => true]);
        exit;
    }

    // APLICAR UTM A UM LINK EXISTENTE
    if ($action === 'apply_utm') {
        $requireFeature('bulk_utm');

        $id = (int)$_POST['link_id'];
        
        // Busca a URL original
        $stmt = $pdo->prepare("SELECT original_url FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $link = $stmt->fetch();

        if (!$link) {
            echo json_encode(['success' => false, 'message' => 'Link não encontrado.']);
            exit;
        }

        $url = $link['original_url'];
        $parts = parse_url($url);
        
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // Mescla com os novos parâmetros UTM (se fornecidos)
        $tags = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($tags as $tag) {
            if (!empty($_POST[$tag])) {
                $query[$tag] = $_POST[$tag];
            }
        }

        // Reconstrói a URL sem duplicar ? ou quebrar o fragmento #
        $newQuery = http_build_query($query);
        
        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = isset($parts['host']) ? $parts['host'] : '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user     = isset($parts['user']) ? $parts['user'] : '';
        $pass     = isset($parts['pass']) ? ':' . $parts['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parts['path']) ? $parts['path'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $newUrl = "$scheme$user$pass$host$port$path" . ($newQuery ? "?$newQuery" : "") . "$fragment";

        $campaignId = null;
        $campaignName = trim((string)($_POST['utm_campaign'] ?? ''));
        if ($campaignName !== '' && $hasCampaignsTable && $hasLinksCampaignId) {
            $normalized = normalizeCampaignName($campaignName);
            $findCampaign = $pdo->prepare("SELECT id FROM campaigns WHERE user_id = ? AND normalized_name = ? LIMIT 1");
            $findCampaign->execute([$userId, $normalized]);
            $campaignId = (int)($findCampaign->fetchColumn() ?: 0);

            if ($campaignId <= 0) {
                $enforceCampaignCreationLimit();
                $insertCampaign = $pdo->prepare("INSERT INTO campaigns (user_id, name, normalized_name) VALUES (?, ?, ?)");
                $insertCampaign->execute([$userId, $campaignName, $normalized]);
                $campaignId = (int)$pdo->lastInsertId();
            } else {
                $updateCampaign = $pdo->prepare("UPDATE campaigns SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
                $updateCampaign->execute([$campaignName, $campaignId, $userId]);
            }
        }

        if ($hasLinksCampaignId) {
            $stmt = $pdo->prepare("UPDATE links SET original_url = ?, campaign_id = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$newUrl, $campaignId ?: null, $id, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE links SET original_url = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$newUrl, $id, $userId]);
        }

        $syncEdgeCacheForLink($id, 'apply_utm');
        echo json_encode(['success' => true, 'new_url' => $newUrl]);
        exit;
    }

    if ($action === 'update_campaign_link_utm') {
        $requireFeature('bulk_utm');

        $id = (int)($_POST['link_id'] ?? 0);
        $campaignName = trim((string)($_POST['campaign_name'] ?? $_POST['utm_campaign'] ?? ''));
        if ($id <= 0 || $campaignName === '') {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT original_url FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link) {
            echo json_encode(['success' => false, 'message' => 'Link não encontrado.']);
            exit;
        }

        $url = (string)$link['original_url'];
        $parts = parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $tags = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($tags as $tag) {
            $value = trim((string)($_POST[$tag] ?? ''));
            if ($tag === 'utm_campaign') {
                $value = $campaignName;
            }
            if ($value === '') {
                unset($query[$tag]);
            } else {
                $query[$tag] = $value;
            }
        }

        $newUrl = rebuildUrlWithQueryParams($url, $query);

        $campaignId = null;
        if ($hasCampaignsTable && $hasLinksCampaignId) {
            $normalized = normalizeCampaignName($campaignName);
            $findCampaign = $pdo->prepare("SELECT id FROM campaigns WHERE user_id = ? AND normalized_name = ? LIMIT 1");
            $findCampaign->execute([$userId, $normalized]);
            $campaignId = (int)($findCampaign->fetchColumn() ?: 0);

            if ($campaignId <= 0) {
                $enforceCampaignCreationLimit();
                $insertCampaign = $pdo->prepare("INSERT INTO campaigns (user_id, name, normalized_name) VALUES (?, ?, ?)");
                $insertCampaign->execute([$userId, $campaignName, $normalized]);
                $campaignId = (int)$pdo->lastInsertId();
            } else {
                $updateCampaign = $pdo->prepare("UPDATE campaigns SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
                $updateCampaign->execute([$campaignName, $campaignId, $userId]);
            }
        }

        if ($hasLinksCampaignId) {
            $upd = $pdo->prepare("UPDATE links SET original_url = ?, campaign_id = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$newUrl, $campaignId ?: null, $id, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE links SET original_url = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$newUrl, $id, $userId]);
        }

        $syncEdgeCacheForLink($id, 'update_campaign_link_utm');
        echo json_encode(['success' => true, 'new_url' => $newUrl]);
        exit;
    }

    if ($action === 'remove_link_from_campaign') {
        $requireFeature('bulk_utm');

        $id = (int)($_POST['link_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Link inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT original_url FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link) {
            echo json_encode(['success' => false, 'message' => 'Link não encontrado.']);
            exit;
        }

        $url = (string)$link['original_url'];
        $parts = parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);
        $newUrl = rebuildUrlWithQueryParams($url, $query);

        if ($hasLinksCampaignId) {
            $upd = $pdo->prepare("UPDATE links SET original_url = ?, campaign_id = NULL WHERE id = ? AND user_id = ?");
            $upd->execute([$newUrl, $id, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE links SET original_url = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$newUrl, $id, $userId]);
        }

        $syncEdgeCacheForLink($id, 'remove_link_from_campaign');
        echo json_encode(['success' => true, 'new_url' => $newUrl]);
        exit;
    }

    if ($action === 'create_campaign') {
        $requireFeature('bulk_utm');

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Informe o nome da campanha.']);
            exit;
        }

        if (!($hasCampaignsTable && $hasLinksCampaignId)) {
            echo json_encode(['success' => false, 'message' => 'Estrutura de campanhas ainda não disponível.']);
            exit;
        }

        $normalized = normalizeCampaignName($name);
        $findCampaign = $pdo->prepare("SELECT id, name FROM campaigns WHERE user_id = ? AND normalized_name = ? LIMIT 1");
        $findCampaign->execute([$userId, $normalized]);
        $existing = $findCampaign->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => true,
                'campaign' => ['id' => (int)$existing['id'], 'name' => (string)$existing['name']],
                'already_exists' => true
            ]);
            exit;
        }

        $enforceCampaignCreationLimit();
        $insertCampaign = $pdo->prepare("INSERT INTO campaigns (user_id, name, normalized_name) VALUES (?, ?, ?)");
        $insertCampaign->execute([$userId, $name, $normalized]);
        $campaignId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'campaign' => ['id' => $campaignId, 'name' => $name],
            'already_exists' => false
        ]);
        exit;
    }

    // ALTERAR TIPO DO LINK (link <=> qr)
    if ($action === 'update_type') {
        $id = (int)$_POST['id'];
        $type = ($_POST['type'] === 'qr') ? 'qr' : 'link';

        $currentStmt = $pdo->prepare("SELECT type FROM links WHERE id = ? AND user_id = ? LIMIT 1");
        $currentStmt->execute([$id, $userId]);
        $currentType = (string)$currentStmt->fetchColumn();
        if ($currentType === '') {
            echo json_encode(['success' => false, 'message' => 'Link não encontrado ou sem permissão.']);
            exit;
        }

        if ($currentType !== $type) {
            $limitKey = ($type === 'qr') ? 'max_qrs' : 'max_links';
            $maxAllowed = EntitlementService::getEffectiveLimit($userId, $limitKey, 0);
            if ($maxAllowed <= 0) {
                echo json_encode(['success' => false, 'error' => 'quota_exceeded', 'message' => 'Seu plano não permite este tipo de item.']);
                exit;
            }
            if ($maxAllowed < 999999) {
                if ($hasLinksCreatedAtColumn) {
                    $cycle = EntitlementService::getUsageCycleWindow($userId);
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND type = ? AND created_at >= ? AND created_at < ?");
                    $countStmt->execute([$userId, $type, $cycle['start'], $cycle['end']]);
                } else {
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ? AND type = ?");
                    $countStmt->execute([$userId, $type]);
                }
                $currentCount = (int)$countStmt->fetchColumn();
                if ($currentCount >= $maxAllowed) {
                    echo json_encode(['success' => false, 'error' => 'quota_exceeded', 'message' => 'Você atingiu o limite deste tipo no seu plano.']);
                    exit;
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE links SET type = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$type, $id, $userId]);
        $syncEdgeCacheForLink($id, 'update_type');
        echo json_encode(['success' => true]);
        exit;
    }

    // --- CONVERSIONS & TRACKING CRUD ---

    // 0. Salvar Pixels de Retargeting Externo (Facebook, Google Ads, LinkedIn, X, Pinterest e TikTok)
    if ($action === 'save_retargeting_pixels') {
        $requireFeature('tracking');

        if (!$hasRetargetingPixelsColumn) {
            echo json_encode(['success' => false, 'message' => 'Coluna retargeting_pixels_json não encontrada. Rode a migration.']);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $pixelsPayload = trim((string)($_POST['pixels'] ?? ''));
        $decoded = json_decode($pixelsPayload, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Link inválido para salvar pixels de retargeting.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado ou sem permissão.']);
            exit;
        }

        $normalized = normalizeRetargetingPixelsInput($decoded);
        $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $upd = $pdo->prepare("UPDATE links SET retargeting_pixels_json = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $upd->execute([$normalizedJson, $id]);
        $syncEdgeCacheForLink($id, 'save_retargeting_pixels');

        echo json_encode([
            'success' => true,
            'retargeting_pixels' => $normalized
        ]);
        exit;
    }

    // 1. Ligar/Desligar Rastreamento no link
    if ($action === 'toggle_tracking') {
        $requireFeature('tracking');

        $id = (int)$_POST['id'];
        $status = (int)$_POST['status']; // 1 or 0
        $stmt = $pdo->prepare("UPDATE links SET tracking_enabled = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
        $syncEdgeCacheForLink($id, 'toggle_tracking');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle_dwell_monitor') {
        if (!$hasMonitorDwellColumn) {
            echo json_encode(['success' => false, 'message' => 'Coluna monitor_dwell_enabled não encontrada. Rode a migration.']);
            exit;
        }

        if (!EntitlementService::canAccessFeature($userId, 'dwell_monitor')) {
            echo json_encode([
                'success' => false,
                'error' => 'feature_locked',
                'message' => 'Seu plano atual não permite monitoramento de permanência.'
            ]);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $enabled = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, user_id, type, monitor_dwell_enabled FROM links WHERE id = ? AND user_id IN ($teamUserIdsStr) LIMIT 1");
        $stmt->execute([$id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link) {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado ou sem permissão.']);
            exit;
        }

        $ownerUserId = (int)($link['user_id'] ?? 0);
        $linkType = (string)($link['type'] ?? 'link');
        $currentlyEnabled = (int)($link['monitor_dwell_enabled'] ?? 0) === 1;

        if ($enabled === 1 && !$currentlyEnabled) {
            $countStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM links
                WHERE user_id = ?
                  AND type = ?
                  AND monitor_dwell_enabled = 1
            ");
            $countStmt->execute([$ownerUserId, $linkType]);
            $activeCount = (int)$countStmt->fetchColumn();

            if (!EntitlementService::canActivateDwellMonitor($ownerUserId, $linkType, $activeCount)) {
                $limitKey = $linkType === 'qr' ? 'max_qr_dwell_monitors' : 'max_link_dwell_monitors';
                $limitVal = EntitlementService::getEffectiveLimit($ownerUserId, $limitKey, 0);
                echo json_encode([
                    'success' => false,
                    'error' => 'quota_exceeded',
                    'message' => 'Você atingiu o limite do plano para monitoramento de permanência neste tipo de item.',
                    'limit' => $limitVal,
                    'used' => $activeCount
                ]);
                exit;
            }
        }

        $upd = $pdo->prepare("UPDATE links SET monitor_dwell_enabled = ? WHERE id = ? AND user_id IN ($teamUserIdsStr)");
        $upd->execute([$enabled, $id]);
        $syncEdgeCacheForLink($id, 'toggle_dwell_monitor');
        echo json_encode(['success' => true]);
        exit;
    }

    // 2. Buscar todas as Ações cadastradas de um link
    if ($action === 'get_actions') {
        $link_id = (int)$_GET['link_id'];
        $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$link_id, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado Acesso negado']);
            exit;
        }

        $stmtA = $pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM conversions_log WHERE action_id = a.id) as total_conversions FROM conversion_actions a WHERE a.link_id = ? ORDER BY a.id ASC");
        $stmtA->execute([$link_id]);
        $actions = $stmtA->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'actions' => $actions]);
        exit;
    }

    // 3. Salvar nova ação ou editar existente
    if ($action === 'save_action') {
        $requireFeature('tracking');

        $link_id = (int)$_POST['link_id'];
        $action_id = !empty($_POST['action_id']) ? (int)$_POST['action_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $ref_slug = trim($_POST['ref_slug'] ?? '');

        // Validar slug
        if (preg_match('/[^a-zA-Z0-9_\-]/', $ref_slug)) {
            echo json_encode(['success' => false, 'message' => 'O slug deve conter apenas letras, números, _ ou -']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$link_id, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }

        try {
            if ($action_id) {
                // Update
                $upd = $pdo->prepare("UPDATE conversion_actions SET name = ?, ref_slug = ? WHERE id = ? AND link_id = ?");
                $upd->execute([$name, $ref_slug, $action_id, $link_id]);
            } else {
                // Insert
                $ins = $pdo->prepare("INSERT INTO conversion_actions (link_id, name, ref_slug, is_active) VALUES (?, ?, ?, 1)");
                $ins->execute([$link_id, $name, $ref_slug]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Slug já existe para este link. Escolha outro.']);
        }
        exit;
    }

    // 4. Ligar/desligar uma Ação
    if ($action === 'toggle_action') {
        $requireFeature('tracking');

        $action_id = (int)$_POST['action_id'];
        $link_id = (int)$_POST['link_id'];
        $status = (int)$_POST['status']; // 1 or 0
        $stmt = $pdo->prepare("UPDATE conversion_actions SET is_active = ? WHERE id = ? AND link_id = ? AND EXISTS (SELECT 1 FROM links WHERE id = ? AND user_id = ?)");
        $stmt->execute([$status, $action_id, $link_id, $link_id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 5. Excluir uma ação
    if ($action === 'delete_action') {
        $requireFeature('tracking');

        $action_id = (int)$_POST['action_id'];
        $link_id = (int)$_POST['link_id'];
        $stmt = $pdo->prepare("DELETE FROM conversion_actions WHERE id = ? AND link_id = ? AND EXISTS (SELECT 1 FROM links WHERE id = ? AND user_id = ?)");
        $stmt->execute([$action_id, $link_id, $link_id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }


} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>