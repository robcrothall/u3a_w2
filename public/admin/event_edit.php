<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$existing = $id > 0 ? event_find($id) : null;
if ($id > 0 && $existing === null) {
    flash_set("That event no longer exists.", "warning");
    redirect("/admin/events.php");
}

$errors = [];
$event = $existing ?? [
    "presenter_name" => "",
    "title" => "",
    "presentation_date" => "",
    "summary" => "",
    "status" => "published",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    [$errors, $clean] = event_validate($_POST);
    $event = $clean;
    if (empty($errors)) {
        if ($existing !== null) {
            event_update($id, $clean);
        } else {
            event_create($clean);
        }
        flash_set("Saved: " . $clean["title"]);
        redirect("/admin/events.php");
    }
}

$page_title = $existing !== null ? "Edit event" : "Add event";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 720px;">
    <h1><?php echo $existing !== null ? "Edit Event" : "Add an Event"; ?></h1>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="mb-3">
            <label class="form-label" for="presentation_date">Date</label>
            <input type="date" class="form-control" id="presentation_date" name="presentation_date"
                   value="<?php echo htmlspecialchars($event["presentation_date"]); ?>" required>
            <div class="form-text">Meetings are on the second and fourth Thursday of the month.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="presenter_name">Presenter</label>
            <input type="text" class="form-control" id="presenter_name" name="presenter_name" maxlength="255"
                   value="<?php echo htmlspecialchars($event["presenter_name"]); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="title">Title</label>
            <input type="text" class="form-control" id="title" name="title" maxlength="255"
                   value="<?php echo htmlspecialchars($event["title"]); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="summary">Summary (optional)</label>
            <textarea class="form-control" id="summary" name="summary" rows="6" maxlength="5000"><?php echo htmlspecialchars($event["summary"] ?? ""); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="published"<?php echo $event["status"] === "published" ? " selected" : ""; ?>>Published (visible to everyone)</option>
                <option value="draft"<?php echo $event["status"] === "draft" ? " selected" : ""; ?>>Draft (staff only)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-outline-secondary" href="/admin/events.php">Cancel</a>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
