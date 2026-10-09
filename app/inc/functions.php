<?php

/**
 * functions.php
 *
 * Shared helpers. This is a lean starting set for the w2 rebuild - we'll
 * port over the rest of w1's functions.php (full_name, formatDate, roles,
 * my_encrypt/my_decrypt, etc.) as each feature is rebuilt, rather than
 * carrying everything across unreviewed.
 */

/**
 * Executes a SQL statement, optionally parameterized, returning an array
 * of result rows, or false on (non-fatal) error. DB credentials come from
 * .env via env(), never hardcoded.
 */
function query(/* $sql [, ...params] */)
{
    static $handle;

    $sql = func_get_arg(0);
    $parameters = array_slice(func_get_args(), 1);

    if (!isset($handle)) {
        try {
            $dsn = "mysql:dbname=" . env("DB_NAME") . ";host=" . env("DB_HOST", "localhost") . ";charset=utf8mb4";
            $handle = new PDO($dsn, env("DB_USER"), env("DB_PASS"));
            $handle->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            exit;
        }
    }

    $statement = $handle->prepare($sql);
    $results = $statement->execute($parameters);
    $_SESSION["inserted_row_id"] = $handle->lastInsertId();

    return $results !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : false;
}

/**
 * Redirects the browser to a destination (absolute URL or path) and
 * halts execution. Must be called before any output.
 */
function redirect(string $destination)
{
    if (preg_match("/^https?:\/\//", $destination)) {
        header("Location: " . $destination);
    } elseif (str_starts_with($destination, "/")) {
        $protocol = isset($_SERVER["HTTPS"]) ? "https" : "http";
        header("Location: $protocol://" . $_SERVER["HTTP_HOST"] . $destination);
    } else {
        $protocol = isset($_SERVER["HTTPS"]) ? "https" : "http";
        $path = rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
        header("Location: $protocol://" . $_SERVER["HTTP_HOST"] . "$path/$destination");
    }
    exit;
}

/**
 * Cleans a piece of user input for safe display: trims, strips slashes,
 * escapes HTML special characters.
 */
function test_input(string $data): string
{
    if ($data === "") {
        return "";
    }
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES);
}

function apologize(string $message)
{
    http_response_code(400);
    require dirname(__DIR__) . "/templates/header.php";
    echo "<div class=\"container mt-5\"><div class=\"alert alert-danger\">"
        . htmlspecialchars($message) . "</div></div>";
    require dirname(__DIR__) . "/templates/footer.php";
    exit;
}

/**
 * CSRF protection for state-changing forms. Put <?php echo csrf_field(); ?>
 * inside every POST form, and call csrf_check() before acting on the POST.
 */
function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $sent = $_POST["csrf_token"] ?? "";
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit("Invalid or expired form. Please go back, reload the page and try again.");
    }
}

/**
 * One-time message shown on the next page load (e.g. "Event saved").
 */
function flash_set(string $message, string $type = "success"): void
{
    $_SESSION["flash"] = ["message" => $message, "type" => $type];
}

function flash_get(): ?array
{
    $flash = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $flash;
}
