<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["delete_id"])) {
    csrf_check();
    $event = event_find((int) $_POST["delete_id"]);
    if ($event !== null) {
        event_delete((int) $event["id"]);
        flash_set("Deleted: " . $event["title"]);
    }
    redirect("/admin/events.php");
}

$events = events_all();
$flash = flash_get();
$today = date("Y-m-d");

$page_title = "Manage events";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Manage Events</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p>
        <a class="btn btn-primary" href="/admin/event_edit.php">Add an event</a>
        <a class="btn btn-outline-secondary" href="/events.php">View public Events page</a>
        <a class="btn btn-outline-secondary" href="/admin/recording_log.php">Recording usage</a>
    </p>

    <?php if (empty($events)): ?>
    <p><em>No events yet. Click "Add an event" to create the first one.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr><th>Date</th><th>Presenter</th><th>Title</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
            <tr<?php echo $event["presentation_date"] < $today ? ' class="text-muted"' : ""; ?>>
                <td><?php echo htmlspecialchars($event["presentation_date"]); ?></td>
                <td><?php echo htmlspecialchars($event["presenter_name"]); ?></td>
                <td><?php echo htmlspecialchars($event["title"]); ?></td>
                <td>
                    <?php if ($event["status"] === "draft"): ?>
                    <span class="badge bg-warning text-dark">Draft</span>
                    <?php else: ?>
                    <span class="badge bg-success">Published</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="/admin/event_files.php?event_id=<?php echo (int) $event["id"]; ?>">Recordings</a>
                    <a class="btn btn-sm btn-outline-primary" href="/admin/event_edit.php?id=<?php echo (int) $event["id"]; ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this event? This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_id" value="<?php echo (int) $event["id"]; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
