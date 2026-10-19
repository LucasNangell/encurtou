<?php
declare(strict_types=1);

/**
 * Parâmetros de query na URL curta (ex.: ?utm_campaign=email&ref=dep) que devem
 * ir para o destino e podem ser gravados em clicks_log.incoming_query.
 * Chaves internas do Encurtou não são repassadas.
 */
function linkPassThroughReservedKeysLower(): array
{
    return [
        'slug' => true,
        'subdomain' => true,
        'src' => true,
        'debug' => true,
        'aff' => true,
        'affiliate' => true,
        'ep_cid' => true,
    ];
}

function linkPassThroughParamsFromSuperglobalGet(array $get): array
{
    $exclude = linkPassThroughReservedKeysLower();
    $out = [];
    foreach ($get as $k => $v) {
        if (!is_string($k) || $k === '') {
            continue;
        }
        if (isset($exclude[strtolower($k)])) {
            continue;
        }
        if (is_array($v)) {
            continue;
        }
        $sv = is_string($v) ? $v : (is_numeric($v) ? (string)$v : '');
        if ($sv === '') {
            continue;
        }
        if (strlen($sv) > 500) {
            $sv = mb_substr($sv, 0, 500);
        }
        $out[$k] = $sv;
    }
    return $out;
}

function linkPassThroughHttpQueryFromParams(array $params): string
{
    if ($params === []) {
        return '';
    }
    $q = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    if (strlen($q) > 2048) {
        $q = mb_substr($q, 0, 2048);
    }
    return $q;
}

function linkMergePassThroughQueryOntoUrl(string $destination, string $passThroughQuery): string
{
    if ($passThroughQuery === '') {
        return $destination;
    }
    $parsed = parse_url($destination);
    if (!isset($parsed['scheme'], $parsed['host'])) {
        return $destination;
    }
    $base = [];
    if (!empty($parsed['query']) && is_string($parsed['query'])) {
        parse_str($parsed['query'], $base);
    }
    if (!is_array($base)) {
        $base = [];
    }
    $inc = [];
    parse_str($passThroughQuery, $inc);
    if (!is_array($inc)) {
        $inc = [];
    }
    $merged = array_merge($base, $inc);
    $newQuery = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
    $port = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';
    $path = $parsed['path'] ?? '';
    if ($path === '') {
        $path = '/';
    }
    $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#' . $parsed['fragment'] : '';
    return $parsed['scheme'] . '://' . $parsed['host'] . $port . $path . '?' . $newQuery . $fragment;
}

/** Mescla query do destino cadastrado com a query recebida no clique (incoming_query). */
function linkMergedAttributionParamsForClick(string $originalUrl, ?string $incomingQuery): array
{
    $base = [];
    $oq = parse_url($originalUrl, PHP_URL_QUERY);
    if (is_string($oq) && $oq !== '') {
        parse_str($oq, $base);
    }
    if (!is_array($base)) {
        $base = [];
    }
    if ($incomingQuery !== null && trim($incomingQuery) !== '') {
        $inc = [];
        parse_str($incomingQuery, $inc);
        if (is_array($inc) && $inc !== []) {
            $base = array_merge($base, $inc);
        }
    }
    return $base;
}

/**
 * Incrementa contadores UTM a partir de params já mesclados; $addClicks = número de cliques no grupo.
 */
function linkAddUtmsFromParamsToBuckets(array $params, int $addClicks, array &$utmSources, array &$utmMediums, array &$utmCampaigns, int &$organicClicks): void
{
    if ($addClicks <= 0) {
        return;
    }
    $hasUtmDimension = false;
    if (!empty($params['utm_source'])) {
        $src = (string)$params['utm_source'];
        $utmSources[$src] = ($utmSources[$src] ?? 0) + $addClicks;
        $hasUtmDimension = true;
    }
    if (!empty($params['utm_medium'])) {
        $med = (string)$params['utm_medium'];
        $utmMediums[$med] = ($utmMediums[$med] ?? 0) + $addClicks;
        $hasUtmDimension = true;
    }
    $camp = null;
    if (!empty($params['utm_campaign'])) {
        $camp = (string)$params['utm_campaign'];
        $hasUtmDimension = true;
    } elseif (!empty($params['ref'])) {
        $camp = 'ref: ' . (string)$params['ref'];
        $hasUtmDimension = true;
    }
    if ($camp !== null) {
        $utmCampaigns[$camp] = ($utmCampaigns[$camp] ?? 0) + $addClicks;
    }
    if (!$hasUtmDimension) {
        $organicClicks += $addClicks;
    }
}
