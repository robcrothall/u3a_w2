<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$event_id = (int) ($_GET["event_id"] ?? $_POST["event_id"] ?? 0);
$event = event_find($event_id);
if ($event === null) {
    flash_set("That event no longer exists.", "warning");
    redirect("/admin/events.php");
}

$errors = [];
$form = ["link_text" => "", "url" => "", "file_type" => "video", "sort_order" => 0];

// ?edit=<file id> loads an existing link into the form so it can be changed
$editing = null;
$edit_id = (int) ($_GET["edit"] ?? $_POST["file_id"] ?? 0);
if ($edit_id > 0) {
    $editing = file_find($edit_id);
    if ($editing !== null && (int) $editing["presentation_id"] !== $event_id) {
        $editing = null;
    }
    if ($editing !== null) {
        $form = [
            "link_text" => $editing["link_text"],
            "url" => $editing["url"],
            "file_type" => $editing["file_type"],
            "sort_order" => (int) $editing["sort_order"],
        ];
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    if (!empty($_POST["delete_file_id"])) {
        $file = file_find((int) $_POST["delete_file_id"]);
        if ($file !== null && (int) $file["presentation_id"] === $event_id) {
            file_delete((int) $file["id"]);
            flash_set("Removed: " . $file["link_text"]);
        }
        redirect("/admin/event_files.php?event_id=" . $event_id);
    }

    [$errors, $form] = file_validate($_POST);
    if (empty($errors)) {
        if ($editing !== null) {
            file_update((int) $editing["id"], $form);
            flash_set("Updated: " . $form["link_text"]);
        } else {
            file_create($event_id, $form);
            flash_set("Added: " . $form["link_text"]);
        }
        redirect("/admin/event_files.php?event_id=" . $event_id);
    }
}

$files = event_files($event_id);
$flash = flash_get();
if (empty($errors) && $_SERVER["REQUEST_METHOD"] !== "POST" && $editing === null) {
    // Suggest the next position in the list for a new link.
    $form["sort_order"] = count($files) + 1;
}

$page_title = "Recordings for an event";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <h1>Recordings and Documents</h1>
    <p class="lead mb-1">
        <?php echo htmlspecialchars($event["presenter_name"]); ?> &ndash;
        <?php echo htmlspecialchars($event["title"]); ?>
    </p>
    <p class="text-muted"><?php echo htmlspecialchars($event["presentation_date"]); ?></p>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if (empty($files)): ?>
    <p><em>No recordings or documents attached yet.</em></p>
    <?php else: ?>
    <table class="table table-striped align-middle">
        <thead><tr><th>Order</th><th>Link text</th><th>Type</th><th>Address</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($files as $file): ?>
            <tr>
                <td><?php echo (int) $file["sort_order"]; ?></td>
                <td><?php echo htmlspecialchars($file["link_text"]); ?></td>
                <td><?php echo htmlspecialchars(FILE_TYPES[$file["file_type"]] ?? "Other"); ?></td>
                <td class="text-break"><a href="<?php echo htmlspecialchars($file["url"]); ?>" target="_blank" rel="noopener">Open</a></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="/admin/event_files.php?event_id=<?php echo $event_id; ?>&amp;edit=<?php echo (int) $file["id"]; ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this link? The file itself in Google Drive is not deleted.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                        <input type="hidden" name="delete_file_id" value="<?php echo (int) $file["id"]; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2 class="mt-4"><?php echo $editing !== null ? "Edit link" : "Add a link"; ?></h2>
    <p class="text-muted">
        Upload the file to Google Drive (or similar), set sharing to "Anyone with the link can view",
        then paste its address here.
    </p>
    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <?php if ($editing !== null): ?>
        <input type="hidden" name="file_id" value="<?php echo (int) $editing["id"]; ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="link_text">Text to show</label>
            <input type="text" class="form-control" id="link_text" name="link_text" maxlength="255"
                   value="<?php echo htmlspecialchars($form["link_text"]); ?>" placeholder="e.g. Video recording - Part 1" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="url">Web address (https://...)</label>
            <input type="url" class="form-control" id="url" name="url" maxlength="500"
                   value="<?php echo htmlspecialchars($form["url"]); ?>" required>
        </div>
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label" for="file_type">Type</label>
                <select class="form-select" id="file_type" name="file_type">
                    <?php foreach (FILE_TYPES as $value => $label): ?>
                    <option value="<?php echo $value; ?>"<?php echo $form["file_type"] === $value ? " selected" : ""; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="sort_order">Order in list</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="9999"
                       value="<?php echo (int) $form["sort_order"]; ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $editing !== null ? "Save changes" : "Add link"; ?></button>
        <?php if ($editing !== null): ?>
        <a class="btn btn-outline-secondary" href="/admin/event_files.php?event_id=<?php echo $event_id; ?>">Cancel</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/admin/events.php">Back to events</a>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
