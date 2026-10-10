<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");


$errors = [];
$about = site_content_get("about_text", ABOUT_DEFAULT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "save_about") {
        $about = str_replace("\r\n", "\n", trim((string) ($_POST["about_text"] ?? "")));
        if ($about === "") {
            $errors[] = "The About text cannot be empty.";
        } elseif (mb_strlen($about) > 6000) {
            $errors[] = "The About text is too long (maximum 6000 characters).";
        } else {
            site_content_set("about_text", $about, (int) $_SESSION["id"]);
            flash_set("About U3A text saved.");
            redirect("/admin/site_content.php");
        }
    } elseif ($action === "upload_photo") {
        $error = committee_photo_save($_FILES["photo"] ?? [], (int) $_SESSION["id"]);
        flash_set($error ?? "Committee photo replaced.", $error ? "danger" : "success");
        redirect("/admin/site_content.php");
    } elseif ($action === "reset_photo") {
        committee_photo_reset((int) $_SESSION["id"]);
        flash_set("Back to the original committee photo.");
        redirect("/admin/site_content.php");
    } elseif ($action === "reset_about") {
        site_content_set("about_text", "", (int) $_SESSION["id"]);
        flash_set("About U3A text reset to the original.");
        redirect("/admin/site_content.php");
    }
}

$photo = committee_photo_url();
$flash = flash_get();

$page_title = "Edit home page";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <h1>Edit the Home Page</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <h2 class="h4 mt-4">About U3A</h2>
    <p class="text-muted">
        Plain text. Leave a blank line between paragraphs. Changes appear on the
        <a href="/#about">home page</a> straight away.
    </p>
    <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_about">
        <div class="mb-3">
            <label class="visually-hidden" for="about_text">About U3A text</label>
            <textarea class="form-control" id="about_text" name="about_text" rows="14" maxlength="6000"><?php echo htmlspecialchars($about); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save text</button>
    </form>
    <form method="post" class="mt-2" onsubmit="return confirm('Replace your text with the original wording?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reset_about">
        <button type="submit" class="btn btn-link text-muted p-0">Reset to the original wording</button>
    </form>

    <h2 class="h4 mt-5">Committee photo</h2>
    <p class="text-muted">
        JPEG or PNG, up to 6 MB. Landscape photos about 1600 pixels wide look best. Large photos are
        resized automatically.
    </p>
    <div class="mb-3">
        <img src="<?php echo htmlspecialchars($photo ?? "/img/U3A_PortAlfred_Committee_2024_v2.jpg"); ?>"
             alt="Current committee photo" class="img-fluid rounded shadow-sm" style="max-height: 320px;">
        <div class="form-text"><?php echo $photo ? "Photo uploaded by staff." : "This is the original photo."; ?></div>
    </div>
    <form method="post" enctype="multipart/form-data" class="mb-2">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_photo">
        <div class="mb-3">
            <label class="form-label" for="photo">Choose a new photo</label>
            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload photo</button>
    </form>
    <?php if ($photo): ?>
    <form method="post" onsubmit="return confirm('Go back to the original committee photo?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reset_photo">
        <button type="submit" class="btn btn-link text-muted p-0">Go back to the original photo</button>
    </form>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
