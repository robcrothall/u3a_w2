<?php

/**
 * recording_functions.php
 *
 * Recordings and related documents are rows in `presentation_files`, each
 * attached to a presentation (event). Anyone may open them; each open is logged
 * (user id, or NULL = "Visitor" when not logged in).
 */

const FILE_TYPES = [
    "video" => "Video",
    "document" => "Document",
    "presentation" => "Presentation slides",
    "article" => "Article",
    "other" => "Other",
];

/** Past, published presentations that have at least one file. */
function recordings_events(int $limit, int $offset): array
{
    $events = query(
        "SELECT p.* FROM presentations p
         WHERE p.status = 'published' AND p.presentation_date <= ?
         AND EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id)
         ORDER BY p.presentation_date DESC, p.id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset,
        date("Y-m-d")
    );
    foreach ($events as &$event) {
        $event["files"] = event_files((int) $event["id"]);
    }
    unset($event);
    return $events;
}

function recordings_count(): int
{
    $rows = query(
        "SELECT COUNT(*) AS n FROM presentations p
         WHERE p.status = 'published' AND p.presentation_date <= ?
         AND EXISTS (SELECT 1 FROM presentation_files f WHERE f.presentation_id = p.id)",
        date("Y-m-d")
    );
    return (int) $rows[0]["n"];
}

function event_files(int $event_id): array
{
    return query(
        "SELECT * FROM presentation_files WHERE presentation_id = ? ORDER BY sort_order, id",
        $event_id
    );
}

function file_find(int $id): ?array
{
    $rows = query("SELECT * FROM presentation_files WHERE id = ?", $id);
    return $rows[0] ?? null;
}

/** Validates raw form input. Returns [array $errors, array $clean]. */
function file_validate(array $input): array
{
    $errors = [];
    $clean = [
        "link_text" => trim((string) ($input["link_text"] ?? "")),
        "url" => trim((string) ($input["url"] ?? "")),
        "file_type" => (string) ($input["file_type"] ?? "video"),
        "sort_order" => (int) ($input["sort_order"] ?? 0),
    ];

    if ($clean["link_text"] === "" || mb_strlen($clean["link_text"]) > 255) {
        $errors[] = "Please enter the text to show for the link (up to 255 characters).";
    }
    $scheme = strtolower((string) parse_url($clean["url"], PHP_URL_SCHEME));
    if (
        $clean["url"] === "" || mb_strlen($clean["url"]) > 500
        || filter_var($clean["url"], FILTER_VALIDATE_URL) === false || $scheme !== "https"
    ) {
        $errors[] = "Please enter a valid web address starting with https:// (up to 500 characters).";
    }
    if (!array_key_exists($clean["file_type"], FILE_TYPES)) {
        $errors[] = "Invalid file type.";
    }
    if ($clean["sort_order"] < 0 || $clean["sort_order"] > 9999) {
        $errors[] = "The order must be a number from 0 to 9999.";
    }
    return [$errors, $clean];
}

function file_create(int $event_id, array $f): void
{
    query(
        "INSERT INTO presentation_files (presentation_id, url, link_text, file_type, sort_order)
         VALUES (?, ?, ?, ?, ?)",
        $event_id,
        $f["url"],
        $f["link_text"],
        $f["file_type"],
        $f["sort_order"]
    );
}

function file_update(int $id, array $f): void
{
    query(
        "UPDATE presentation_files SET url = ?, link_text = ?, file_type = ?, sort_order = ? WHERE id = ?",
        $f["url"],
        $f["link_text"],
        $f["file_type"],
        $f["sort_order"],
        $id
    );
}

function file_delete(int $id): void
{
    query("DELETE FROM presentation_files WHERE id = ?", $id);
}

function log_file_access(int $file_id, ?int $user_id): void
{
    query("INSERT INTO file_access_log (file_id, user_id) VALUES (?, ?)", $file_id, $user_id);
}

/** Opens per file (all time), for the staff report. */
function file_access_totals(): array
{
    return query(
        "SELECT p.presentation_date, p.presenter_name, f.id, f.link_text,
                COUNT(l.id) AS opens,
                SUM(l.user_id IS NULL) AS visitor_opens
         FROM presentation_files f
         JOIN presentations p ON p.id = f.presentation_id
         LEFT JOIN file_access_log l ON l.file_id = f.id
         GROUP BY f.id, p.presentation_date, p.presenter_name, f.link_text
         ORDER BY p.presentation_date DESC, f.sort_order, f.id"
    );
}

/** Most recent opens; users who were not logged in show as "Visitor". */
function file_access_recent(int $limit = 100): array
{
    return query(
        "SELECT l.accessed, f.link_text, p.presenter_name,
                COALESCE(CONCAT(u.first_name, ' ', u.surname), 'Visitor') AS who
         FROM file_access_log l
         LEFT JOIN presentation_files f ON f.id = l.file_id
         LEFT JOIN presentations p ON p.id = f.presentation_id
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT " . (int) $limit
    );
}
