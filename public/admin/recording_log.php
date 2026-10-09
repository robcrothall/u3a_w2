<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$totals = file_access_totals();
$recent = file_access_recent(100);

$page_title = "Recording usage";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Recording Usage</h1>
    <p class="text-muted">
        Each time someone opens a recording or document link it is logged. People who are not
        logged in are shown as "Visitor". Only the person, file and time are recorded.
    </p>

    <h2 class="mt-4">Opens per file</h2>
    <?php if (empty($totals)): ?>
    <p><em>No files yet.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Presentation</th><th>File</th><th class="text-end">Opens</th><th class="text-end">By visitors</th></tr></thead>
        <tbody>
            <?php foreach ($totals as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row["presentation_date"] . " " . $row["presenter_name"]); ?></td>
                <td><?php echo htmlspecialchars($row["link_text"]); ?></td>
                <td class="text-end"><?php echo (int) $row["opens"]; ?></td>
                <td class="text-end"><?php echo (int) $row["visitor_opens"]; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <h2 class="mt-4">Most recent opens</h2>
    <?php if (empty($recent)): ?>
    <p><em>Nothing has been opened yet.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr><th>When</th><th>Who</th><th>File</th></tr></thead>
        <tbody>
            <?php foreach ($recent as $row): ?>
            <tr>
                <td class="text-nowrap"><?php echo htmlspecialchars($row["accessed"]); ?></td>
                <td><?php echo htmlspecialchars($row["who"]); ?></td>
                <td><?php echo htmlspecialchars(($row["presenter_name"] ?? "") . " - " . ($row["link_text"] ?? "(file removed)")); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <p><a class="btn btn-outline-secondary" href="/admin/events.php">Back to events</a></p>
</div>
<?php
require APP_DIR . "/templates/footer.php";
