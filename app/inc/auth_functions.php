<?php

/**
 * auth_functions.php
 *
 * Registration, login, password management, and role checks.
 * All passwords use PHP's password_hash()/password_verify() (bcrypt) -
 * no legacy crypt() support needed since this is a fresh database.
 */

/**
 * Returns a user row by email, or null if not found.
 */
function find_user_by_email(string $email): ?array
{
    $rows = query("SELECT * FROM users WHERE email = ?", $email);
    return $rows[0] ?? null;
}

/**
 * Returns a user row by id, or null if not found.
 */
function find_user_by_id(int $id): ?array
{
    $rows = query("SELECT * FROM users WHERE id = ?", $id);
    return $rows[0] ?? null;
}

/**
 * Creates a new self-registered user with the 'registered' role.
 * Returns the new user's id. Caller must already have validated the
 * inputs (unique email, matching password confirmation, etc).
 */
function create_user(string $email, string $password, string $first_name, string $surname, string $given_name = ""): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    query(
        "INSERT INTO users (email, password_hash, first_name, surname, given_name) VALUES (?, ?, ?, ?, ?)",
        $email,
        $hash,
        $first_name,
        $surname,
        $given_name !== "" ? $given_name : null
    );
    $user_id = (int) $_SESSION["inserted_row_id"];
    assign_role($user_id, "registered");
    return $user_id;
}

/**
 * Verifies credentials. Returns the user row on success, or false on
 * failure. Does not check must_change_password - the caller (login.php)
 * decides what to do with that.
 */
function attempt_login(string $email, string $password)
{
    $user = find_user_by_email($email);
    if ($user === null) {
        return false;
    }
    if (!password_verify($password, $user["password_hash"])) {
        return false;
    }
    return $user;
}

/**
 * Records a successful login's timestamp.
 */
function record_login(int $user_id): void
{
    query("UPDATE users SET last_logon = NOW() WHERE id = ?", $user_id);
}

/**
 * Sets a new password for a user. If $force_change is true, the user
 * will be required to change it again immediately on next login (used
 * for admin-initiated resets).
 */
function set_password(int $user_id, string $new_password, bool $force_change = false): void
{
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    query(
        "UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?",
        $hash,
        $force_change ? 1 : 0,
        $user_id
    );
}

/**
 * Admin action: generates a random temporary password for a user,
 * sets it, and flags the account to force a change on next login.
 * Returns the plaintext temporary password so the admin can relay it
 * to the member (by phone, in person, etc - no email sending is wired
 * up yet).
 */
function admin_reset_password(int $user_id): string
{
    // 10 random bytes -> readable base32-ish string, avoids ambiguous chars
    $temp_password = substr(str_replace(["+", "/", "="], "", base64_encode(random_bytes(9))), 0, 10);
    set_password($user_id, $temp_password, true);
    return $temp_password;
}

/**
 * Assigns a role to a user (idempotent - won't duplicate if already held).
 * $expiry is a 'Y-m-d' date string, or null for a role that doesn't expire.
 */
function assign_role(int $user_id, string $role_name, ?string $expiry = null): void
{
    $roles = query("SELECT id FROM roles WHERE role_name = ?", $role_name);
    if (empty($roles)) {
        return;
    }
    $role_id = $roles[0]["id"];
    query(
        "INSERT INTO user_roles (user_id, role_id, expiry) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE expiry = VALUES(expiry)",
        $user_id,
        $role_id,
        $expiry
    );
}

/**
 * Removes a role from a user.
 */
function revoke_role(int $user_id, string $role_name): void
{
    query(
        "DELETE ur FROM user_roles ur
         JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = ? AND r.role_name = ?",
        $user_id,
        $role_name
    );
}

/**
 * True if the given user currently holds the named role (and it hasn't
 * expired, if it has an expiry date).
 */
function user_has_role(int $user_id, string $role_name): bool
{
    $rows = query(
        "SELECT ur.id FROM user_roles ur
         JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = ? AND r.role_name = ?
         AND (ur.expiry IS NULL OR ur.expiry >= CURDATE())",
        $user_id,
        $role_name
    );
    return count($rows) > 0;
}

/**
 * Redirects to the login page if nobody's logged in.
 */
function require_login(): void
{
    if (empty($_SESSION["id"])) {
        redirect("/login.php");
    }
}

/**
 * Requires login AND a specific role, else redirects home.
 */
function require_role(string $role_name): void
{
    require_login();
    if (!user_has_role((int) $_SESSION["id"], $role_name)) {
        redirect("/index.php");
    }
}
