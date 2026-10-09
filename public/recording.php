<?php
// Logs that someone opened a recording/document, then sends them to it.
// Logged-in users are logged by id; everyone else is logged as a visitor
// (user_id NULL).
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$file = file_find((int) ($_GET["id"] ?? 0));
if ($file === null) {
    http_response_code(404);
    exit("That file was not found.");
}

// URLs are validated as https:// when saved; check again before redirecting.
if (stripos($file["url"], "https://") !== 0) {
    http_response_code(500);
    exit("This link is not valid. Please tell the webmaster.");
}

// Link-preview and crawler HEAD requests are not "views".
if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
    log_file_access((int) $file["id"], !empty($_SESSION["id"]) ? (int) $_SESSION["id"] : null);
}
header("Location: " . $file["url"]);
exit;
