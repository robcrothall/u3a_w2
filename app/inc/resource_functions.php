<?php

/**
 * resource_functions.php
 *
 * Links and documents for the Resources page. Documents live in a private
 * folder OUTSIDE the web root (<folder above app>/storage/resources) and are
 * only ever delivered by public/resource.php after checking who may read them.
 */

const RESOURCE_VISIBILITY = [
    "everyone" => "Everyone (anyone visiting the site)",
    "members" => "Logged-in members",
    "admins" => "Admins only",
];

/** Allowed document types: extension => [label, content type for download, inline in browser?] */
const RESOURCE_DOC_TYPES = [
    "pdf" => ["PDF", "application/pdf", true],
    "doc" => ["Word", "application/msword", false],
    "docx" => ["Word", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", false],
    "odt" => ["OpenDocument text", "application/vnd.oasis.opendocument.text", false],
    "ods" => ["OpenDocument spreadsheet", "application/vnd.oasis.opendocument.spreadsheet", false],
];

const RESOURCE_MAX_BYTES = 10 * 1024 * 1024;

/** Private folder for uploaded documents; created on first use. */
function resource_storage_dir(): string
{
    $dir = dirname(APP_DIR) . "/storage/resources";
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $ht = $dir . "/.htaccess";
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");   // belt and braces: this folder is not web-served anyway
    }
    return $dir;
}

/** Visibility values the current viewer may see. */
function resource_levels_for_viewer(): array
{
    if (empty($_SESSION["id"])) {
        return ["everyone"];
    }
    $user_id = (int) $_SESSION["id"];
    return user_has_role($user_id, "admin") ? ["everyone", "members", "admins"] : ["everyone", "members"];
}

function resource_can_view(array $r): bool
{
    return in_array($r["visibility"], resource_levels_for_viewer(), true);
}

/** Resources the viewer may see, newest first; optional category filter. */
function resources_for_viewer(?string $category = null): array
{
    $levels = resource_levels_for_viewer();
    $in = implode(",", array_fill(0, count($levels), "?"));
    $sql = "SELECT * FROM resources WHERE visibility IN ($in)";
    $params = $levels;
    if ($category !== null && $category !== "") {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    return query($sql . " ORDER BY date_added DESC, id DESC", ...$params);
}

/** Every resource (staff list). */
function resources_all(): array
{
    return query("SELECT * FROM resources ORDER BY date_added DESC, id DESC");
}

function resource_find(int $id): ?array
{
    $rows = query("SELECT * FROM resources WHERE id = ?", $id);
    return $rows[0] ?? null;
}

/** Categories in use, for the filter and the form suggestions. */
function resource_categories(bool $for_viewer = true): array
{
    if ($for_viewer) {
        $levels = resource_levels_for_viewer();
        $in = implode(",", array_fill(0, count($levels), "?"));
        $rows = query("SELECT DISTINCT category FROM resources WHERE category IS NOT NULL AND category <> '' AND visibility IN ($in) ORDER BY category", ...$levels);
    } else {
        $rows = query("SELECT DISTINCT category FROM resources WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    }
    return array_map(fn($r) => $r["category"], $rows);
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . " MB";
    }
    return max(1, (int) round($bytes / 1024)) . " KB";
}

/** Largest upload the server will accept, in bytes (the smaller of PHP's two limits). */
function resource_server_upload_limit(): int
{
    $a = ini_bytes((string) ini_get("upload_max_filesize"));
    $b = ini_bytes((string) ini_get("post_max_size"));
    $limits = array_filter([$a, $b], fn($v) => $v > 0);
    return $limits ? min($limits) : RESOURCE_MAX_BYTES;
}

/** Validates the staff form. Returns [array $errors, array $clean]. $kind is 'link' or 'document'. */
function resource_validate(array $input, string $kind): array
{
    $errors = [];
    $clean = [
        "title" => trim(preg_replace("/\s+/u", " ", (string) ($input["title"] ?? ""))),
        "information" => trim((string) ($input["information"] ?? "")),
        "category" => trim(preg_replace("/\s+/u", " ", (string) ($input["category"] ?? ""))),
        "visibility" => (string) ($input["visibility"] ?? "everyone"),
        "date_added" => trim((string) ($input["date_added"] ?? "")),
        "url" => trim((string) ($input["url"] ?? "")),
    ];

    if ($clean["title"] === "" || mb_strlen($clean["title"]) > 80) {
        $errors[] = "Please enter a title of up to 80 characters (this is the text people click).";
    }
    if (mb_strlen($clean["information"]) > 1000) {
        $errors[] = "The information is too long (maximum 1000 characters).";
    }
    if (mb_strlen($clean["category"]) > 40) {
        $errors[] = "The category is too long (maximum 40 characters).";
    }
    if (!array_key_exists($clean["visibility"], RESOURCE_VISIBILITY)) {
        $errors[] = "Please choose who may see this.";
    }
    $date = DateTime::createFromFormat("Y-m-d", $clean["date_added"]);
    if (!$date || $date->format("Y-m-d") !== $clean["date_added"]) {
        $errors[] = "Please enter a valid date.";
    } elseif ($clean["date_added"] > date("Y-m-d")) {
        $errors[] = "The date cannot be in the future.";
    }
    if ($kind === "link") {
        $scheme = strtolower((string) parse_url($clean["url"], PHP_URL_SCHEME));
        if (
            $clean["url"] === "" || mb_strlen($clean["url"]) > 500
            || filter_var($clean["url"], FILTER_VALIDATE_URL) === false || !in_array($scheme, ["http", "https"], true)
        ) {
            $errors[] = "Please enter a valid web address starting with http:// or https:// (up to 500 characters).";
        }
    }
    return [$errors, $clean];
}

/**
 * Checks an uploaded document and moves it into private storage.
 * Returns [array $stored (stored_name, file_name, file_size), string|null $error].
 */
function resource_store_upload(array $file): array
{
    $empty = [];
    if (!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return [$empty, "Please choose a document to upload."];
    }
    if ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) {
        return [$empty, "That file is too large for this server (limit " . format_bytes(resource_server_upload_limit()) . ")."];
    }
    if ($file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
        return [$empty, "The upload did not work. Please try again."];
    }
    if ($file["size"] > RESOURCE_MAX_BYTES) {
        return [$empty, "That file is too large (maximum " . format_bytes(RESOURCE_MAX_BYTES) . ")."];
    }
    if ($file["size"] < 100) {
        return [$empty, "That file looks empty."];
    }

    $original = basename(str_replace("\\", "/", (string) $file["name"]));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!array_key_exists($ext, RESOURCE_DOC_TYPES)) {
        return [$empty, "Allowed document types: PDF, Word (.doc, .docx) and OpenDocument (.odt, .ods)."];
    }

    // the content must look like what the extension says
    $head = (string) file_get_contents($file["tmp_name"], false, null, 0, 8);
    $ok = match ($ext) {
        "pdf" => strncmp($head, "%PDF-", 5) === 0,
        "doc" => strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0,
        default => strncmp($head, "PK\x03\x04", 4) === 0,   // docx / odt / ods are zip containers
    };
    if (!$ok) {
        return [$empty, "That file does not look like a real ." . $ext . " document."];
    }

    $dir = resource_storage_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return [$empty, "The server could not prepare the document folder. Please tell the webmaster."];
    }
    $stored = bin2hex(random_bytes(16)) . "." . $ext;
    if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $stored)) {
        return [$empty, "The document could not be saved. Please tell the webmaster."];
    }

    // a tidy download name: letters, digits, space, dot, dash, underscore
    $safe = trim(preg_replace("/[^\p{L}\p{N} ._()-]+/u", "_", $original));
    return [["stored_name" => $stored, "file_name" => mb_substr($safe, 0, 200), "file_size" => (int) $file["size"]], null];
}

function resource_delete_file(?string $stored_name): void
{
    if ($stored_name !== null && preg_match("/^[a-f0-9]{32}\.(pdf|doc|docx|odt|ods)$/", $stored_name)) {
        @unlink(resource_storage_dir() . "/" . $stored_name);
    }
}

function resource_create(string $kind, array $c, ?array $stored, int $user_id): int
{
    query(
        "INSERT INTO resources (kind, title, information, category, visibility, date_added, url, file_name, stored_name, file_size, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        $kind,
        $c["title"],
        $c["information"] === "" ? null : $c["information"],
        $c["category"] === "" ? null : $c["category"],
        $c["visibility"],
        $c["date_added"],
        $kind === "link" ? $c["url"] : null,
        $stored["file_name"] ?? null,
        $stored["stored_name"] ?? null,
        $stored["file_size"] ?? null,
        $user_id
    );
    return (int) $_SESSION["inserted_row_id"];
}

function resource_update(int $id, array $c, ?array $stored): void
{
    query(
        "UPDATE resources SET title = ?, information = ?, category = ?, visibility = ?, date_added = ?,
                url = COALESCE(?, url)
         WHERE id = ?",
        $c["title"],
        $c["information"] === "" ? null : $c["information"],
        $c["category"] === "" ? null : $c["category"],
        $c["visibility"],
        $c["date_added"],
        $c["url"] === "" ? null : $c["url"],
        $id
    );
    if ($stored !== null) {
        query(
            "UPDATE resources SET file_name = ?, stored_name = ?, file_size = ? WHERE id = ?",
            $stored["file_name"],
            $stored["stored_name"],
            $stored["file_size"],
            $id
        );
    }
}

function resource_delete(int $id): void
{
    $r = resource_find($id);
    if ($r === null) {
        return;
    }
    query("DELETE FROM resources WHERE id = ?", $id);
    resource_delete_file($r["stored_name"]);
}
