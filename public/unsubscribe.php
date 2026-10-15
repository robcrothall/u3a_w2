<?php
// One-click unsubscribe from newsletters and announcements. The secret token in the
// link identifies the member, so no login is needed. A mail program's "unsubscribe"
// button POSTs here directly (RFC 8058); a person opening the link sees a confirm button,
// so link scanners that merely fetch the page cannot unsubscribe anyone by accident.
require __DIR__ . "/_app_path.php";
require APP_DIR . "/config/config.php";

$token = (string) ($_GET["t"] ?? $_POST["t"] ?? "");
try {
    $person = user_by_unsubscribe_token($token);
} catch (Throwable $e) {
    $person = null;
}
$done = false;

if ($person !== null && $_SERVER["REQUEST_METHOD"] === "POST") {
    email_opt_out_set((int) $person["id"], true);
    $done = true;
    if (isset($_POST["List-Unsubscribe"])) {      // a mail program's own button: plain reply
        header("Content-Type: text/plain");
        exit("You have been unsubscribed.");
    }
}

$page_title = "Unsubscribe";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 560px;">
    <h1>Newsletters and announcements</h1>

    <?php if ($person === null): ?>
    <div class="alert alert-warning">
        This link is not valid any more. You can change your choice any time after logging in, under
        <strong>My account &rarr; My preferences</strong>, or ask a committee member.
    </div>
    <?php elseif ($done || !empty($person["email_opt_out"])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($person["first_name"]); ?>, you will not receive newsletters or announcements from U3A Port Alfred.
        You stay a member. Messages you ask for yourself (such as a password link) and receipts for payments are still sent.
    </div>
    <p>Changed your mind? Log in and use <a href="/preferences.php">My preferences</a>.</p>
    <?php else: ?>
    <p>
        <?php echo htmlspecialchars($person["first_name"] . " " . $person["surname"]); ?>, would you like to stop receiving
        newsletters and announcements from U3A Port Alfred?
    </p>
    <form method="post">
        <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
        <button type="submit" class="btn btn-primary">Yes, unsubscribe me</button>
        <a class="btn btn-link" href="/">No, keep sending them</a>
    </form>
    <?php endif; ?>
</div>
<?php
require APP_DIR . "/templates/footer.php";
