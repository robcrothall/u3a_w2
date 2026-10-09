<?php
/**
 * header.php
 *
 * Shared page header for every inner page (login, register, admin, ...).
 * Matches the public home page (public/index.php): same stylesheets, dark
 * navbar and menu. Opens <html>, <body> and <main>; footer.php closes them.
 * Optional: set $page_title before including to change the <title>.
 */
$page_title = isset($page_title) ? $page_title . " - " . CLIENT_NAME : "University of the Third Age - " . CLIENT_NAME;
$nav_logged_in = !empty($_SESSION["id"]);
$nav_is_admin = $nav_logged_in && user_has_role((int) $_SESSION["id"], "admin");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/print.css" rel="stylesheet" media="print">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        body { min-height: 100vh; display: flex; flex-direction: column; }
    </style>
</head>
<body>
    <header class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">University of the Third Age - U3A</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="/recordings.php">Recordings</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#contact">Contact us</a></li>
                    <?php if (!$nav_logged_in): ?>
                    <li class="nav-item"><a class="nav-link" href="/register.php">Register</a></li>
                    <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                    <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/change_password.php">Change password</a></li>
                    <?php if ($nav_is_admin): ?>
                    <li class="nav-item"><a class="nav-link" href="/admin/events.php">Manage events</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/reset_password.php">Reset member password</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="/logout.php">Log off</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <main class="flex-grow-1">
