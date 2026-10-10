<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$sent = false;
$errors = [];
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $email = strtolower(trim($_POST["email"] ?? ""));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        handle_forgot_password($email);
        $sent = true;   // always the same answer, whether or not the address is registered
    }
}

$page_title = "Forgot password";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 480px;">
    <h1>Forgot your password?</h1>

    <?php if ($sent): ?>
    <div class="alert alert-success">
        If that email address belongs to a U3A account, we have just emailed it a link to choose a new password.
        The link works once and is valid for one hour. Please also check your spam folder.
    </div>
    <p><a href="/login.php">Back to login</a></p>
    <?php else: ?>
    <p>Enter the email address you use with U3A and we will email you a link to choose a new password.
       If you are a member who has not yet set a password, use this page too.</p>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" autofocus required
                   value="<?php echo htmlspecialchars($email); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Email me a link</button>
        <a class="btn btn-link" href="/login.php">Cancel</a>
    </form>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
