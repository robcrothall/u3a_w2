<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$member = member_find($id);
if ($member === null) {
    flash_set("That member no longer exists.", "warning");
    redirect("/admin/members.php");
}
$self = "/admin/member_edit.php?id=" . $id;
$errors = [];
$form = $member;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $action = (string) ($_POST["action"] ?? "");

    if ($action === "save") {
        [$errors, $clean] = member_validate($_POST, $id);
        $form = array_merge($member, $clean);
        if (empty($errors)) {
            member_update($id, $clean);
            flash_set("Details saved.");
            redirect($self);
        }
    } elseif ($action === "set_type") {
        $error = membership_set_type($id, (string) ($_POST["membership_type"] ?? ""));
        flash_set($error ?? "Membership type changed.", $error ? "danger" : "success");
        redirect($self);
    } elseif ($action === "link") {
        $error = membership_link($id, (int) ($_POST["partner_id"] ?? 0));
        flash_set($error ?? "Partner linked. Their payments are now shared.", $error ? "danger" : "success");
        redirect($self);
    } elseif ($action === "unlink") {
        $error = membership_unlink($id);
        flash_set($error ?? "Unlinked. This member is now an individual membership.", $error ? "danger" : "success");
        redirect($self);
    }
}

$household = member_household($member);
$partners = array_filter($household, fn($m) => (int) $m["id"] !== $id);
$partnerSearch = trim($_GET["partner_search"] ?? "");
$partnerResults = [];
if ($partnerSearch !== "" && count($household) < 2) {
    $partnerResults = array_filter(members_search($partnerSearch), fn($m) => (int) $m["id"] !== $id && $m["partners"] === "");
}
$thisYear = (int) date("Y");
$flash = flash_get();

$page_title = "Edit member";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 860px;">
    <p><a href="/admin/members.php">&larr; All members</a></p>
    <h1><?php echo htmlspecialchars($member["first_name"] . " " . $member["surname"]); ?></h1>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash["type"]); ?>"><?php echo htmlspecialchars($flash["message"]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <h2 class="h4 mt-4">Details</h2>
    <form method="post" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="save">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="first_name">First name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" maxlength="100" required
                       value="<?php echo htmlspecialchars($form["first_name"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="surname">Surname</label>
                <input type="text" class="form-control" id="surname" name="surname" maxlength="100" required
                       value="<?php echo htmlspecialchars($form["surname"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="email">Email (used to log in)</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="255"
                       value="<?php echo htmlspecialchars((string) $form["email"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="email2">Second email (optional)</label>
                <input type="email" class="form-control" id="email2" name="email2" maxlength="255"
                       value="<?php echo htmlspecialchars((string) $form["email2"]); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="100"
                       value="<?php echo htmlspecialchars((string) $form["phone"]); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="500"><?php echo htmlspecialchars((string) $form["address"]); ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save details</button>
    </form>

    <h2 class="h4 mt-5">Membership</h2>
    <p>
        Type: <strong><?php echo htmlspecialchars(MEMBERSHIP_TYPES[$member["membership_type"]] ?? $member["membership_type"]); ?></strong>
        <?php if ($partners): ?>
        &mdash; with
        <?php foreach ($partners as $p): ?>
        <a href="/admin/member_edit.php?id=<?php echo (int) $p["id"]; ?>"><?php echo htmlspecialchars($p["first_name"] . " " . $p["surname"]); ?></a>
        <?php endforeach; ?>
        <?php endif; ?>
    </p>

    <form method="post" class="row g-2 align-items-end mb-3">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="set_type">
        <div class="col-auto">
            <label class="form-label" for="membership_type">Change type</label>
            <select class="form-select" id="membership_type" name="membership_type">
                <?php foreach (MEMBERSHIP_TYPES as $value => $label): ?>
                <option value="<?php echo $value; ?>"<?php echo $member["membership_type"] === $value ? " selected" : ""; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Change</button></div>
    </form>

    <?php if ($partners): ?>
    <form method="post" onsubmit="return confirm('Unlink these two people? They will each become an individual membership.');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="unlink">
        <button type="submit" class="btn btn-outline-danger">Unlink from partner</button>
    </form>
    <?php else: ?>
    <h3 class="h5 mt-4">Link a partner</h3>
    <p class="text-muted">A couple pays R80 once and both people become paid-up. Search for the partner (who must be registered or imported already, and not already linked).</p>
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="col-md-6">
            <input type="text" class="form-control" name="partner_search" placeholder="Partner's name or email"
                   value="<?php echo htmlspecialchars($partnerSearch); ?>">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Search</button></div>
    </form>
    <?php if ($partnerSearch !== "" && empty($partnerResults)): ?>
    <p><em>No unlinked members found matching "<?php echo htmlspecialchars($partnerSearch); ?>".</em></p>
    <?php endif; ?>
    <?php foreach ($partnerResults as $r): ?>
    <form method="post" class="d-flex align-items-center gap-3 mb-2">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="link">
        <input type="hidden" name="partner_id" value="<?php echo (int) $r["id"]; ?>">
        <span><?php echo htmlspecialchars($r["first_name"] . " " . $r["surname"]); ?>
            <span class="text-muted small"><?php echo htmlspecialchars((string) $r["email"]); ?></span></span>
        <button type="submit" class="btn btn-sm btn-primary">Link as partner</button>
    </form>
    <?php endforeach; ?>
    <?php endif; ?>

    <h2 class="h4 mt-5">Payments</h2>
    <table class="table table-sm" style="max-width: 420px;">
        <thead><tr><th>Year</th><th>Status</th></tr></thead>
        <tbody>
            <?php for ($y = $thisYear + 1; $y >= $thisYear - 3; $y--): ?>
            <?php $p = payment_find($id, $y); ?>
            <tr>
                <td><?php echo $y; ?></td>
                <td>
                    <?php if ($member["membership_type"] === "honorary"): ?>
                    Honorary &ndash; paid-up for life
                    <?php elseif ($p): ?>
                    Paid <?php echo htmlspecialchars($p["paid_date"]); ?><?php echo $p["amount"] === null ? " (partner paid)" : " &ndash; R" . number_format((float) $p["amount"], 2); ?>
                    <?php else: ?>
                    <span class="text-muted">Not paid</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <p><a class="btn btn-outline-secondary" href="/admin/payments.php?search=<?php echo urlencode($member["surname"]); ?>">Record a payment</a></p>
</div>
<?php
require APP_DIR . "/templates/footer.php";
