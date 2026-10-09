<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$search = trim($_GET["search"] ?? "");
$per_page = 50;
$page = max(1, (int) ($_GET["page"] ?? 1));
$total = members_count($search);
$pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $pages);
$members = members_list($search, $per_page, ($page - 1) * $per_page);
$flash = flash_get();
$qs = $search !== "" ? "&search=" . urlencode($search) : "";

$page_title = "Members";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4">
    <h1>Members</h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label class="form-label" for="search">Search by name, email or phone</label>
            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
            <?php if ($search !== ""): ?><a class="btn btn-link" href="/admin/members.php">Clear</a><?php endif; ?>
        </div>
    </form>
    <p class="text-muted"><?php echo $total; ?> member<?php echo $total === 1 ? "" : "s"; ?>
        <?php echo $search !== "" ? "match" : "in total"; ?>. Paid-up is for <?php echo date("Y"); ?>.</p>

    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Membership</th><th>Paid-up</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m["surname"] . ", " . $m["first_name"]); ?></td>
                <td><?php echo $m["email"] !== null ? htmlspecialchars($m["email"]) : '<span class="text-muted">none</span>'; ?></td>
                <td class="text-nowrap"><?php echo htmlspecialchars((string) $m["phone"]); ?></td>
                <td>
                    <?php echo htmlspecialchars(ucfirst($m["membership_type"])); ?>
                    <?php if ($m["partners"] !== ""): ?><span class="text-muted">with <?php echo htmlspecialchars($m["partners"]); ?></span><?php endif; ?>
                </td>
                <td><?php echo $m["paid_up"] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-light text-dark border">No</span>'; ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/member_edit.php?id=<?php echo (int) $m["id"]; ?>">Edit</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav aria-label="Member pages">
        <ul class="pagination flex-wrap">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item<?php echo $i === $page ? " active" : ""; ?>">
                <a class="page-link" href="/admin/members.php?page=<?php echo $i . $qs; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
