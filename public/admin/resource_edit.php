<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$existing = $id > 0 ? resource_find($id) : null;
if ($id > 0 && $existing === null) {
    flash_set("That item no longer exists.", "warning");
    redirect("/admin/resources.php");
}
$kind = $existing !== null ? $existing["kind"] : (($_GET["kind"] ?? $_POST["kind"] ?? "link") === "document" ? "document" : "link");

$errors = [];
$form = $existing ?? [
    "title" => "", "information" => "", "category" => "", "visibility" => "everyone",
    "date_added" => date("Y-m-d"), "url" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    [$errors, $clean] = resource_validate($_POST, $kind);
    $form = array_merge($form, $clean);
    $stored = null;
    $hasUpload = isset($_FILES["document"]) && ($_FILES["document"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (empty($errors) && $kind === "document" && ($existing === null || $hasUpload)) {
        [$stored, $uploadError] = resource_store_upload($_FILES["document"] ?? []);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }
    if (empty($errors)) {
        if ($existing !== null) {
            $oldFile = $existing["stored_name"];
            resource_update($id, $clean, $stored ?: null);
            if ($stored) {
                resource_delete_file($oldFile);
            }
        } else {
            resource_create($kind, $clean, $stored ?: null, (int) $_SESSION["id"]);
        }
        flash_set("Saved: " . $clean["title"]);
        redirect("/admin/resources.php");
    }
}

$categories = resource_categories(false);
$limit = resource_server_upload_limit();
$page_title = $existing !== null ? "Edit resource" : ($kind === "document" ? "Upload document" : "Add link");
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 720px;">
    <h1><?php echo $existing !== null ? "Edit" : "Add"; ?> <?php echo $kind === "document" ? "a Document" : "a Link"; ?></h1>

    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="kind" value="<?php echo htmlspecialchars($kind); ?>">

        <div class="mb-3">
            <label class="form-label" for="title">Title (up to 80 characters)</label>
            <input type="text" class="form-control" id="title" name="title" maxlength="80" required
                   value="<?php echo htmlspecialchars($form["title"]); ?>">
            <div class="form-text">This is the text people click on.</div>
        </div>

        <?php if ($kind === "link"): ?>
        <div class="mb-3">
            <label class="form-label" for="url">Web address</label>
            <input type="url" class="form-control" id="url" name="url" maxlength="500" required placeholder="https://..."
                   value="<?php echo htmlspecialchars((string) $form["url"]); ?>">
            <div class="form-text">It opens in a new browser tab.</div>
        </div>
        <?php else: ?>
        <div class="mb-3">
            <label class="form-label" for="document">
                <?php echo $existing !== null ? "Replace the document (leave empty to keep the current one)" : "Document"; ?>
            </label>
            <input type="file" class="form-control" id="document" name="document"
                   accept=".pdf,.doc,.docx,.odt,.ods" <?php echo $existing === null ? "required" : ""; ?>>
            <div class="form-text">
                PDF, Word (.doc, .docx) or OpenDocument (.odt, .ods), up to
                <?php echo htmlspecialchars(format_bytes(min(RESOURCE_MAX_BYTES, $limit))); ?>.
                <?php if ($existing !== null): ?>
                Current file: <?php echo htmlspecialchars((string) $existing["file_name"]); ?>
                (<?php echo htmlspecialchars(format_bytes((int) $existing["file_size"])); ?>).
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="information">Information about it (optional)</label>
            <textarea class="form-control" id="information" name="information" rows="4" maxlength="1000"><?php echo htmlspecialchars((string) $form["information"]); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="category">Category (optional)</label>
                <input type="text" class="form-control" id="category" name="category" maxlength="40" list="category_list"
                       value="<?php echo htmlspecialchars((string) $form["category"]); ?>">
                <datalist id="category_list">
                    <?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?>
                </datalist>
                <div class="form-text">For example Newsletter, Speaker information, Bursaries.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="date_added">Date added</label>
                <input type="date" class="form-control" id="date_added" name="date_added" required
                       max="<?php echo date("Y-m-d"); ?>" value="<?php echo htmlspecialchars($form["date_added"]); ?>">
                <div class="form-text">The list is shown newest first.</div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="visibility">Who can see it</label>
            <select class="form-select" id="visibility" name="visibility">
                <?php foreach (RESOURCE_VISIBILITY as $value => $label): ?>
                <option value="<?php echo $value; ?>"<?php echo $form["visibility"] === $value ? " selected" : ""; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-outline-secondary" href="/admin/resources.php">Cancel</a>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
