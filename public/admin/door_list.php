<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$year = payment_year();
$payments = paid_up_members($year);

$page_title = "Door list " . $year;
require APP_DIR . "/templates/header.php";
?>
<style>
    @media print {
        header.navbar, footer, .no-print { display: none !important; }
        body { background: #fff !important; font-size: 11pt; }
        .door-table td, .door-table th { padding: 4px 8px; }
    }
    .door-table .tick { width: 90px; }
</style>
<div class="container mt-4">
    <h1>Door List <?php echo $year; ?></h1>
    <p class="no-print">
        Paid-up members for <?php echo $year; ?>, sorted by surname.
        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="window.print()">Print</button>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/payments.php?year=<?php echo $year; ?>">Back to payments</a>
    </p>
    <p class="d-none d-print-block">U3A Port Alfred &ndash; paid-up members <?php echo $year; ?> &ndash; printed <?php echo date("j F Y"); ?></p>
    <p><strong><?php echo count($payments); ?></strong> paid-up members.</p>

    <?php if (empty($payments)): ?>
    <p><em>No payments recorded for <?php echo $year; ?> yet.</em></p>
    <?php else: ?>
    <table class="table table-bordered table-sm door-table">
        <thead>
            <tr><th>#</th><th>Surname</th><th>First name</th><th class="tick">Present</th></tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $i => $p): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($p["surname"]); ?></td>
                <td><?php echo htmlspecialchars($p["first_name"]); ?></td>
                <td class="tick"></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
