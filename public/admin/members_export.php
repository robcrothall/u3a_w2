<?php
// CSV export for Excel/Word mail-merge. Staff only: contains personal information.
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$scope = ($_GET["scope"] ?? "all") === "paid" ? "paid" : "all";
$year = payment_year();

if ($scope === "paid") {
    $rows = array_map(fn($p) => [
        "first_name" => $p["first_name"],
        "surname" => $p["surname"],
        "email" => $p["email"],
        "paid_year" => $p["year"],
    ], payments_for_year($year));
    $filename = "u3a-paid-up-members-$year.csv";
} else {
    $rows = array_map(fn($m) => [
        "first_name" => $m["first_name"],
        "surname" => $m["surname"],
        "email" => $m["email"],
        "paid_year" => is_paid_up((int) $m["id"], (int) date("Y")) ? date("Y") : "",
    ], members_all());
    $filename = "u3a-all-members-" . date("Y-m-d") . ".csv";
}

header("Content-Type: text/csv; charset=utf-8");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header("Cache-Control: no-store");

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accented names correctly
fputcsv($out, ["First name", "Surname", "Email", "Paid-up year"], ",", '"', "");
foreach ($rows as $row) {
    fputcsv($out, [
        csv_safe($row["first_name"]),
        csv_safe($row["surname"]),
        csv_safe($row["email"]),
        $row["paid_year"],
    ], ",", '"', "");
}
fclose($out);
exit;
