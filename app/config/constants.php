<?php

/**
 * constants.php
 *
 * Non-secret, non-environment-specific constants. Anything that differs
 * between local/staging/production (DB creds, encryption key, site URL)
 * lives in .env instead - see env.php.
 */

define("CLIENT_NAME", "U3A Port Alfred");
define("SYSTEM_NAME", "U3A Port Alfred Membership Portal");

define("WEBSITE", env("SITE_URL", "https://u3aportalfred.org.za"));
define("HELPDESK_EMAIL", env("HELPDESK_EMAIL", "rob@crothall.co.za"));
define("HELPDESK_CC", "");

define("COPYRIGHT", "U3A Port Alfred, Horton Road, Port Alfred, South Africa");
define("PHYSICAL_ADDR", "U3A, Settlers Park Retirement Village, Horton Road, Port Alfred 6170, South Africa");
define("POSTAL_ADDR", "U3A, Settlers Park Association, Private Bag 6125, Port Alfred 6170, South Africa");

define("CIPHERING", "AES-128-CTR");
define("ENCRYPTION_KEY", env("ENCRYPTION_KEY"));
define("ENCRYPTION_IV", env("ENCRYPTION_IV"));

// Where uploaded images (e.g. the committee photo) are stored and served from.
// UPLOAD_DIR is a filesystem path (for writing); UPLOAD_URL is how the
// browser reaches the same files.
define("UPLOAD_DIR", dirname(__DIR__, 2) . "/public/uploads");
define("UPLOAD_URL", WEBSITE . "/uploads");
