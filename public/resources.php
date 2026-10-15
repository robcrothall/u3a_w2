<?php
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$category = trim((string) ($_GET["category"] ?? ""));
$categories = resource_categories();
if ($category !== "" && !in_array($category, $categories, true)) {
    $category = "";
}
$items = resources_for_viewer($category);
$is_admin = !empty($_SESSION["id"]) && user_has_role((int) $_SESSION["id"], "admin");

$page_title = "Resources";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <h1>Resources</h1>
    <p class="text-muted">
        Interesting links and documents from U3A Port Alfred, newest first.
        <?php if (empty($_SESSION["id"])): ?>
        <a href="/login.php">Log in</a> to see more.
        <?php endif; ?>
    </p>

    <?php if ($is_admin): ?>
    <p><a class="btn btn-sm btn-outline-primary" href="/admin/resources.php">Manage resources</a></p>
    <?php endif; ?>

    <?php if ($categories): ?>
    <p>
        <a class="btn btn-sm <?php echo $category === "" ? "btn-secondary" : "btn-outline-secondary"; ?>" href="/resources.php">All</a>
        <?php foreach ($categories as $c): ?>
        <a class="btn btn-sm <?php echo $category === $c ? "btn-secondary" : "btn-outline-secondary"; ?>"
           href="/resources.php?category=<?php echo urlencode($c); ?>"><?php echo htmlspecialchars($c); ?></a>
        <?php endforeach; ?>
    </p>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <p><em>Nothing has been added yet.</em></p>
    <?php endif; ?>

    <?php foreach ($items as $r): ?>
    <?php
    $isDoc = $r["kind"] === "document";
    $href = $isDoc ? "/resource.php?id=" . (int) $r["id"] : $r["url"];
    $ext = $isDoc ? strtolower(pathinfo((string) $r["stored_name"], PATHINFO_EXTENSION)) : "";
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title mb-1">
                <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo htmlspecialchars($r["title"]); ?></a>
            </h5>
            <p class="text-muted small mb-2">
                Added <?php echo htmlspecialchars(date("j F Y", strtotime($r["date_added"]))); ?>
                <?php if ($isDoc): ?>
                &middot; <?php echo htmlspecialchars(RESOURCE_DOC_TYPES[$ext][0] ?? "Document"); ?> document, <?php echo htmlspecialchars(format_bytes((int) $r["file_size"])); ?>
                <?php else: ?>
                &middot; link to <?php echo htmlspecialchars((string) parse_url($r["url"], PHP_URL_HOST)); ?>
                <?php endif; ?>
                <?php if (!empty($r["category"])): ?><span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($r["category"]); ?></span><?php endif; ?>
                <?php if ($is_admin && $r["visibility"] !== "everyone"): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo htmlspecialchars(RESOURCE_VISIBILITY[$r["visibility"]]); ?></span>
                <?php endif; ?>
            </p>
            <?php if (!empty($r["information"])): ?>
            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($r["information"])); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
