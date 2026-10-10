<?php

/**
 * import_members.php - turns the membership spreadsheet (as TSV, see
 * xlsx_to_tsv.ps1) and the Google Groups export into:
 *
 *   members_review.csv   one row per proposed person, with flags - CHECK THIS FIRST
 *   issues.txt           summary of everything that needs a human decision
 *   import_members.sql   memberships, users, roles and payments, ready for phpMyAdmin
 *
 * Usage:  php tools/import_members.php <members.tsv> <googlegroups.csv> <output-dir>
 *
 * The outputs contain personal information. Write them OUTSIDE the git
 * repository (this repo is public) and never commit them.
 *
 * Sheet columns: A email, B nickname (first name), C surname, D phone,
 * E address, F partner, G date paid (ignored), H 2024, I 2025, J 2026.
 */

if ($argc < 4) {
    fwrite(STDERR, "Usage: php import_members.php <members.tsv> <googlegroups.csv> <output-dir>\n");
    exit(1);
}
[, $tsvFile, $ggFile, $outDir] = $argv;
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

const YEARS = [2024 => 8, 2025 => 9, 2026 => 10];   // year => column index in the TSV
const PARTICLES = ["van", "von", "der", "den", "de", "da", "du", "le", "la", "di", "del", "ten", "ter", "op", "'t"];
const SPRV_FULL = "Settlers Park Retirement Village, Horton Road, Port Alfred 6170";
$overrides = is_file("$outDir/overrides.json") ? (json_decode(file_get_contents("$outDir/overrides.json"), true) ?: []) : [];
// Google Groups addresses that are not members (own aliases, system mailboxes): one per line in <output-dir>/skip_emails.txt
$ggSkip = [];
if (is_file("$outDir/skip_emails.txt")) {
    foreach (file("$outDir/skip_emails.txt") as $ln) {
        $ln = trim($ln);
        if ($ln === "" || !preg_match("/^(\S+@\S+)\s*(.*)$/", $ln, $m)) {
            continue;
        }
        if (preg_match("/remain|keep|should be a member/i", $m[2]) && !preg_match("/\bskip\b/i", $m[2])) {
            continue;   // note says: this one is a real member, import it
        }
        $ggSkip[] = strtolower($m[1]);
    }
}
// overrides.json: drop_emails = wrong/unwanted addresses removed everywhere; deceased_emails = belongs to someone who has died
$dropEmails = array_map("strtolower", $overrides["drop_emails"] ?? []);
foreach (array_map("strtolower", $overrides["deceased_emails"] ?? []) as $de) {
    $dropEmails[] = $de;
}

$people = [];        // id => person
$skipped = [];       // rows not imported (deceased, ...)
$deceasedKeys = [];  // name keys of deceased people, so a partner reference to them is not created
$deceasedEmails = [];
$issues = [];        // general messages

// ---------------------------------------------------------------- helpers

function key_of(string $first, string $surname): string
{
    $s = mb_strtolower(trim($first . " " . $surname));
    return trim(preg_replace("/[^a-z0-9 ]+/u", "", preg_replace("/\s+/", " ", $s)));
}

function squash(string $s): string
{
    return preg_replace("/[^a-z0-9]/", "", mb_strtolower($s));
}

function clean_text(string $s): string
{
    return trim(preg_replace("/\s+/u", " ", $s));
}

function is_particle(string $w): bool
{
    return in_array(mb_strtolower($w), PARTICLES, true);
}

function strip_titles(string $s): string
{
    return trim(preg_replace("/^(\(?(dr|prof|mr|mrs|ms|rev)\)?\.?\s+)+/i", "", $s));
}

function fix_case(string $s): string
{
    // all-lowercase first names ("tony", "rod") get an initial capital
    if ($s !== "" && $s === mb_strtolower($s) && !preg_match("/[0-9@]/", $s)) {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
    return $s;
}

function parse_emails(string $s): array
{
    preg_match_all("/[A-Za-z0-9._%+'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/", $s, $m);
    return array_values(array_unique(array_map("strtolower", $m[0])));
}

/** Local parts of anything that looks like an address but is not a complete one (typos). */
function broken_email_locals(string $s): array
{
    $good = parse_emails($s);
    $locals = [];
    foreach (preg_split("/[;\s\/,<>]+/", $s) as $tok) {
        if (strpos($tok, "@") !== false && !in_array(strtolower($tok), $good, true)) {
            $locals[] = strtolower(substr($tok, 0, strpos($tok, "@")));
        }
    }
    return $locals;
}

function format_phone(string $digits): ?string
{
    if (strlen($digits) === 9) {
        $digits = "0" . $digits;   // Excel dropped the leading zero
    }
    if (strlen($digits) !== 10) {
        return null;
    }
    return substr($digits, 0, 3) . " " . substr($digits, 3, 3) . " " . substr($digits, 6);
}

function parse_phones(string $raw, array &$flags): array
{
    $raw = trim($raw);
    if ($raw === "") {
        return [];
    }
    if (preg_match("/^\d(\.\d+)?E\+?\d+$/i", $raw)) {   // scientific notation from Excel
        $raw = sprintf("%.0f", (float) $raw);
    }
    $out = [];
    foreach (preg_split("/;|\/|,|\band\b/i", $raw) as $part) {
        $digits = preg_replace("/\D/", "", $part);
        if ($digits === "") {
            continue;
        }
        $f = format_phone($digits);
        if ($f === null) {
            $flags[] = "phone not 10 digits: " . trim($part);
        } else {
            $out[] = $f;
        }
    }
    return $out;
}

function expand_address(string $raw, array &$flags): string
{
    $a = clean_text($raw);
    if ($a === "" || preg_match("/^(new members?|deceased)$/i", $a)) {
        return "";
    }
    $a = preg_replace("/\bsprv\b(\s*,?\s*PA\b)?/i", SPRV_FULL, $a);
    $a = preg_replace("/\bR\.?A\.?\s*Marina\b|\bRPA\s+Marina\b/i", "Royal Alfred Marina", $a);
    $a = preg_replace("/\bPA\b\s*$/", "Port Alfred 6170", $a);
    $a = preg_replace("/\bPA\b/", "Port Alfred", $a);
    return trim($a, " ,");
}

/** Interprets one 2024/2025/2026 cell. */
function parse_pay(string $raw, int $year): array
{
    $raw = trim($raw);
    $months = ["jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6,
        "jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12];
    if ($raw === "") {
        return ["type" => "none"];
    }
    if (preg_match("/^\d+(\.\d+)?$/", $raw) && (float) $raw > 30000) {
        return ["type" => "date", "date" => gmdate("Y-m-d", (int) (((float) $raw - 25569) * 86400))];
    }
    if (preg_match("/^n\/?a$/i", $raw)) {
        return ["type" => "na"];
    }
    if (preg_match("/deceased/i", $raw)) {
        return ["type" => "deceased"];
    }
    if (preg_match("/^\??\s*paid$/i", $raw)) {
        return ["type" => "paid", "note" => "marked 'paid' with no date"];
    }
    if (preg_match("/services rendered/i", $raw)) {
        return ["type" => "paid", "note" => "'Services rendered' - treated as paid, no money"];
    }
    if (preg_match("/^(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{2,4})$/", $raw, $m) && isset($months[strtolower(substr($m[2], 0, 3))])) {
        $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        return ["type" => "date", "date" => sprintf("%04d-%02d-%02d", $y, $months[strtolower(substr($m[2], 0, 3))], $m[1]),
            "note" => "date text '$raw'"];
    }
    if (preg_match("/^(\d{1,2})\s*[=\-\/]\s*(\d{2})\s*-\s*(\d{2})$/", $raw, $m)) {
        return ["type" => "date", "date" => sprintf("20%02d-%02d-%02d", $m[3], $m[2], $m[1]), "note" => "date text '$raw'"];
    }
    if (preg_match("/^(\d{2})\s*-\s*(\d{2})$/", $raw, $m)) {
        return ["type" => "date", "date" => sprintf("20%02d-%02d-01", $m[2], $m[1]), "note" => "only month known ('$raw'), day set to 1st"];
    }
    return ["type" => "other", "note" => "unrecognised: '$raw'"];
}

/**
 * Splits the nickname + surname columns into one or two (first, surname) pairs.
 * Returns [list of [first, surname], flags[]]
 */
function parse_names(string $nick, string $surnameCol): array
{
    $flags = [];
    $nick = clean_text($nick);
    $S = clean_text(preg_replace("/\s+-\s+.*$/", "", $surnameCol));
    if (preg_match("/^(.*?)\s+-\s+(.+)$/", $surnameCol, $m)) {
        $flags[] = "surname column had a note: '" . trim($m[2]) . "'";
    }
    // "Surname, First"
    if (preg_match("/^([^,]+),\s*([^,]+)$/", $nick, $m)) {
        $nick = clean_text($m[2] . " " . $m[1]);
    }
    $nick = strip_titles($nick);

    // two people in one cell: "Peter & Cindy", "Dave and Margaret Koller"
    $parts = preg_split("/\s*(?:&|\band\b)\s*/i", $nick);
    if (count($parts) === 2 && $parts[0] !== "" && $parts[1] !== "") {
        $trim = function (string $p) use ($S) {
            $p = clean_text($p);
            if ($S !== "" && preg_match("/^(.*?)\s*\b" . preg_quote($S, "/") . "$/i", $p, $mm)) {
                $p = trim($mm[1]);
            }
            return $p;
        };
        $f1 = $trim($parts[0]);
        $f2 = $trim($parts[1]);
        return [[[fix_case($f1), $S], [fix_case($f2), $S]], array_merge($flags, ["two people in one row"])];
    }

    $tokens = $nick === "" ? [] : explode(" ", $nick);
    $first = $nick;
    $surname = $S;
    if ($S === "" && count($tokens) > 1) {
        $surname = array_pop($tokens);
        $first = implode(" ", $tokens);
        $flags[] = "surname column empty - guessed '$surname'";
    } elseif ($S !== "") {
        $last = $tokens ? end($tokens) : "";
        if (count($tokens) === 1 && (strcasecmp($nick, $S) === 0 || preg_match("/-" . preg_quote($S, "/") . "$/i", $nick))) {
            $first = "";                       // nickname is just the surname
            $surname = preg_match("/-" . preg_quote($S, "/") . "$/i", $nick) ? $nick : $S;
            $flags[] = "no first name in the sheet";
        } elseif (preg_match("/^(.*)\s+" . preg_quote($S, "/") . "$/i", $nick, $m)) {      // ends with the surname column (may be several words)
            $ft = explode(" ", trim($m[1]));
            $sn = [$S];
            while ($ft && is_particle(end($ft))) {                                          // "Dawie Van" + "Wyk" -> Dawie, "Van Wyk"
                array_unshift($sn, array_pop($ft));
            }
            $first = implode(" ", $ft);
            $surname = implode(" ", $sn);
        } elseif (strcasecmp($last, $S) === 0) {                                          // last word is the surname; absorb "van der" particles
            array_pop($tokens);
            $sn = [$S];
            while ($tokens && is_particle(end($tokens))) {
                array_unshift($sn, array_pop($tokens));
            }
            $surname = implode(" ", $sn);
            $first = implode(" ", $tokens);
        } elseif ($last !== "" && preg_match("/^(.+)-" . preg_quote($S, "/") . "$/i", $last)) {   // Hilton-Barber with column "Barber"
            array_pop($tokens);
            $surname = $last;
            $first = implode(" ", $tokens);
        } elseif ($tokens && strcasecmp($tokens[0], $S) === 0 && count($tokens) > 1) {  // "Mol Anske"
            array_shift($tokens);
            $first = implode(" ", $tokens);
            $flags[] = "name was 'Surname First' - swapped";
        } elseif (count($tokens) === 1 && preg_match("/[0-9@]/", $nick)) {
            $first = "";
            $flags[] = "no first name in the sheet (only a handle)";
        }
    }
    $first = fix_case(clean_text($first));
    if (strpos($surname, " ") === false) {
        $surname = fix_case($surname);
    }
    if ($first !== "" && preg_match("/^[A-Za-z]\.?$/", $first)) {
        $flags[] = "first name is only an initial";
    }
    return [[[$first, $surname]], $flags];
}

// ---------------------------------------------------------------- read the sheet

$records = [];
foreach (file($tsvFile, FILE_IGNORE_NEW_LINES) as $i => $line) {
    if ($i === 0) {
        continue;   // header
    }
    $c = array_pad(explode("\t", $line), 12, "");
    $records[] = [
        "row" => (int) $c[0], "email" => $c[1], "nick" => $c[2], "surname" => $c[3], "phone" => $c[4],
        "address" => $c[5], "partner" => $c[6], "years" => [2024 => $c[8], 2025 => $c[9], 2026 => $c[10]],
        "line" => $line,
    ];
}

// ---------------------------------------------------------------- build people

/** First name without trailing single-letter initials: "Dawn E" -> "dawn". */
function base_first(string $s): string
{
    return trim(preg_replace("/(\s+[A-Za-z]\.?)+$/", "", mb_strtolower(trim($s))));
}

function add_person(array &$people, string $first, string $surname, array $extra): int
{
    // merge with an existing person: same first name and a compatible surname
    foreach ($people as $id => $p) {
        $sameFull = $first !== "" && squash($p["first"] . $p["surname"]) === squash($first . $surname);
        if ($first !== "" && $p["first"] !== "" && (base_first($p["first"]) === base_first($first) || $sameFull)) {
            $a = squash($p["surname"]);
            $b = squash($surname);
            if ($a === $b || ($a !== "" && $b !== "" && (substr($a, -strlen($b)) === $b || substr($b, -strlen($a)) === $a))) {
                if (strlen($surname) > strlen($p["surname"]) || ($surname !== mb_strtolower($surname) && $p["surname"] === mb_strtolower($p["surname"]))) {
                    $people[$id]["surname"] = $surname;
                }
                if ($sameFull) {
                    // identical full name, split differently ("Willem Janse"+"van Rensburg" vs "Willem"+"Janse van Rensburg"):
                    // keep the short first name and the long surname
                    $people[$id]["first"] = strlen($first) < strlen($p["first"]) ? $first : $p["first"];
                } elseif (strlen($first) > strlen($p["first"])) {
                    $people[$id]["first"] = $first;   // keep the fuller first name ("Dawn E" over "Dawn")
                }
                foreach ($extra["emails"] as $e) {
                    if (!in_array($e, $people[$id]["emails"], true)) {
                        $people[$id]["emails"][] = $e;
                    }
                }
                $people[$id]["flags"] = array_merge($people[$id]["flags"], $extra["flags"], ["same person on more than one sheet row"]);
                $people[$id]["rows"] = array_merge($people[$id]["rows"], $extra["rows"]);
                $people[$id]["pay"] = array_merge($people[$id]["pay"], $extra["pay"]);
                foreach (["phone", "address"] as $k) {
                    if ($people[$id][$k] === "" && $extra[$k] !== "") {
                        $people[$id][$k] = $extra[$k];
                    }
                }
                $people[$id]["broken"] = array_merge($people[$id]["broken"], $extra["broken"]);
                return $id;
            }
        }
    }
    $id = count($people) + 1;
    $people[$id] = array_merge(["id" => $id, "first" => $first, "surname" => $surname, "hh" => $id, "gg" => ""], $extra);
    return $id;
}

$pending = [];   // [personId, partnerRaw, recordRow]
foreach ($records as $r) {
    if (preg_match("/deceased/i", $r["line"])) {
        [$nm] = parse_names($r["nick"], $r["surname"]);
        foreach ($nm as $n) {
            $deceasedKeys[key_of($n[0], $n[1])] = true;
        }
        foreach (parse_emails($r["email"]) as $de) {
            $deceasedEmails[$de] = true;
        }
        $skipped[] = "row {$r["row"]}: " . clean_text($r["nick"] . " " . $r["surname"]) . " - marked deceased";
        continue;
    }
    [$nm, $nameFlags] = parse_names($r["nick"], $r["surname"]);
    $flags = $nameFlags;
    $emails = array_values(array_diff(parse_emails($r["email"]), $dropEmails));
    $broken = broken_email_locals($r["email"]);
    if (trim($r["email"]) !== "" && !$emails) {
        $flags[] = "email column not usable: '" . trim($r["email"]) . "'";
    }
    $phones = parse_phones($r["phone"], $flags);
    $address = expand_address($r["address"], $flags);
    if (trim($r["address"]) !== "" && $address === "") {
        $flags[] = "address ignored: '" . trim($r["address"]) . "'";
    }
    $pay = [["row" => $r["row"], "years" => $r["years"]]];
    $ids = [];
    foreach ($nm as $k => $n) {
        $extra = [
            "emails" => $k === 0 ? $emails : (count($emails) > 1 ? array_slice($emails, 1, 1) : []),
            "phone" => count($nm) === 1 ? implode("; ", $phones) : ($phones[$k] ?? ($k === 0 ? ($phones[0] ?? "") : "")),
            "address" => $address, "rows" => [$r["row"]], "flags" => $flags, "pay" => $pay, "broken" => $broken,
        ];
        if ($k === 0 && count($nm) === 2 && count($emails) > 1) {
            $extra["emails"] = [$emails[0]];
        }
        $ids[] = add_person($people, $n[0], $n[1], $extra);
    }
    if (count($ids) === 2) {
        $people[$ids[1]]["hh"] = $ids[0];
    }
    if (trim($r["partner"]) !== "") {
        $pending[] = [$ids[0], clean_text($r["partner"]), $r["row"]];
    }
}

// ---------------------------------------------------------------- households (union-find) and partners

function hh_root(array &$people, int $id): int
{
    while ($people[$id]["hh"] !== $id) {
        $people[$id]["hh"] = $people[$people[$id]["hh"]]["hh"];
        $id = $people[$id]["hh"];
    }
    return $id;
}

function hh_union(array &$people, int $a, int $b): void
{
    $ra = hh_root($people, $a);
    $rb = hh_root($people, $b);
    if ($ra !== $rb) {
        $people[max($ra, $rb)]["hh"] = min($ra, $rb);
    }
}

// people created by "&" rows are tied to their first person already (hh set); make that real
foreach ($people as $id => $p) {
    if ($p["hh"] !== $id) {
        hh_union($people, $id, $p["hh"]);
    }
}

foreach ($pending as [$pid, $praw, $row]) {
    $me = $people[$pid];
    $note = null;
    if (preg_match("/^(yes|no|partner.*|\?.*|.*\bvisitors?\b.*|.*,.*)$/i", $praw) || strlen(preg_replace("/[^A-Za-z]/", "", $praw)) < 2) {
        $people[$pid]["flags"][] = "partner column unclear, not imported: '$praw'";
        continue;
    }
    $tokens = explode(" ", strip_titles($praw));
    if (count($tokens) === 1) {
        $pf = fix_case($tokens[0]);
        $ps = $me["surname"];
    } else {
        $ps = array_pop($tokens);
        $pf = fix_case(implode(" ", $tokens));
        $people[$pid]["flags"][] = "partner '$praw' has a different surname - please check";
    }
    if (isset($deceasedKeys[key_of($pf, $ps)])) {
        $people[$pid]["flags"][] = "partner '$praw' is deceased - not added";
        continue;
    }
    $found = null;
    foreach ($people as $oid => $o) {
        if ($oid !== $pid && strcasecmp($o["first"], $pf) === 0 && (strcasecmp(squash($o["surname"]), squash($ps)) === 0
                || substr(squash($o["surname"]), -strlen(squash($ps))) === squash($ps))) {
            $found = $oid;
            break;
        }
    }
    $separate = false;
    foreach ($overrides["separate_partners"] ?? [] as $o) {
        if ((int) $o["row"] === $row && strcasecmp(trim($o["partner"]), $praw) === 0) {
            $separate = true;
        }
    }
    if ($found === null) {
        $found = add_person($people, $pf, $ps, ["emails" => [], "phone" => "", "address" => $separate ? "" : $me["address"], "rows" => [],
            "flags" => [$separate ? "listed in the Partner column of {$me["first"]} {$me["surname"]} (row $row) but kept as a separate individual membership"
                : "only listed as the partner of {$me["first"]} {$me["surname"]} (row $row) - no details of their own"],
            "pay" => [], "broken" => []]);
    }
    if (!$separate) {
        hh_union($people, $pid, $found);
    }
}

// ---------------------------------------------------------------- Google Groups

$gg = [];
$h = fopen($ggFile, "r");
$header = null;
while (($row = fgetcsv($h, 0, ",", '"', "")) !== false) {
    if ($header === null) {
        if (isset($row[0]) && strcasecmp(trim($row[0]), "Email address") === 0) {
            $header = $row;
        }
        continue;
    }
    if (!isset($row[0]) || trim($row[0]) === "") {
        continue;
    }
    $gg[] = ["email" => strtolower(trim($row[0])), "nick" => trim($row[1] ?? ""), "status" => $row[2] ?? "", "estatus" => $row[3] ?? ""];
}
fclose($h);

$emailToPerson = [];
$localToPerson = [];
foreach ($people as $id => $p) {
    foreach ($p["emails"] as $e) {
        $emailToPerson[$e] = $id;
    }
    foreach ($p["broken"] as $l) {
        $localToPerson[$l] = $id;
    }
    foreach ($p["emails"] as $e) {
        $localToPerson[substr($e, 0, strpos($e, "@"))] ??= $id;
    }
}

function first_compat(string $a, string $b): int
{
    $a = mb_strtolower($a);
    $b = mb_strtolower($b);
    if ($a === "" || $b === "") {
        return 0;
    }
    if ($a === $b) {
        return 3;
    }
    $sa = squash($a);
    $sb = squash($b);
    if ($sa === "" || $sb === "") {
        return 0;
    }
    if ((strlen($sa) <= 2 && $sa[0] === $sb[0]) || (strlen($sb) <= 2 && $sb[0] === $sa[0])) {
        return 2;   // initial(s) only
    }
    $p = 0;
    while ($p < min(strlen($sa), strlen($sb)) && $sa[$p] === $sb[$p]) {
        $p++;
    }
    if ($p >= 3 || levenshtein($sa, $sb) <= 1) {
        return 2;
    }
    return 0;
}

function surname_compat(string $a, string $b): int
{
    $a = squash($a);
    $b = squash($b);
    if ($a === "" || $b === "") {
        return 0;
    }
    if ($a === $b) {
        return 3;
    }
    if ((strlen($a) >= 3 && strlen($b) >= 3) && (substr($a, -strlen($b)) === $b || substr($b, -strlen($a)) === $a)) {
        return 2;
    }
    return levenshtein($a, $b) <= 1 ? 1 : 0;
}

/** Best unique match among existing people; returns [id, exact?] or null. */
function find_person_fuzzy(array $people, string $first, string $surname): ?array
{
    $best = [];
    foreach ($people as $oid => $o) {
        $sc = surname_compat($o["surname"], $surname);
        if ($sc === 0) {
            continue;
        }
        if ($o["first"] === "") {
            if ($sc === 3 && $first !== "") {
                $best[$oid] = 1 + $sc;   // surname-only record: weak match
            }
            continue;
        }
        $fc = first_compat($o["first"], $first);
        if ($fc > 0) {
            $best[$oid] = $fc * 10 + $sc;
        }
    }
    if (!$best) {
        return null;
    }
    arsort($best);
    $ids = array_keys($best);
    $top = $best[$ids[0]];
    if (isset($ids[1]) && $best[$ids[1]] === $top) {
        return null;   // ambiguous
    }
    return [$ids[0], $top >= 33];
}

/** Names from a Google Groups nickname/email: [list of [first, surname], guessedFromEmail]. */
function gg_names(string $nick, string $email): array
{
    $nick = clean_text(preg_replace("/[\"<>]/", "", $nick));
    $local = substr($email, 0, strpos($email, "@"));
    $isHandle = $nick === "" || strpos($nick, "@") !== false || (preg_match("/^[A-Za-z0-9._-]+$/", $nick) && strpos($nick, " ") === false);
    if ($isHandle) {
        // "ian.stockwell" -> Ian Stockwell
        $src = ($nick !== "" && strpos($nick, "@") === false) ? $nick : $local;
        if (preg_match("/^([a-z]{2,})[._-]([a-z]{2,})$/i", $src, $m)) {
            return [[[ucfirst(strtolower($m[1])), ucfirst(strtolower($m[2]))]], true];
        }
        return [[], false];
    }
    if (preg_match("/^([^,]+),\s*(.+)$/", $nick, $m)) {      // "Jurgensen, Emil and Wilma"
        $nick = clean_text($m[2] . " " . $m[1]);
    }
    $nick = strip_titles($nick);
    $parts = preg_split("/\s*(?:&|\band\b)\s*/i", $nick);
    $splitSurname = function (string $s): array {
        $t = explode(" ", $s);
        $sn = [array_pop($t)];
        while ($t && is_particle(end($t))) {
            array_unshift($sn, array_pop($t));
        }
        return [fix_case(implode(" ", $t)), implode(" ", $sn)];
    };
    if (count($parts) === 2) {
        [$f2, $sn] = $splitSurname($parts[1]);
        if ($f2 === "") {
            return [[], false];
        }
        return [[[fix_case(trim($parts[0])), $sn], [$f2, $sn]], false];
    }
    if (strpos($nick, " ") === false) {
        return [[], false];
    }
    [$f, $sn] = $splitSurname($nick);
    return [$f === "" ? [] : [[$f, $sn]], false];
}

$ggNew = [];
foreach ($gg as $g) {
    $info = [];
    if ($g["estatus"] === "bouncing") {
        $info[] = "Google Groups says this address is bouncing";
    }
    if ($g["status"] === "invited") {
        $info[] = "invited to Google Groups, not yet joined";
    }
    if (in_array($g["email"], $dropEmails, true)) {
        $issues[] = "Google Groups address ignored (removed per your notes): {$g["email"]}";
        continue;
    }
    if (in_array($g["email"], $ggSkip, true)) {
        $issues[] = "Google Groups address skipped (own alias/system mailbox): {$g["email"]}";
        continue;
    }
    if (isset($deceasedEmails[$g["email"]])) {
        $issues[] = "Google Groups address skipped, belongs to a deceased person: {$g["email"]}";
        continue;
    }
    if (isset($emailToPerson[$g["email"]])) {
        $id = $emailToPerson[$g["email"]];
        $people[$id]["gg"] = "on Google Groups";
        $people[$id]["flags"] = array_merge($people[$id]["flags"], $info);
        if ($people[$id]["first"] === "") {      // sheet had only a surname: take the first name from Google Groups
            [$nm] = gg_names($g["nick"], $g["email"]);
            if ($nm && surname_compat($people[$id]["surname"], $nm[0][1]) >= 2) {
                $people[$id]["first"] = $nm[0][0];
                $people[$id]["flags"][] = "first name '{$nm[0][0]}' taken from Google Groups";
            }
        }
        continue;
    }
    $local = substr($g["email"], 0, strpos($g["email"], "@"));
    $domain = substr($g["email"], strpos($g["email"], "@") + 1);
    $hit = $localToPerson[$local] ?? null;
    if ($hit === null && strlen($local) >= 6) {   // a one-letter typo before the @, same domain
        foreach ($localToPerson as $l => $pid) {
            foreach ($people[$pid]["emails"] as $se) {
                if (substr($se, strpos($se, "@") + 1) === $domain && levenshtein((string) $l, $local) === 1) {
                    $hit = $pid;
                    break 2;
                }
            }
        }
    }
    if ($hit !== null) {                         // same address with a typo/different domain in the sheet
        $old = $people[$hit]["emails"][0] ?? "(none usable)";
        $people[$hit]["emails"] = array_values(array_unique(array_merge([$g["email"]], array_diff($people[$hit]["emails"], [$old]))));
        $people[$hit]["gg"] = "on Google Groups";
        $people[$hit]["flags"][] = "email taken from Google Groups ({$g["email"]}); sheet had '$old'";
        $people[$hit]["flags"] = array_merge($people[$hit]["flags"], $info);
        $emailToPerson[$g["email"]] = $hit;
        continue;
    }

    [$names, $guessed] = gg_names($g["nick"], $g["email"]);
    $matched = null;
    $why = "";
    $nameUsed = null;
    if (!$names) {   // a handle such as "cindychaplin1953" or "visserc554": match it to a known person
        $h = preg_replace("/[^a-z]/", "", strtolower(strpos($g["nick"], "@") === false && $g["nick"] !== "" ? $g["nick"] : $local));
        $cands = [];
        foreach ($people as $oid => $o) {
            $f = squash(base_first($o["first"]));
            $s = squash($o["surname"]);
            if ($f === "" || strlen($s) < 4) {
                continue;
            }
            foreach ([$f . $s, $s . $f, $f[0] . $s, $s . $f[0]] as $pat) {
                if (strlen($h) >= strlen($pat) && strncmp($h, $pat, strlen($pat)) === 0 && strlen($h) - strlen($pat) <= 3) {
                    $cands[$oid] = true;
                }
            }
        }
        if (count($cands) === 1) {
            $matched = array_key_first($cands);
            $why = "handle '{$g["nick"]}' looks like this person";
        }
    }
    foreach ($names as $n) {
        if (isset($deceasedKeys[key_of($n[0], $n[1])])) {
            $matched = -1;
            break;
        }
        $m = find_person_fuzzy($people, $n[0], $n[1]);
        if ($m !== null) {
            $matched = $m[0];
            $why = $m[1] ? "same name" : "similar name '{$n[0]} {$n[1]}'";
            $nameUsed = $n;
            break;
        }
    }
    if ($matched === -1) {
        $issues[] = "Google Groups entry skipped, name matches a deceased person: {$g["email"]} ({$g["nick"]})";
        continue;
    }
    if ($matched !== null) {
        $o = &$people[$matched];
        if (!$o["emails"]) {
            $o["emails"][] = $g["email"];
            $o["flags"][] = "email {$g["email"]} added from Google Groups ($why)";
        } elseif (count($o["emails"]) === 1) {
            $o["emails"][] = $g["email"];
            $o["flags"][] = "second email {$g["email"]} added from Google Groups ($why)";
        } else {
            $o["flags"][] = "Google Groups also has {$g["email"]} ($why)";
        }
        if ($o["first"] === "" && $nameUsed !== null) {
            $o["first"] = $nameUsed[0];
            $o["flags"][] = "first name '{$nameUsed[0]}' taken from Google Groups";
        }
        $o["gg"] = "on Google Groups";
        $o["flags"] = array_merge($o["flags"], $info);
        unset($o);
        $emailToPerson[$g["email"]] = $matched;
        continue;
    }

    // not on the sheet at all: add as registered member(s)
    $flags = array_merge(["not on the membership sheet - added from Google Groups"], $info);
    $ids = [];
    if ($names) {
        foreach ($names as $k => $n) {
            $f = $flags;
            if ($guessed) {
                $f[] = "name guessed from the email address - please check";
            }
            if (count($names) === 2) {
                $f[] = "Google Groups name '{$g["nick"]}' has two people - second has no email of their own";
            }
            $ids[] = add_person($people, $n[0], $n[1], ["emails" => $k === 0 ? [$g["email"]] : [], "phone" => "", "address" => "",
                "rows" => [], "flags" => $f, "pay" => [], "broken" => []]);
        }
        if (count($ids) === 2) {
            hh_union($people, $ids[1], $ids[0]);
        }
    } else {
        $first = ($g["nick"] !== "" && strpos($g["nick"], "@") === false) ? $g["nick"] : $local;
        $flags[] = "NAME NEEDED: Google Groups only gave '" . $g["nick"] . "'";
        $ids[] = add_person($people, $first, "", ["emails" => [$g["email"]], "phone" => "", "address" => "", "rows" => [],
            "flags" => $flags, "pay" => [], "broken" => []]);
    }
    foreach ($ids as $nid) {
        $people[$nid]["gg"] = "on Google Groups";
        $ggNew[] = $nid;
    }
    $emailToPerson[$g["email"]] = $ids[0];
}

// ---------------------------------------------------------------- households: type and payments

$households = [];
foreach ($people as $id => $p) {
    $households[hh_root($people, $id)][] = $id;
}
ksort($households);

foreach ($households as $members) {
    $addr = "";
    foreach ($members as $mid) {
        if ($people[$mid]["address"] !== "") {
            $addr = $people[$mid]["address"];
            break;
        }
    }
    foreach ($members as $mid) {
        if ($people[$mid]["address"] === "" && $addr !== "") {
            $people[$mid]["address"] = $addr;
        }
        if (count($members) > 2) {
            $people[$mid]["flags"][] = "membership has " . count($members) . " people - please check they really belong together";
        }
    }
}

$hhInfo = [];
foreach ($households as $root => $members) {
    $perYear = [];
    $hasNa = false;
    $anyPaid = false;
    $notes = [];
    foreach (YEARS as $y => $_) {
        $dates = [];
        $payerByDate = [];
        $paidNoDate = false;
        $firstPaidNoDate = null;
        $na = false;
        foreach ($members as $mid) {
            foreach ($people[$mid]["pay"] as $src) {
                $res = parse_pay($src["years"][$y] ?? "", $y);
                if ($res["type"] === "date") {
                    $dates[] = $res["date"];
                    $payerByDate[$res["date"]] ??= $mid;
                    if (isset($res["note"])) {
                        $notes[] = "$y: " . $res["note"];
                    }
                } elseif ($res["type"] === "paid") {
                    $paidNoDate = true;
                    $firstPaidNoDate ??= $mid;
                    $notes[] = "$y: " . $res["note"];
                } elseif ($res["type"] === "na") {
                    $na = true;
                    $hasNa = true;
                } elseif ($res["type"] === "other") {
                    $notes[] = "$y: " . $res["note"];
                }
            }
        }
        if ($dates) {
            sort($dates);
            $perYear[$y] = ["date" => $dates[0], "payer" => $payerByDate[$dates[0]]];
            $anyPaid = true;
        } elseif ($paidNoDate) {
            $perYear[$y] = ["date" => "$y-01-01", "estimated" => true, "payer" => $firstPaidNoDate];
            $notes[] = "$y: paid date unknown, set to $y-01-01";
            $anyPaid = true;
        } elseif ($na) {
            $perYear[$y] = ["na" => true];
        }
    }
    if (!$anyPaid && $hasNa) {
        $notes[] = "marked N/A but no payment is recorded for this membership";
    }
    if (count($members) >= 2) {
        $type = "couple";
    } else {
        $type = "individual";
    }
    $hhInfo[$root] = ["type" => $type, "pay" => $perYear, "notes" => array_values(array_unique($notes))];
}

// ---------------------------------------------------------------- unique emails

$used = [];
foreach ($people as $id => $p) {          // first claim: everyone's main address
    if ($p["emails"] && !isset($used[$p["emails"][0]])) {
        $used[$p["emails"][0]] = $id;
    }
}
foreach ($people as $id => &$p) {
    $keep = [];
    foreach ($p["emails"] as $e) {
        if (isset($used[$e]) && $used[$e] !== $id) {
            $p["flags"][] = "shares email $e with " . trim($people[$used[$e]]["first"] . " " . $people[$used[$e]]["surname"]) . " - kept on them only";
        } else {
            $used[$e] = $id;
            $keep[] = $e;
        }
    }
    $p["emails"] = $keep;
    if (count($keep) > 2) {
        $p["flags"][] = "more than two emails; extra ones not imported: " . implode(", ", array_slice($keep, 2));
    }
}
unset($p);

// ---------------------------------------------------------------- output

function csv_cell(string $v): string
{
    return preg_match("/^[=+\-@]/", $v) ? "'" . $v : $v;
}
function sqs(string $s): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";   // always quoted, never NULL
}
function sq(?string $s): string
{
    return $s === null || $s === "" ? "NULL" : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";
}

$csv = fopen("$outDir/members_review.csv", "w");
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, ["Group", "Type", "First name", "Surname", "Email", "Email 2", "Phone", "Address", "2024", "2025", "2026",
    "Google Groups", "Sheet rows", "Things to check"], ",", '"', "");

$sql = ["-- Generated by tools/import_members.php on " . date("Y-m-d H:i") . " - CONTAINS PERSONAL DATA, do not commit.",
    "-- Run ONCE on the test database, after migrations 001-004. Imported people get an unusable password ('!').",
    "SET NAMES utf8mb4;",
    "-- Safety: this marker makes a second run stop with a duplicate-key error instead of creating everyone twice.",
    "CREATE TABLE IF NOT EXISTS import_log (name VARCHAR(100) PRIMARY KEY, run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    "INSERT INTO import_log (name) VALUES ('members_import');",
    "START TRANSACTION;", ""];

$counts = ["individual" => 0, "couple" => 0, "honorary" => 0];
$nPeople = 0;
$nPay = 0;
$flagged = 0;
$groupNo = 0;
foreach ($households as $root => $members) {
    $groupNo++;
    $info = $hhInfo[$root];
    $counts[$info["type"]]++;
    $sql[] = "-- group $groupNo: " . $info["type"];
    $sql[] = "INSERT INTO memberships (membership_type) VALUES ('{$info["type"]}');";
    $sql[] = "SET @m = LAST_INSERT_ID();";
    $first = true;
    foreach ($members as $mid) {
        $p = $people[$mid];
        $nPeople++;
        $flags = array_values(array_unique(array_merge($p["flags"], $info["notes"])));
        if ($p["first"] === "" && $p["surname"] === "") {
            $flags[] = "NAME NEEDED";
        }
        if (!$p["emails"]) {
            $flags[] = "no email address - cannot log in until one is added";
        }
        if ($flags) {
            $flagged++;
        }
        $cols = [];
        foreach (YEARS as $y => $_) {
            $v = $info["pay"][$y] ?? null;
            $cols[] = $v === null ? "" : (isset($v["na"]) ? "covered (N/A)" : $v["date"] . (isset($v["estimated"]) ? " (est.)" : ""));
        }
        fputcsv($csv, array_map("csv_cell", array_merge([$groupNo, $info["type"], $p["first"], $p["surname"],
            $p["emails"][0] ?? "", $p["emails"][1] ?? "", $p["phone"], $p["address"]], $cols,
            [$p["gg"], implode(",", $p["rows"]), implode(" | ", $flags)])), ",", '"', "");

        $vals = sq($p["emails"][0] ?? null) . ", " . sq($p["emails"][1] ?? null) . ", " . sqs($p["first"]) . ", " . sqs($p["surname"]) . ", "
            . sq($p["phone"]) . ", " . sq($p["address"]) . ", '!', 1, @m";
        if (!empty($p["emails"][0])) {
            // someone with this email may already have an account (e.g. the admin): attach to it instead of failing
            $em = sq($p["emails"][0]);
            $sql[] = "INSERT INTO users (email, email2, first_name, surname, phone, address, password_hash, must_change_password, membership_id)"
                . " SELECT $vals FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = $em);";
            $sql[] = "SET @u = (SELECT id FROM users WHERE email = $em LIMIT 1);";
            $sql[] = "UPDATE users SET membership_id = @m, phone = COALESCE(phone, " . sq($p["phone"]) . "), address = COALESCE(address, " . sq($p["address"]) . ")"
                . " WHERE id = @u AND membership_id IS NULL;";
        } else {
            $sql[] = "INSERT INTO users (email, email2, first_name, surname, phone, address, password_hash, must_change_password, membership_id) VALUES ($vals);";
            $sql[] = "SET @u = LAST_INSERT_ID();";
        }
        $sql[] = "INSERT IGNORE INTO user_roles (user_id, role_id) SELECT @u, id FROM roles WHERE role_name = 'registered';";
        foreach ($info["pay"] as $y => $v) {
            if (isset($v["date"])) {
                $isPayer = ($v["payer"] ?? $members[0]) === $mid;
                $amount = $isPayer ? (count($members) >= 2 ? "80.00" : "50.00") : "NULL";
                $sql[] = "INSERT IGNORE INTO membership_payments (user_id, year, amount, paid_date, recorded_by) VALUES (@u, $y, $amount, '{$v["date"]}', NULL);";
                $nPay++;
            }
        }
    }
    $sql[] = "";
}
$sql[] = "COMMIT;";
fclose($csv);
file_put_contents("$outDir/import_members.sql", implode("\n", $sql) . "\n");

$text = [];
$text[] = "IMPORT SUMMARY (" . date("Y-m-d H:i") . ")";
$text[] = "Sheet rows read: " . count($records) . "; skipped: " . count($skipped);
$text[] = "People to create: $nPeople in " . count($households) . " memberships "
    . "(individual {$counts["individual"]}, couple {$counts["couple"]}, honorary {$counts["honorary"]})";
$text[] = "Payment rows (paid-up years incl. partners): $nPay";
$text[] = "People with something to check: $flagged";
$text[] = "Added from Google Groups only: " . count($ggNew);
$text[] = "";
$text[] = "SKIPPED ROWS";
$text = array_merge($text, $skipped ?: ["(none)"], ["", "OTHER NOTES"], $issues ?: ["(none)"]);
file_put_contents("$outDir/issues.txt", implode("\n", $text) . "\n");
echo implode("\n", array_slice($text, 0, 7)) . "\n";
