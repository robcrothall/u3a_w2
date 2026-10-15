<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$search = trim($_GET["search"] ?? "");
$rows = members_with_interests($search);

$page_title = "Volunteers and interests";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Volunteers and Interests</h1>
    <p class="text-muted">
        What members said about presenting, their skills, and the subjects they would like offered.
        Only admins can see this page.
    </p>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label class="form-label" for="search">Search names, fields, skills or subjects</label>
            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            <?php if ($search !== ""): ?><a class="btn btn-link" href="/admin/speakers.php">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($rows)): ?>
    <p><em>No answers recorded<?php echo $search !== "" ? " matching your search" : " yet"; ?>.</em></p>
    <?php else: ?>
    <p class="text-muted"><?php echo count($rows); ?> member<?php echo count($rows) === 1 ? "" : "s"; ?>.</p>
    <div class="table-responsive">
    <table class="table table-striped align-top">
        <thead><tr><th>Member</th><th>Prepared to present</th><th>Skills / expertise</th><th>Would like to see</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="text-nowrap">
                    <a href="/admin/member_edit.php?id=<?php echo (int) $r["id"]; ?>">
                        <?php echo htmlspecialchars(trim(($r["title"] ? $r["title"] . " " : "") . $r["first_name"] . " " . $r["surname"])); ?></a>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($r["mobile"] ?: $r["email"])); ?></div>
                </td>
                <td><?php echo htmlspecialchars((string) $r["can_present"]); ?></td>
                <td><?php echo htmlspecialchars((string) $r["skills"]); ?></td>
                <td><?php echo htmlspecialchars((string) $r["wants_subjects"]); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
