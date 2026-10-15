<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$campaign = campaign_find($id);
if ($campaign === null) {
    flash_set("That message no longer exists.", "warning");
    redirect("/admin/mail_campaigns.php");
}
$self = "/admin/mail_campaign.php?id=" . $id;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $action = (string) ($_POST["action"] ?? "");
    if ($action === "send") {
        $counts = campaign_counts($id);
        $allowance = mail_batch_allowance();
        $take = min($allowance, $counts["pending"]);
        if ($campaign["status"] !== "queued") {
            flash_set("This message is no longer queued.", "warning");
        } elseif ($take <= 0) {
            flash_set("The hourly sending limit has been reached. Please try again in a little while.", "warning");
        } else {
            [$sent, $failed, $skipped] = campaign_send_batch($id, $take);
            flash_set("Batch finished: $sent sent" . ($failed ? ", $failed failed" : "") . ($skipped ? ", $skipped skipped" : "") . ".",
                $failed ? "warning" : "success");
        }
    } elseif ($action === "cancel") {
        campaign_cancel($id);
        flash_set("The remaining messages were cancelled.");
    } elseif ($action === "retry") {
        query("UPDATE mail_queue SET status = 'pending', note = NULL WHERE campaign_id = ? AND status = 'failed'", $id);
        query("UPDATE mail_campaigns SET status = 'queued' WHERE id = ? AND status = 'done'", $id);
        flash_set("Failed messages were put back in the queue.");
    }
    redirect($self);
}

$counts = campaign_counts($id);
$rows = query(
    "SELECT q.*, u.first_name, u.surname FROM mail_queue q LEFT JOIN users u ON u.id = q.user_id
     WHERE q.campaign_id = ? ORDER BY q.status = 'pending' DESC, q.id LIMIT 1000",
    $id
);
$docs = campaign_documents($campaign["doc_ids"]);
$allowance = mail_batch_allowance();
$flash = flash_get();
$total = max(1, array_sum($counts));

$page_title = "Sent message";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 900px;">
    <p><a href="/admin/mail_campaigns.php">&larr; All messages</a></p>
    <h1><?php echo htmlspecialchars($campaign["subject"]); ?></h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p class="text-muted">
        To: <?php echo htmlspecialchars(MAIL_AUDIENCES[$campaign["audience"]]); ?><br>
        Created <?php echo htmlspecialchars($campaign["created"]); ?>
        &middot; Status: <strong><?php echo htmlspecialchars($campaign["status"]); ?></strong>
        <?php if ($docs): ?>
        <br>Documents (<?php echo $campaign["attach"] ? "attached" : "as links"; ?>):
        <?php echo htmlspecialchars(implode(", ", array_map(fn($d) => $d["title"], $docs))); ?>
        <?php endif; ?>
    </p>

    <div class="progress mb-2" style="height: 22px;">
        <div class="progress-bar bg-success" style="width: <?php echo round(100 * $counts["sent"] / $total); ?>%"><?php echo $counts["sent"]; ?> sent</div>
        <div class="progress-bar bg-secondary" style="width: <?php echo round(100 * $counts["skipped"] / $total); ?>%"><?php echo $counts["skipped"] ? $counts["skipped"] . " skipped" : ""; ?></div>
        <div class="progress-bar bg-danger" style="width: <?php echo round(100 * $counts["failed"] / $total); ?>%"><?php echo $counts["failed"] ? $counts["failed"] . " failed" : ""; ?></div>
    </div>
    <p>
        <?php echo $counts["sent"]; ?> sent, <?php echo $counts["pending"]; ?> waiting,
        <?php echo $counts["failed"]; ?> failed, <?php echo $counts["skipped"]; ?> skipped.
    </p>

    <?php if ($campaign["status"] === "queued" && $counts["pending"] > 0): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="action" value="send">
            <button type="submit" class="btn btn-primary" <?php echo $allowance <= 0 ? "disabled" : ""; ?>
                    onclick="return confirm('Send the next batch of up to <?php echo min($allowance, $counts["pending"]); ?> emails now?');">
                Send next batch (<?php echo min($allowance, $counts["pending"]); ?>)
            </button>
        </form>
        <form method="post" onsubmit="return confirm('Cancel all messages that have not been sent yet?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline-danger">Cancel the rest</button>
        </form>
    </div>
    <p class="text-muted small">
        Batches are limited to <?php echo MAIL_BATCH_SIZE; ?> and to the server's hourly limit of <?php echo MAIL_HOURLY_LIMIT; ?>:
        <?php echo emails_last_hour(); ?> sent in the last hour (all kinds of mail).
    </p>
    <?php elseif ($counts["failed"] > 0 && $campaign["status"] !== "cancelled"): ?>
    <form method="post" class="mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="retry">
        <button type="submit" class="btn btn-outline-primary">Put the failed messages back in the queue</button>
    </form>
    <?php endif; ?>

    <details class="mb-4">
        <summary>Show the message text</summary>
        <pre class="mt-2" style="white-space: pre-wrap; font-family: inherit;"><?php echo htmlspecialchars($campaign["body"]); ?></pre>
    </details>

    <h2 class="h5">Recipients</h2>
    <div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr><th>Member</th><th>Email</th><th>Status</th><th>When</th><th>Note</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $q): ?>
            <tr>
                <td><?php echo htmlspecialchars(trim(($q["surname"] ?? "") . ", " . ($q["first_name"] ?? ""), ", ")); ?></td>
                <td><?php echo htmlspecialchars($q["email"]); ?></td>
                <td><span class="badge <?php echo ["sent" => "bg-success", "pending" => "bg-secondary", "failed" => "bg-danger", "skipped" => "bg-warning text-dark"][$q["status"]]; ?>"><?php echo htmlspecialchars($q["status"]); ?></span></td>
                <td class="text-nowrap"><?php echo htmlspecialchars((string) $q["sent_at"]); ?></td>
                <td><?php echo htmlspecialchars((string) $q["note"]); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php
require APP_DIR . "/templates/footer.php";
