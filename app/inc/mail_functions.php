<?php

/**
 * mail_functions.php
 *
 * Sends email from the website. Uses PHP's mail() on the cPanel host, from a
 * mailbox on the same domain so SPF/DKIM line up and messages are not treated
 * as spoofed. Default sender is MAIL_FROM in .env (default
 * info@u3aportalfred.org.za); callers may pass another sender and a Reply-To.
 * For local testing set MAIL_TRANSPORT=log in .env: messages are then appended
 * to mail_out.log next to .env instead of being sent.
 */

function mail_from_address(): string
{
    $from = env("MAIL_FROM", "info@u3aportalfred.org.za");
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : "info@u3aportalfred.org.za";
}

/** Absolute URL for a path on this site, e.g. site_url("/set_password.php"). */
function site_url(string $path = "/"): string
{
    return rtrim(env("SITE_URL", "https://u3aportalfred.org.za"), "/") . $path;
}

/**
 * True if the message was handed to the mail system (or logged).
 *
 * $to may hold several addresses separated by commas; all must be valid.
 * $opts:
 *   from, from_name, reply_to   sender details (from must be on our own domain)
 *   headers                     extra header lines, e.g. List-Unsubscribe
 *   attachments                 list of ["name" => file name, "path" => file on disk, "mime" => type]
 */
function send_email(string $to, string $subject, string $body, array $opts = []): bool
{
    $recipients = array_values(array_filter(array_map("trim", explode(",", $to)), fn($a) => $a !== ""));
    if (!$recipients) {
        return false;
    }
    foreach ($recipients as $r) {
        if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
    }
    // header injection guard: no line breaks in anything that goes into headers
    $clean = fn(string $v): string => trim(preg_replace("/[\r\n]+/", " ", $v));
    $subject = $clean($subject);
    $from = $opts["from"] ?? mail_from_address();
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = mail_from_address();
    }
    $fromName = trim(preg_replace("/[\r\n\"<>]+/", " ", (string) ($opts["from_name"] ?? "U3A Port Alfred")));
    $replyTo = $opts["reply_to"] ?? $from;
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = $from;
    }
    $toHeader = implode(", ", $recipients);
    $extra = array_map($clean, array_filter($opts["headers"] ?? [], "is_string"));
    $attachments = $opts["attachments"] ?? [];

    if (env("MAIL_TRANSPORT", "mail") === "log") {
        $log = dirname(__DIR__, 2) . "/mail_out.log";
        $entry = "To: $toHeader\nFrom: $fromName <$from>\nReply-To: $replyTo\n";
        foreach ($extra as $h) {
            $entry .= $h . "\n";
        }
        foreach ($attachments as $a) {
            $entry .= "Attachment: " . $a["name"] . " (" . (is_file($a["path"]) ? filesize($a["path"]) : 0) . " bytes)\n";
        }
        $entry .= "Subject: $subject\n\n$body\n" . str_repeat("-", 60) . "\n";
        return file_put_contents($log, $entry, FILE_APPEND) !== false;
    }

    $headers = [
        "From: $fromName <$from>",
        "Reply-To: $replyTo",
        "MIME-Version: 1.0",
        "X-Mailer: U3A website",
    ];
    foreach ($extra as $h) {
        $headers[] = $h;
    }
    $text = str_replace("\r\n", "\n", $body);

    if (!$attachments) {
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $payload = $text;
    } else {
        $boundary = "u3a_" . bin2hex(random_bytes(12));
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
        $payload = "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . str_replace("\n", "\r\n", $text) . "\r\n";
        foreach ($attachments as $a) {
            if (!is_file($a["path"])) {
                return false;
            }
            $name = str_replace(["\r", "\n", '"'], "", (string) $a["name"]);
            $payload .= "--$boundary\r\n"
                . "Content-Type: " . $a["mime"] . "; name=\"$name\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
                . chunk_split(base64_encode((string) file_get_contents($a["path"])), 76, "\r\n");
        }
        $payload .= "--$boundary--\r\n";
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $ok = @mail($toHeader, $encodedSubject, $payload, implode("\r\n", $headers), "-f" . $from);
    if (!$ok) {
        error_log("send_email: mail() failed for a message to " . count($recipients) . " recipient(s)");
    }
    return $ok;
}
