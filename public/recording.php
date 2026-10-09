<?php
// Logs that a member opened a recording/document, then sends them to it.
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";
require_login();

if (!can_view_recordings()) {
    redirect("/recordings.php");
}

$file = file_find((int) ($_GET["id"] ?? 0));
if ($file === null) {
    http_response_code(404);
    exit("That file was not found.");
}

log_file_access((int) $file["id"], (int) $_SESSION["id"]);

// URLs are validated as https:// when saved; check again before redirecting.
if (stripos($file["url"], "https://") !== 0) {
    http_response_code(500);
    exit("This link is not valid. Please tell the webmaster.");
}
header("Location: " . $file["url"]);
exit;
