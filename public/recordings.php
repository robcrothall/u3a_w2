<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";
require_login();

$page_title = "Recordings";

if (!can_view_recordings()) {
    require APP_DIR . "/templates/header.php";
    ?>
<div class="container mt-4" style="max-width: 720px;">
    <h1>Recordings of Presentations</h1>
    <div class="alert alert-info">
        Recordings are a benefit for paid-up U3A members. If you are a member and your
        membership payment has been received, please contact
        <a href="mailto:membership@u3aportalfred.org.za">membership@u3aportalfred.org.za</a>
        so we can update your account.
    </div>
    <p>Membership details are on the <a href="/#membership">home page</a>.</p>
</div>
    <?php
    require APP_DIR . "/templates/footer.php";
    exit;
}

$per_page = 20;
$page = max(1, (int) ($_GET["page"] ?? 1));
$events = recordings_events($per_page, ($page - 1) * $per_page);
$pages = max(1, (int) ceil(recordings_count() / $per_page));

require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>U3A Port Alfred Recordings of Presentations</h1>
    <p>These videos and (sometimes) additional information are provided in raw form, as recorded.
        They are provided for our members who may have had to miss the presentation itself.
        It is much better if you attend in person, because we like to see you!</p>
    <p>When you click the link to show the video recording, it will typically be displayed in low
        resolution by Google Drive. If you would prefer a higher resolution video, click on the
        "Download" icon (top-left on your screen). The video will download to your Downloads
        directory, and then if you double-click it, the video will open on your desktop.</p>
    <p>Note that downloading or watching these videos will use about 1GB of your data.</p>

    <?php if (empty($events)): ?>
    <p><em>No recordings have been added yet.</em></p>
    <?php endif; ?>

    <?php foreach ($events as $event): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title">
                <?php echo htmlspecialchars($event["presenter_name"]); ?> &ndash;
                <?php echo htmlspecialchars($event["title"]); ?>
            </h5>
            <p class="text-muted mb-2"><?php echo htmlspecialchars(date("j F Y", strtotime($event["presentation_date"]))); ?></p>
            <ul class="mb-0">
                <?php foreach ($event["files"] as $file): ?>
                <li>
                    <a href="/recording.php?id=<?php echo (int) $file["id"]; ?>" target="_blank" rel="noopener">
                        <?php echo htmlspecialchars($file["link_text"]); ?></a>
                    <span class="text-muted">(<?php echo htmlspecialchars(FILE_TYPES[$file["file_type"]] ?? "Other"); ?>)</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
    <nav aria-label="Recordings pages">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item<?php echo $i === $page ? " active" : ""; ?>">
                <a class="page-link" href="/recordings.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
