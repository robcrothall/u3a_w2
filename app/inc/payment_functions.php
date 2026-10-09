<?php

/**
 * payment_functions.php
 *
 * Membership payments: one row per person per membership year
 * (membership_payments, unique on user_id + year). The membership year is
 * the calendar year. "Paid-up" is computed - nobody maintains a flag:
 *
 *   - individual: paid-up if they have a payment row for the year
 *   - couple:     when one partner pays, BOTH get a payment row (the partner's
 *                 row has no amount), so both are paid-up
 *   - honorary:   paid-up for life, no payment needed
 *
 * A user with no membership (users.membership_id NULL) counts as an individual.
 */

const MEMBERSHIP_FEE_HINT = "Annual membership: individuals R50, couples R80 (record it once - a partner is marked paid-up automatically).";

/** The membership year to show: ?year= if sensible, else the current year. */
function payment_year(): int
{
    $current = (int) date("Y");
    $year = (int) ($_GET["year"] ?? $_POST["year"] ?? $current);
    return ($year >= 2020 && $year <= $current + 1) ? $year : $current;
}

/** Years offered in the year picker, newest first. */
function payment_year_choices(): array
{
    return range((int) date("Y") + 1, 2020);
}

/** Members whose name or email matches the search text, with their membership details. */
function members_search(string $search, int $limit = 25): array
{
    $like = "%" . $search . "%";
    $rows = query(
        "SELECT u.id, u.first_name, u.surname, u.given_name, u.email, u.email2, u.phone, u.membership_id,
                COALESCE(m.membership_type, 'individual') AS membership_type
         FROM users u LEFT JOIN memberships m ON m.id = u.membership_id
         WHERE u.email LIKE ? OR u.email2 LIKE ? OR u.surname LIKE ? OR u.first_name LIKE ? OR u.given_name LIKE ?
         ORDER BY u.surname, u.first_name LIMIT " . (int) $limit,
        $like,
        $like,
        $like,
        $like,
        $like
    );
    foreach ($rows as &$row) {
        $row["partners"] = member_partner_names($row);
    }
    unset($row);
    return $rows;
}

/** All registered members, for the export. */
function members_all(): array
{
    return query(
        "SELECT u.id, u.first_name, u.surname, u.given_name, u.email, u.email2, u.phone, u.address,
                COALESCE(m.membership_type, 'individual') AS membership_type
         FROM users u LEFT JOIN memberships m ON m.id = u.membership_id
         ORDER BY u.surname, u.first_name"
    );
}

function payment_find(int $user_id, int $year): ?array
{
    $rows = query("SELECT * FROM membership_payments WHERE user_id = ? AND year = ?", $user_id, $year);
    return $rows[0] ?? null;
}

function payment_find_by_id(int $id): ?array
{
    $rows = query("SELECT * FROM membership_payments WHERE id = ?", $id);
    return $rows[0] ?? null;
}

/** User ids in the same membership as this user (always includes the user). */
function household_user_ids(int $user_id): array
{
    $rows = query(
        "SELECT u2.id FROM users u
         JOIN users u2 ON u2.membership_id = u.membership_id
         WHERE u.id = ? AND u.membership_id IS NOT NULL",
        $user_id
    );
    $ids = array_map(fn($r) => (int) $r["id"], $rows);
    return $ids ?: [$user_id];
}

/**
 * Everyone who is paid-up for a year: those with a payment row, plus honorary
 * members. Ordered by surname. payment_id is NULL for honorary members.
 */
function paid_up_members(int $year): array
{
    return query(
        "SELECT u.id, u.first_name, u.surname, u.given_name, u.email, u.email2, u.phone, u.address,
                COALESCE(m.membership_type, 'individual') AS membership_type,
                p.id AS payment_id, p.amount, p.paid_date, p.recorded_by,
                CONCAT(r.first_name, ' ', r.surname) AS recorder
         FROM users u
         LEFT JOIN memberships m ON m.id = u.membership_id
         LEFT JOIN membership_payments p ON p.user_id = u.id AND p.year = ?
         LEFT JOIN users r ON r.id = p.recorded_by
         WHERE p.id IS NOT NULL OR m.membership_type = 'honorary'
         ORDER BY u.surname, u.first_name",
        $year
    );
}

function is_paid_up(int $user_id, ?int $year = null): bool
{
    $year = $year ?? (int) date("Y");
    $rows = query(
        "SELECT 1 FROM users u
         LEFT JOIN memberships m ON m.id = u.membership_id
         LEFT JOIN membership_payments p ON p.user_id = u.id AND p.year = ?
         WHERE u.id = ? AND (p.id IS NOT NULL OR m.membership_type = 'honorary')",
        $year,
        $user_id
    );
    return count($rows) > 0;
}

/** Validates the record-payment form. Returns [array $errors, array $clean]. */
function payment_validate(array $input): array
{
    $errors = [];
    $current = (int) date("Y");
    $clean = [
        "user_id" => (int) ($input["user_id"] ?? 0),
        "year" => (int) ($input["year"] ?? 0),
        "amount" => null,
        "paid_date" => trim((string) ($input["paid_date"] ?? "")),
    ];

    if ($clean["user_id"] <= 0 || find_user_by_id($clean["user_id"]) === null) {
        $errors[] = "That member could not be found.";
    }
    if ($clean["year"] < 2020 || $clean["year"] > $current + 1) {
        $errors[] = "Please choose a valid membership year.";
    }

    $amount = str_replace([",", " "], [".", ""], trim((string) ($input["amount"] ?? "")));
    if ($amount !== "") {
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $amount)) {
            $errors[] = "The amount must be a number such as 50 or 80.00.";
        } else {
            $clean["amount"] = number_format((float) $amount, 2, ".", "");
        }
    }

    $date = DateTime::createFromFormat("Y-m-d", $clean["paid_date"]);
    if (!$date || $date->format("Y-m-d") !== $clean["paid_date"]) {
        $errors[] = "Please enter a valid payment date.";
    } elseif ($clean["paid_date"] > date("Y-m-d")) {
        $errors[] = "The payment date cannot be in the future.";
    }
    return [$errors, $clean];
}

/**
 * Gives every other person in the same membership a payment row for the year
 * (no amount) if they do not have one yet, so a couple is paid-up together.
 */
function payment_sync_household(int $user_id, int $year, string $paid_date): void
{
    foreach (household_user_ids($user_id) as $other) {
        if ($other !== $user_id && payment_find($other, $year) === null) {
            query(
                "INSERT INTO membership_payments (user_id, year, amount, paid_date, recorded_by)
                 VALUES (?, ?, NULL, ?, NULL)",
                $other,
                $year,
                $paid_date
            );
        }
    }
}

/**
 * Records a payment, or corrects the existing one for that member and year,
 * and marks their partner paid-up too. Returns true if an existing payment
 * was updated.
 */
function payment_save(array $p, int $recorded_by): bool
{
    $existing = payment_find($p["user_id"], $p["year"]);
    if ($existing !== null) {
        query(
            "UPDATE membership_payments SET amount = ?, paid_date = ?, recorded_by = ? WHERE id = ?",
            $p["amount"],
            $p["paid_date"],
            $recorded_by,
            $existing["id"]
        );
    } else {
        query(
            "INSERT INTO membership_payments (user_id, year, amount, paid_date, recorded_by)
             VALUES (?, ?, ?, ?, ?)",
            $p["user_id"],
            $p["year"],
            $p["amount"],
            $p["paid_date"],
            $recorded_by
        );
    }
    payment_sync_household($p["user_id"], $p["year"], $p["paid_date"]);
    return $existing !== null;
}

/** Removes a payment and the matching rows of the person's partner for that year. */
function payment_delete(int $id): void
{
    $payment = payment_find_by_id($id);
    if ($payment === null) {
        return;
    }
    foreach (household_user_ids((int) $payment["user_id"]) as $uid) {
        query("DELETE FROM membership_payments WHERE user_id = ? AND year = ?", $uid, $payment["year"]);
    }
}

/**
 * Makes a value safe for a CSV cell opened in Excel: cells starting with
 * = + - @ would otherwise be treated as formulas.
 */
function csv_safe(?string $value): string
{
    $value = (string) $value;
    if ($value !== "" && strpbrk($value[0], "=+-@\t\r") !== false) {
        return "'" . $value;
    }
    return $value;
}
