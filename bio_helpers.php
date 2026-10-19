<?php

function bio_page_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return [];
    }

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['Field']] = true;
    }

    $cache[$table] = $columns;
    return $columns;
}

function bio_has_column(PDO $pdo, string $table, string $column): bool
{
    $columns = bio_page_columns($pdo, $table);
    return isset($columns[$column]);
}

function bio_safe_json_decode($value, $fallback)
{
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function bio_generate_block_id(string $prefix = 'block'): string
{
    return $prefix . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 10);
}

function bio_normalize_text($value, string $default = ''): string
{
    if (is_scalar($value)) {
        return trim((string) $value);
    }

    return $default;
}

function bio_sanitize_link_item(array $item): array
{
    $linkType = bio_normalize_text($item['link_type'] ?? 'link', 'link');
    if (!in_array($linkType, ['link', 'social', 'pix'], true)) {
        $linkType = 'link';
    }

    return [
        'id' => bio_normalize_text($item['id'] ?? '', ''),
        'title' => bio_normalize_text($item['title'] ?? '', ''),
        'url' => bio_normalize_text($item['url'] ?? '', ''),
        'icon_class' => bio_normalize_text($item['icon_class'] ?? '', ''),
        'is_featured' => !empty($item['is_featured']) ? 1 : 0,
        'link_type' => $linkType,
        'social_network' => bio_normalize_text($item['social_network'] ?? '', ''),
        'social_value' => bio_normalize_text($item['social_value'] ?? '', ''),
        'element_styles' => bio_normalize_element_styles($item['element_styles'] ?? null),
    ];
}

function bio_normalize_form_field_item(array $item): array
{
    $allowedTypes = [
        'short_text', 'long_text', 'email', 'phone', 'number', 'date', 'time', 'url',
        'select', 'radio', 'checkbox', 'multiselect',
    ];
    $ft = bio_normalize_text($item['field_type'] ?? $item['type'] ?? 'short_text', 'short_text');
    if (!in_array($ft, $allowedTypes, true)) {
        $ft = 'short_text';
    }
    $opts = [];
    if (!empty($item['options']) && is_array($item['options'])) {
        foreach ($item['options'] as $o) {
            if (!is_array($o)) {
                continue;
            }
            $opts[] = [
                'value' => bio_normalize_text($o['value'] ?? '', ''),
                'label' => bio_normalize_text($o['label'] ?? '', ''),
            ];
        }
    }
    $showIf = null;
    if (!empty($item['show_if']) && is_array($item['show_if'])) {
        $fk = bio_normalize_text($item['show_if']['field_key'] ?? '', '');
        if ($fk !== '') {
            $showIf = [
                'field_key' => $fk,
                'operator' => bio_normalize_text($item['show_if']['operator'] ?? 'eq', 'eq'),
                'value' => bio_normalize_text($item['show_if']['value'] ?? '', ''),
            ];
        }
    }
    $maxLen = (int) ($item['max_length'] ?? 0);
    if ($maxLen < 0) {
        $maxLen = 0;
    }

    return [
        'id' => bio_normalize_text($item['id'] ?? '', bio_generate_block_id('fld')),
        'field_type' => $ft,
        'label' => bio_normalize_text($item['label'] ?? $item['title'] ?? '', 'Pergunta'),
        'required' => !empty($item['required']),
        'placeholder' => bio_normalize_text($item['placeholder'] ?? '', ''),
        'help_text' => bio_normalize_text($item['help_text'] ?? '', ''),
        'max_length' => $maxLen,
        'mask_pattern' => bio_normalize_text($item['mask_pattern'] ?? '', ''),
        'default_value' => bio_normalize_text($item['default_value'] ?? '', ''),
        'options' => $opts,
        'show_if' => $showIf,
        'element_styles' => bio_normalize_element_styles($item['element_styles'] ?? null),
    ];
}

function bio_normalize_typography_slot(?array $slot): array
{
    if (!is_array($slot)) {
        return [];
    }
    $keys = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'color', 'textTransform'];
    $out = [];
    foreach ($keys as $k) {
        if (!array_key_exists($k, $slot)) {
            continue;
        }
        $out[$k] = bio_normalize_text((string) $slot[$k], '');
    }

    return array_filter($out, static fn($v) => $v !== '');
}

function bio_normalize_element_styles($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $slot => $slotVal) {
        if (!is_string($slot)) {
            continue;
        }
        $slot = preg_replace('/[^a-z0-9_-]/i', '', $slot);
        if ($slot === '' || !is_array($slotVal)) {
            continue;
        }
        $norm = bio_normalize_typography_slot($slotVal);
        if (!empty($norm)) {
            $out[$slot] = $norm;
        }
    }

    return $out;
}

function bio_element_styles_css(?array $slot): string
{
    if (!is_array($slot) || empty($slot)) {
        return '';
    }
    $parts = [];
    if (!empty($slot['fontFamily'])) {
        $f = (string) $slot['fontFamily'];
        if (!preg_match('/[<>{;]|url\s*\(/i', $f)) {
            $parts[] = 'font-family:' . $f;
        }
    }
    if (!empty($slot['fontSize'])) {
        $s = (string) $slot['fontSize'];
        if (preg_match('/^[0-9a-z.%()\s,+×x\-]+$/i', $s)) {
            $parts[] = 'font-size:' . $s;
        }
    }
    if (!empty($slot['fontWeight'])) {
        $w = (string) $slot['fontWeight'];
        if (preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/', $w)) {
            $parts[] = 'font-weight:' . $w;
        }
    }
    if (!empty($slot['lineHeight'])) {
        $lh = (string) $slot['lineHeight'];
        if ($lh === 'normal' || preg_match('/^[0-9.]+$/', $lh)) {
            $parts[] = 'line-height:' . $lh;
        }
    }
    if (!empty($slot['letterSpacing'])) {
        $ls = (string) $slot['letterSpacing'];
        if (preg_match('/^[0-9a-z.\-]+$/i', $ls)) {
            $parts[] = 'letter-spacing:' . $ls;
        }
    }
    if (!empty($slot['color'])) {
        $c = trim((string) $slot['color']);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c)
            || preg_match('/^rgba?\\(\\s*[0-9.,\\s%]+\\)$/i', $c)) {
            $parts[] = 'color:' . $c;
        }
    }
    if (!empty($slot['textTransform'])) {
        $t = strtolower((string) $slot['textTransform']);
        if (in_array($t, ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $parts[] = 'text-transform:' . $t;
        }
    }

    return implode(';', $parts);
}

function bio_element_styles_attr(?array $slot): string
{
    $css = bio_element_styles_css($slot);
    if ($css === '') {
        return '';
    }

    return ' style="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '"';
}

function bio_normalize_block(array $block): array
{
    $type = bio_normalize_text($block['type'] ?? 'text', 'text');
    $avatarShape = bio_normalize_text($block['avatar_shape'] ?? '', 'rounded');
    if (!in_array($avatarShape, ['rounded', 'circle', 'square', 'banner', 'soft'], true)) {
        $avatarShape = 'rounded';
    }
    $embedProvider = bio_normalize_text($block['embed_provider'] ?? '', 'youtube');
    if (!in_array($embedProvider, ['youtube', 'google_maps', 'spotify', 'vimeo'], true)) {
        $embedProvider = 'youtube';
    }
    $normalized = [
        'id' => bio_normalize_text($block['id'] ?? '', bio_generate_block_id($type)),
        'type' => $type,
        'title' => bio_normalize_text($block['title'] ?? '', ''),
        'subtitle' => bio_normalize_text($block['subtitle'] ?? '', ''),
        'content' => bio_normalize_text($block['content'] ?? '', ''),
        'style' => bio_normalize_text($block['style'] ?? '', 'default'),
        'layout' => bio_normalize_text($block['layout'] ?? '', 'stack'),
        'badge' => bio_normalize_text($block['badge'] ?? '', ''),
        'label' => bio_normalize_text($block['label'] ?? '', ''),
        'url' => bio_normalize_text($block['url'] ?? '', ''),
        'icon_class' => bio_normalize_text($block['icon_class'] ?? '', ''),
        'media_url' => bio_normalize_text($block['media_url'] ?? '', ''),
        'cover_image_url' => bio_normalize_text($block['cover_image_url'] ?? '', ''),
        'avatar_shape' => $avatarShape,
        'embed_provider' => $embedProvider,
        'embed_url' => bio_normalize_text($block['embed_url'] ?? '', ''),
        'element_styles' => bio_normalize_element_styles($block['element_styles'] ?? null),
        'items' => [],
    ];

    if (!empty($block['items']) && is_array($block['items'])) {
        foreach ($block['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($type === 'form') {
                $normalized['items'][] = bio_normalize_form_field_item($item);
                continue;
            }

            $normalized['items'][] = [
                'id' => bio_normalize_text($item['id'] ?? '', bio_generate_block_id('item')),
                'title' => bio_normalize_text($item['title'] ?? '', ''),
                'label' => bio_normalize_text($item['label'] ?? '', ''),
                'text' => bio_normalize_text($item['text'] ?? '', ''),
                'url' => bio_normalize_text($item['url'] ?? '', ''),
                'image_url' => bio_normalize_text($item['image_url'] ?? '', ''),
                'caption' => bio_normalize_text($item['caption'] ?? '', ''),
                'badge' => bio_normalize_text($item['badge'] ?? '', ''),
                'meta' => bio_normalize_text($item['meta'] ?? '', ''),
                'icon_class' => bio_normalize_text($item['icon_class'] ?? '', ''),
                'link_type' => bio_normalize_text($item['link_type'] ?? 'link', 'link'),
                'social_network' => bio_normalize_text($item['social_network'] ?? '', ''),
                'social_value' => bio_normalize_text($item['social_value'] ?? '', ''),
                'is_featured' => !empty($item['is_featured']) ? 1 : 0,
                'element_styles' => bio_normalize_element_styles($item['element_styles'] ?? null),
            ];
        }
    }

    return $normalized;
}

function bio_normalize_blocks(array $blocks): array
{
    $normalized = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $normalized[] = bio_normalize_block($block);
    }

    return $normalized;
}

function bio_create_default_blocks(array $page, array $links): array
{
    $blocks = [];

    $blocks[] = bio_normalize_block([
        'id' => 'hero-default',
        'type' => 'hero',
        'title' => $page['title'] ?? '',
        'subtitle' => $page['slug'] ?? '',
        'content' => $page['description'] ?? '',
        'media_url' => $page['avatar_url'] ?? '',
        'cover_image_url' => '',
        'avatar_shape' => 'rounded',
        'style' => 'profile',
    ]);

    $featured = null;
    $primaryLinks = [];
    $socialItems = [];

    foreach ($links as $link) {
        $item = bio_sanitize_link_item($link);
        if ($item['title'] === '' && $item['url'] === '' && $item['social_value'] === '') {
            continue;
        }

        if ($item['is_featured'] && !$featured) {
            $featured = $item;
        }

        if ($item['link_type'] === 'social') {
            $socialItems[] = $item;
            continue;
        }

        $primaryLinks[] = $item;
    }

    if ($featured) {
        $blocks[] = bio_normalize_block([
            'type' => 'cta',
            'title' => $featured['title'],
            'content' => $page['description'] ?? '',
            'label' => $featured['title'],
            'url' => $featured['url'],
            'icon_class' => $featured['icon_class'],
            'style' => 'primary',
        ]);
    }

    if (!empty($primaryLinks)) {
        $blocks[] = bio_normalize_block([
            'type' => 'links',
            'title' => 'Links principais',
            'layout' => 'stack',
            'items' => $primaryLinks,
        ]);
    }

    if (!empty($socialItems)) {
        $blocks[] = bio_normalize_block([
            'type' => 'social',
            'title' => 'Redes e contatos',
            'layout' => 'grid',
            'items' => $socialItems,
        ]);
    }

    if (!empty($page['whatsapp_number']) || !empty($page['pix_key'])) {
        $blocks[] = bio_normalize_block([
            'type' => 'contact',
            'title' => 'Contato rápido',
            'items' => [[
                'title' => 'WhatsApp',
                'text' => bio_normalize_text($page['whatsapp_number'] ?? '', ''),
                'icon_class' => 'fab fa-whatsapp',
            ], [
                'title' => 'Pix',
                'text' => bio_normalize_text($page['pix_key'] ?? '', ''),
                'icon_class' => 'fas fa-qrcode',
            ]],
        ]);
    }

    return $blocks;
}

function bio_resolve_schema(array $page, array $links): array
{
    $pageType = bio_normalize_text($page['page_type'] ?? 'bio', 'bio');
    $pageStatus = bio_normalize_text($page['page_status'] ?? 'published', 'published');
    $selectedGoalKey = bio_normalize_text($page['selected_goal_key'] ?? '', '');

    $schema = bio_safe_json_decode($page['builder_schema_json'] ?? '', []);
    if (!is_array($schema) || empty($schema['blocks']) || !is_array($schema['blocks'])) {
        $schema = [
            'version' => 2,
            'page_type' => $pageType,
            'page_status' => $pageStatus,
            'selected_goal_key' => $selectedGoalKey,
            'blocks' => bio_create_default_blocks($page, $links),
        ];
    }

    $schema['version'] = (int) ($schema['version'] ?? 2);
    $schema['page_type'] = bio_normalize_text($schema['page_type'] ?? $pageType, $pageType);
    $schema['page_status'] = bio_normalize_text($schema['page_status'] ?? $pageStatus, $pageStatus);
    $schema['selected_goal_key'] = bio_normalize_text($schema['selected_goal_key'] ?? $selectedGoalKey, $selectedGoalKey);
    $schema['blocks'] = bio_normalize_blocks($schema['blocks']);

    return $schema;
}

function bio_flatten_action_links_from_schema(array $schema): array
{
    $links = [];

    if (empty($schema['blocks']) || !is_array($schema['blocks'])) {
        return $links;
    }

    foreach ($schema['blocks'] as $block) {
        if (!is_array($block)) {
            continue;
        }

        $type = $block['type'] ?? '';
        if ($type === 'cta') {
            $links[] = bio_sanitize_link_item([
                'title' => $block['label'] ?: ($block['title'] ?? ''),
                'url' => $block['url'] ?? '',
                'icon_class' => $block['icon_class'] ?? '',
                'is_featured' => 1,
                'link_type' => 'link',
            ]);
            continue;
        }

        if (in_array($type, ['links', 'social'], true) && !empty($block['items']) && is_array($block['items'])) {
            foreach ($block['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $links[] = bio_sanitize_link_item([
                    'title' => $item['title'] ?? ($item['label'] ?? ''),
                    'url' => $item['url'] ?? '',
                    'icon_class' => $item['icon_class'] ?? '',
                    'is_featured' => $item['is_featured'] ?? 0,
                    'link_type' => $type === 'social' ? 'social' : ($item['link_type'] ?? 'link'),
                    'social_network' => $item['social_network'] ?? '',
                    'social_value' => $item['social_value'] ?? '',
                ]);
            }
        }
    }

    return array_values(array_filter($links, function ($item) {
        return !empty($item['title']) || !empty($item['url']) || !empty($item['social_value']);
    }));
}

function bio_collect_urls_from_schema(array $node, array &$urls): void
{
    foreach (['url', 'media_url', 'cover_image_url', 'embed_url', 'image_url'] as $key) {
        if (!empty($node[$key]) && is_string($node[$key])) {
            $urls[] = $node[$key];
        }
    }
    if (!empty($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as $item) {
            if (is_array($item)) {
                bio_collect_urls_from_schema($item, $urls);
            }
        }
    }
    if (!empty($node['blocks']) && is_array($node['blocks'])) {
        foreach ($node['blocks'] as $block) {
            if (is_array($block)) {
                bio_collect_urls_from_schema($block, $urls);
            }
        }
    }
}

function bio_validate_schema_urls(PDO $pdo, int $userId, array $schema): void
{
    require_once __DIR__ . '/AbusePolicyService.php';
    $urls = [];
    bio_collect_urls_from_schema($schema, $urls);
    foreach (array_unique($urls) as $url) {
        if (!preg_match('#^https?://#i', (string)$url)) {
            continue;
        }
        $block = AbusePolicyService::validateUrlEdit($pdo, $userId, (string)$url);
        if ($block !== null) {
            header('Content-Type: application/json');
            echo json_encode($block);
            exit;
        }
    }
}