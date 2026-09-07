<?php

/**
 * env.php
 *
 * Minimal .env file loader — no Composer/vendor folder needed, since we
 * have no shell access on the host to run `composer install`.
 *
 * Reads KEY=VALUE lines from a .env file into $_ENV / getenv(), skipping
 * blank lines and lines starting with #. Values can optionally be quoted.
 */

function load_env(string $path): void
{
    if (!is_readable($path)) {
        trigger_error("Missing .env file at $path", E_USER_ERROR);
        exit;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        if (!str_contains($line, "=")) {
            continue;
        }
        [$key, $value] = explode("=", $line, 2);
        $key = trim($key);
        $value = trim($value);

        // strip matching surrounding quotes, if present
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Convenience getter with an optional default, so config.php reads cleanly.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false && $value !== null ? $value : $default;
}
