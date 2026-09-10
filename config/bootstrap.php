<?php

declare(strict_types=1);

/**
 * Loads .env into PHP. Docker Compose env vars WIN over the file
 * (same idea as image-jobs-demo).
 */
if (!defined('CRYPTO_WATCHDESK_BOOTSTRAP')) {
    define('CRYPTO_WATCHDESK_BOOTSTRAP', true);

    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $existing = getenv($name);
            if ($existing !== false && $existing !== '') {
                $_ENV[$name] = $existing;
                continue;
            }
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null || $value === '' ? $default : $value;
    }
}
