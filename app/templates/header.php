<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo CLIENT_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/site.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/">U3A Port Alfred</a>
            <div class="navbar-nav">
                <a class="nav-link" href="/recordings.php">Recordings</a>
                <?php if (empty($_SESSION["id"])): ?>
                <a class="nav-link" href="/login.php">Login</a>
                <a class="nav-link" href="/register.php">Register</a>
                <?php else: ?>
                <a class="nav-link" href="/change_password.php">Change Password</a>
                <?php if (user_has_role((int) $_SESSION["id"], "admin")): ?>
                <a class="nav-link" href="/admin/reset_password.php">Reset Member Password</a>
                <?php endif; ?>
                <a class="nav-link" href="/logout.php">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>