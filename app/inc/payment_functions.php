<?php

/**
 * payment_functions.php
 *
 * Membership payments: one row per member per membership year
 * (membership_payments, unique on user_id + year). The membership year is
 * the calendar year. "Paid-up" is computed from these rows - it is not a
 * flag anyone maintains by hand.
 */

const MEMBERSHIP_FEE_HINT = "Annual membership: individuals R50, couples R80 (record a payment for each partner).";

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

/** Members whose name or email matches the search text. */
function members_search(string $search, int $limit = 25): array
{
    $like = "%" . $search . "%";
    return query(
        "SELECT id, first_name, surname, given_name, email FROM users
         WHERE email LIKE ? OR surname LIKE ? OR first_name LIKE ? OR given_name LIKE ?
         ORDER BY surname, first_name LIMIT " . (int) $limit,
        $like,
        $like,
        $like,
        $like
    );
}

/** All registered members, for the export. */
function members_all(): array
{
    return query("SELECT id, first_name, surname, given_name, email FROM users ORDER BY surname, first_name");
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

/** Payments for a year, with member names, ordered by surname. */
function payments_for_year(int $year): array
{
    return query(
        "SELECT p.*, u.first_name, u.surname, u.given_name, u.email,
                CONCAT(r.first_name, ' ', r.surname) AS recorder
         FROM membership_payments p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN users r ON r.id = p.recorded_by
         WHERE p.year = ?
         ORDER BY u.surname, u.first_name",
        $year
    );
}

function is_paid_up(int $user_id, ?int $year = null): bool
{
    return payment_find($user_id, $year ?? (int) date("Y")) !== null;
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
 * Records a payment, or corrects the existing one for that member and year.
 * Returns true if an existing payment was updated.
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
        return true;
    }
    query(
        "INSERT INTO membership_payments (user_id, year, amount, paid_date, recorded_by)
         VALUES (?, ?, ?, ?, ?)",
        $p["user_id"],
        $p["year"],
        $p["amount"],
        $p["paid_date"],
        $recorded_by
    );
    return false;
}

function payment_delete(int $id): void
{
    query("DELETE FROM membership_payments WHERE id = ?", $id);
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
