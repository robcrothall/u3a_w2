<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["delete_id"])) {
    csrf_check();
    $r = resource_find((int) $_POST["delete_id"]);
    if ($r !== null) {
        resource_delete((int) $r["id"]);
        flash_set("Deleted: " . $r["title"]);
    }
    redirect("/admin/resources.php");
}

$items = resources_all();
$flash = flash_get();

$page_title = "Manage resources";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Manage Resources</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <p>
        <a class="btn btn-primary" href="/admin/resource_edit.php?kind=link">Add a link</a>
        <a class="btn btn-primary" href="/admin/resource_edit.php?kind=document">Upload a document</a>
        <a class="btn btn-outline-secondary" href="/resources.php">View the public page</a>
    </p>

    <?php if (empty($items)): ?>
    <p><em>Nothing yet. Add a link or upload a document.</em></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Added</th><th>Type</th><th>Title</th><th>Category</th><th>Visible to</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($items as $r): ?>
            <tr>
                <td class="text-nowrap"><?php echo htmlspecialchars($r["date_added"]); ?></td>
                <td><?php echo $r["kind"] === "document" ? "Document" : "Link"; ?></td>
                <td>
                    <?php echo htmlspecialchars($r["title"]); ?>
                    <?php if ($r["kind"] === "document"): ?>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) $r["file_name"]); ?> (<?php echo htmlspecialchars(format_bytes((int) $r["file_size"])); ?>)</div>
                    <?php else: ?>
                    <div class="small text-muted text-break"><?php echo htmlspecialchars((string) $r["url"]); ?></div>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars((string) $r["category"]); ?></td>
                <td>
                    <?php $v = $r["visibility"]; ?>
                    <span class="badge <?php echo $v === "everyone" ? "bg-success" : ($v === "members" ? "bg-info text-dark" : "bg-warning text-dark"); ?>">
                        <?php echo htmlspecialchars(["everyone" => "Everyone", "members" => "Members", "admins" => "Admins"][$v]); ?></span>
                </td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="/admin/resource_edit.php?id=<?php echo (int) $r["id"]; ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this item? Uploaded documents are removed from the server. This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_id" value="<?php echo (int) $r["id"]; ?>">
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
