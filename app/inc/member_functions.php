<?php

/**
 * member_functions.php
 *
 * Staff-side member management: editing details, and the membership that
 * groups people (individual / couple / honorary). A membership has one
 * person (individual), at most two (couple) or is honorary (paid-up for
 * life, one or two people). users.membership_id NULL = individual.
 */

const MEMBERSHIP_TYPES = [
    "individual" => "Individual (R50)",
    "couple" => "Couple (R80)",
    "honorary" => "Honorary (paid-up for life)",
];

/** A user with their membership type. */
function member_find(int $id): ?array
{
    $rows = query(
        "SELECT u.*, COALESCE(m.membership_type, 'individual') AS membership_type
         FROM users u LEFT JOIN memberships m ON m.id = u.membership_id WHERE u.id = ?",
        $id
    );
    return $rows[0] ?? null;
}

/** Everyone in the user's membership, including the user. */
function member_household(array $user): array
{
    if (empty($user["membership_id"])) {
        return [$user];
    }
    return query(
        "SELECT id, first_name, surname, email FROM users WHERE membership_id = ? ORDER BY id",
        $user["membership_id"]
    );
}

/** "Jane Smith" for the other people in this user's membership ('' if none). */
function member_partner_names(array $user): string
{
    if (empty($user["membership_id"])) {
        return "";
    }
    $names = [];
    foreach (member_household($user) as $m) {
        if ((int) $m["id"] !== (int) $user["id"]) {
            $names[] = trim($m["first_name"] . " " . $m["surname"]);
        }
    }
    return implode(" and ", $names);
}

/** Page of members for the staff list; $search matches name/email/phone. */
function members_list(string $search, int $limit, int $offset): array
{
    $where = "1 = 1";
    $params = [];
    if ($search !== "") {
        $like = "%" . $search . "%";
        $where = "(u.email LIKE ? OR u.email2 LIKE ? OR u.surname LIKE ? OR u.first_name LIKE ? OR u.phone LIKE ?)";
        $params = [$like, $like, $like, $like, $like];
    }
    $rows = query(
        "SELECT u.id, u.first_name, u.surname, u.email, u.phone, u.membership_id,
                COALESCE(m.membership_type, 'individual') AS membership_type
         FROM users u LEFT JOIN memberships m ON m.id = u.membership_id
         WHERE $where ORDER BY u.surname, u.first_name LIMIT " . (int) $limit . " OFFSET " . (int) $offset,
        ...$params
    );
    foreach ($rows as &$row) {
        $row["partners"] = member_partner_names($row);
        $row["paid_up"] = is_paid_up((int) $row["id"]);
    }
    unset($row);
    return $rows;
}

function members_count(string $search): int
{
    if ($search === "") {
        return (int) query("SELECT COUNT(*) AS n FROM users")[0]["n"];
    }
    $like = "%" . $search . "%";
    return (int) query(
        "SELECT COUNT(*) AS n FROM users u
         WHERE u.email LIKE ? OR u.email2 LIKE ? OR u.surname LIKE ? OR u.first_name LIKE ? OR u.phone LIKE ?",
        $like,
        $like,
        $like,
        $like,
        $like
    )[0]["n"];
}

/** Validates the member-details form. Returns [array $errors, array $clean]. */
function member_validate(array $input, int $id): array
{
    $errors = [];
    $clean = [
        "first_name" => trim((string) ($input["first_name"] ?? "")),
        "surname" => trim((string) ($input["surname"] ?? "")),
        "email" => strtolower(trim((string) ($input["email"] ?? ""))),
        "email2" => strtolower(trim((string) ($input["email2"] ?? ""))),
        "phone" => trim((string) ($input["phone"] ?? "")),
        "address" => trim((string) ($input["address"] ?? "")),
    ];
    if ($clean["first_name"] === "" || mb_strlen($clean["first_name"]) > 100) {
        $errors[] = "Please enter a first name (up to 100 characters).";
    }
    if ($clean["surname"] === "" || mb_strlen($clean["surname"]) > 100) {
        $errors[] = "Please enter a surname (up to 100 characters).";
    }
    foreach (["email" => "Email", "email2" => "Second email"] as $field => $label) {
        if ($clean[$field] === "") {
            continue;
        }
        if (!filter_var($clean[$field], FILTER_VALIDATE_EMAIL) || mb_strlen($clean[$field]) > 255) {
            $errors[] = "$label is not a valid email address.";
            continue;
        }
        $other = find_user_by_email($clean[$field]);
        if ($other !== null && (int) $other["id"] !== $id) {
            $errors[] = "$label {$clean[$field]} already belongs to another member.";
        }
    }
    if ($clean["email"] !== "" && $clean["email"] === $clean["email2"]) {
        $errors[] = "The second email must be different from the first.";
    }
    if ($clean["email"] === "" && $clean["email2"] !== "") {
        $errors[] = "Please use the first email box if there is only one address.";
    }
    if (mb_strlen($clean["phone"]) > 100) {
        $errors[] = "The phone field is too long (maximum 100 characters).";
    }
    if (mb_strlen($clean["address"]) > 500) {
        $errors[] = "The address is too long (maximum 500 characters).";
    }
    return [$errors, $clean];
}

function member_update(int $id, array $c): void
{
    query(
        "UPDATE users SET first_name = ?, surname = ?, email = ?, email2 = ?, phone = ?, address = ? WHERE id = ?",
        $c["first_name"],
        $c["surname"],
        $c["email"] === "" ? null : $c["email"],
        $c["email2"] === "" ? null : $c["email2"],
        $c["phone"] === "" ? null : $c["phone"],
        $c["address"] === "" ? null : $c["address"],
        $id
    );
}

/** Deletes a membership that no longer has any members. */
function membership_cleanup(?int $membership_id): void
{
    if ($membership_id) {
        query(
            "DELETE FROM memberships WHERE id = ? AND NOT EXISTS (SELECT 1 FROM users WHERE membership_id = ?)",
            $membership_id,
            $membership_id
        );
    }
}

function membership_create(string $type): int
{
    query("INSERT INTO memberships (membership_type) VALUES (?)", $type);
    return (int) $_SESSION["inserted_row_id"];
}

/** Makes sure the user belongs to a membership row; returns its id. */
function membership_ensure(array $user): int
{
    if (!empty($user["membership_id"])) {
        return (int) $user["membership_id"];
    }
    $mid = membership_create("individual");
    query("UPDATE users SET membership_id = ? WHERE id = ?", $mid, $user["id"]);
    return $mid;
}

/** Copies a payment year from one partner to the other where only one has it. */
function membership_sync_payments(int $membership_id): void
{
    $rows = query(
        "SELECT p.user_id, p.year, p.paid_date FROM membership_payments p
         JOIN users u ON u.id = p.user_id WHERE u.membership_id = ? ORDER BY p.paid_date",
        $membership_id
    );
    foreach ($rows as $r) {
        payment_sync_household((int) $r["user_id"], (int) $r["year"], $r["paid_date"]);
    }
}

/**
 * Links two people as partners in one membership (a couple, or honorary if
 * either is honorary). Returns an error message, or null on success.
 */
function membership_link(int $user_id, int $partner_id): ?string
{
    if ($user_id === $partner_id) {
        return "A member cannot be their own partner.";
    }
    $a = member_find($user_id);
    $b = member_find($partner_id);
    if ($a === null || $b === null) {
        return "Member not found.";
    }
    if (!empty($a["membership_id"]) && $a["membership_id"] === $b["membership_id"]) {
        return "They are already linked.";
    }
    if (count(member_household($a)) > 1) {
        return trim($a["first_name"] . " " . $a["surname"]) . " is already linked to someone. Unlink them first.";
    }
    if (count(member_household($b)) > 1) {
        return trim($b["first_name"] . " " . $b["surname"]) . " already has a partner. Unlink them first.";
    }
    $type = ($a["membership_type"] === "honorary" || $b["membership_type"] === "honorary") ? "honorary" : "couple";
    $old = $a["membership_id"] ? (int) $a["membership_id"] : null;
    $mid = membership_ensure($b);
    query("UPDATE memberships SET membership_type = ? WHERE id = ?", $type, $mid);
    query("UPDATE users SET membership_id = ? WHERE id = ?", $mid, $user_id);
    membership_cleanup($old);
    membership_sync_payments($mid);
    return null;
}

/** Separates a person from their partner; they become an individual member. */
function membership_unlink(int $user_id): ?string
{
    $u = member_find($user_id);
    if ($u === null || empty($u["membership_id"]) || count(member_household($u)) < 2) {
        return "That member is not linked to anyone.";
    }
    $old = (int) $u["membership_id"];
    $mid = membership_create("individual");
    query("UPDATE users SET membership_id = ? WHERE id = ?", $mid, $user_id);
    // the one left behind is no longer part of a couple (honorary stays honorary)
    query("UPDATE memberships SET membership_type = 'individual' WHERE id = ? AND membership_type = 'couple'", $old);
    return null;
}

/** Changes the membership type. Returns an error message, or null on success. */
function membership_set_type(int $user_id, string $type): ?string
{
    if (!array_key_exists($type, MEMBERSHIP_TYPES)) {
        return "Invalid membership type.";
    }
    $u = member_find($user_id);
    if ($u === null) {
        return "Member not found.";
    }
    $size = count(member_household($u));
    if ($type === "individual" && $size > 1) {
        return "An individual membership has one person. Unlink the partner first.";
    }
    if ($type === "couple" && $size < 2) {
        return "A couple needs two people. Link a partner first.";
    }
    $mid = membership_ensure($u);
    query("UPDATE memberships SET membership_type = ? WHERE id = ?", $type, $mid);
    return null;
}

/**
 * Creates a member on behalf of a person who has not registered themselves
 * (no usable password - they must set one before they can log in).
 * Returns the new user's id.
 */
function member_create(array $c, string $type = "individual"): int
{
    $mid = membership_create(array_key_exists($type, MEMBERSHIP_TYPES) && $type !== "couple" ? $type : "individual");
    query(
        "INSERT INTO users (email, email2, first_name, surname, phone, address, password_hash, must_change_password, membership_id)
         VALUES (?, ?, ?, ?, ?, ?, '!', 1, ?)",
        $c["email"] === "" ? null : $c["email"],
        $c["email2"] === "" ? null : $c["email2"],
        $c["first_name"],
        $c["surname"],
        $c["phone"] === "" ? null : $c["phone"],
        $c["address"] === "" ? null : $c["address"],
        $mid
    );
    $user_id = (int) $_SESSION["inserted_row_id"];
    assign_role($user_id, "registered");
    return $user_id;
}

/**
 * Deletes a member and their payments and roles (e.g. a duplicate, or
 * someone who has died). Refuses to delete yourself. Returns an error
 * message, or null on success.
 */
function member_delete(int $id, int $acting_user_id): ?string
{
    if ($id === $acting_user_id) {
        return "You cannot delete your own account.";
    }
    $u = member_find($id);
    if ($u === null) {
        return "Member not found.";
    }
    $mid = $u["membership_id"] ? (int) $u["membership_id"] : null;
    query("DELETE FROM users WHERE id = ?", $id);
    if ($mid !== null) {
        $left = query("SELECT COUNT(*) AS n FROM users WHERE membership_id = ?", $mid)[0]["n"];
        if ((int) $left === 0) {
            membership_cleanup($mid);
        } elseif ((int) $left === 1) {
            query("UPDATE memberships SET membership_type = 'individual' WHERE id = ? AND membership_type = 'couple'", $mid);
        }
    }
    return null;
}
