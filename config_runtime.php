<?php
/**
 * Runtime config bootstrap for shared-host environments.
 *
 * Load order (lowest to highest priority):
 * 1) Existing process/server env vars
 * 2) .env file (optional)
 * 3) config_secrets.php (fallback for shared-host)
 */

if (!function_exists('configRuntimeSetVar')) {
    function configRuntimeSetVar(string $key, string $value, bool $overwrite = false): void
    {
        $current = $_SERVER[$key] ?? getenv($key);
        if (!$overwrite && $current !== false && $current !== null && $current !== '') {
            return;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (!function_exists('configRuntimeLoadDotEnv')) {
    function configRuntimeLoadDotEnv(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim((string)$parts[0]);
            $value = trim((string)$parts[1]);
            if ($name === '') {
                continue;
            }

            configRuntimeSetVar($name, $value, false);
        }
    }
}

if (!function_exists('configRuntimeLoadSecretsFile')) {
    function configRuntimeLoadSecretsFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $secrets = require $filePath;
        if (!is_array($secrets)) {
            return;
        }

        foreach ($secrets as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }

            // Never override an already-defined var from process/server env.
            configRuntimeSetVar($key, (string)$value, false);
        }
    }
}

if (!function_exists('bootstrapRuntimeConfig')) {
    function bootstrapRuntimeConfig(): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        $root = __DIR__;

        // Optional local/dev file.
        configRuntimeLoadDotEnv($root . '/.env');

        // Primary source for shared hosts without env vars panel.
        configRuntimeLoadSecretsFile($root . '/config_secrets.php');

        $bootstrapped = true;
    }
}

bootstrapRuntimeConfig();
