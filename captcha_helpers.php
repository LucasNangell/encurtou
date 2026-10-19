<?php

function captchaConfigured(): bool
{
    return envOrDefault('HCAPTCHA_SECRET', '') !== '' || envOrDefault('RECAPTCHA_SECRET', '') !== '';
}

function verifyCaptchaToken(string $token, ?string $remoteIp = null): bool
{
    if (!captchaConfigured()) {
        return true;
    }
    if ($token === '') {
        return false;
    }

    $hcaptchaSecret = envOrDefault('HCAPTCHA_SECRET', '');
    if ($hcaptchaSecret !== '') {
        return captchaVerifyRemote('https://hcaptcha.com/siteverify', [
            'secret' => $hcaptchaSecret,
            'response' => $token,
            'remoteip' => $remoteIp ?? getRealIP(),
        ]);
    }

    $recaptchaSecret = envOrDefault('RECAPTCHA_SECRET', '');
    if ($recaptchaSecret !== '') {
        return captchaVerifyRemote('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $recaptchaSecret,
            'response' => $token,
            'remoteip' => $remoteIp ?? getRealIP(),
        ]);
    }

    return true;
}

function captchaVerifyRemote(string $url, array $payload): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body)) {
        return false;
    }
    $json = json_decode($body, true);
    return is_array($json) && !empty($json['success']);
}

function captchaSiteKey(): string
{
    $hcaptcha = envOrDefault('HCAPTCHA_SITE_KEY', '');
    if ($hcaptcha !== '') {
        return $hcaptcha;
    }
    return envOrDefault('RECAPTCHA_SITE_KEY', '');
}
