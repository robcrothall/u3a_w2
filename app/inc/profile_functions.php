<?php

/**
 * profile_functions.php
 *
 * The extra member information (title, mobile number, three short
 * questions) and the participation/privacy acknowledgement.
 */

/** Bump this when the acknowledgement wording changes; members are asked to accept the new version. */
const CONSENT_VERSION = 1;

const MEMBER_TITLE_SUGGESTIONS = ["Mr", "Mrs", "Ms", "Miss", "Dr", "Prof", "Rev", "Adv", "Sir", "Lady"];

/**
 * The acknowledgement shown at registration and at the next login of
 * existing members. DRAFT wording: the Committee should approve it.
 * Changing the text means increasing CONSENT_VERSION.
 */
function consent_text(): string
{
    return "I acknowledge that my participation in any U3A Port Alfred activity is entirely at my own risk, "
        . "and that I will not hold U3A Port Alfred, its committee or its members liable for any loss or damage "
        . "to myself or my property. I understand that meetings and events may be photographed, filmed or "
        . "recorded, and that these images and recordings may be published on the U3A website and shared "
        . "online, including internationally. I understand that U3A Port Alfred keeps my personal information "
        . "to run my membership, and handles it in line with the Protection of Personal Information Act (POPIA).";
}

function consent_record(int $user_id): void
{
    query("UPDATE users SET consent_version = ?, consent_at = NOW() WHERE id = ?", CONSENT_VERSION, $user_id);
}

function consent_needed(array $user): bool
{
    return (int) ($user["consent_version"] ?? 0) < CONSENT_VERSION;
}

/** Country calling code at the start of $digits, or null if not recognised. */
function country_code_prefix(string $digits): ?string
{
    static $two = ["20", "27", "30", "31", "32", "33", "34", "36", "39", "40", "41", "43", "44", "45", "46", "47", "48",
        "49", "51", "52", "53", "54", "55", "56", "57", "58", "60", "61", "62", "63", "64", "65", "66", "81", "82",
        "84", "86", "90", "91", "92", "93", "94", "95", "98"];
    if ($digits === "") {
        return null;
    }
    if ($digits[0] === "1" || $digits[0] === "7") {
        return $digits[0];
    }
    if (in_array(substr($digits, 0, 2), $two, true)) {
        return substr($digits, 0, 2);
    }
    if (strlen($digits) >= 3 && strpos("2345689", $digits[0]) !== false) {
        return substr($digits, 0, 3);
    }
    return null;
}

/** "0836785050" -> "083 678 5050": last 4 digits, then groups of 3 from the right, remainder first. */
function group_digits(string $d): string
{
    $parts = [substr($d, -4)];
    $d = substr($d, 0, -4);
    while (strlen($d) > 3) {
        array_unshift($parts, substr($d, -3));
        $d = substr($d, 0, -3);
    }
    if ($d !== "") {
        array_unshift($parts, $d);
    }
    return implode(" ", $parts);
}

/**
 * Turns a typed mobile number into the stored form.
 *   South Africa, no country code:  083 678 5050
 *   with a country code:            (+27) 83 678 5050   (the leading 0 is dropped)
 * Returns [formatted or null, error message or null]. Empty input -> [null, null].
 */
function normalize_mobile(string $raw): array
{
    $raw = trim($raw);
    if ($raw === "") {
        return [null, null];
    }
    $help = "Please enter a mobile number such as 083 678 5050, or with a country code such as +44 7700 900123.";
    $s = preg_replace('/[\s().\-]/', "", $raw);
    if (!preg_match('/^\+?\d+$/', $s)) {
        return [null, "A mobile number may only contain digits, spaces and + ( ) - . " . $help];
    }
    $intl = false;
    if ($s[0] === "+") {
        $intl = true;
        $d = substr($s, 1);
    } elseif (strncmp($s, "00", 2) === 0) {
        $intl = true;
        $d = substr($s, 2);
    } else {
        $d = $s;
        if ($d[0] === "0") {
            if (strlen($d) !== 10) {
                return [null, "That number does not have 10 digits. " . $help];
            }
        } elseif (strlen($d) === 9) {
            $d = "0" . $d;            // Excel and phones often drop the leading zero
        } elseif (strlen($d) === 11 && strncmp($d, "27", 2) === 0) {
            $intl = true;             // 27836785050 without a plus
        } else {
            return [null, $help];
        }
    }
    if (!$intl) {
        return [group_digits($d), null];
    }
    $cc = country_code_prefix($d);
    if ($cc === null) {
        return [null, "The country code was not recognised. " . $help];
    }
    $national = substr($d, strlen($cc));
    if ($national !== "" && $national[0] === "0") {
        $national = substr($national, 1);      // "+27 (0)83 ..." -> trunk zero is not part of the number
    }
    if (strlen($national) < 6 || strlen($national) > 12) {
        return [null, "That number is too short or too long. " . $help];
    }
    return ["(+$cc) " . group_digits($national), null];
}

/** Validates the extra profile fields. Returns [array $errors, array $clean]. */
function profile_validate(array $input): array
{
    $errors = [];
    $clean = [];

    $title = trim(preg_replace("/\s+/u", " ", (string) ($input["title"] ?? "")));
    if ($title !== "" && (mb_strlen($title) > 40 || !preg_match("/^[\p{L}.' -]+$/u", $title))) {
        $errors[] = "Title may contain letters only (for example Rev Dr), up to 40 characters.";
    }
    $clean["title"] = $title;

    [$mobile, $mobileError] = normalize_mobile((string) ($input["mobile"] ?? ""));
    if ($mobileError !== null) {
        $errors[] = $mobileError;
    }
    $clean["mobile"] = $mobile ?? "";

    foreach (["can_present" => "Prepared to present", "skills" => "Skills or expertise", "wants_subjects" => "Subjects you would like offered"] as $field => $label) {
        $text = trim(preg_replace("/\s+/u", " ", (string) ($input[$field] ?? "")));
        if (mb_strlen($text) > 250) {
            $errors[] = "$label must be 250 characters or fewer (now " . mb_strlen($text) . ").";
        }
        $clean[$field] = $text;
    }
    return [$errors, $clean];
}

/** Saves the extra profile fields for a member. */
function member_profile_save(int $user_id, array $c): void
{
    query(
        "UPDATE users SET title = ?, mobile = ?, can_present = ?, skills = ?, wants_subjects = ? WHERE id = ?",
        $c["title"] === "" ? null : $c["title"],
        $c["mobile"] === "" ? null : $c["mobile"],
        $c["can_present"] === "" ? null : $c["can_present"],
        $c["skills"] === "" ? null : $c["skills"],
        $c["wants_subjects"] === "" ? null : $c["wants_subjects"],
        $user_id
    );
}

/** Members who answered at least one of the three questions (staff report). */
function members_with_interests(string $search = ""): array
{
    $where = "(u.can_present IS NOT NULL OR u.skills IS NOT NULL OR u.wants_subjects IS NOT NULL)";
    $params = [];
    if ($search !== "") {
        $like = "%" . $search . "%";
        $where .= " AND (u.first_name LIKE ? OR u.surname LIKE ? OR u.can_present LIKE ? OR u.skills LIKE ? OR u.wants_subjects LIKE ?)";
        $params = [$like, $like, $like, $like, $like];
    }
    return query(
        "SELECT u.id, u.title, u.first_name, u.surname, u.email, u.mobile, u.can_present, u.skills, u.wants_subjects
         FROM users u WHERE $where ORDER BY u.surname, u.first_name",
        ...$params
    );
}
