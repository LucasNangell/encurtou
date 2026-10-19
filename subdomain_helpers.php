<?php

if (!function_exists('subdomainGetBaseHost')) {
    function subdomainGetBaseHost(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $baseUrl = getenv('PUBLIC_BASE_URL') ?: '';
        if ($baseUrl === '' && isset($_SERVER['PUBLIC_BASE_URL'])) {
            $baseUrl = (string)$_SERVER['PUBLIC_BASE_URL'];
        }

        $host = '';
        if ($baseUrl !== '') {
            $parsed = parse_url($baseUrl);
            if (is_array($parsed) && !empty($parsed['host'])) {
                $host = strtolower((string)$parsed['host']);
            }
        }

        if ($host === '') {
            $host = 'linkneo.pro';
        }

        $cached = $host;
        return $cached;
    }
}

if (!function_exists('subdomainGetReservedNames')) {
    function subdomainGetReservedNames(): array
    {
        return [
            'www', 'api', 'admin', 'mail', 'smtp', 'ftp', 'bio', 'root',
            'cdn', 'static', 'assets', 'dev', 'staging', 'test',
        ];
    }
}

if (!function_exists('subdomainIsReserved')) {
    function subdomainIsReserved(string $sub): bool
    {
        return in_array(strtolower($sub), subdomainGetReservedNames(), true);
    }
}

if (!function_exists('subdomainSanitize')) {
    function subdomainSanitize(string $raw): string
    {
        $sub = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($raw)));
        $sub = trim($sub, '-');
        return $sub;
    }
}

if (!function_exists('subdomainValidateForRegistration')) {
    /**
     * @return string|null Mensagem de erro ou null se válido.
     */
    function subdomainValidateForRegistration(string $sub): ?string
    {
        if ($sub === '' || $sub === 'root') {
            return 'Informe um subdomínio válido.';
        }

        if (strlen($sub) < 3) {
            return 'O subdomínio deve ter pelo menos 3 caracteres.';
        }

        if (strlen($sub) > 50) {
            return 'O subdomínio deve ter no máximo 50 caracteres.';
        }

        if ($sub[0] === '-' || substr($sub, -1) === '-') {
            return 'O subdomínio não pode começar ou terminar com hífen.';
        }

        if (strpos($sub, '--') !== false) {
            return 'O subdomínio não pode conter hífens consecutivos.';
        }

        if (subdomainIsReserved($sub)) {
            return 'Este subdomínio está reservado pela plataforma.';
        }

        return null;
    }
}

if (!function_exists('subdomainNormalizeForLink')) {
    function subdomainNormalizeForLink(string $raw): string
    {
        $sub = subdomainSanitize($raw);
        if ($sub === '' || $sub === 'root') {
            return 'ROOT';
        }
        return $sub;
    }
}
