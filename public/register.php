<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$errors = [];
$email = "";
$first_name = "";
$surname = "";
$given_name = "";
$form = ["title" => "", "mobile" => "", "can_present" => "", "skills" => "", "wants_subjects" => ""];
$opt_out = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";
    // plain text is stored; it is escaped when displayed
    $first_name = trim((string) ($_POST["first_name"] ?? ""));
    $surname = trim((string) ($_POST["surname"] ?? ""));
    $given_name = trim((string) ($_POST["given_name"] ?? ""));
    [$profileErrors, $form] = profile_validate($_POST);
    $opt_out = ($_POST["email_opt_out"] ?? "") === "1";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirmation) {
        $errors[] = "Password and confirmation do not match.";
    }
    if ($first_name === "" || $surname === "") {
        $errors[] = "First name and surname are required.";
    }
    $errors = array_merge($errors, $profileErrors);
    if (($_POST["consent"] ?? "") !== "1") {
        $errors[] = "Please tick the box to confirm that you accept the acknowledgement.";
    }
    if (empty($errors) && find_user_by_email($email) !== null) {
        $errors[] = "That email address is already registered. Try logging in, or use \"forgot password\" if needed.";
    }

    if (empty($errors)) {
        $user_id = create_user($email, $password, $first_name, $surname, $given_name);
        member_profile_save($user_id, $form);
        consent_record($user_id);
        if ($opt_out) {
            email_opt_out_set($user_id, true);
        }
        $_SESSION["id"] = $user_id;
        $_SESSION["user_full_name"] = trim("$first_name $surname");
        redirect("/index.php");
    }
}

require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 480px;">
    <h1>Register</h1>
    <p class="text-muted">Registering adds you to our mailing list for newsletters and meeting reminders.</p>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <div class="mb-3">
            <label class="form-label" for="first_name">First name</label>
            <input type="text" class="form-control" id="first_name" name="first_name"
                   value="<?php echo htmlspecialchars($first_name); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="surname">Surname</label>
            <input type="text" class="form-control" id="surname" name="surname"
                   value="<?php echo htmlspecialchars($surname); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="given_name">Preferred/given name (optional)</label>
            <input type="text" class="form-control" id="given_name" name="given_name"
                   value="<?php echo htmlspecialchars($given_name); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="confirmation">Confirm password</label>
            <input type="password" class="form-control" id="confirmation" name="confirmation" minlength="8" required>
        </div>
        <hr>
        <h2 class="h5">More about you (optional)</h2>
        <?php require APP_DIR . "/templates/profile_fields.php"; ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="email_opt_out" name="email_opt_out" value="1"
                   <?php echo $opt_out ? "checked" : ""; ?>>
            <label class="form-check-label" for="email_opt_out">Please do not send me newsletters or announcements</label>
        </div>
        <hr>
        <div class="border rounded p-3 mb-3 bg-light small"><?php echo htmlspecialchars(consent_text()); ?></div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
            <label class="form-check-label" for="consent">I have read and I accept this acknowledgement.</label>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
