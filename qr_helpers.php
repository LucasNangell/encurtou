<?php
/**
 * Helpers para destinos dinâmicos de QR Codes.
 *
 * A UI envia "destination_type" (ex: link, social, vcard, phone, email) e
 * "destination_metadata" (JSON) com os campos estruturados. O backend é a
 * fonte da verdade: valida metadados e (re)gera a string `original_url` que
 * o redirect.php entrega ao visitante.
 */

if (!function_exists('qr_allowed_destination_types')) {

    function qr_allowed_destination_types(): array
    {
        return ['link', 'social', 'vcard', 'phone', 'email'];
    }

    function qr_allowed_social_platforms(): array
    {
        return ['whatsapp', 'instagram', 'facebook', 'linkedin', 'tiktok'];
    }

    /**
     * Decodifica o JSON de metadados, aceitando array já decodificado também.
     * Retorna array em caso de sucesso; null caso contrário.
     */
    function qr_parse_metadata($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * Normaliza um array de metadados, mantendo apenas os campos esperados
     * para o tipo informado. Garante strings e remove chaves inesperadas
     * (evita que a UI persista dados arbitrários no JSON).
     */
    function qr_sanitize_metadata(string $type, array $meta): array
    {
        $allowedFields = [
            'link'   => ['url'],
            'social' => ['platform', 'value'],
            'vcard'  => ['name', 'phone', 'email', 'org'],
            'phone'  => ['phone'],
            'email'  => ['to', 'subject', 'body'],
        ];
        $fields = $allowedFields[$type] ?? [];
        $out = [];
        foreach ($fields as $key) {
            if (isset($meta[$key]) && (is_string($meta[$key]) || is_numeric($meta[$key]))) {
                $value = (string)$meta[$key];
                // Proibir caracteres de controle e quebras de linha em campos
                // curtos para evitar CRLF injection em headers futuros.
                if (!in_array($key, ['body'], true)) {
                    $value = preg_replace('/[\r\n\t\x00-\x1F]+/u', ' ', $value);
                }
                $out[$key] = trim($value);
            } else {
                $out[$key] = '';
            }
        }
        return $out;
    }

    /**
     * Monta a URL final (original_url) a partir do tipo e dos metadados já
     * higienizados. Retorna null se não houver dados suficientes ou se
     * algum valor crítico (URL/email) for inválido.
     */
    function qr_build_destination(string $type, array $meta): ?string
    {
        switch ($type) {
            case 'link': {
                $url = (string)($meta['url'] ?? '');
                if ($url === '') return null;
                if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
                    $url = 'https://' . $url;
                }
                // Restringe a http(s) para evitar schemes perigosos (javascript:, data:, file:...)
                if (!preg_match('#^https?://#i', $url)) return null;
                if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
                return $url;
            }

            case 'social': {
                $platform = strtolower((string)($meta['platform'] ?? ''));
                $value = (string)($meta['value'] ?? '');
                if ($value === '' || !in_array($platform, qr_allowed_social_platforms(), true)) {
                    return null;
                }
                switch ($platform) {
                    case 'whatsapp':
                        $digits = preg_replace('/\D/', '', $value);
                        if (!$digits) return null;
                        return 'https://wa.me/' . $digits;
                    case 'instagram':
                        return 'https://instagram.com/' . rawurlencode(ltrim($value, '@'));
                    case 'facebook':
                        return 'https://facebook.com/' . rawurlencode($value);
                    case 'linkedin':
                        return 'https://linkedin.com/in/' . rawurlencode($value);
                    case 'tiktok':
                        return 'https://tiktok.com/@' . rawurlencode(ltrim($value, '@'));
                }
                return null;
            }

            case 'vcard': {
                $name  = (string)($meta['name'] ?? '');
                $phone = (string)($meta['phone'] ?? '');
                $email = (string)($meta['email'] ?? '');
                $org   = (string)($meta['org'] ?? '');
                if ($name === '' && $phone === '') return null;
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
                // CRLF escape dentro do vCard usando \r\n conforme RFC 6350
                $lines = [
                    'BEGIN:VCARD',
                    'VERSION:3.0',
                    'FN:' . $name,
                    'TEL:' . $phone,
                    'EMAIL:' . $email,
                    'ORG:' . $org,
                    'END:VCARD',
                ];
                return implode("\r\n", $lines);
            }

            case 'phone': {
                $phone = (string)($meta['phone'] ?? '');
                $phone = preg_replace('/\s+/', '', $phone);
                if ($phone === '' || !preg_match('/^\+?[0-9\-\(\)]{3,20}$/', $phone)) return null;
                return 'tel:' . $phone;
            }

            case 'email': {
                $to = (string)($meta['to'] ?? '');
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
                $subject = (string)($meta['subject'] ?? '');
                $body    = (string)($meta['body'] ?? '');
                $params = [];
                if ($subject !== '') $params['subject'] = $subject;
                if ($body !== '')    $params['body']    = $body;
                $qs = $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
                return 'mailto:' . $to . $qs;
            }
        }
        return null;
    }

    /**
     * Validação completa: parse + normalização + geração do destino final.
     * Retorna ['type' => string, 'metadata' => array, 'url' => string] ou
     * null em caso de falha (com $error preenchido por referência).
     */
    function qr_process_destination($rawType, $rawMetadata, ?string &$error = null): ?array
    {
        $type = is_string($rawType) ? strtolower(trim($rawType)) : '';
        if (!in_array($type, qr_allowed_destination_types(), true)) {
            $error = 'Tipo de destino QR inválido.';
            return null;
        }

        $meta = qr_parse_metadata($rawMetadata);
        if ($meta === null) {
            $error = 'Metadados do QR Code inválidos ou JSON malformado.';
            return null;
        }
        $meta = qr_sanitize_metadata($type, $meta);
        $url = qr_build_destination($type, $meta);
        if ($url === null) {
            $error = 'Preencha corretamente as informações do QR Code.';
            return null;
        }

        return ['type' => $type, 'metadata' => $meta, 'url' => $url];
    }
}
