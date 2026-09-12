<?php
require __DIR__ . "/../../app/config/config.php";
require_role("admin");

$search = trim($_GET["search"] ?? "");
$results = [];
$temp_password = null;
$reset_user = null;

if ($search !== "") {
    $like = "%" . $search . "%";
    $results = query(
        "SELECT * FROM users WHERE email LIKE ? OR surname LIKE ? OR first_name LIKE ? ORDER BY surname, first_name",
        $like,
        $like,
        $like
    );
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["reset_user_id"])) {
    $reset_user_id = (int) $_POST["reset_user_id"];
    $reset_user = find_user_by_id($reset_user_id);
    if ($reset_user !== null) {
        $temp_password = admin_reset_password($reset_user_id);
    }
}

require __DIR__ . "/../../app/templates/header.php";
?>
<div class="container mt-5">
    <h1>Reset a Member's Password</h1>

    <?php if ($temp_password !== null && $reset_user !== null): ?>
    <div class="alert alert-success">
        <strong>Password reset for <?php echo htmlspecialchars($reset_user["first_name"] . " " . $reset_user["surname"]); ?>.</strong><br>
        Temporary password: <code><?php echo htmlspecialchars($temp_password); ?></code><br>
        Please pass this to them directly (phone, in person, etc). They'll be required to
        choose their own new password the moment they log in with it.
        This temporary password will not be shown again.
    </div>
    <?php endif; ?>

    <form method="get" class="mb-4">
        <div class="input-group" style="max-width: 480px;">
            <input type="text" class="form-control" name="search" placeholder="Search by name or email"
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
        </div>
    </form>

    <?php if ($search !== "" && empty($results)): ?>
    <p><em>No members found matching "<?php echo htmlspecialchars($search); ?>".</em></p>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row["first_name"] . " " . $row["surname"]); ?></td>
                <td><?php echo htmlspecialchars($row["email"]); ?></td>
                <td class="text-end">
                    <form method="post" onsubmit="return confirm('Reset the password for this member? They will need a new temporary password from you to log in.');">
                        <input type="hidden" name="reset_user_id" value="<?php echo (int) $row["id"]; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Reset password</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
require __DIR__ . "/../../app/templates/footer.php";
