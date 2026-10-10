<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

const INVITE_BATCH = 40;   // shared hosting limits how many emails can go out per hour

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $sent = 0;
    $failed = 0;
    foreach (users_needing_invitation(INVITE_BATCH) as $u) {
        if (send_password_email($u, "invite")) {
            $sent++;
        } else {
            $failed++;
        }
    }
    flash_set("Sent $sent invitation" . ($sent === 1 ? "" : "s") . ($failed ? ", $failed could not be sent" : "") . ".",
        $failed ? "warning" : "success");
    redirect("/admin/invitations.php");
}

$waiting = users_needing_invitation_count();
$next = users_needing_invitation(INVITE_BATCH);
$noEmail = (int) query("SELECT COUNT(*) AS n FROM users WHERE email IS NULL")[0]["n"];
$flash = flash_get();

$page_title = "Password invitations";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <h1>Password Invitations</h1>
    <p class="text-muted">
        Imported members have no password yet. Sending an invitation emails them a one-time link (valid
        for 7 days) to choose one. They can also do this themselves with "Forgotten your password?" on the
        login page.
    </p>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p>
        <strong><?php echo $waiting; ?></strong> member<?php echo $waiting === 1 ? "" : "s"; ?> with an email address
        have not set a password and have no pending invitation.
        <?php if ($noEmail > 0): ?><br><span class="text-muted"><?php echo $noEmail; ?> members have no email address, so cannot be invited.</span><?php endif; ?>
    </p>

    <?php if ($waiting > 0): ?>
    <form method="post" onsubmit="return confirm('Send up to <?php echo INVITE_BATCH; ?> invitation emails now?');">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-primary">Send the next <?php echo min(INVITE_BATCH, $waiting); ?> invitations</button>
        <span class="text-muted ms-2">Sent in batches of <?php echo INVITE_BATCH; ?>; come back later for the rest.</span>
    </form>

    <h2 class="h5 mt-4">Next in line</h2>
    <ul class="list-unstyled">
        <?php foreach ($next as $u): ?>
        <li><?php echo htmlspecialchars($u["surname"] . ", " . $u["first_name"]); ?>
            <span class="text-muted small"><?php echo htmlspecialchars((string) $u["email"]); ?></span></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="alert alert-success">Everyone with an email address has either set a password or has a pending invitation.</div>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
