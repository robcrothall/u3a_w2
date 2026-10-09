<?php

/**
 * event_functions.php
 *
 * Events = rows in `presentations`. "Upcoming" and "past" are just queries
 * on presentation_date; only status = 'published' rows are shown publicly.
 */

function events_upcoming(int $limit = 50): array
{
    return query(
        "SELECT * FROM presentations
         WHERE status = 'published' AND presentation_date >= ?
         ORDER BY presentation_date ASC, id ASC LIMIT " . (int) $limit,
        date("Y-m-d")
    );
}

function events_past(int $limit = 20, int $offset = 0): array
{
    return query(
        "SELECT * FROM presentations
         WHERE status = 'published' AND presentation_date < ?
         ORDER BY presentation_date DESC, id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset,
        date("Y-m-d")
    );
}

function events_past_count(): int
{
    $rows = query(
        "SELECT COUNT(*) AS n FROM presentations WHERE status = 'published' AND presentation_date < ?",
        date("Y-m-d")
    );
    return (int) $rows[0]["n"];
}

/** Every event including drafts, newest first - for the staff list. */
function events_all(): array
{
    return query("SELECT * FROM presentations ORDER BY presentation_date DESC, id DESC");
}

function event_find(int $id): ?array
{
    $rows = query("SELECT * FROM presentations WHERE id = ?", $id);
    return $rows[0] ?? null;
}

/**
 * Validates raw form input. Returns [array $errors, array $clean].
 */
function event_validate(array $input): array
{
    $errors = [];
    $clean = [
        "presenter_name" => trim((string) ($input["presenter_name"] ?? "")),
        "title" => trim((string) ($input["title"] ?? "")),
        "presentation_date" => trim((string) ($input["presentation_date"] ?? "")),
        "summary" => trim((string) ($input["summary"] ?? "")),
        "status" => (string) ($input["status"] ?? "published"),
    ];

    if ($clean["presenter_name"] === "" || mb_strlen($clean["presenter_name"]) > 255) {
        $errors[] = "Please enter the presenter's name (up to 255 characters).";
    }
    if ($clean["title"] === "" || mb_strlen($clean["title"]) > 255) {
        $errors[] = "Please enter a title (up to 255 characters).";
    }
    $date = DateTime::createFromFormat("Y-m-d", $clean["presentation_date"]);
    if (!$date || $date->format("Y-m-d") !== $clean["presentation_date"]) {
        $errors[] = "Please enter a valid date.";
    }
    if (mb_strlen($clean["summary"]) > 5000) {
        $errors[] = "The summary is too long (maximum 5000 characters).";
    }
    if (!in_array($clean["status"], ["draft", "published"], true)) {
        $errors[] = "Invalid status.";
    }
    return [$errors, $clean];
}

function event_create(array $e): int
{
    query(
        "INSERT INTO presentations (presenter_name, title, presentation_date, summary, status)
         VALUES (?, ?, ?, ?, ?)",
        $e["presenter_name"],
        $e["title"],
        $e["presentation_date"],
        $e["summary"] === "" ? null : $e["summary"],
        $e["status"]
    );
    return (int) $_SESSION["inserted_row_id"];
}

function event_update(int $id, array $e): void
{
    query(
        "UPDATE presentations
         SET presenter_name = ?, title = ?, presentation_date = ?, summary = ?, status = ?
         WHERE id = ?",
        $e["presenter_name"],
        $e["title"],
        $e["presentation_date"],
        $e["summary"] === "" ? null : $e["summary"],
        $e["status"],
        $id
    );
}

function event_delete(int $id): void
{
    query("DELETE FROM presentations WHERE id = ?", $id);
}

/** "Thursday 11 June 2026" */
function event_date_label(string $date): string
{
    return date("l j F Y", strtotime($date));
}
