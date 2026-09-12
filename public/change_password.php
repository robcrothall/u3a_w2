<?php
require __DIR__ . "/../app/config/config.php";
require_login();

$user = find_user_by_id((int) $_SESSION["id"]);
$forced = isset($_GET["forced"]) || (int) $user["must_change_password"] === 1;
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";

    if (!password_verify($current_password, $user["password_hash"])) {
        $errors[] = $forced
            ? "That's not the temporary password you were given. Check it and try again."
            : "Your current password is incorrect.";
    }
    if (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    }
    if ($new_password !== $confirmation) {
        $errors[] = "New password and confirmation do not match.";
    }

    if (empty($errors)) {
        set_password((int) $user["id"], $new_password, false);
        $success = true;
        $forced = false;
    }
}

require __DIR__ . "/../app/templates/header.php";
?>
<div class="container mt-5" style="max-width: 420px;">
    <h1>Change Password</h1>

    <?php if ($forced): ?>
    <div class="alert alert-warning">
        Your password was reset by an administrator. Please choose a new password
        to continue - enter the temporary password you were given as your
        "current password" below.
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">Your password has been changed.</div>
    <a href="/index.php" class="btn btn-primary">Continue to the site</a>
    <?php else: ?>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <div class="mb-3">
            <label class="form-label" for="current_password"><?php echo $forced ? "Temporary password" : "Current password"; ?></label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new_password">New password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="confirmation">Confirm new password</label>
            <input type="password" class="form-control" id="confirmation" name="confirmation" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary">Change password</button>
    </form>
    <?php endif; ?>
</div>
<?php
require __DIR__ . "/../app/templates/footer.php";
