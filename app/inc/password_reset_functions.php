<?php

/**
 * password_reset_functions.php
 *
 * "Forgot password" and "set your password" by email. A one-time link holds a
 * random token; only its SHA-256 hash is stored. Links expire (1 hour for
 * "forgot password", 7 days for an invitation) and work once.
 */

const RESET_TTL_SECONDS = 3600;          // forgot-password link
const INVITE_TTL_SECONDS = 7 * 86400;    // "set your password" invitation
const RESET_MAX_PER_HOUR = 5;            // per account, to stop mail flooding

/** Users who have an email address but no usable password yet (imported, not set up). */
function users_needing_invitation(int $limit = 50): array
{
    return query(
        "SELECT u.id, u.first_name, u.surname, u.email FROM users u
         WHERE u.email IS NOT NULL AND u.password_hash = '!'
           AND NOT EXISTS (SELECT 1 FROM password_resets p
                           WHERE p.user_id = u.id AND p.used_at IS NULL AND p.expires_at > NOW())
         ORDER BY u.surname, u.first_name LIMIT " . (int) $limit
    );
}

function users_needing_invitation_count(): int
{
    return (int) query(
        "SELECT COUNT(*) AS n FROM users u
         WHERE u.email IS NOT NULL AND u.password_hash = '!'
           AND NOT EXISTS (SELECT 1 FROM password_resets p
                           WHERE p.user_id = u.id AND p.used_at IS NULL AND p.expires_at > NOW())"
    )[0]["n"];
}

/** Creates a token for a user and returns the plaintext token (shown only in the email). */
function reset_token_create(int $user_id, string $purpose): string
{
    $token = bin2hex(random_bytes(32));
    $ttl = $purpose === "invite" ? INVITE_TTL_SECONDS : RESET_TTL_SECONDS;
    // older unused links stop working once a new one is issued
    query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", $user_id);
    query(
        "INSERT INTO password_resets (user_id, token_hash, purpose, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . (int) $ttl . " SECOND))",
        $user_id,
        hash("sha256", $token),
        $purpose
    );
    return $token;
}

/** The valid (unused, unexpired) reset row for a token, or null. */
function reset_token_lookup(string $token): ?array
{
    if (!preg_match("/^[a-f0-9]{64}$/", $token)) {
        return null;
    }
    $rows = query(
        "SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()",
        hash("sha256", $token)
    );
    return $rows[0] ?? null;
}

function reset_recent_count(int $user_id): int
{
    return (int) query(
        "SELECT COUNT(*) AS n FROM password_resets WHERE user_id = ? AND created > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $user_id
    )[0]["n"];
}

/**
 * Emails a password link to a member. $purpose 'reset' (they asked) or
 * 'invite' (staff sent it). Returns true if an email was handed off.
 */
function send_password_email(array $user, string $purpose): bool
{
    if (empty($user["email"])) {
        return false;
    }
    $token = reset_token_create((int) $user["id"], $purpose);
    $link = site_url("/set_password.php?token=" . $token);
    $name = trim($user["first_name"]);
    if ($purpose === "invite") {
        $subject = "Set your password for the U3A Port Alfred website";
        $body = "Hello $name,\n\n"
            . "U3A Port Alfred has a members' area on its website. Please use the link below to choose your\n"
            . "own password. The link works once and is valid for 7 days:\n\n$link\n\n"
            . "Your login name is this email address: {$user["email"]}\n\n"
            . "If you were not expecting this message, you can ignore it.\n\n"
            . "U3A Port Alfred\n" . site_url("/") . "\n";
    } else {
        $subject = "Reset your U3A Port Alfred password";
        $body = "Hello $name,\n\n"
            . "Someone (hopefully you) asked to reset the password for your U3A Port Alfred account.\n"
            . "Use the link below to choose a new password. It works once and is valid for 1 hour:\n\n$link\n\n"
            . "If you did not ask for this, ignore this message - your password has not been changed.\n\n"
            . "U3A Port Alfred\n" . site_url("/") . "\n";
    }
    return send_email($user["email"], $subject, $body);
}

/**
 * Handles a "forgot password" request for an email address. Never reveals
 * whether the address belongs to an account.
 */
function handle_forgot_password(string $email): void
{
    $user = find_user_by_email($email);
    if ($user === null || empty($user["email"])) {
        return;
    }
    if (reset_recent_count((int) $user["id"]) >= RESET_MAX_PER_HOUR) {
        return;
    }
    // send to the address they typed (it may be their second address)
    $user["email"] = $email;
    send_password_email($user, "reset");
}
