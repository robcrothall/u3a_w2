<?php

/**
 * mail_functions.php
 *
 * Sends plain-text email from the website. Uses PHP's mail() on the cPanel
 * host, from a mailbox on the same domain so SPF/DKIM line up and messages are
 * not treated as spoofed. Default sender is MAIL_FROM in .env (default
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
 * $opts: "from" (address on our own domain), "from_name", "reply_to".
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
    $subject = trim(preg_replace("/[\r\n]+/", " ", $subject));
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

    if (env("MAIL_TRANSPORT", "mail") === "log") {
        $log = dirname(__DIR__, 2) . "/mail_out.log";
        $entry = "To: $toHeader\nFrom: $fromName <$from>\nReply-To: $replyTo\nSubject: $subject\n\n$body\n" . str_repeat("-", 60) . "\n";
        return file_put_contents($log, $entry, FILE_APPEND) !== false;
    }

    $headers = [
        "From: $fromName <$from>",
        "Reply-To: $replyTo",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "X-Mailer: U3A website",
    ];
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $ok = @mail($toHeader, $encodedSubject, str_replace("\r\n", "\n", $body), implode("\r\n", $headers), "-f" . $from);
    if (!$ok) {
        error_log("send_email: mail() failed for a message to " . count($recipients) . " recipient(s)");
    }
    return $ok;
}
