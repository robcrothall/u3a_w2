<?php
/**
 * Program: index_live.php
 *
 * Public home page for production. Deployed to the web root as index.php
 * (see .cpanel.yml). Static content only: no session, no database, no
 * members-only links. Derived from index.php.
 *
 * @author   "Rob Crothall" <rob@crothall.co.za>
 * @license  GPL 1.0 or later
 * @link     https://u3aportalfred.org.za
 */

// Member menu items (Login, Register, ...) appear only where the member pages
// are deployed. The live site does not publish login.php yet, so it stays a
// fully static page that needs no .env or database.
$members_enabled = is_file(__DIR__ . "/login.php");
$logged_in = false;
$is_admin = false;
if ($members_enabled) {
    require __DIR__ . "/_app_path.php";
    require APP_DIR . "/config/config.php";
    $logged_in = !empty($_SESSION["id"]);
    $is_admin = $logged_in && user_has_role((int) $_SESSION["id"], "admin");
}
// Next three published events; the Upcoming Events section is hidden when empty.
$home_events = $members_enabled ? events_upcoming(3) : [];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>University of the Third Age - U3A Port Alfred</title>
    <meta name="description" content="U3A Port Alfred - learning for leisure and pleasure. Presentations on the second and fourth Thursday of each month at Settlers Park.">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/print.css" rel="stylesheet" media="print">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
      body {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }
      .hero {
        background: url('img/parked-bg.jpg') center/cover;
        color: #fff;
        width: 100%;
        height: 100vh;
      }
      .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
      }
      .hero .container {
        position: relative;
        z-index: 1;
      }
      .hero h1,
      .hero p {
        text-shadow: 0 0 12px rgba(0,0,0,0.5);
      }
    </style>
  </head>
  <body>
    <header class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#home">University of the Third Age - U3A</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="#home">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="#contact">Contact us</a></li>
<?php if ($members_enabled): ?>
            <li class="nav-item"><a class="nav-link" href="/events.php">Events</a></li>
            <li class="nav-item"><a class="nav-link" href="/recordings.php">Recordings</a></li>
<?php endif; ?>
<?php if ($members_enabled && !$logged_in): ?>
            <li class="nav-item"><a class="nav-link" href="/register.php">Register</a></li>
            <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
<?php elseif ($members_enabled): ?>
            <li class="nav-item"><a class="nav-link" href="/change_password.php">Change password</a></li>
<?php if ($is_admin): ?>
<li class="nav-item dropdown">
  <a class="nav-link dropdown-toggle" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">Admin</a>
  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
    <li><a class="dropdown-item" href="/admin/events.php">Manage events</a></li>
    <li><a class="dropdown-item" href="/admin/payments.php">Membership payments</a></li>
    <li><a class="dropdown-item" href="/admin/door_list.php">Door list</a></li>
    <li><a class="dropdown-item" href="/admin/members_export.php?scope=all">Export members (CSV)</a></li>
    <li><a class="dropdown-item" href="/admin/recording_log.php">Recording usage</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="/admin/reset_password.php">Reset member password</a></li>
  </ul>
</li>
<?php endif; ?>
            <li class="nav-item"><a class="nav-link" href="/logout.php">Log off</a></li>
<?php endif; ?>
          </ul>
        </div>
      </div>
    </header>

    <main class="flex-grow-1">
      <section id="home" class="hero d-flex align-items-center position-relative">
        <div class="container text-center py-5">
          <img src="img/U3A_Logo.jpg" class="rounded shadow-sm" alt="U3A Logo"
                  style="object-fit: cover; width: 50%; height: 50%;">
          <h1 class="display-5 fw-bold">University of the Third Age - U3A</h1>
          <p class="lead mb-4">“Learning for leisure and pleasure”</p>
          <p class="mb-4">The “University of the Third Age”, or U3A as it is usually known,
            is a learning and social community organized by and for people who can best be
            described as in active retirement – the “Third Age” of their lives.</p>
          <p class="mb-4">The overall aim is to provide members with both the stimulus of
            mental activity and the satisfaction of suitable activities with people of a similar age.
          </p>
          <a href="#about" class="btn btn-primary btn-lg me-2">Learn More</a>
          <a href="#membership" class="btn btn-outline-light btn-lg">Join Us</a>
        </div>
      </section>

      <section id="about" class="py-5">
        <div class="container">
          <div class="row align-items-center">
            <div class="col-lg-6">
              <h2>About U3A</h2>
              <p>The University of the Third Age is dedicated to providing educational and social opportunities for older adults. We maintain a vibrant community of learners who engage in a wide range of activities, from academic discussions to creative pursuits.</p>
              <p>Our programs are designed to foster intellectual curiosity, personal growth, and meaningful connections within our diverse membership.</p>
              <p>We welcome individuals from all walks of life to join us in celebrating lifelong learning and community engagement.</p>
              <p>Our primary activity is hosting two presentations per month, on the second and fourth Thursday of each month, at the Settlers Park Don Powis Hall. These presentations cover a variety of topics, including history, science, arts, and culture.</p>
              <p>Enjoy a cup of tea or coffee and chat before the meeting, which starts at 09h30 for 10h00.
                The presentations are followed by a Q&amp;A session with the presenter.</p>
            </div>
            <div class="col-lg-6">
              <div class="ratio ratio-16x9">
                <img src="img/U3A_PortAlfred_Committee_2024_v2.jpg" class="rounded shadow-sm" alt="U3A Committee"
                  style="object-fit: cover; width: 100%; height: 100%;">
              </div>
            </div>
          </div>
        </div>
      </section>

      <?php if (!empty($home_events)): ?>
      <section id="events" class="py-5 bg-light">
        <div class="container">
          <div class="text-center mb-5">
            <h2>Upcoming Events</h2>
            <p class="text-muted">Attend our meetings on the second and fourth Thursday of each month
              in the Settlers Park Don Powis Hall at 09h30 for 10h00.
              Enjoy a cup of tea or coffee and chat before the meeting.
              The presentations are followed by a Q&amp;A session with the presenter.
            </p>
          </div>
          <div class="row g-4">
            <?php foreach ($home_events as $event): ?>
            <div class="col-md-4">
              <div class="card h-100 shadow-sm">
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars(event_date_label($event["presentation_date"])); ?></h5>
                  <p class="card-text fw-bold"><?php echo htmlspecialchars($event["presenter_name"]); ?> &ndash; <?php echo htmlspecialchars($event["title"]); ?></p>
                  <?php if (!empty($event["summary"])): ?>
                  <p class="card-text"><?php echo nl2br(htmlspecialchars($event["summary"])); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="text-center mt-4"><a href="/events.php">All events, including past presentations</a></p>
        </div>
      </section>
      <?php endif; ?>

      <section id="membership" class="py-5 bg-light">
        <div class="container">
          <div class="row align-items-center">
            <div class="col-lg-7">
              <h2>Become a Member</h2>
              <p>Join U3A Port Alfred and enjoy member benefits such as meeting notifications, a monthly
                newsletter, access to recordings of presentations, and a discounted meeting fee.</p>
              <ul class="list-unstyled">
                <li>• Annual membership for individuals - R50-00</li>
                <li>• Annual Membership for couples - R80-00</li>
                <li>• Meeting fee for members - R5-00</li>
                <li>• Meeting fee for non-members - R10-00</li>
              </ul>
            </div>
            <div class="col-lg-5">
              <div class="card border-primary shadow-sm">
                <div class="card-body">
                  <h5 class="card-title">Join today</h5>
                  <p class="card-text">Download a membership form
                <a href="docs/U3A_Mem_Appl_20230317.pdf" target="_blank">here</a>
                and hand it in at the next meeting.  If you have paid the membership
                fee online, please attach the confirmation of payment to the form.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer id="contact" class="bg-dark text-white py-4">
      <div class="container">
        <div class="row">
          <div class="col-md-6">
            <h5>Contact</h5>
            <p class="mb-1">University of the Third Age - U3A</p>
            <p class="mb-1">c/o Settlers Park, Horton Road, Port Alfred</p>
            <p class="mb-0"><a href="mailto:info@u3aportalfred.org.za">info@u3aportalfred.org.za</a></p>
            <p class="mb-0"><a href="mailto:membership@u3aportalfred.org.za">membership@u3aportalfred.org.za</a></p>
            <p class="mb-0"><a href="mailto:secretary@u3aportalfred.org.za">secretary@u3aportalfred.org.za</a></p>
            <p class="mb-0"><a href="mailto:chairman@u3aportalfred.org.za">chairman@u3aportalfred.org.za</a></p>
            <p class="mb-0"><a href="mailto:speakerseeker@u3aportalfred.org.za">speakerseeker@u3aportalfred.org.za</a></p>
          </div>
          <div class="col-md-6 text-md-end">
            <h5>Follow Us</h5>
            <p class="mb-0">Stay connected for event updates in the newsletters,
              Talk of the Town, and The Announcer.</p>
          </div>
        </div>
      </div>
    </footer>
    <script src="js/bootstrap.bundle.min.js"></script>
  </body>
</html>
