<?php
// Delivers an uploaded document, after checking that the viewer is allowed to read it.
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$r = resource_find((int) ($_GET["id"] ?? 0));
if ($r === null || $r["kind"] !== "document" || empty($r["stored_name"])) {
    http_response_code(404);
    exit("That document was not found.");
}
if (!resource_can_view($r)) {
    if (empty($_SESSION["id"])) {
        redirect("/login.php");        // members-only: ask them to log in
    }
    http_response_code(403);
    exit("You do not have permission to open this document.");
}

$stored = (string) $r["stored_name"];
if (!preg_match("/^[a-f0-9]{32}\.(pdf|doc|docx|odt|ods)$/", $stored)) {
    http_response_code(404);
    exit("That document was not found.");
}
$path = resource_storage_dir() . "/" . $stored;
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
if (!is_file($path) || !isset(RESOURCE_DOC_TYPES[$ext])) {
    http_response_code(404);
    exit("That document is no longer available.");
}

[, $mime, $inline] = RESOURCE_DOC_TYPES[$ext];
$name = (string) $r["file_name"] !== "" ? $r["file_name"] : "document." . $ext;
$name = str_replace(["\r", "\n", '"'], "", $name);

header("Content-Type: " . $mime);
header("Content-Length: " . filesize($path));
header("Content-Disposition: " . ($inline ? "inline" : "attachment") . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=0, must-revalidate");
readfile($path);
exit;
