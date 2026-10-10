<?php

/**
 * site_content_functions.php
 *
 * Small staff-editable parts of the public home page:
 *   about_text       the "About U3A" paragraphs (plain text; blank line = new paragraph)
 *   committee_photo  file name of the current photo in public/uploads/committee/
 */

// The About text shown until staff change it (same wording as the original home page).
const ABOUT_DEFAULT = "The University of the Third Age is dedicated to providing educational and social opportunities for older adults. We maintain a vibrant community of learners who engage in a wide range of activities, from academic discussions to creative pursuits.\n\nOur programs are designed to foster intellectual curiosity, personal growth, and meaningful connections within our diverse membership.\n\nWe welcome individuals from all walks of life to join us in celebrating lifelong learning and community engagement.\n\nOur primary activity is hosting two presentations per month, on the second and fourth Thursday of each month, at the Settlers Park Don Powis Hall. These presentations cover a variety of topics, including history, science, arts, and culture.\n\nEnjoy a cup of tea or coffee and chat before the meeting, which starts at 09h30 for 10h00. The presentations are followed by a Q&A session with the presenter.";

const COMMITTEE_PHOTO_DIR = "/uploads/committee";
const COMMITTEE_PHOTO_MAX_BYTES = 6 * 1024 * 1024;
const COMMITTEE_PHOTO_MAX_WIDTH = 1600;

function site_content_get(string $key, string $default = ""): string
{
    try {
        $rows = query("SELECT content_value FROM site_content WHERE content_key = ?", $key);
    } catch (Throwable $e) {
        return $default;   // table not created yet: the public home page must still work
    }
    $value = $rows[0]["content_value"] ?? null;
    return ($value !== null && $value !== "") ? $value : $default;
}

function site_content_set(string $key, string $value, int $user_id): void
{
    query(
        "INSERT INTO site_content (content_key, content_value, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by)",
        $key,
        $value,
        $user_id
    );
}

/** Plain text -> safe HTML paragraphs. */
function paragraphs_html(string $text): string
{
    $out = "";
    foreach (preg_split("/\R{2,}/", trim($text)) as $p) {
        $p = trim($p);
        if ($p !== "") {
            $out .= "<p>" . nl2br(htmlspecialchars($p)) . "</p>\n";
        }
    }
    return $out;
}

/** URL path of the current uploaded committee photo, or null to use the built-in one. */
function committee_photo_url(): ?string
{
    $name = site_content_get("committee_photo");
    if ($name === "" || !preg_match("/^committee-[A-Za-z0-9._-]+\.(jpg|png)$/", $name)) {
        return null;
    }
    if (!is_file(PUBLIC_DIR . COMMITTEE_PHOTO_DIR . "/" . $name)) {
        return null;
    }
    return COMMITTEE_PHOTO_DIR . "/" . $name;
}

/**
 * Validates and stores an uploaded committee photo. Returns an error
 * message, or null on success (the new photo becomes the current one).
 */
function committee_photo_save(array $file, int $user_id): ?string
{
    if (!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return "Please choose a photo to upload.";
    }
    if ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) {
        return "That file is too large (maximum 6 MB).";
    }
    if ($file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
        return "The upload did not work. Please try again.";
    }
    if ($file["size"] > COMMITTEE_PHOTO_MAX_BYTES) {
        return "That file is too large (maximum 6 MB).";
    }

    $info = @getimagesize($file["tmp_name"]);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return "Please upload a JPEG or PNG photo.";
    }
    [$width, $height] = $info;
    if ($width < 200 || $height < 150 || $width > 8000 || $height > 8000) {
        return "The photo must be between 200 and 8000 pixels wide.";
    }

    $dir = PUBLIC_DIR . COMMITTEE_PHOTO_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return "The server could not create the photo folder. Please tell the webmaster.";
    }
    // never let an uploaded folder run scripts
    foreach ([PUBLIC_DIR . "/uploads/.htaccess", $dir . "/.htaccess"] as $ht) {
        if (!is_file($ht)) {
            @file_put_contents($ht, "<FilesMatch \"\\.(php|php\\d|phtml|phar|pl|py|cgi|sh)$\">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\n");
        }
    }

    $useGd = function_exists("imagecreatefromjpeg") && function_exists("imagecreatefrompng");
    if ($useGd) {
        // GD decodes to ~4 bytes a pixel; make sure a big photo cannot exhaust PHP's memory
        @ini_set("memory_limit", "384M");
        $limit = ini_bytes((string) ini_get("memory_limit"));
        $needed = (int) ($width * $height * 5.0) + memory_get_usage();
        if ($limit > 0 && $needed > $limit) {
            $mp = round($width * $height / 1000000, 1);
            return "That photo is very large ($mp megapixels) for this server. Please reduce it to about 12 megapixels "
                . "(for example 4000 x 3000) and try again.";
        }
    }
    $base = "committee-" . date("Ymd-His") . "-" . bin2hex(random_bytes(4));
    if ($useGd) {
        // re-encode: strips metadata (GPS etc.), resizes, and guarantees it really is an image
        $src = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($file["tmp_name"]) : @imagecreatefromjpeg($file["tmp_name"]);
        if ($src === false) {
            return "That image could not be read. Please try a different photo.";
        }
        if ($width > COMMITTEE_PHOTO_MAX_WIDTH) {
            $newH = (int) round($height * COMMITTEE_PHOTO_MAX_WIDTH / $width);
            $dst = imagecreatetruecolor(COMMITTEE_PHOTO_MAX_WIDTH, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, COMMITTEE_PHOTO_MAX_WIDTH, $newH, $width, $height);
            imagedestroy($src);
            $src = $dst;
        } elseif ($info[2] === IMAGETYPE_PNG) {
            $flat = imagecreatetruecolor($width, $height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $src, 0, 0, 0, 0, $width, $height);
            imagedestroy($src);
            $src = $flat;
        }
        $name = "$base.jpg";
        $ok = imagejpeg($src, "$dir/$name", 85);
        imagedestroy($src);
    } else {
        $name = $base . ($info[2] === IMAGETYPE_PNG ? ".png" : ".jpg");
        $ok = move_uploaded_file($file["tmp_name"], "$dir/$name");
    }
    if (!$ok) {
        return "The photo could not be saved. Please tell the webmaster.";
    }

    $previous = site_content_get("committee_photo");
    site_content_set("committee_photo", $name, $user_id);
    if ($previous !== "" && $previous !== $name && preg_match("/^committee-[A-Za-z0-9._-]+\.(jpg|png)$/", $previous)) {
        @unlink("$dir/$previous");
    }
    return null;
}

/** Goes back to the built-in photo. */
function committee_photo_reset(int $user_id): void
{
    $previous = site_content_get("committee_photo");
    site_content_set("committee_photo", "", $user_id);
    if ($previous !== "" && preg_match("/^committee-[A-Za-z0-9._-]+\.(jpg|png)$/", $previous)) {
        @unlink(PUBLIC_DIR . COMMITTEE_PHOTO_DIR . "/" . $previous);
    }
}

/** "128M" -> bytes; -1/0 means no limit. */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === "" || $v === "-1") {
        return 0;
    }
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case "g":
            $n *= 1024;
            // fall through
        case "m":
            $n *= 1024;
            // fall through
        case "k":
            $n *= 1024;
    }
    return $n;
}
