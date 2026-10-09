<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$errors = [];
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $errors[] = "Please enter both your email address and password.";
    } else {
        $user = attempt_login($email, $password);
        if ($user === false) {
            $errors[] = "Invalid email address or password.";
        } else {
            $_SESSION["id"] = $user["id"];
            $_SESSION["user_full_name"] = trim($user["given_name"] ?: $user["first_name"]) . " " . $user["surname"];
            record_login((int) $user["id"]);

            if ((int) $user["must_change_password"] === 1) {
                redirect("/change_password.php?forced=1");
            }
            redirect("/index.php");
        }
    }
}

require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 420px;">
    <h1>Log in</h1>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>" autofocus required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Log in</button>
    </form>

    <p class="mt-3">
        Forgotten your password? Contact
        <a href="mailto:webmaster@u3aportalfred.org.za">the Webmaster</a> to have it reset.
    </p>
    <p>Not registered yet? <a href="/register.php">Register here</a>.</p>
</div>
<?php
require APP_DIR . "/templates/footer.php";
