<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";
require_login();

$user_id = (int) $_SESSION["id"];
$member = member_find($user_id);
if ($member === null) {
    redirect("/logout.php");
}

$errors = [];
$form = $member;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    [$errors, $clean] = member_self_validate($_POST);
    $form = array_merge($member, $clean);
    if (empty($errors)) {
        member_self_update($user_id, $clean);
        $_SESSION["user_full_name"] = trim($clean["first_name"] . " " . $clean["surname"]);
        flash_set("Your details have been saved.");
        redirect("/my_details.php");
    }
}

$flash = flash_get();
$page_title = "My details";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 640px;">
    <h1>My Details</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <p class="text-muted">
        Your email address (<?php echo htmlspecialchars((string) $member["email"]); ?>) is your login name.
        To change it, please ask a committee member.
        Newsletter choices are on <a href="/preferences.php">My preferences</a>.
    </p>

    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="first_name">First name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" maxlength="100" required
                       value="<?php echo htmlspecialchars($form["first_name"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="surname">Surname</label>
                <input type="text" class="form-control" id="surname" name="surname" maxlength="100" required
                       value="<?php echo htmlspecialchars($form["surname"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Other phone (optional)</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="100"
                       value="<?php echo htmlspecialchars((string) $form["phone"]); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="address">Address (optional)</label>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="500"><?php echo htmlspecialchars((string) $form["address"]); ?></textarea>
            </div>
        </div>
        <?php require APP_DIR . "/templates/profile_fields.php"; ?>
        <button type="submit" class="btn btn-primary">Save my details</button>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
