<?php
require_once 'db.php';
require_once 'bio_helpers.php';
require_once 'user_ban_helpers.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(404);
    die('Pagina nao encontrada.');
}

$stmt = $pdo->prepare('SELECT * FROM bio_pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    die('Pagina nao encontrada.');
}

$ownerBan = fetchUserBanRow($pdo, (int)$page['user_id']);
if ($ownerBan && isUserCurrentlyBanned($ownerBan)) {
    header('Location: /blocked.php?reason=paused', true, 302);
    exit;
}
if (($page['page_status'] ?? 'published') === 'paused') {
    header('Location: /blocked.php?reason=paused', true, 302);
    exit;
}
if (userBanBioColumnReady($pdo) && (int)($page['admin_ban_paused'] ?? 0) === 1) {
    header('Location: /blocked.php?reason=paused', true, 302);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM bio_links WHERE bio_page_id = ? ORDER BY display_order ASC');
$stmt->execute([$page['id']]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
$schema = bio_resolve_schema($page, $links);

function bio_public_track_page_view(PDO $pdo, int $pageId): void
{
    static $tableChecked = false;
    static $tableExists = false;

    if (!$tableChecked) {
        $tableChecked = true;
        try {
            $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'bio_page_views' LIMIT 1");
            $tableExists = (bool) $check->fetchColumn();
        } catch (Throwable $e) {
            $tableExists = false;
        }
    }

    if (!$tableExists) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO bio_page_views (bio_page_id, visitor_ip, user_agent, referer, viewed_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([
            $pageId,
            getRealIP(),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            (string) ($_SERVER['HTTP_REFERER'] ?? ''),
        ]);
    } catch (Throwable $e) {
        // Não interrompe renderização pública da página por falha de analytics.
    }
}

bio_public_track_page_view($pdo, (int) $page['id']);

$bgColor = $page['theme_bg_color'] ?? '#0f172a';
$btnColor = $page['theme_btn_color'] ?? '#1A8CFF';
$textColor = $page['theme_text_color'] ?? '#ffffff';
$btnStyle = $page['theme_btn_style'] ?? 'solid';
$templateId = (int) ($page['template_id'] ?? 1);
$pageType = $schema['page_type'] ?? ($page['page_type'] ?? 'bio');

$allowedButtonStyles = ['solid', 'outline', 'rounded', 'pill'];
if (!in_array($btnStyle, $allowedButtonStyles, true)) {
    $btnStyle = 'solid';
}

function bio_public_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bio_public_multiline($value): string
{
    return nl2br(bio_public_html($value));
}

function bio_public_js_string($value): string
{
    return json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function bio_public_base_path(): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($base === '/' || $base === '.') {
        return '';
    }
    return rtrim($base, '/');
}

function bio_public_asset_url(string $relativePath): string
{
    return bio_public_base_path() . '/' . ltrim($relativePath, '/');
}

function bio_public_template_info(int $templateId): array
{
    $templateSlug = 'classic';
    $templateFolder = 'classic';
    $templatesDir = __DIR__ . '/templates/bio';

    if (is_dir($templatesDir)) {
        $folders = array_diff(scandir($templatesDir), ['.', '..']);
        foreach ($folders as $folder) {
            $configPath = $templatesDir . '/' . $folder . '/config.json';
            if (!file_exists($configPath)) {
                continue;
            }

            $config = json_decode((string) file_get_contents($configPath), true);
            if (!$config || !isset($config['id']) || (int) $config['id'] !== $templateId) {
                continue;
            }

            $templateFolder = $folder;
            $templateSlug = $config['slug'] ?? $folder;
            break;
        }
    }

    return [
        'slug' => $templateSlug,
        'folder' => $templateFolder,
        'css_path' => "templates/bio/{$templateFolder}/style.css",
    ];
}

function getSocialLink($network, $value): array
{
    $val = trim((string) $value);
    $clean = preg_replace('/[^a-zA-Z0-9._]/', '', $val);
    $phone = preg_replace('/\D/', '', $val);

    switch ($network) {
        case 'instagram':
            return ['web' => 'https://instagram.com/' . ltrim($val, '@'), 'app' => 'instagram://user?username=' . ltrim($val, '@')];
        case 'tiktok':
            return ['web' => 'https://tiktok.com/@' . ltrim($val, '@'), 'app' => 'snssdk1233://user/profile/' . $clean];
        case 'youtube':
            return ['web' => 'https://youtube.com/@' . ltrim($val, '@'), 'app' => 'vnd.youtube://www.youtube.com/@' . ltrim($val, '@')];
        case 'whatsapp':
            return ['web' => 'https://wa.me/' . $phone, 'app' => 'whatsapp://send?phone=' . $phone];
        case 'facebook':
            return ['web' => 'https://facebook.com/' . $val, 'app' => 'fb://facewebmodal/f?href=https://facebook.com/' . $val];
        case 'twitter':
            return ['web' => 'https://x.com/' . ltrim($val, '@'), 'app' => 'twitter://user?screen_name=' . ltrim($val, '@')];
        case 'linkedin':
            return ['web' => 'https://linkedin.com/in/' . $val, 'app' => 'linkedin://profile/' . $val];
        case 'telegram':
            return ['web' => 'https://t.me/' . ltrim($val, '@'), 'app' => 'tg://resolve?domain=' . ltrim($val, '@')];
        case 'spotify':
            return ['web' => $val, 'app' => str_replace('https://open.spotify.com/', 'spotify:', $val)];
        case 'behance':
            return ['web' => 'https://behance.net/' . $val, 'app' => 'https://behance.net/' . $val];
        case 'github':
            return ['web' => 'https://github.com/' . $val, 'app' => 'https://github.com/' . $val];
        default:
            return ['web' => $val, 'app' => $val];
    }
}

function bio_public_button_style(array $item, array $page, string $btnStyle): string
{
    if (($item['link_type'] ?? '') === 'social' && ($item['social_network'] ?? '') === 'whatsapp') {
        return 'background:#25D366;color:#ffffff;border:none;';
    }

    if (($item['link_type'] ?? '') === 'pix') {
        return 'background:#32BCAD;color:#ffffff;border:none;';
    }

    $primary = $page['theme_btn_color'] ?? '#1A8CFF';
    $background = $page['theme_bg_color'] ?? '#0f172a';

    if ($btnStyle === 'outline') {
        return 'background:transparent;border:2px solid ' . $primary . ';color:' . $primary . ';';
    }

    return 'background:' . $primary . ';color:' . $background . ';border:none;';
}

function bio_public_typo_from_block(array $block, string $slot): string
{
    $styles = $block['element_styles'] ?? [];
    if (empty($styles[$slot]) || !is_array($styles[$slot])) {
        return '';
    }

    return bio_element_styles_attr($styles[$slot]);
}

function bio_public_typo_from_item(array $item, string $slot): string
{
    $styles = $item['element_styles'] ?? [];
    if (empty($styles[$slot]) || !is_array($styles[$slot])) {
        return '';
    }

    return bio_element_styles_attr($styles[$slot]);
}

function bio_public_render_button(array $item, array $page, string $btnStyle): string
{
    $title = trim((string) ($item['title'] ?? ($item['label'] ?? 'Acessar')));
    if ($title === '') {
        $title = 'Acessar';
    }

    $iconClass = trim((string) ($item['icon_class'] ?? ''));
    $linkType = trim((string) ($item['link_type'] ?? 'link'));
    $href = trim((string) ($item['url'] ?? '#'));
    $target = ' target="_blank" rel="noopener noreferrer"';
    $classNames = ['btn-bio', $btnStyle];
    $onclick = 'logClick(' . (int) $page['id'] . ', \'bio_page\')';

    if ($linkType === 'social') {
        $social = getSocialLink($item['social_network'] ?? '', $item['social_value'] ?? '');
        $href = '#';
        $target = '';
        $onclick = 'smartOpen(' . bio_public_js_string($social['app']) . ', ' . bio_public_js_string($social['web']) . '); logClick(' . (int) $page['id'] . ', \'bio_page\'); return false;';
        if (($item['social_network'] ?? '') === 'whatsapp') {
            $classNames[] = 'btn-whatsapp';
        }
    } elseif ($linkType === 'pix') {
        $href = '#';
        $target = '';
        $onclick = 'copyPix(' . bio_public_js_string($item['social_value'] ?? '') . '); logClick(' . (int) $page['id'] . ', \'bio_page\'); return false;';
        $classNames[] = 'btn-pix';
    } elseif ($href === '') {
        $href = '#';
        $target = '';
        $onclick .= '; return false;';
    }

    $iconHtml = $iconClass !== '' ? '<i class="' . bio_public_html($iconClass) . '"></i>' : '';
    $spanTypo = bio_public_typo_from_item($item, 'button');

    return '<a href="' . bio_public_html($href) . '"' . $target .
        ' class="' . bio_public_html(implode(' ', array_filter($classNames))) . '"' .
        ' style="' . bio_public_html(bio_public_button_style($item, $page, $btnStyle)) . '"' .
        ' onclick="' . bio_public_html($onclick) . '">' .
        $iconHtml .
        '<span' . $spanTypo . '>' . bio_public_html($title) . '</span>' .
        '</a>';
}

function bio_public_embed_defaults(string $provider): array
{
    $defaults = [
        'youtube' => [
            'label' => 'YouTube',
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'sandbox' => 'allow-scripts allow-same-origin allow-presentation allow-popups',
        ],
        'google_maps' => [
            'label' => 'Google Maps',
            'allow' => 'geolocation; fullscreen',
            'referrerpolicy' => 'no-referrer-when-downgrade',
            'sandbox' => 'allow-scripts allow-same-origin allow-popups',
        ],
        'spotify' => [
            'label' => 'Spotify',
            'allow' => 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'sandbox' => 'allow-scripts allow-same-origin allow-popups',
        ],
        'vimeo' => [
            'label' => 'Vimeo',
            'allow' => 'autoplay; fullscreen; picture-in-picture',
            'referrerpolicy' => 'strict-origin-when-cross-origin',
            'sandbox' => 'allow-scripts allow-same-origin allow-presentation',
        ],
    ];

    return $defaults[$provider] ?? $defaults['youtube'];
}

function bio_public_embed_meta(string $providerHint, string $url): array
{
    $provider = trim(strtolower($providerHint));
    if (!in_array($provider, ['youtube', 'google_maps', 'spotify', 'vimeo'], true)) {
        $provider = 'youtube';
    }

    $defaults = bio_public_embed_defaults($provider);
    $meta = [
        'provider' => $provider,
        'url' => '',
        'allow' => $defaults['allow'],
        'referrerpolicy' => $defaults['referrerpolicy'],
        'sandbox' => $defaults['sandbox'],
        'error' => 'Cole um link valido de ' . $defaults['label'] . '.',
    ];

    $url = trim($url);
    if ($url === '') {
        return $meta;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        $meta['error'] = 'Link invalido. Use uma URL completa com https://.';
        return $meta;
    }

    $host = strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '');
    $query = (string) ($parts['query'] ?? '');

    if ($provider === 'youtube') {
        $videoId = '';
        if (strpos($host, 'youtube.com') !== false) {
            parse_str($query, $params);
            $videoId = trim((string) ($params['v'] ?? ''));
            if ($videoId === '') {
                $partsPath = array_values(array_filter(explode('/', trim($path, '/'))));
                if (!empty($partsPath[0]) && in_array($partsPath[0], ['shorts', 'embed'], true) && !empty($partsPath[1])) {
                    $videoId = (string) $partsPath[1];
                }
            }
        } elseif ($host === 'youtu.be') {
            $videoId = trim($path, '/');
        }

        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $videoId);
        if ($videoId !== '') {
            $meta['url'] = 'https://www.youtube.com/embed/' . $videoId;
            $meta['error'] = '';
            return $meta;
        }
        return $meta;
    }

    if ($provider === 'google_maps') {
        if ((strpos($host, 'google.') !== false && strpos($host, 'maps') !== false) || strpos($host, 'goo.gl') !== false) {
            if (strpos($path, '/maps/embed') !== false) {
                $meta['url'] = 'https://www.google.com' . $path . ($query !== '' ? '?' . $query : '');
            } else {
                $meta['url'] = 'https://www.google.com/maps?q=' . rawurlencode($url) . '&output=embed';
            }
            $meta['error'] = '';
            return $meta;
        }
        return $meta;
    }

    if ($provider === 'spotify') {
        if ($host !== 'open.spotify.com') {
            return $meta;
        }
        $partsPath = array_values(array_filter(explode('/', trim($path, '/'))));
        if (empty($partsPath)) {
            return $meta;
        }
        $type = (string) ($partsPath[0] ?? '');
        $id = (string) ($partsPath[1] ?? '');
        if ($type === 'embed') {
            $type = (string) ($partsPath[1] ?? '');
            $id = (string) ($partsPath[2] ?? '');
        }
        if (!in_array($type, ['track', 'album', 'playlist', 'artist', 'show', 'episode'], true)) {
            return $meta;
        }
        $id = preg_replace('/[^a-zA-Z0-9]/', '', $id);
        if ($id === '') {
            return $meta;
        }
        $meta['url'] = 'https://open.spotify.com/embed/' . $type . '/' . $id;
        $meta['error'] = '';
        return $meta;
    }

    if ($provider === 'vimeo') {
        if (strpos($host, 'vimeo.com') === false) {
            return $meta;
        }
        $videoId = preg_replace('/\D/', '', trim($path, '/'));
        if ($videoId === '') {
            return $meta;
        }
        $meta['url'] = 'https://player.vimeo.com/video/' . $videoId;
        $meta['error'] = '';
        return $meta;
    }

    return $meta;
}

function bio_public_embed_url(string $url): string
{
    $meta = bio_public_embed_meta('youtube', $url);
    return $meta['url'];
}

function bio_public_render_block(array $block, array $page, string $btnStyle): string
{
    $type = $block['type'] ?? 'text';
    $title = trim((string) ($block['title'] ?? ''));
    $subtitle = trim((string) ($block['subtitle'] ?? ''));
    $content = trim((string) ($block['content'] ?? ''));
    $badge = trim((string) ($block['badge'] ?? ''));
    $items = is_array($block['items'] ?? null) ? $block['items'] : [];

    $titleHtml = $title !== '' ? '<div class="bio-block-title"' . bio_public_typo_from_block($block, 'title') . '>' . bio_public_html($title) . '</div>' : '';
    $subtitleHtml = $subtitle !== '' ? '<div class="bio-block-subtitle"' . bio_public_typo_from_block($block, 'subtitle') . '>' . bio_public_html($subtitle) . '</div>' : '';
    $badgeHtml = $badge !== '' ? '<div class="bio-badge"' . bio_public_typo_from_block($block, 'badge') . '>' . bio_public_html($badge) . '</div>' : '';
    $bodyOpen = '<div class="bio-copy"' . bio_public_typo_from_block($block, 'body') . '>';
    $bodyClose = '</div>';

    switch ($type) {
        case 'heading':
            return '<section class="bio-block bio-block--heading">' . $badgeHtml . $titleHtml . $subtitleHtml . '</section>';

        case 'text':
            return '<section class="bio-block bio-block--text">' . $titleHtml . $subtitleHtml . $bodyOpen . bio_public_multiline($content) . $bodyClose . '</section>';

        case 'highlight':
            return '<section class="bio-block bio-highlight">' . $badgeHtml . $titleHtml . $bodyOpen . bio_public_multiline($content) . $bodyClose . '</section>';

        case 'cta':
            $ctaBtn = [
                'title' => $block['label'] ?? $title,
                'url' => $block['url'] ?? '',
                'icon_class' => $block['icon_class'] ?? '',
                'link_type' => 'link',
                'element_styles' => $block['element_styles'] ?? [],
            ];
            return '<section class="bio-block bio-block--cta">' . $titleHtml .
                ($content !== '' ? $bodyOpen . bio_public_multiline($content) . $bodyClose : '') .
                '<div class="bio-action-grid">' .
                bio_public_render_button($ctaBtn, $page, $btnStyle) .
                '</div></section>';

        case 'links':
            $buttons = array_map(function ($item) use ($page, $btnStyle) {
                return bio_public_render_button($item, $page, $btnStyle);
            }, $items);
            return '<section class="bio-block bio-block--links">' . $titleHtml . $subtitleHtml . '<div class="links preview-links bio-action-grid">' . implode('', $buttons) . '</div></section>';

        case 'social':
            $buttons = array_map(function ($item) use ($page, $btnStyle) {
                $item['link_type'] = 'social';
                return bio_public_render_button($item, $page, $btnStyle);
            }, $items);
            return '<section class="bio-block bio-block--social">' . $titleHtml . $subtitleHtml . '<div class="bio-social-grid">' . implode('', $buttons) . '</div></section>';

        case 'cards':
            $cards = array_map(function ($item) use ($page, $btnStyle) {
                $cardTitle = bio_public_html($item['title'] ?? 'Card');
                $cardText = bio_public_html($item['text'] ?? '');
                $cardBadge = trim((string) ($item['badge'] ?? '')) !== '' ? '<div class="bio-badge"' . bio_public_typo_from_item($item, 'badge') . '>' . bio_public_html($item['badge']) . '</div>' : '';
                $cardButton = trim((string) ($item['url'] ?? '')) !== ''
                    ? '<div style="margin-top:12px;">' . bio_public_render_button([
                        'title' => $item['label'] ?? 'Abrir',
                        'url' => $item['url'] ?? '',
                        'icon_class' => $item['icon_class'] ?? 'fas fa-arrow-up-right-from-square',
                        'link_type' => 'link',
                        'element_styles' => $item['element_styles'] ?? [],
                    ], $page, $btnStyle) . '</div>'
                    : '';
                return '<article class="bio-card-item">' . $cardBadge . '<h4' . bio_public_typo_from_item($item, 'cardTitle') . '>' . $cardTitle . '</h4><p' . bio_public_typo_from_item($item, 'cardText') . '>' . $cardText . '</p>' . $cardButton . '</article>';
            }, $items);
            return '<section class="bio-block bio-block--cards">' . $titleHtml . $subtitleHtml . '<div class="bio-card-grid">' . implode('', $cards) . '</div></section>';

        case 'faq':
            $faqItems = array_map(function ($item) {
                return '<article class="bio-faq-item"><div class="bio-faq-question"' . bio_public_typo_from_item($item, 'question') . '>' . bio_public_html($item['title'] ?? 'Pergunta') . '</div><div class="bio-faq-answer"' . bio_public_typo_from_item($item, 'answer') . '>' . bio_public_multiline($item['text'] ?? '') . '</div></article>';
            }, $items);
            return '<section class="bio-block bio-block--faq">' . $titleHtml . $subtitleHtml . '<div class="bio-stack-list">' . implode('', $faqItems) . '</div></section>';

        case 'gallery':
            $galleryItems = array_map(function ($item) {
                $img = trim((string) ($item['image_url'] ?? ''));
                $caption = trim((string) ($item['caption'] ?? ''));
                $imageHtml = $img !== ''
                    ? '<img src="' . bio_public_html($img) . '" alt="' . bio_public_html($caption !== '' ? $caption : 'Imagem da galeria') . '">'
                    : '<div class="bio-gallery-placeholder">Adicione uma imagem</div>';
                $captionHtml = $caption !== '' ? '<div class="bio-gallery-caption"' . bio_public_typo_from_item($item, 'caption') . '>' . bio_public_html($caption) . '</div>' : '';
                return '<article class="bio-gallery-item">' . $imageHtml . $captionHtml . '</article>';
            }, $items);
            return '<section class="bio-block bio-block--gallery">' . $titleHtml . $subtitleHtml . '<div class="bio-gallery-grid">' . implode('', $galleryItems) . '</div></section>';

        case 'posts':
            $postItems = array_map(function ($item) use ($page, $btnStyle) {
                $meta = trim((string) ($item['meta'] ?? ''));
                $metaHtml = $meta !== '' ? '<div class="bio-post-meta"' . bio_public_typo_from_item($item, 'meta') . '>' . bio_public_html($meta) . '</div>' : '';
                $buttonHtml = trim((string) ($item['url'] ?? '')) !== ''
                    ? '<div style="margin-top:12px;">' . bio_public_render_button([
                        'title' => $item['label'] ?? 'Ler conteudo',
                        'url' => $item['url'] ?? '',
                        'icon_class' => 'fas fa-book-open',
                        'link_type' => 'link',
                        'element_styles' => $item['element_styles'] ?? [],
                    ], $page, $btnStyle) . '</div>'
                    : '';
                return '<article class="bio-post-item"><h4' . bio_public_typo_from_item($item, 'postTitle') . '>' . bio_public_html($item['title'] ?? 'Post') . '</h4><p' . bio_public_typo_from_item($item, 'postBody') . '>' . bio_public_html($item['text'] ?? '') . '</p>' . $metaHtml . $buttonHtml . '</article>';
            }, $items);
            return '<section class="bio-block bio-block--posts">' . $titleHtml . $subtitleHtml . '<div class="bio-post-list">' . implode('', $postItems) . '</div></section>';

        case 'contact':
            $contactItems = array_map(function ($item) {
                return '<div class="bio-info-item bio-contact-row"><i class="' . bio_public_html($item['icon_class'] ?? 'fas fa-circle') . '"></i><div><strong' . bio_public_typo_from_item($item, 'lineTitle') . '>' . bio_public_html($item['title'] ?? 'Contato') . '</strong><span' . bio_public_typo_from_item($item, 'lineText') . '>' . bio_public_html($item['text'] ?? '') . '</span></div></div>';
            }, $items);
            return '<section class="bio-block bio-block--contact">' . $titleHtml . $subtitleHtml . '<div class="bio-contact-list">' . implode('', $contactItems) . '</div></section>';

        case 'schedule':
            $scheduleItems = array_map(function ($item) {
                return '<div class="bio-info-item"><strong' . bio_public_typo_from_item($item, 'lineTitle') . '>' . bio_public_html($item['title'] ?? 'Item') . '</strong><span' . bio_public_typo_from_item($item, 'lineText') . '>' . bio_public_html($item['text'] ?? '') . '</span></div>';
            }, $items);
            return '<section class="bio-block bio-block--schedule">' . $titleHtml . $subtitleHtml . '<div class="bio-info-list">' . implode('', $scheduleItems) . '</div></section>';

        case 'video':
            $embedMeta = bio_public_embed_meta(
                (string) ($block['embed_provider'] ?? 'youtube'),
                (string) ($block['embed_url'] ?? '')
            );
            $frameHtml = $embedMeta['url'] !== ''
                ? '<div class="bio-video-frame"><iframe src="' . bio_public_html($embedMeta['url']) . '" loading="lazy" allow="' . bio_public_html($embedMeta['allow']) . '" referrerpolicy="' . bio_public_html($embedMeta['referrerpolicy']) . '" sandbox="' . bio_public_html($embedMeta['sandbox']) . '" allowfullscreen></iframe></div>'
                : '<div class="bio-copy bio-embed-note">' . bio_public_html($embedMeta['error']) . '</div>';
            $capTypo = $content !== '' ? '<div class="bio-gallery-caption"' . bio_public_typo_from_block($block, 'caption') . '>' . bio_public_multiline($content) . '</div>' : '';
            return '<section class="bio-block bio-block--video">' . $titleHtml . $subtitleHtml . $frameHtml . $capTypo . '</section>';

        case 'divider':
            return '<section class="bio-block bio-block--divider"><div class="bio-divider-line"></div>' . ($title !== '' ? '<div class="bio-divider-label"' . bio_public_typo_from_block($block, 'title') . '>' . bio_public_html($title) . '</div>' : '') . '<div class="bio-divider-line"></div></section>';

        case 'form':
            return bio_public_render_form_block($block, $page, $btnStyle);

        default:
            return '<section class="bio-block">' . $titleHtml . $subtitleHtml . ($content !== '' ? $bodyOpen . bio_public_multiline($content) . $bodyClose : '') . '</section>';
    }
}

function bio_public_render_form_block(array $block, array $page, string $btnStyle): string
{
    $slug = bio_public_html($page['slug'] ?? '');
    $csrf = getCsrfToken();
    $blockId = bio_public_html($block['id'] ?? 'form');
    $titleHtml = trim((string) ($block['title'] ?? '')) !== '' ? '<div class="bio-block-title"' . bio_public_typo_from_block($block, 'title') . '>' . bio_public_html($block['title']) . '</div>' : '';
    $subtitleHtml = trim((string) ($block['subtitle'] ?? '')) !== '' ? '<div class="bio-block-subtitle"' . bio_public_typo_from_block($block, 'subtitle') . '>' . bio_public_html($block['subtitle']) . '</div>' : '';
    $introHtml = trim((string) ($block['content'] ?? '')) !== '' ? '<div class="bio-form-intro bio-copy"' . bio_public_typo_from_block($block, 'body') . '>' . bio_public_multiline($block['content']) . '</div>' : '';

    $items = is_array($block['items'] ?? null) ? $block['items'] : [];
    $fieldsHtml = '';

    foreach ($items as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fid = bio_public_html($field['id'] ?? '');
        if ($fid === '') {
            continue;
        }
        $fkey = bio_public_html(($block['id'] ?? '') . '|' . ($field['id'] ?? ''));
        $label = trim((string) ($field['label'] ?? '')) !== '' ? $field['label'] : 'Pergunta';
        $req = !empty($field['required']);
        $ph = bio_public_html($field['placeholder'] ?? '');
        $help = trim((string) ($field['help_text'] ?? ''));
        $ftype = $field['field_type'] ?? 'short_text';
        $maxLen = (int) ($field['max_length'] ?? 0);
        $mask = trim((string) ($field['mask_pattern'] ?? ''));
        $defVal = (string) ($field['default_value'] ?? '');
        $showIf = !empty($field['show_if']) && is_array($field['show_if']) ? $field['show_if'] : null;
        $showAttr = '';
        if ($showIf) {
            $showAttr = ' data-show-if="' . bio_public_html(json_encode($showIf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
        }

        $helpHtml = $help !== '' ? '<div class="bio-form-hint">' . bio_public_html($help) . '</div>' : '';
        $reqStar = $req ? ' <span class="bio-form-req" aria-hidden="true">*</span>' : '';
        $fieldDomId = 'bf-' . $blockId . '-' . $fid;
        $labelHtml = '<label class="bio-form-label" for="' . bio_public_html($fieldDomId) . '"' . bio_public_typo_from_item($field, 'label') . '>' . bio_public_html($label) . $reqStar . '</label>';

        $commonWrap = '<div class="bio-form-field"' . $showAttr . ' data-bio-field-wrap="' . $fkey . '">';

        $dataAttrs = ' data-bio-field-key="' . $fkey . '" data-field-type="' . bio_public_html($ftype) . '"';
        if ($req) {
            $dataAttrs .= ' data-required="1"';
        }

        $inputAttrs = ' id="' . bio_public_html($fieldDomId) . '"' . $dataAttrs;
        if ($maxLen > 0) {
            $inputAttrs .= ' maxlength="' . (int) $maxLen . '"';
        }
        if ($mask !== '') {
            try {
                preg_match($mask, '');
                $inputAttrs .= ' pattern="' . bio_public_html($mask) . '"';
            } catch (Throwable $e) {
                // ignora máscara inválida
            }
        }

        $control = '';
        switch ($ftype) {
            case 'long_text':
                $control = '<textarea class="bio-form-control"' . $inputAttrs . ' rows="4" placeholder="' . $ph . '">' . bio_public_html($defVal) . '</textarea>';
                break;
            case 'email':
                $control = '<input type="email" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '" placeholder="' . $ph . '" autocomplete="email">';
                break;
            case 'url':
                $control = '<input type="url" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '" placeholder="' . $ph . '">';
                break;
            case 'number':
                $control = '<input type="text" inputmode="decimal" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '" placeholder="' . $ph . '">';
                break;
            case 'date':
                $control = '<input type="date" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '">';
                break;
            case 'time':
                $control = '<input type="time" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '">';
                break;
            case 'phone':
                $control = '<input type="tel" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '" placeholder="' . $ph . '" autocomplete="tel">';
                break;
            case 'select':
                $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
                $optsHtml = '<option value="">' . bio_public_html($field['placeholder'] ?? 'Selecione...') . '</option>';
                foreach ($opts as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $ov = (string) ($opt['value'] ?? '');
                    if ($ov === '') {
                        continue;
                    }
                    $sel = $defVal === $ov ? ' selected' : '';
                    $optsHtml .= '<option value="' . bio_public_html($ov) . '"' . $sel . '>' . bio_public_html($opt['label'] ?? $ov) . '</option>';
                }
                $control = '<select class="bio-form-control"' . $inputAttrs . '>' . $optsHtml . '</select>';
                break;
            case 'radio':
                $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
                $radios = [];
                $ri = 0;
                $radioName = 'bf_r_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', ($block['id'] ?? '') . '_' . ($field['id'] ?? ''));
                foreach ($opts as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $ov = (string) ($opt['value'] ?? '');
                    if ($ov === '') {
                        continue;
                    }
                    $rid = $fieldDomId . '-' . $ri++;
                    $chk = $defVal === $ov ? ' checked' : '';
                    $radios[] = '<label class="bio-form-check"><input type="radio" name="' . bio_public_html($radioName) . '" value="' . bio_public_html($ov) . '"' . $chk . $dataAttrs . ' id="' . bio_public_html($rid) . '"> ' . bio_public_html($opt['label'] ?? $ov) . '</label>';
                }
                $labelHtml = '<div class="bio-form-label">' . bio_public_html($label) . $reqStar . '</div>';
                $control = '<div class="bio-form-radio-group" data-bio-radio="' . $fkey . '">' . implode('', $radios) . '</div>';
                break;
            case 'checkbox':
                $labelHtml = '<div class="bio-form-label">' . bio_public_html($label) . $reqStar . '</div>';
                $chk = ($defVal === '1' || strtolower($defVal) === 'sim') ? ' checked' : '';
                $control = '<label class="bio-form-check"><input type="checkbox" class="bio-form-control"' . $inputAttrs . ' value="1"' . $chk . '> ' . bio_public_html($field['placeholder'] ?: 'Sim') . '</label>';
                break;
            case 'multiselect':
                $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
                $optsHtml = '';
                foreach ($opts as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $ov = (string) ($opt['value'] ?? '');
                    if ($ov === '') {
                        continue;
                    }
                    $optsHtml .= '<option value="' . bio_public_html($ov) . '">' . bio_public_html($opt['label'] ?? $ov) . '</option>';
                }
                $control = '<select class="bio-form-control" multiple' . $inputAttrs . '>' . $optsHtml . '</select>';
                break;
            default:
                $control = '<input type="text" class="bio-form-control"' . $inputAttrs . ' value="' . bio_public_html($defVal) . '" placeholder="' . $ph . '">';
        }

        $errId = 'err-' . $blockId . '-' . $fid;
        $fieldsHtml .= $commonWrap . $labelHtml . $control . $helpHtml . '<div class="bio-form-error" id="' . bio_public_html($errId) . '" hidden></div></div>';
    }

    $primary = $page['theme_btn_color'] ?? '#1A8CFF';
    $background = $page['theme_bg_color'] ?? '#0f172a';
    $btnInline = bio_public_button_style(['link_type' => 'link'], $page, $btnStyle);
    $submitSpanAttr = bio_element_styles_attr(is_array($block['element_styles']['submit'] ?? null) ? $block['element_styles']['submit'] : null);

    $formHtml = '<section class="bio-block bio-block--form">'
        . $titleHtml . $subtitleHtml . $introHtml
        . '<form class="bio-public-form" id="bioPublicForm" data-slug="' . $slug . '" novalidate>'
        . '<input type="hidden" name="csrf_token" value="' . bio_public_html($csrf) . '">'
        . '<div class="bio-form-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . $fieldsHtml
        . '<button type="submit" class="btn-bio ' . bio_public_html($btnStyle) . '" style="' . bio_public_html($btnInline) . '"><span' . $submitSpanAttr . '>Enviar respostas</span></button>'
        . '<div class="bio-form-status" role="status" aria-live="polite"></div>'
        . '</form>'
        . '</section>';

    return $formHtml;
}

$template = bio_public_template_info($templateId);
$templateCssPath = __DIR__ . '/' . $template['css_path'];
$templateClass = 'tpl-' . ($template['slug'] ?? 'classic');

$blocks = is_array($schema['blocks'] ?? null) ? $schema['blocks'] : [];
$heroBlock = null;
$contentBlocks = [];
foreach ($blocks as $block) {
    if (($block['type'] ?? '') === 'hero' && $heroBlock === null) {
        $heroBlock = $block;
        continue;
    }
    $contentBlocks[] = $block;
}

if ($heroBlock === null) {
    $heroBlock = [
        'badge' => ucfirst($pageType),
        'title' => $page['title'] ?? '',
        'subtitle' => $page['slug'] ?? '',
        'content' => $page['description'] ?? '',
        'media_url' => $page['avatar_url'] ?? '',
        'cover_image_url' => '',
        'element_styles' => [],
    ];
}

$hasPublicForm = false;
foreach ($contentBlocks as $__blk) {
    if (($__blk['type'] ?? '') === 'form') {
        $hasPublicForm = true;
        break;
    }
}

$heroBadge = trim((string) ($heroBlock['badge'] ?? '')) !== '' ? $heroBlock['badge'] : ucfirst($pageType);
$heroTitle = trim((string) ($page['title'] ?? '')) !== '' ? $page['title'] : ($heroBlock['title'] ?? 'Sua pagina');
$heroSubtitle = trim((string) ($heroBlock['subtitle'] ?? ''));
$heroDescription = trim((string) ($page['description'] ?? '')) !== '' ? $page['description'] : ($heroBlock['content'] ?? '');
$heroMedia = trim((string) ($heroBlock['media_url'] ?? '')) !== '' ? $heroBlock['media_url'] : ($page['avatar_url'] ?? '');
$heroCover = trim((string) ($heroBlock['cover_image_url'] ?? ''));
$heroAvatarShape = trim((string) ($heroBlock['avatar_shape'] ?? 'rounded'));
if (!in_array($heroAvatarShape, ['rounded', 'circle', 'square', 'banner', 'soft'], true)) {
    $heroAvatarShape = 'rounded';
}
if ($heroMedia === '') {
    $heroMedia = 'https://cdn-icons-png.flaticon.com/512/1243/1243933.png';
}

$heroElementStyles = is_array($heroBlock['element_styles'] ?? null) ? $heroBlock['element_styles'] : [];
$isAtelierMinimalCover = ($heroCover !== '' && ($template['slug'] ?? '') === 'atelier-minimal');
$atelierHeaderCoverStyle = $isAtelierMinimalCover
    ? ' style="--bio-atelier-cover: url(\'' . bio_public_html($heroCover) . '\');"'
    : '';
$themeInlineVars = '--bg: ' . bio_public_html($bgColor) . '; --primary: ' . bio_public_html($btnColor) . '; --text: ' . bio_public_html($textColor) . ';';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo bio_public_html($heroTitle); ?></title>
    <script>
        (function () {
            var key = 'enc_theme_preference';
            var root = document.documentElement;
            var stored = null;
            try { stored = localStorage.getItem(key); } catch (e) { stored = null; }
            var theme = (stored === 'light' || stored === 'dark') ? stored : 'light';
            root.setAttribute('data-theme', theme);
        })();
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo bio_public_html(bio_public_asset_url('bio_builder_shared.css')); ?>?v=<?php echo file_exists(__DIR__ . '/bio_builder_shared.css') ? filemtime(__DIR__ . '/bio_builder_shared.css') : time(); ?>">
    <?php if (file_exists($templateCssPath)): ?>
        <link rel="stylesheet" href="<?php echo bio_public_html(bio_public_asset_url($template['css_path'])); ?>?v=<?php echo (int) filemtime($templateCssPath); ?>">
    <?php endif; ?>
    <style>
        :root {
            --bg: <?php echo bio_public_html($bgColor); ?>;
            --primary: <?php echo bio_public_html($btnColor); ?>;
            --text: <?php echo bio_public_html($textColor); ?>;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }
        .bio-public-wrap {
            width: 100%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 0;
            overflow-x: hidden;
        }
        .bio-public-wrap .bio-page-shell {
            width: 100%;
            max-width: 620px;
            min-height: 100vh;
        }
        .btn-bio.solid { border-radius: 10px; }
        .btn-bio.rounded { border-radius: 18px; }
        .btn-bio.pill { border-radius: 999px; }
        .btn-bio.outline { border-radius: 12px; }
        .bio-gallery-placeholder {
            min-height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.65);
        }

        @media (max-width: 760px) {
            .bio-public-wrap .bio-page-shell {
                max-width: 100%;
            }
        }
    </style>
</head>
<body class="<?php echo bio_public_html($templateClass); ?>" style="<?php echo $themeInlineVars; ?>">
    <div class="bio-public-bg-fixed" aria-hidden="true"></div>
    <main class="bio-public-wrap">
        <div class="bio-page-shell container is-public <?php echo bio_public_html($templateClass); ?>">
            <header class="bio-header <?php echo $heroCover !== '' ? 'bio-header--has-cover' : ''; ?>"<?php echo $atelierHeaderCoverStyle; ?>>
                <?php if ($heroCover !== '' && !$isAtelierMinimalCover): ?>
                    <div class="bio-header-cover-layer" style="background-image:url('<?php echo bio_public_html($heroCover); ?>');"></div>
                <?php endif; ?>
                <div class="bio-avatar bio-avatar--<?php echo bio_public_html($heroAvatarShape); ?>" style="background-image:url('<?php echo bio_public_html($heroMedia); ?>');background-size:contain;background-position:center;background-repeat:no-repeat;"></div>
                <div class="bio-hero-copy">
                    <?php if (trim((string) $heroBadge) !== ''): ?>
                        <div class="bio-kicker"<?php echo bio_element_styles_attr($heroElementStyles['kicker'] ?? null); ?>><?php echo bio_public_html($heroBadge); ?></div>
                    <?php endif; ?>
                    <div class="preview-name"<?php echo bio_element_styles_attr($heroElementStyles['title'] ?? null); ?>><?php echo bio_public_html($heroTitle); ?></div>
                    <?php if ($heroSubtitle !== ''): ?>
                        <div class="bio-subtitle"<?php echo bio_element_styles_attr($heroElementStyles['subtitle'] ?? null); ?>><?php echo bio_public_html($heroSubtitle); ?></div>
                    <?php endif; ?>
                    <?php if ($heroDescription !== ''): ?>
                        <div class="preview-bio"<?php echo bio_element_styles_attr($heroElementStyles['body'] ?? null); ?>><?php echo bio_public_multiline($heroDescription); ?></div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="links-wrapper">
                <div class="bio-content">
                    <?php foreach ($contentBlocks as $block): ?>
                        <?php echo bio_public_render_block($block, $page, $btnStyle); ?>
                    <?php endforeach; ?>
                </div>
                <div class="bio-footer">Criado com <a href="https://linkneo.pro" target="_blank" rel="noopener noreferrer">LinkNeo.pro</a></div>
            </div>
        </div>
    </main>

    <script>
        function smartOpen(appUri, webUrl) {
            const start = Date.now();
            window.location.href = appUri;
            setTimeout(() => {
                if (Date.now() - start < 3000) {
                    window.location.href = webUrl;
                }
            }, 1800);
        }

        function logClick(pageId, source) {
            return { pageId, source };
        }

        function copyPix(key) {
            navigator.clipboard.writeText(key).then(() => {
                const toast = document.createElement('div');
                toast.style = 'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#32BCAD; color:white; padding:10px 20px; border-radius:10px; z-index:1000; font-size:0.8rem;';
                toast.innerText = 'Chave Pix copiada!';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }

        <?php if (!empty($hasPublicForm)): ?>
        (function () {
            function qAttr(v) {
                return String(v).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            }

            function parseShowIf(el) {
                const raw = el.getAttribute('data-show-if');
                if (!raw) return null;
                try { return JSON.parse(raw); } catch (e) { return null; }
            }

            function getVal(key) {
                const q = qAttr(key);
                const r = document.querySelector('input[type="radio"][data-bio-field-key="' + q + '"]:checked');
                if (r) return r.value || '';
                const c = document.querySelector('input[type="checkbox"][data-bio-field-key="' + q + '"]');
                if (c) return c.checked ? '1' : '';
                const s = document.querySelector('select[data-bio-field-key="' + q + '"]');
                if (s && !s.multiple) return s.value || '';
                if (s && s.multiple) return Array.from(s.selectedOptions).map(o => o.value).join(',');
                const t = document.querySelector('textarea[data-bio-field-key="' + q + '"],input[data-bio-field-key="' + q + '"]');
                return t ? (t.value || '').trim() : '';
            }

            function evalVisibility() {
                document.querySelectorAll('.bio-form-field[data-show-if]').forEach(wrap => {
                    const rule = parseShowIf(wrap);
                    if (!rule || !rule.field_key) return;
                    const dep = getVal(rule.field_key);
                    const exp = String(rule.value ?? '');
                    const ok = (rule.operator === 'neq') ? dep !== exp : dep === exp;
                    wrap.style.display = ok ? '' : 'none';
                });
            }

            function collectAnswers() {
                const answers = {};
                const keys = new Set();
                document.querySelectorAll('[data-bio-field-key]').forEach(el => {
                    keys.add(el.getAttribute('data-bio-field-key'));
                });
                keys.forEach(key => {
                    const wq = qAttr(key);
                    const wrap = document.querySelector('[data-bio-field-wrap="' + wq + '"]');
                    if (wrap && wrap.style.display === 'none') return;
                    const first = document.querySelector('[data-bio-field-key="' + wq + '"]');
                    const type = (first && first.dataset) ? first.dataset.fieldType || '' : '';
                    if (type === 'radio') {
                        const r = document.querySelector('input[type="radio"][data-bio-field-key="' + wq + '"]:checked');
                        answers[key] = r ? r.value : '';
                        return;
                    }
                    if (type === 'checkbox') {
                        const c = document.querySelector('input[type="checkbox"][data-bio-field-key="' + wq + '"]');
                        answers[key] = c && c.checked ? '1' : '';
                        return;
                    }
                    const sel = document.querySelector('select[data-bio-field-key="' + wq + '"]');
                    if (sel && sel.multiple) {
                        answers[key] = Array.from(sel.selectedOptions).map(o => o.value);
                        return;
                    }
                    const one = document.querySelector('select[data-bio-field-key="' + wq + '"],textarea[data-bio-field-key="' + wq + '"],input[data-bio-field-key="' + wq + '"]');
                    answers[key] = one ? one.value : '';
                });
                return answers;
            }

            function validateClient(answers) {
                const errs = [];
                document.querySelectorAll('[data-bio-field-wrap]').forEach(wrap => {
                    if (wrap.style.display === 'none') return;
                    const key = wrap.getAttribute('data-bio-field-wrap');
                    const el = wrap.querySelector('[data-required="1"]');
                    if (!el) return;
                    const type = el.getAttribute('data-field-type') || '';
                    const v = answers[key];
                    if (type === 'checkbox' && v !== '1') errs.push(key);
                    else if (type === 'multiselect' && (!Array.isArray(v) || v.length === 0)) errs.push(key);
                    else if (type !== 'checkbox' && type !== 'multiselect' && String(v || '').trim() === '') errs.push(key);
                });
                return errs;
            }

            const form = document.getElementById('bioPublicForm');
            if (!form) return;
            const statusEl = form.querySelector('.bio-form-status');
            form.addEventListener('change', evalVisibility);
            evalVisibility();

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (statusEl) { statusEl.textContent = ''; statusEl.classList.remove('bio-form-status--error', 'bio-form-status--ok'); }
                const slug = form.getAttribute('data-slug') || '';
                const csrf = form.querySelector('input[name="csrf_token"]')?.value || '';
                const hp = form.querySelector('input[name="website"]')?.value || '';
                const answers = collectAnswers();
                const bad = validateClient(answers);
                if (bad.length) {
                    if (statusEl) {
                        statusEl.textContent = 'Preencha os campos obrigatórios destacados.';
                        statusEl.classList.add('bio-form-status--error');
                    }
                    return;
                }
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;
                try {
                    const res = await fetch(<?php echo bio_public_js_string(bio_public_asset_url('bio_form_submit.php')); ?>, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ slug, csrf_token: csrf, answers, website: hp })
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (statusEl) {
                            statusEl.textContent = data.message || 'Enviado!';
                            statusEl.classList.add('bio-form-status--ok');
                        }
                        form.reset();
                        evalVisibility();
                    } else {
                        if (statusEl) {
                            statusEl.textContent = data.message || 'Não foi possível enviar.';
                            statusEl.classList.add('bio-form-status--error');
                        }
                    }
                } catch (err) {
                    if (statusEl) {
                        statusEl.textContent = 'Erro de rede. Tente novamente.';
                        statusEl.classList.add('bio-form-status--error');
                    }
                } finally {
                    if (btn) btn.disabled = false;
                }
            });
        })();
        <?php endif; ?>
    </script>
</body>
</html>
