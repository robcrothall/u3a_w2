<?php
/**
 * profile_fields.php - the "about you" inputs shared by registration, My details
 * and the staff member forms. Expects $form with: title, mobile, can_present,
 * skills, wants_subjects (missing keys are treated as empty).
 */
$pf = fn(string $k): string => htmlspecialchars((string) ($form[$k] ?? ""));
?>
<div class="mb-3">
    <label class="form-label" for="title">Title (optional)</label>
    <input type="text" class="form-control" id="title" name="title" maxlength="40" list="title_suggestions"
           style="max-width: 220px;" value="<?php echo $pf("title"); ?>" autocomplete="honorific-prefix">
    <datalist id="title_suggestions">
        <?php foreach (MEMBER_TITLE_SUGGESTIONS as $t): ?><option value="<?php echo htmlspecialchars($t); ?>"><?php endforeach; ?>
    </datalist>
    <div class="form-text">For example Mr, Mrs, Dr or Rev Dr.</div>
</div>
<div class="mb-3">
    <label class="form-label" for="mobile">Mobile number (optional)</label>
    <input type="text" class="form-control" id="mobile" name="mobile" maxlength="30" inputmode="tel"
           style="max-width: 260px;" value="<?php echo $pf("mobile"); ?>" autocomplete="tel">
    <div class="form-text">For example 083 678 5050, or with a country code such as +44 7700 900123.</div>
</div>
<div class="mb-3">
    <label class="form-label" for="can_present">Are you prepared to present? If so, in what field? (optional)</label>
    <textarea class="form-control" id="can_present" name="can_present" rows="2" maxlength="250"><?php echo $pf("can_present"); ?></textarea>
    <div class="form-text">Up to 250 characters.</div>
</div>
<div class="mb-3">
    <label class="form-label" for="skills">What are your skills or expertise? (optional)</label>
    <textarea class="form-control" id="skills" name="skills" rows="2" maxlength="250"><?php echo $pf("skills"); ?></textarea>
</div>
<div class="mb-3">
    <label class="form-label" for="wants_subjects">What subjects or information would you like to see offered? (optional)</label>
    <textarea class="form-control" id="wants_subjects" name="wants_subjects" rows="2" maxlength="250"><?php echo $pf("wants_subjects"); ?></textarea>
</div>
