<?php
// CSV export for Excel/Word mail-merge. Staff only: contains personal information.
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$scope = ($_GET["scope"] ?? "all") === "paid" ? "paid" : "all";
$year = payment_year();

if ($scope === "paid") {
    $members = paid_up_members($year);
    $paid_label = fn($m) => $year;
    $filename = "u3a-paid-up-members-$year.csv";
} else {
    $members = members_all();
    $paidIds = array_flip(array_map(fn($p) => (int) $p["id"], paid_up_members((int) date("Y"))));
    $paid_label = fn($m) => isset($paidIds[(int) $m["id"]]) ? date("Y") : "";
    $filename = "u3a-all-members-" . date("Y-m-d") . ".csv";
}

header("Content-Type: text/csv; charset=utf-8");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header("Cache-Control: no-store");

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accented names correctly
fputcsv($out, ["First name", "Surname", "Email", "Email 2", "Phone", "Address", "Membership", "Paid-up year"], ",", '"', "");
foreach ($members as $m) {
    fputcsv($out, [
        csv_safe($m["first_name"]),
        csv_safe($m["surname"]),
        csv_safe($m["email"]),
        csv_safe($m["email2"]),
        csv_safe($m["phone"]),
        csv_safe($m["address"]),
        $m["membership_type"],
        $paid_label($m),
    ], ",", '"', "");
}
fclose($out);
exit;
