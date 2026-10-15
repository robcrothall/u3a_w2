<?php
require __DIR__ . "/../_app_path.php";
require APP_DIR . "/config/config.php";
require_role("admin");

$me = (int) $_SESSION["id"];
$errors = [];
$notice = null;
$preview = null;
$form = ["audience" => "admins", "subject" => "", "body" => "", "docs" => [], "delivery" => "links"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $form = array_merge($form, [
        "audience" => (string) ($_POST["audience"] ?? "admins"),
        "subject" => (string) ($_POST["subject"] ?? ""),
        "body" => (string) ($_POST["body"] ?? ""),
        "docs" => array_map("intval", (array) ($_POST["docs"] ?? [])),
        "delivery" => (string) ($_POST["delivery"] ?? "links"),
    ]);
    [$errors, $clean] = campaign_validate($_POST);
    $action = (string) ($_POST["action"] ?? "preview");

    if (empty($errors)) {
        $recipients = mail_audience_recipients($clean["audience"]);
        $campaign = $clean;       // doc_ids / attach / subject / body in the shape the builder expects

        if ($action === "queue") {
            if (!bulk_mail_enabled()) {
                $errors[] = BULK_MAIL_OFF_MESSAGE;
            } elseif (!$recipients) {
                $errors[] = "Nobody is in that audience, so there is nothing to send.";
            } else {
                $id = campaign_create($clean, $me);
                flash_set("Queued for " . count($recipients) . " recipient" . (count($recipients) === 1 ? "" : "s") . ". Nothing has been sent yet: use \"Send next batch\".");
                redirect("/admin/mail_campaign.php?id=" . $id);
            }
        } elseif ($action === "test") {
            $self = mail_recipient_by_user($me);
            if ($self === null || empty($self["email"])) {
                $errors[] = "Your own account has no email address, so a test cannot be sent to you.";
            } else {
                [$subject, $body, $opts] = campaign_build_message($campaign, $self, true);
                $ok = send_email($self["email"], $subject, $body, $opts);
                $notice = $ok ? ["success", "A test copy was sent to " . $self["email"] . "."]
                              : ["danger", "The test could not be sent. Please try again."];
            }
        }
        if (empty($errors) && $action !== "queue") {
            $sample = $recipients[0] ?? mail_recipient_by_user($me);
            if ($sample !== null) {
                [$pSubject, $pBody] = campaign_build_message($campaign, $sample);
                $preview = ["to" => $sample, "subject" => $pSubject, "body" => $pBody, "count" => count($recipients)];
            }
        }
    }
}

$counts = [];
foreach (array_keys(MAIL_AUDIENCES) as $a) {
    $counts[$a] = count(mail_audience_recipients($a));
}
$documents = array_values(array_filter(resources_all(), fn($r) => $r["kind"] === "document"));
$allowance = mail_batch_allowance();

$page_title = "Send an email";
require APP_DIR . "/templates/header.php";
?>
<div class="container mt-4" style="max-width: 900px;">
    <h1>Send an Email</h1>
    <p class="text-muted">
        Compose a message to members. It is queued first and sent in small batches (the server allows
        <?php echo MAIL_HOURLY_LIMIT; ?> emails an hour). Every message includes an unsubscribe link and is sent from
        <strong><?php echo htmlspecialchars(mail_from_address()); ?></strong>; replies go to the same address.
        <a href="/admin/mail_campaigns.php">Messages already sent</a>
    </p>

    <?php if (!bulk_mail_enabled()): ?>
    <div class="alert alert-warning">
        <strong>Bulk sending is switched off on this site.</strong> You can preview and send a test to yourself,
        but not queue a message for members. (This protects real members from messages sent by a test site.)
    </div>
    <?php endif; ?>
    <?php if ($notice): ?>
    <div class="alert alert-<?php echo $notice[0]; ?>"><?php echo htmlspecialchars($notice[1]); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($preview): ?>
    <div class="card border-primary mb-4">
        <div class="card-header">Preview, as it would look for
            <strong><?php echo htmlspecialchars(trim($preview["to"]["first_name"] . " " . $preview["to"]["surname"])); ?></strong>
            (first of <?php echo (int) $preview["count"]; ?> recipient<?php echo $preview["count"] === 1 ? "" : "s"; ?>)</div>
        <div class="card-body">
            <p class="mb-2"><strong>Subject:</strong> <?php echo htmlspecialchars($preview["subject"]); ?></p>
            <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;"><?php echo htmlspecialchars($preview["body"]); ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?php echo csrf_field(); ?>

        <fieldset class="mb-3">
            <legend class="h6">Who is it for?</legend>
            <?php foreach (MAIL_AUDIENCES as $key => $label): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="audience" id="aud_<?php echo $key; ?>" value="<?php echo $key; ?>"
                       <?php echo $form["audience"] === $key ? "checked" : ""; ?>>
                <label class="form-check-label" for="aud_<?php echo $key; ?>">
                    <?php echo htmlspecialchars($label); ?> <span class="badge bg-secondary"><?php echo (int) $counts[$key]; ?></span>
                </label>
            </div>
            <?php endforeach; ?>
        </fieldset>

        <div class="mb-3">
            <label class="form-label" for="subject">Subject</label>
            <input type="text" class="form-control" id="subject" name="subject" maxlength="150" required
                   value="<?php echo htmlspecialchars($form["subject"]); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label" for="body">Message</label>
            <textarea class="form-control" id="body" name="body" rows="14" required><?php echo htmlspecialchars($form["body"]); ?></textarea>
            <div class="form-text">
                Plain text. Keywords are replaced for each person:
                <?php foreach (MAIL_MERGE_FIELDS as $k => $label): ?>
                <code title="<?php echo htmlspecialchars($label); ?>">$<?php echo $k; ?>$</code>
                <?php endforeach; ?>
                e.g. <em>Dear $first_name$,</em>
            </div>
        </div>

        <?php if ($documents): ?>
        <fieldset class="mb-3">
            <legend class="h6">Documents (up to <?php echo MAIL_MAX_DOCS; ?>) from Resources</legend>
            <?php foreach ($documents as $d): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="docs[]" id="doc_<?php echo (int) $d["id"]; ?>" value="<?php echo (int) $d["id"]; ?>"
                       <?php echo in_array((int) $d["id"], $form["docs"], true) ? "checked" : ""; ?>>
                <label class="form-check-label" for="doc_<?php echo (int) $d["id"]; ?>">
                    <?php echo htmlspecialchars($d["title"]); ?>
                    <span class="text-muted small">(<?php echo htmlspecialchars(format_bytes((int) $d["file_size"])); ?>,
                    visible to: <?php echo htmlspecialchars($d["visibility"]); ?>)</span>
                </label>
            </div>
            <?php endforeach; ?>
            <div class="mt-2">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="delivery" id="del_links" value="links"
                           <?php echo $form["delivery"] !== "attach" ? "checked" : ""; ?>>
                    <label class="form-check-label" for="del_links">Put links in the message (recommended)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="delivery" id="del_attach" value="attach"
                           <?php echo $form["delivery"] === "attach" ? "checked" : ""; ?>>
                    <label class="form-check-label" for="del_attach">Attach the files (up to <?php echo htmlspecialchars(format_bytes(MAIL_ATTACH_MAX_BYTES)); ?> in total)</label>
                </div>
            </div>
        </fieldset>
        <?php else: ?>
        <p class="text-muted">No documents have been uploaded yet. Add some under <a href="/admin/resources.php">Manage resources</a> to include them.</p>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="action" value="preview" class="btn btn-outline-secondary">Preview</button>
            <button type="submit" name="action" value="test" class="btn btn-outline-primary">Send a test to me</button>
            <button type="submit" name="action" value="queue" class="btn btn-primary"
                    onclick="return confirm('Queue this message for everyone in the chosen group? Nothing is sent until you click Send next batch.');">Queue for sending</button>
        </div>
        <p class="text-muted small mt-2">
            Mail sent in the last hour (all kinds): <?php echo emails_last_hour(); ?> of <?php echo MAIL_HOURLY_LIMIT; ?>.
            You can send <?php echo $allowance; ?> more right now.
        </p>
    </form>
</div>
<?php
require APP_DIR . "/templates/footer.php";
