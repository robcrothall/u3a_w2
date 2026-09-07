<?php

/**
 * config.php
 *
 * Bootstraps the application: loads .env, sets error handling based on
 * environment, starts the session, and requires shared constants/functions.
 *
 * Include this once per request, near the top, before any output.
 */

require_once __DIR__ . "/env.php";

// .env lives outside the repo on the live server (FTP account home
// directory, alongside but not inside public_html) and at the repo root
// during local Laragon development.
$env_path = getenv("U3A_ENV_PATH") ?: dirname(__DIR__, 2) . "/.env";
load_env($env_path);

$is_local = env("APP_ENV", "production") === "local";
ini_set("display_errors", $is_local);
error_reporting($is_local ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("Africa/Johannesburg");

require_once __DIR__ . "/constants.php";
require_once dirname(__DIR__) . "/inc/functions.php";
