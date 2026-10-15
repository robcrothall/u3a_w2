<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";
require_login();

$user_id = (int) $_SESSION["id"];
$member = member_find($user_id);
if ($member === null) {
    redirect("/logout.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $optOut = ($_POST["email_opt_out"] ?? "") === "1";
    email_opt_out_set($user_id, $optOut);
    flash_set($optOut
        ? "Saved. We will not send you newsletters or announcements."
        : "Saved. You will receive newsletters and announcements.");
    redirect("/preferences.php");
}

$flash = flash_get();
$page_title = "My preferences";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 640px;">
    <h1>My Preferences</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p>
        Signed in as <strong><?php echo htmlspecialchars($member["first_name"] . " " . $member["surname"]); ?></strong>
        <?php if (!empty($member["email"])): ?>(<?php echo htmlspecialchars($member["email"]); ?>)<?php endif; ?>.
    </p>

    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="email_opt_out" name="email_opt_out" value="1"
                   <?php echo !empty($member["email_opt_out"]) ? "checked" : ""; ?>>
            <label class="form-check-label" for="email_opt_out">
                <strong>Do not send me newsletters or announcements</strong>
            </label>
            <div class="form-text">
                You will stay a member. We will still send messages you ask for yourself (such as a
                password reset link) and confirmation of any payment you make.
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>

    <?php if (!empty($member["email_opt_out"]) && !empty($member["email_opt_out_at"])): ?>
    <p class="text-muted mt-3 small">You chose this on <?php echo htmlspecialchars($member["email_opt_out_at"]); ?>.</p>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
