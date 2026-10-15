<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";
require_login();

$user_id = (int) $_SESSION["id"];
$member = member_find($user_id);
if ($member === null) {
    redirect("/logout.php");
}
if (!consent_needed($member)) {
    redirect("/index.php");
}

$errors = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    if (($_POST["consent"] ?? "") !== "1") {
        $errors[] = "Please tick the box to confirm that you accept this acknowledgement.";
    } else {
        consent_record($user_id);
        redirect("/index.php");
    }
}

$page_title = "Please confirm";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 640px;">
    <h1>Please confirm</h1>
    <p>Before you continue, please read and accept this acknowledgement. You only need to do this once.</p>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="border rounded p-3 mb-3 bg-light"><?php echo htmlspecialchars(consent_text()); ?></div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1">
            <label class="form-check-label" for="consent">I have read and I accept this acknowledgement.</label>
        </div>
        <button type="submit" class="btn btn-primary">Continue</button>
        <a class="btn btn-link" href="/logout.php">Log off instead</a>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
