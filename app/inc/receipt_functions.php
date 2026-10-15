<?php

/**
 * receipt_functions.php
 *
 * Emails a confirmation of receipt when a membership payment is recorded.
 * One message per membership: a couple gets a single email addressed to
 * both partners (those who have an email address). Receipts are service
 * messages, so they are sent even to members who opted out of newsletters.
 * Sent from, and replies go to, the accounts mailbox.
 */

function accounts_address(): string
{
    $a = env("MAIL_ACCOUNTS", "accounts@u3aportalfred.org.za");
    return filter_var($a, FILTER_VALIDATE_EMAIL) ? $a : "accounts@u3aportalfred.org.za";
}

/** The people in a user's membership (including the user): id, names, email. */
function receipt_household(int $user_id): array
{
    $rows = query(
        "SELECT u2.id, u2.first_name, u2.surname, u2.email
         FROM users u JOIN users u2 ON u2.membership_id = u.membership_id
         WHERE u.id = ? AND u.membership_id IS NOT NULL ORDER BY u2.id",
        $user_id
    );
    if (!$rows) {
        $rows = query("SELECT id, first_name, surname, email FROM users WHERE id = ?", $user_id);
    }
    return $rows;
}

/** "Tom", "Tom and Alison", "Tom, Alison and Pat" */
function join_names(array $names): string
{
    $names = array_values($names);
    if (count($names) <= 1) {
        return $names[0] ?? "";
    }
    $last = array_pop($names);
    return implode(", ", $names) . " and " . $last;
}

/**
 * The payment to acknowledge for this member and year: the household row that
 * has an amount (the payer's), otherwise the member's own row.
 */
function receipt_payment(int $user_id, int $year): ?array
{
    $ids = array_map(fn($m) => (int) $m["id"], receipt_household($user_id));
    $in = implode(",", array_fill(0, count($ids), "?"));
    $rows = query(
        "SELECT * FROM membership_payments WHERE year = ? AND user_id IN ($in)
         ORDER BY (amount IS NULL), id LIMIT 1",
        $year,
        ...$ids
    );
    return $rows[0] ?? null;
}

/** The subject and body of a receipt. */
function receipt_message(array $household, array $payment, string $membership_type): array
{
    $first_names = array_map(fn($m) => $m["first_name"], $household);
    $date = date("j F Y", strtotime($payment["paid_date"]));
    $year = (int) $payment["year"];
    $type = $membership_type === "couple" || count($household) > 1 ? "Couple membership" : "Individual membership";

    $lines = [
        "Dear " . join_names($first_names) . ",",
        "",
        "Thank you. U3A Port Alfred acknowledges receipt of your membership payment:",
        "",
    ];
    if ($payment["amount"] !== null) {
        $lines[] = "  Amount received:   R" . number_format((float) $payment["amount"], 2, ".", " ");
    }
    $lines[] = "  Date received:     " . $date;
    $lines[] = "  Membership year:   " . $year . " (1 January to 31 December " . $year . ")";
    $lines[] = "  Membership:        " . $type;
    $lines[] = "  Members covered:   " . join_names(array_map(fn($m) => trim($m["first_name"] . " " . $m["surname"]), $household));
    $lines[] = "";
    $lines[] = "If any of this is not correct, please reply to this email.";
    $lines[] = "";
    $lines[] = "With thanks,";
    $lines[] = "U3A Port Alfred";
    $lines[] = PHYSICAL_ADDR;
    $lines[] = site_url("/");
    return ["Receipt for your U3A Port Alfred membership $year", implode("\n", $lines) . "\n"];
}

/**
 * Emails the receipt for a member's payment in a year.
 * Returns [bool $sent, string $note] - the note is shown to staff.
 */
function send_receipt(int $user_id, int $year): array
{
    $member = member_find($user_id);
    if ($member === null) {
        return [false, "Receipt not sent: member not found."];
    }
    if ($member["membership_type"] === "honorary") {
        return [false, "No receipt: honorary members do not pay."];
    }
    $payment = receipt_payment($user_id, $year);
    if ($payment === null) {
        return [false, "No receipt sent: there is no payment recorded for $year."];
    }

    $household = receipt_household($user_id);
    $addresses = [];
    foreach ($household as $m) {
        $email = strtolower(trim((string) $m["email"]));
        if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $addresses[$email] = true;
        }
    }
    $addresses = array_keys($addresses);
    if (!$addresses) {
        return [false, "No receipt sent: no email address on record."];
    }

    [$subject, $body] = receipt_message($household, $payment, $member["membership_type"]);
    $ok = send_email(implode(", ", $addresses), $subject, $body, [
        "from" => accounts_address(),
        "from_name" => "U3A Port Alfred Accounts",
        "reply_to" => accounts_address(),
    ]);
    if (!$ok) {
        return [false, "The receipt could not be sent. You can try again with the Resend button."];
    }
    try {
        foreach ($household as $m) {
            query(
                "UPDATE membership_payments SET receipt_sent_at = NOW() WHERE user_id = ? AND year = ?",
                (int) $m["id"],
                $year
            );
        }
    } catch (Throwable $e) {
        error_log("receipt timestamp not saved: " . $e->getMessage());   // migration 010 not run yet; the email was sent
    }
    return [true, "Receipt emailed to " . implode(" and ", $addresses) . "."];
}
