<?php
/**
 * Copy this file to config_secrets.php and replace values.
 * Keep config_secrets.php blocked in .htaccess and ignored by git.
 */
return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'your_database',
    'DB_USER' => 'your_user',
    'DB_PASS' => 'your_password',

    'SMTP_HOST' => 'smtp.hostinger.com',
    'SMTP_PORT' => '465',
    'SMTP_SECURE' => 'ssl',
    'SMTP_USER' => 'you@example.com',
    'SMTP_PASS' => 'your_smtp_password',

    'SESSION_NAME' => 'ENCURTOUSESSID',
    'CSRF_ENFORCE' => '1',
    'HEALTHCHECK_TOKEN' => 'change_this_token',

    'ASAAS_ENV' => 'production',
    'ASAAS_API_KEY' => 'change_me',
    'ASAAS_WEBHOOK_TOKEN' => 'change_me',

    'GEMINI_API_KEY' => 'change_me',
    'GEMINI_MODEL' => 'gemini-2.5-flash',
    'AI_TUTOR_REQUIRE_AUTH' => '1',
    'GOOGLE_CLIENT_ID' => 'change_me',

    'STRIPE_SECRET_KEY' => 'change_me',
    'STRIPE_WEBHOOK_SECRET' => 'change_me',
    'BILLING_STRIPE_CHECKOUT_ENABLED' => '0',
    'BILLING_ALLOW_MOCK_CHECKOUT' => '0',

    'PUBLIC_BASE_URL' => 'https://linkneo.pro',
    'EDGE_API_KEY' => 'change_me',
    'EDGE_SHARED_SECRET' => 'change_me',
    'EDGE_INVALIDATE_TOKEN' => 'change_me',
    'EDGE_WORKER_INVALIDATE_URL' => 'https://linkneo.pro/__edge/invalidate',
    'EDGE_WORKER_INVALIDATE_SECRET' => 'change_me',

    // Opcional: assinatura HMAC para precise_geo_click.php (fallback: EDGE_SHARED_SECRET)
    'PRECISE_GEO_SECRET' => 'change_me',
    // Reverse geocode (Nominatim) para preencher bairro em analytics — desligue com '0' se não quiser chamadas externas
    'PRECISE_GEO_REVERSE_LOOKUP' => '1',
    // URL ou e-mail de contato exigido pela política de uso do Nominatim (User-Agent)
    'OSM_NOMINATIM_CONTACT' => 'https://linkneo.pro',

    // Anti-abuse / segurança
    'WEB_RISK_API_KEY' => 'change_me',
    'ABUSE_SCAN_FAIL_OPEN' => '0',
    'SECURITY_ALERT_EMAIL' => 'you@example.com',
    'EMAIL_VERIFY_GRACE_DAYS' => '7',
    'HCAPTCHA_SITE_KEY' => '',
    'HCAPTCHA_SECRET' => '',
    'RECAPTCHA_SITE_KEY' => '',
    'RECAPTCHA_SECRET' => '',
];
