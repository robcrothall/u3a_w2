<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$per_page = 20;
$page = max(1, (int) ($_GET["page"] ?? 1));
$upcoming = events_upcoming();
$past = events_past($per_page, ($page - 1) * $per_page);
$past_pages = max(1, (int) ceil(events_past_count() / $per_page));
$is_admin = !empty($_SESSION["id"]) && user_has_role((int) $_SESSION["id"], "admin");

$page_title = "Events";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Events</h1>
    <p class="text-muted">
        We meet on the second and fourth Thursday of each month in the Settlers Park
        Don Powis Hall at 09h30 for 10h00. Enjoy a cup of tea or coffee and chat before the
        meeting. Presentations are followed by a Q&amp;A session with the presenter.
    </p>
    <?php if ($is_admin): ?>
    <p><a class="btn btn-sm btn-outline-primary" href="/admin/events.php">Manage events</a></p>
    <?php endif; ?>

    <h2 class="mt-4">Upcoming</h2>
    <?php if (empty($upcoming)): ?>
    <p><em>No upcoming events have been announced yet. Please check back soon.</em></p>
    <?php endif; ?>
    <?php foreach ($upcoming as $event): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars(event_date_label($event["presentation_date"])); ?></h5>
            <p class="card-text fw-bold">
                <?php echo htmlspecialchars($event["presenter_name"]); ?> &ndash;
                <?php echo htmlspecialchars($event["title"]); ?>
            </p>
            <?php if (!empty($event["summary"])): ?>
            <p class="card-text"><?php echo nl2br(htmlspecialchars($event["summary"])); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <h2 class="mt-5">Past presentations</h2>
    <?php if (empty($past)): ?>
    <p><em>Nothing to show yet.</em></p>
    <?php endif; ?>
    <?php foreach ($past as $event): ?>
    <div class="mb-3">
        <strong><?php echo htmlspecialchars(date("j F Y", strtotime($event["presentation_date"]))); ?></strong>
        &ndash; <?php echo htmlspecialchars($event["presenter_name"]); ?>:
        <?php echo htmlspecialchars($event["title"]); ?>
        <?php if (!empty($event["summary"])): ?>
        <div class="text-muted"><?php echo nl2br(htmlspecialchars($event["summary"])); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($past_pages > 1): ?>
    <nav aria-label="Past presentations pages">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $past_pages; $i++): ?>
            <li class="page-item<?php echo $i === $page ? " active" : ""; ?>">
                <a class="page-link" href="/events.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
