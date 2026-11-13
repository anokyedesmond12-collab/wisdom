<?php
require_once __DIR__ . "/../config/superadmin_session.php";
startSuperAdminSession();
require_once __DIR__ . "/../config/database.php";

function endSuperAdminSession($reason)
{
    destroySuperAdminSession();
    header("Location: ../login_superadmin.php?error=" . rawurlencode($reason));
    exit();
}

function destroySuperAdminSession()
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            [
                "expires" => time() - 42000,
                "path" => $params["path"],
                "domain" => $params["domain"],
                "secure" => $params["secure"],
                "httponly" => $params["httponly"],
                "samesite" => $params["samesite"] ?? "Lax"
            ]
        );
    }
    session_destroy();
}

function requireSuperAdmin()
{
    if (empty($_SESSION["superadmin_authenticated"]) || ($_SESSION["role"] ?? "") !== "superadmin") {
        header("Location: ../login_superadmin.php");
        exit();
    }
    if (empty($_SESSION["superadmin_2fa_verified"])) {
        endSuperAdminSession("session_invalid");
    }
    if ((int)($_SESSION["superadmin_id"] ?? 0) !== 1) {
        endSuperAdminSession("session_invalid");
    }

    $lastActivity = filter_var($_SESSION["superadmin_last_activity"] ?? null, FILTER_VALIDATE_INT);
    if ($lastActivity === false || $lastActivity < time() - 1800) {
        endSuperAdminSession("session_expired");
    }

    $account = $GLOBALS["conn"]->prepare("SELECT id FROM superadmin_accounts WHERE id = ? LIMIT 1");
    $account->execute([(int)$_SESSION["superadmin_id"]]);
    if (!$account->fetchColumn()) {
        endSuperAdminSession("account_unavailable");
    }
    $_SESSION["superadmin_last_activity"] = time();
}

function superAdminCsrfToken()
{
    if (empty($_SESSION["superadmin_csrf"])) {
        $_SESSION["superadmin_csrf"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["superadmin_csrf"];
}

function validateSuperAdminCsrf($token)
{
    return isset($_SESSION["superadmin_csrf"])
        && is_string($_SESSION["superadmin_csrf"])
        && is_string($token)
        && hash_equals($_SESSION["superadmin_csrf"], $token);
}
