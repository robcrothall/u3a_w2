<?php

/**
 * _app_path.php
 *
 * Defines APP_DIR, the folder holding config/, inc/ and templates/.
 *
 *   Live site:      ~/public_html/        -> ~/app
 *   Local (Laragon): <repo>/public/        -> <repo>/app
 *   Test subdomain: ~/public_html/w2.u3aportalfred.org.za/ -> ~/w2/app
 *                   (that host cannot put a document root outside public_html)
 *
 * config.php then expects .env one level above APP_DIR (~/.env, <repo>/.env,
 * or ~/w2/.env), so the test site never shares the live site's .env.
 */
$candidates = [
    __DIR__ . "/../app",
    __DIR__ . "/../../w2/app",
];
foreach ($candidates as $candidate) {
    if (is_dir($candidate)) {
        define("APP_DIR", realpath($candidate));
        break;
    }
}
// The test site lives in a folder named w2.<domain> inside public_html, so the live domain could also
// reach it by path. Serve that folder only under its own host name.
if (stripos(basename(__DIR__), "w2.") === 0 && stripos($_SERVER["HTTP_HOST"] ?? "", "w2.") !== 0) {
    http_response_code(404);
    exit("Not found.");
}
define("PUBLIC_DIR", __DIR__);   // folder served by the web server (for uploads)
if (!defined("APP_DIR")) {
    http_response_code(500);
    exit("Application folder not found.");
}
