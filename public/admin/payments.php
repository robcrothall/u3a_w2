<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$year = payment_year();
$search = trim($_GET["search"] ?? "");
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    if (!empty($_POST["delete_payment_id"])) {
        $payment = payment_find_by_id((int) $_POST["delete_payment_id"]);
        if ($payment !== null) {
            $member = find_user_by_id((int) $payment["user_id"]);
            payment_delete((int) $payment["id"]);
            flash_set("Payment removed for " . ($member ? $member["first_name"] . " " . $member["surname"] : "member") . " (" . $payment["year"] . ").");
            redirect("/admin/payments.php?year=" . (int) $payment["year"]);
        }
        redirect("/admin/payments.php?year=" . $year);
    }

    [$errors, $clean] = payment_validate($_POST);
    if (empty($errors)) {
        $member = member_find($clean["user_id"]);
        $updated = payment_save($clean, (int) $_SESSION["id"]);
        $partners = member_partner_names($member);
        flash_set(($updated ? "Payment corrected for " : "Payment recorded for ")
            . $member["first_name"] . " " . $member["surname"]
            . ($partners !== "" ? " and " . $partners : "") . " (" . $clean["year"] . ").");
        redirect("/admin/payments.php?year=" . $clean["year"]);
    }
    $year = $clean["year"] >= 2020 ? $clean["year"] : $year;
}

$results = $search !== "" ? members_search($search) : [];
$paid = paid_up_members($year);
$total = 0.0;
foreach ($paid as $p) {
    $total += (float) $p["amount"];
}
$flash = flash_get();

$page_title = "Membership payments";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Membership Payments</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="get" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label" for="year">Membership year</label>
            <select class="form-select" id="year" name="year" onchange="this.form.submit()">
                <?php foreach (payment_year_choices() as $y): ?>
                <option value="<?php echo $y; ?>"<?php echo $y === $year ? " selected" : ""; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="search">Find a member to record a payment</label>
            <input type="text" class="form-control" id="search" name="search" placeholder="Name or email"
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
        </div>
    </form>

    <?php if ($search !== ""): ?>
        <?php if (empty($results)): ?>
        <p><em>No members found matching "<?php echo htmlspecialchars($search); ?>". The member needs to register first.</em></p>
        <?php else: ?>
        <p class="text-muted"><?php echo htmlspecialchars(MEMBERSHIP_FEE_HINT); ?></p>
        <div class="table-responsive mb-4">
        <table class="table align-middle">
            <thead><tr><th>Member</th><th>Amount (R)</th><th>Date paid</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($results as $m): ?>
                <?php
                $already = payment_find((int) $m["id"], $year);
                $paidUp = is_paid_up((int) $m["id"], $year);
                $honorary = $m["membership_type"] === "honorary";
                ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($m["first_name"] . " " . $m["surname"]); ?>
                        <?php if ($m["partners"] !== ""): ?>
                        <span class="text-muted">&amp; <?php echo htmlspecialchars($m["partners"]); ?></span>
                        <?php endif; ?>
                        <div class="text-muted small"><?php echo htmlspecialchars((string) $m["email"]); ?></div>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars(MEMBERSHIP_TYPES[$m["membership_type"]] ?? $m["membership_type"]); ?></span>
                        <?php if ($paidUp): ?>
                        <span class="badge bg-success"><?php echo $honorary ? "Paid-up for life" : "Paid " . $year; ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($honorary): ?>
                    <td colspan="3" class="text-muted">Honorary members do not pay.</td>
                    <?php else: ?>
                    <td style="max-width: 120px;">
                        <input type="text" inputmode="decimal" class="form-control" name="amount" placeholder="<?php echo $m["membership_type"] === "couple" ? "80" : "50"; ?>"
                               form="pay<?php echo (int) $m["id"]; ?>"
                               value="<?php echo $already ? htmlspecialchars((string) $already["amount"]) : ""; ?>">
                    </td>
                    <td style="max-width: 170px;">
                        <input type="date" class="form-control" name="paid_date" required
                               form="pay<?php echo (int) $m["id"]; ?>"
                               max="<?php echo date("Y-m-d"); ?>"
                               value="<?php echo $already ? htmlspecialchars($already["paid_date"]) : date("Y-m-d"); ?>">
                    </td>
                    <td class="text-end">
                        <form method="post" id="pay<?php echo (int) $m["id"]; ?>"
                              action="/admin/payments.php?year=<?php echo $year; ?>&amp;search=<?php echo urlencode($search); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $m["id"]; ?>">
                            <input type="hidden" name="year" value="<?php echo $year; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <?php echo $already ? "Correct payment" : "Record payment"; ?>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Paid-up members for <?php echo $year; ?></h2>
    <p>
        <strong><?php echo count($paid); ?></strong> paid-up<?php echo count($paid) === 1 ? " member" : " members"; ?>
        (including honorary members and partners), total received R<?php echo number_format($total, 2); ?>.
        <a class="btn btn-sm btn-outline-secondary ms-2" href="/admin/door_list.php?year=<?php echo $year; ?>">Door list</a>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/members_export.php?scope=paid&amp;year=<?php echo $year; ?>">Export paid-up (CSV)</a>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/members_export.php?scope=all">Export all members (CSV)</a>
    </p>

    <?php if (empty($paid)): ?>
    <p><em>No payments recorded for <?php echo $year; ?> yet.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Member</th><th>Membership</th><th class="text-end">Amount</th><th>Date paid</th><th>Recorded by</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($paid as $p): ?>
            <tr>
                <td><a href="/admin/member_edit.php?id=<?php echo (int) $p["id"]; ?>"><?php echo htmlspecialchars($p["surname"] . ", " . $p["first_name"]); ?></a></td>
                <td><?php echo htmlspecialchars(ucfirst($p["membership_type"])); ?></td>
                <td class="text-end"><?php echo $p["amount"] !== null ? "R" . number_format((float) $p["amount"], 2) : "&ndash;"; ?></td>
                <td><?php echo $p["payment_id"] === null ? "Honorary" : htmlspecialchars($p["paid_date"]); ?></td>
                <td><?php echo htmlspecialchars((string) $p["recorder"]); ?></td>
                <td class="text-end">
                    <?php if ($p["payment_id"] !== null): ?>
                    <form method="post" onsubmit="return confirm('Remove this payment? The member (and any partner) will no longer be paid-up for <?php echo $year; ?>.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        <input type="hidden" name="delete_payment_id" value="<?php echo (int) $p["payment_id"]; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
