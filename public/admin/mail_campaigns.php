<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$campaigns = campaigns_recent(100);
$flash = flash_get();

$page_title = "Messages sent";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 960px;">
    <h1>Messages</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p><a class="btn btn-primary" href="/admin/mail_compose.php">Compose a new message</a></p>

    <?php if (empty($campaigns)): ?>
    <p><em>No messages have been composed yet.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Created</th><th>Subject</th><th>To</th><th class="text-end">Recipients</th><th class="text-end">Sent</th><th class="text-end">Waiting</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td class="text-nowrap"><?php echo htmlspecialchars(substr($c["created"], 0, 16)); ?></td>
                <td>
                    <a href="/admin/mail_campaign.php?id=<?php echo (int) $c["id"]; ?>"><?php echo htmlspecialchars($c["subject"]); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) $c["author"]); ?></div>
                </td>
                <td><?php echo htmlspecialchars(["all" => "All members", "unpaid" => "Not paid up", "admins" => "Admins"][$c["audience"]]); ?></td>
                <td class="text-end"><?php echo (int) $c["recipients"]; ?></td>
                <td class="text-end"><?php echo (int) $c["sent_n"]; ?><?php echo $c["failed_n"] ? ' <span class="text-danger">(' . (int) $c["failed_n"] . ' failed)</span>' : ""; ?></td>
                <td class="text-end"><?php echo (int) $c["pending_n"]; ?></td>
                <td><span class="badge <?php echo ["queued" => "bg-primary", "done" => "bg-success", "cancelled" => "bg-secondary"][$c["status"]]; ?>"><?php echo htmlspecialchars($c["status"]); ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
