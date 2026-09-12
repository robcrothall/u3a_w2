<?php
require __DIR__ . "/../app/config/config.php";

$errors = [];
$email = "";
$first_name = "";
$surname = "";
$given_name = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";
    $first_name = test_input($_POST["first_name"] ?? "");
    $surname = test_input($_POST["surname"] ?? "");
    $given_name = test_input($_POST["given_name"] ?? "");

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
    if (empty($errors) && find_user_by_email($email) !== null) {
        $errors[] = "That email address is already registered. Try logging in, or use \"forgot password\" if needed.";
    }

    if (empty($errors)) {
        $user_id = create_user($email, $password, $first_name, $surname, $given_name);
        $_SESSION["id"] = $user_id;
        $_SESSION["user_full_name"] = trim("$first_name $surname");
        redirect("/index.php");
    }
}

require __DIR__ . "/../app/templates/header.php";
?>
<div class="container mt-5" style="max-width: 480px;">
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
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>
<?php
require __DIR__ . "/../app/templates/footer.php";
