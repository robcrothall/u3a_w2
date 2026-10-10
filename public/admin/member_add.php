<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$errors = [];
$form = ["first_name" => "", "surname" => "", "email" => "", "email2" => "", "phone" => "", "address" => ""];
$type = "individual";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    [$errors, $clean] = member_validate($_POST, 0);
    $form = $clean;
    $type = ($_POST["membership_type"] ?? "") === "honorary" ? "honorary" : "individual";
    if (empty($errors)) {
        $new_id = member_create($clean, $type);
        flash_set("Member added. To make them a couple, link their partner below.");
        redirect("/admin/member_edit.php?id=" . $new_id);
    }
}

$page_title = "Add member";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <p><a href="/admin/members.php">&larr; All members</a></p>
    <h1>Add a Member</h1>
    <p class="text-muted">
        For someone who has not registered on the website themselves. They will not be able to log in
        until they have a password. After saving you can link a partner and record a payment.
    </p>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

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
                <label class="form-label" for="email">Email (optional)</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="255"
                       value="<?php echo htmlspecialchars($form["email"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="email2">Second email (optional)</label>
                <input type="email" class="form-control" id="email2" name="email2" maxlength="255"
                       value="<?php echo htmlspecialchars($form["email2"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Phone (optional)</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="100"
                       value="<?php echo htmlspecialchars($form["phone"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="membership_type">Membership</label>
                <select class="form-select" id="membership_type" name="membership_type">
                    <option value="individual"<?php echo $type === "individual" ? " selected" : ""; ?>>Individual (R50)</option>
                    <option value="honorary"<?php echo $type === "honorary" ? " selected" : ""; ?>>Honorary (paid-up for life)</option>
                </select>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="address">Address (optional)</label>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="500"><?php echo htmlspecialchars($form["address"]); ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add member</button>
        <a class="btn btn-outline-secondary" href="/admin/members.php">Cancel</a>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
