<?php
/**
 * Página de acesso direto à seção de Assinaturas e Planos.
 * Reutiliza index.html e abre a seção dedicada via script.
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
    '<title>Assinaturas e Planos - Encurtou.pro</title>',
    $html,
    1
);

$inject = '<script>window.__ENC_BOOT_SECTION__="section-subscription";</script>';
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $inject . "\n</body>", $html);
} else {
    $html .= $inject;
}

echo $html;
