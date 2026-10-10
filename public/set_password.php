<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$token = (string) ($_GET["token"] ?? $_POST["token"] ?? "");
$reset = reset_token_lookup($token);
$errors = [];
$done = false;

if ($reset !== null && $_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $password = $_POST["password"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirmation) {
        $errors[] = "Password and confirmation do not match.";
    }
    if (empty($errors)) {
        set_password((int) $reset["user_id"], $password, false);
        query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL", $reset["user_id"]);
        flash_set("Your password has been set. You can now log in.");
        redirect("/login.php");
    }
}

$page_title = "Choose a password";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 480px;">
    <h1>Choose a password</h1>

    <?php if ($reset === null): ?>
    <div class="alert alert-warning">
        This link is no longer valid. It may have expired or already been used.
    </div>
    <p><a class="btn btn-primary" href="/forgot_password.php">Get a new link</a></p>
    <?php else: ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-3">
            <label class="form-label" for="password">New password (at least 8 characters)</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" autofocus required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="confirmation">Confirm new password</label>
            <input type="password" class="form-control" id="confirmation" name="confirmation" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Set password</button>
    </form>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
