<?php
/**
 * Página de boas-vindas pós-assinatura.
 * Reutiliza index.html (header, sidebar, footer, scripts) e abre a seção dedicada via script.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$indexPath = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
if (!is_readable($indexPath)) {
    http_response_code(500);
    echo 'Página temporariamente indisponível.';
    exit;
}

$html = file_get_contents($indexPath);
if ($html === false || $html === '') {
    http_response_code(500);
    echo 'Página temporariamente indisponível.';
    exit;
}

$html = preg_replace(
    '/<title>[^<]*<\/title>/i',
    '<title>Bem-vindo ao Encurtou.pro — Assinatura confirmada</title>',
    $html,
    1
);

// Snippet de conversão do Google Ads (Assinatura)
$googleAdsSnippet = "<!-- Event snippet for Assinatura (1) conversion page -->\n"
                  . "<script>\n"
                  . "  gtag('event', 'conversion', {'send_to': 'AW-18127733324/JGxuCLmlm6UcEMyE_cND'});\n"
                  . "</script>";

if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', $googleAdsSnippet . "\n</head>", $html);
}

$inject = '<script>window.__ENC_BOOT_SECTION__="section-boasvindas";</script>';
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $inject . "\n</body>", $html);
} else {
    $html .= $inject;
}

echo $html;
