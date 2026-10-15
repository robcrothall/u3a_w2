<?php

/**
 * campaign_functions.php
 *
 * Bulk email to members. An admin composes a message (subject, body with
 * merge keywords, an audience, up to three Resources documents), it is queued
 * as one row per recipient and then sent in batches, each message personalised
 * at send time. Every message carries a one-click unsubscribe link.
 */

const MAIL_AUDIENCES = [
    "all" => "All members with an email address (except those who opted out of newsletters)",
    "unpaid" => "Members not paid up for the current year, with an email address (except those who opted out)",
    "admins" => "Admins with an email address (committee business: includes admins who opted out)",
];

/** Keywords usable in the subject and body, written $first_name$ etc. */
const MAIL_MERGE_FIELDS = [
    "first_name" => "First name",
    "surname" => "Surname",
    "title" => "Title (Mr, Dr, Rev Dr ...)",
    "email" => "Email address",
    "mobile" => "Mobile number",
    "membership_type" => "Membership type (Individual, Couple, Honorary)",
    "paid_up_year" => "The year they are paid up for (empty if not paid up)",
];

const MAIL_ATTACH_MAX_BYTES = 5 * 1024 * 1024;   // all attachments in one message
const MAIL_BATCH_SIZE = 40;                      // messages per click of "send next batch"
const MAIL_HOURLY_LIMIT = 400;                   // the host's limit (per hour, whole account)
const MAIL_HOURLY_SAFE = 340;                    // stay below it: receipts and password emails share the limit
const MAIL_MAX_DOCS = 3;

/**
 * Bulk sending (campaigns and bulk password invitations) is OFF unless this site's .env
 * contains MAIL_BULK_ENABLED=1. The test/development site shares real members' addresses,
 * so it must never mass-mail by accident. "Send a test to me" always works.
 */
function bulk_mail_enabled(): bool
{
    return env("MAIL_BULK_ENABLED", "0") === "1";
}

const BULK_MAIL_OFF_MESSAGE = "Bulk sending is switched off on this site. (It is on only on the live site, to stop test data from emailing real members.) You can still preview and send a test to yourself.";

/** Common SELECT for a recipient with everything the merge keywords need. */
function mail_recipient_sql(): string
{
    return "SELECT u.id, u.first_name, u.surname, u.title, u.email, u.mobile, u.email_opt_out,
                   COALESCE(m.membership_type, 'individual') AS membership_type,
                   CASE WHEN m.membership_type = 'honorary' THEN ?
                        ELSE (SELECT p.year FROM membership_payments p WHERE p.user_id = u.id AND p.year = ? LIMIT 1)
                   END AS paid_year
            FROM users u LEFT JOIN memberships m ON m.id = u.membership_id";
}

/** The people an audience resolves to, one per distinct email address. */
function mail_audience_recipients(string $audience): array
{
    $year = (int) date("Y");
    $where = "u.email IS NOT NULL AND u.email <> ''";
    if ($audience === "all") {
        $where .= " AND u.email_opt_out = 0";
    } elseif ($audience === "unpaid") {
        $where .= " AND u.email_opt_out = 0 AND COALESCE(m.membership_type, 'individual') <> 'honorary'
                    AND NOT EXISTS (SELECT 1 FROM membership_payments p WHERE p.user_id = u.id AND p.year = $year)";
    } elseif ($audience === "admins") {
        $where .= " AND EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id
                                WHERE ur.user_id = u.id AND r.role_name = 'admin'
                                  AND (ur.expiry IS NULL OR ur.expiry >= CURDATE()))";
    } else {
        return [];
    }
    $rows = query(mail_recipient_sql() . " WHERE $where ORDER BY u.surname, u.first_name", $year, $year);
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        $key = strtolower($r["email"]);
        if (!isset($seen[$key]) && filter_var($r["email"], FILTER_VALIDATE_EMAIL)) {
            $seen[$key] = true;
            $out[] = $r;
        }
    }
    return $out;
}

function mail_recipient_by_user(int $user_id): ?array
{
    $year = (int) date("Y");
    $rows = query(mail_recipient_sql() . " WHERE u.id = ?", $year, $year, $user_id);
    return $rows[0] ?? null;
}

/** Keywords in $text that are not in the allowed list. */
function mail_unknown_keywords(string $text): array
{
    preg_match_all('/\$([A-Za-z_]+)\$/', $text, $m);
    return array_values(array_unique(array_filter($m[1], fn($k) => !array_key_exists($k, MAIL_MERGE_FIELDS))));
}

/** Replaces $keywords$ with the recipient's values. */
function mail_merge(string $text, array $r): string
{
    $labels = ["individual" => "Individual", "couple" => "Couple", "honorary" => "Honorary"];
    $values = [
        "first_name" => (string) $r["first_name"],
        "surname" => (string) $r["surname"],
        "title" => (string) ($r["title"] ?? ""),
        "email" => (string) $r["email"],
        "mobile" => (string) ($r["mobile"] ?? ""),
        "membership_type" => $labels[$r["membership_type"] ?? "individual"] ?? "",
        "paid_up_year" => (string) ($r["paid_year"] ?? ""),
    ];
    return preg_replace_callback('/\$([A-Za-z_]+)\$/', fn($m) => $values[$m[1]] ?? $m[0], $text);
}

/** The member's secret unsubscribe token (created on first use). */
function unsubscribe_token(int $user_id): string
{
    $rows = query("SELECT unsubscribe_token FROM users WHERE id = ?", $user_id);
    $token = (string) ($rows[0]["unsubscribe_token"] ?? "");
    if ($token === "") {
        $token = bin2hex(random_bytes(16));
        query("UPDATE users SET unsubscribe_token = ? WHERE id = ? AND unsubscribe_token IS NULL", $token, $user_id);
        $rows = query("SELECT unsubscribe_token FROM users WHERE id = ?", $user_id);
        $token = (string) ($rows[0]["unsubscribe_token"] ?? $token);
    }
    return $token;
}

function user_by_unsubscribe_token(string $token): ?array
{
    if (!preg_match("/^[a-f0-9]{32}$/", $token)) {
        return null;
    }
    $rows = query("SELECT id, first_name, surname, email_opt_out FROM users WHERE unsubscribe_token = ?", $token);
    return $rows[0] ?? null;
}

/** Document rows (kind = document) for the given resource ids, in the order given. */
function campaign_documents(array $ids): array
{
    $docs = [];
    foreach ($ids as $id) {
        $r = resource_find((int) $id);
        if ($r !== null && $r["kind"] === "document" && !empty($r["stored_name"])) {
            $docs[] = $r;
        }
    }
    return $docs;
}

/** Validates the compose form. Returns [array $errors, array $clean]. */
function campaign_validate(array $input): array
{
    $errors = [];
    $clean = [
        "audience" => (string) ($input["audience"] ?? ""),
        "subject" => trim(preg_replace("/\s+/u", " ", (string) ($input["subject"] ?? ""))),
        "body" => trim(str_replace("\r\n", "\n", (string) ($input["body"] ?? ""))),
        "doc_ids" => [],
        "attach" => ($input["delivery"] ?? "links") === "attach",
    ];

    if (!array_key_exists($clean["audience"], MAIL_AUDIENCES)) {
        $errors[] = "Please choose who the message is for.";
    }
    if ($clean["subject"] === "" || mb_strlen($clean["subject"]) > 150) {
        $errors[] = "Please enter a subject of up to 150 characters.";
    }
    if ($clean["body"] === "") {
        $errors[] = "Please write the message.";
    } elseif (mb_strlen($clean["body"]) > 20000) {
        $errors[] = "The message is too long (maximum 20 000 characters).";
    }
    foreach (mail_unknown_keywords($clean["subject"] . "\n" . $clean["body"]) as $k) {
        $errors[] = "Unknown keyword \$$k\$. Allowed: " . implode(", ", array_map(fn($f) => "\$$f\$", array_keys(MAIL_MERGE_FIELDS))) . ".";
    }

    $ids = array_values(array_unique(array_map("intval", (array) ($input["docs"] ?? []))));
    if (count($ids) > MAIL_MAX_DOCS) {
        $errors[] = "Please choose at most " . MAIL_MAX_DOCS . " documents.";
        $ids = array_slice($ids, 0, MAIL_MAX_DOCS);
    }
    $docs = campaign_documents($ids);
    $clean["doc_ids"] = array_map(fn($d) => (int) $d["id"], $docs);
    foreach ($docs as $d) {
        if ($d["visibility"] === "admins" && $clean["audience"] !== "admins") {
            $errors[] = "\"" . $d["title"] . "\" is visible to admins only, so members could not open it. Change its visibility first, or send this message to admins.";
        }
    }
    if ($clean["attach"] && $docs) {
        $total = 0;
        foreach ($docs as $d) {
            $total += (int) $d["file_size"];
        }
        if ($total > MAIL_ATTACH_MAX_BYTES) {
            $errors[] = "The attached documents total " . format_bytes($total) . "; the limit is " . format_bytes(MAIL_ATTACH_MAX_BYTES)
                . ". Send them as links instead, or choose fewer documents.";
        }
    }
    if ($clean["attach"] && !$docs) {
        $clean["attach"] = false;
    }
    return [$errors, $clean];
}

/**
 * Builds the subject, body and send options for one recipient.
 * $campaign needs: subject, body, doc_ids (array of ids), attach (bool).
 */
function campaign_build_message(array $campaign, array $r, bool $is_test = false): array
{
    $subject = mail_merge($campaign["subject"], $r);
    $body = mail_merge($campaign["body"], $r);
    $docs = campaign_documents((array) $campaign["doc_ids"]);
    $opts = ["from" => mail_from_address(), "from_name" => "U3A Port Alfred", "reply_to" => mail_from_address()];

    if ($docs && !$campaign["attach"]) {
        $body .= "\n\nDocuments:\n";
        foreach ($docs as $d) {
            $body .= "  " . $d["title"] . ": " . site_url("/resource.php?id=" . (int) $d["id"]) . "\n";
        }
    } elseif ($docs) {
        $opts["attachments"] = [];
        foreach ($docs as $d) {
            $ext = strtolower(pathinfo((string) $d["stored_name"], PATHINFO_EXTENSION));
            $opts["attachments"][] = [
                "name" => $d["file_name"] ?: ($d["title"] . "." . $ext),
                "path" => resource_storage_dir() . "/" . $d["stored_name"],
                "mime" => RESOURCE_DOC_TYPES[$ext][1] ?? "application/octet-stream",
            ];
        }
    }

    $unsub = site_url("/unsubscribe.php?t=" . unsubscribe_token((int) $r["id"]));
    $body .= "\n\n--\nU3A Port Alfred - " . site_url("/") . "\n"
        . "You receive this because you are a U3A Port Alfred member. To stop receiving newsletters and announcements, use this link:\n"
        . $unsub . "\n";
    $opts["headers"] = [
        "List-Unsubscribe: <$unsub>",
        "List-Unsubscribe-Post: List-Unsubscribe=One-Click",
    ];
    if ($is_test) {
        $subject = "[TEST] " . $subject;
    }
    return [$subject, $body, $opts];
}

/** Creates the campaign and one queue row per recipient. Returns the campaign id. */
function campaign_create(array $c, int $user_id): int
{
    $recipients = mail_audience_recipients($c["audience"]);
    query(
        "INSERT INTO mail_campaigns (subject, body, audience, doc_ids, attach, recipients, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        $c["subject"],
        $c["body"],
        $c["audience"],
        $c["doc_ids"] ? implode(",", $c["doc_ids"]) : null,
        $c["attach"] ? 1 : 0,
        count($recipients),
        $user_id
    );
    $id = (int) $_SESSION["inserted_row_id"];
    foreach ($recipients as $r) {
        query("INSERT INTO mail_queue (campaign_id, user_id, email) VALUES (?, ?, ?)", $id, (int) $r["id"], $r["email"]);
    }
    if (!$recipients) {
        query("UPDATE mail_campaigns SET status = 'done' WHERE id = ?", $id);
    }
    return $id;
}

function campaign_find(int $id): ?array
{
    $rows = query("SELECT * FROM mail_campaigns WHERE id = ?", $id);
    if (!$rows) {
        return null;
    }
    $row = $rows[0];
    $row["doc_ids"] = $row["doc_ids"] ? array_map("intval", explode(",", $row["doc_ids"])) : [];
    $row["attach"] = (bool) $row["attach"];
    return $row;
}

function campaign_counts(int $id): array
{
    $counts = ["pending" => 0, "sent" => 0, "failed" => 0, "skipped" => 0];
    foreach (query("SELECT status, COUNT(*) AS n FROM mail_queue WHERE campaign_id = ? GROUP BY status", $id) as $r) {
        $counts[$r["status"]] = (int) $r["n"];
    }
    return $counts;
}

/** Messages sent in the last hour (queue plus receipts and password emails) - approximate. */
function emails_last_hour(): int
{
    $n = 0;
    $n += (int) query("SELECT COUNT(*) AS n FROM mail_queue WHERE status = 'sent' AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")[0]["n"];
    try {
        $n += (int) query("SELECT COUNT(*) AS n FROM membership_payments WHERE receipt_sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")[0]["n"];
    } catch (Throwable $e) {
    }
    $n += (int) query("SELECT COUNT(*) AS n FROM password_resets WHERE created > DATE_SUB(NOW(), INTERVAL 1 HOUR)")[0]["n"];
    return $n;
}

/** How many messages may go out right now without passing the hourly limit. */
function mail_batch_allowance(): int
{
    return max(0, min(MAIL_BATCH_SIZE, MAIL_HOURLY_SAFE - emails_last_hour()));
}

/**
 * Sends up to $max pending messages of a campaign.
 * Returns [sent, failed, skipped].
 */
function campaign_send_batch(int $campaign_id, int $max): array
{
    $result = [0, 0, 0];
    $campaign = campaign_find($campaign_id);
    if ($campaign === null || $campaign["status"] !== "queued" || $max <= 0) {
        return $result;
    }
    @set_time_limit(180);
    $rows = query(
        "SELECT * FROM mail_queue WHERE campaign_id = ? AND status = 'pending' ORDER BY id LIMIT " . (int) $max,
        $campaign_id
    );
    foreach ($rows as $q) {
        $r = $q["user_id"] ? mail_recipient_by_user((int) $q["user_id"]) : null;
        if ($r === null || empty($r["email"])) {
            query("UPDATE mail_queue SET status = 'skipped', note = ? WHERE id = ?", "member no longer has an email address", $q["id"]);
            $result[2]++;
            continue;
        }
        if ($campaign["audience"] !== "admins" && !empty($r["email_opt_out"])) {
            query("UPDATE mail_queue SET status = 'skipped', note = ? WHERE id = ?", "opted out since the message was queued", $q["id"]);
            $result[2]++;
            continue;
        }
        [$subject, $body, $opts] = campaign_build_message($campaign, $r);
        $ok = send_email($r["email"], $subject, $body, $opts);
        if ($ok) {
            query("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), email = ? WHERE id = ?", $r["email"], $q["id"]);
            $result[0]++;
        } else {
            query("UPDATE mail_queue SET status = 'failed', note = ? WHERE id = ?", "the server did not accept the message", $q["id"]);
            $result[1]++;
        }
    }
    $left = campaign_counts($campaign_id)["pending"];
    if ($left === 0) {
        query("UPDATE mail_campaigns SET status = 'done' WHERE id = ? AND status = 'queued'", $campaign_id);
    }
    return $result;
}

function campaign_cancel(int $id): void
{
    query("UPDATE mail_queue SET status = 'skipped', note = 'cancelled' WHERE campaign_id = ? AND status = 'pending'", $id);
    query("UPDATE mail_campaigns SET status = 'cancelled' WHERE id = ? AND status = 'queued'", $id);
}

function campaigns_recent(int $limit = 50): array
{
    return query(
        "SELECT c.*, CONCAT(u.first_name, ' ', u.surname) AS author,
                (SELECT COUNT(*) FROM mail_queue q WHERE q.campaign_id = c.id AND q.status = 'sent') AS sent_n,
                (SELECT COUNT(*) FROM mail_queue q WHERE q.campaign_id = c.id AND q.status = 'pending') AS pending_n,
                (SELECT COUNT(*) FROM mail_queue q WHERE q.campaign_id = c.id AND q.status = 'failed') AS failed_n
         FROM mail_campaigns c LEFT JOIN users u ON u.id = c.created_by
         ORDER BY c.id DESC LIMIT " . (int) $limit
    );
}
