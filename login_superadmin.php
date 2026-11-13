<?php
require_once __DIR__ . "/config/superadmin_session.php";
startSuperAdminSession();
header("Cache-Control: no-store, private");
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/private_settings.php";
require_once __DIR__ . "/config/africastalking_sms.php";

function loadSuperAdminLoginAccount(PDO $conn)
{
    return $conn->query(
        "SELECT id, full_name, email, password_hash, login_otp_hash,
                login_otp_expires_at, login_otp_attempts, login_otp_send_count,
                login_otp_sent_at, login_otp_window_started_at,
                login_otp_session_hash,
                (login_otp_expires_at IS NOT NULL AND login_otp_expires_at > NOW()) AS otp_is_active
         FROM superadmin_accounts
         WHERE id = 1
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
}

function superAdminLoginSessionHash(string $challenge): string
{
    return hash("sha256", session_id() . ":" . $challenge);
}

function prepareSuperAdminLoginOtp(PDO $conn, string $otpHash, string $sessionHash): void
{
    try {
        $conn->beginTransaction();
        $state = $conn->query(
            "SELECT login_otp_sent_at, login_otp_window_started_at, login_otp_send_count,
                    (login_otp_sent_at IS NOT NULL AND login_otp_sent_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)) AS cooldown_active,
                    (login_otp_window_started_at IS NULL OR login_otp_window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS window_expired
             FROM superadmin_accounts
             WHERE id = 1
             FOR UPDATE"
        )->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            throw new RuntimeException("account_unavailable");
        }
        if ((int)$state["cooldown_active"] === 1) {
            throw new RuntimeException("otp_cooldown");
        }

        $windowExpired = (int)$state["window_expired"] === 1;
        $sendCount = $windowExpired ? 0 : (int)$state["login_otp_send_count"];
        if ($sendCount >= 3) {
            throw new RuntimeException("otp_send_limit");
        }

        $sql =
            "UPDATE superadmin_accounts
             SET login_otp_hash = ?, login_otp_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                 login_otp_sent_at = NOW(), login_otp_attempts = 0,
                 login_otp_session_hash = ?";
        $sql .= $windowExpired
            ? ", login_otp_window_started_at = NOW(), login_otp_send_count = 1"
            : ", login_otp_send_count = login_otp_send_count + 1";
        $sql .= " WHERE id = 1";
        $update = $conn->prepare($sql);
        $update->execute([$otpHash, $sessionHash]);
        $conn->commit();
    } catch (RuntimeException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function clearSuperAdminLoginOtp(PDO $conn, string $sessionHash, bool $resetLimits = false): void
{
    $sql =
        "UPDATE superadmin_accounts
         SET login_otp_hash = NULL, login_otp_expires_at = NULL,
             login_otp_attempts = 0, login_otp_session_hash = NULL";
    if ($resetLimits) {
        $sql .= ", login_otp_sent_at = NULL, login_otp_window_started_at = NULL,
                 login_otp_send_count = 0";
    }
    $sql .= " WHERE id = 1 AND login_otp_session_hash = ?";
    $clear = $conn->prepare($sql);
    $clear->execute([$sessionHash]);
}

function loginOtpErrorMessage(string $reason): string
{
    $messages = [
        "account_unavailable" => "The Super Admin account is unavailable. Please try again.",
        "otp_cooldown" => "Wait 60 seconds before requesting another sign-in code.",
        "otp_send_limit" => "The hourly SMS-code limit has been reached. Try again later.",
        "challenge_invalid" => "This sign-in attempt is no longer valid. Enter your password again."
    ];
    return $messages[$reason] ?? "Unable to prepare a sign-in code. Please try again.";
}

if (
    !empty($_SESSION["superadmin_authenticated"]) &&
    ($_SESSION["role"] ?? "") === "superadmin" &&
    (int)($_SESSION["superadmin_id"] ?? 0) === 1 &&
    !empty($_SESSION["superadmin_2fa_verified"])
) {
    header("Location: admin/dashboard.php");
    exit();
}
if (($_SESSION["role"] ?? "") === "superadmin") {
    $_SESSION = [];
    session_regenerate_id(true);
}

if (empty($_SESSION["superadmin_login_csrf"])) {
    $_SESSION["superadmin_login_csrf"] = bin2hex(random_bytes(32));
}
$error = "";
$message = "";
$errorMessages = [
    "session_expired" => "Your Super Admin session expired. Please sign in again.",
    "session_invalid" => "This Super Admin session is invalid. Please sign in again.",
    "account_unavailable" => "The Super Admin account is unavailable. Please contact the site operator."
];
if (isset($_GET["error"]) && is_string($_GET["error"]) && isset($errorMessages[$_GET["error"]])) {
    $error = $errorMessages[$_GET["error"]];
}
$success = ($_GET["setup"] ?? "") === "complete"
    ? "Super Admin account registered. Sign in with your email and password, then verify the SMS code."
    : "";

$settings = [];
$smsConfigured = false;
try {
    $settings = edutrackPrivateSettings();
    $provider = $settings["africastalking"] ?? [];
    $ownerPhone = $settings["superadmin_owner_phone"] ?? "";
    $smsConfigured = is_array($provider)
        && trim((string)($provider["username"] ?? "")) !== ""
        && trim((string)($provider["api_key"] ?? "")) !== ""
        && is_string($ownerPhone)
        && preg_match('/^\+[1-9]\d{7,14}$/', trim($ownerPhone)) === 1
        && extension_loaded("curl");
} catch (RuntimeException | ParseError $e) {
    error_log("Unable to load Super Admin login SMS settings: " . $e->getMessage());
}

$admin = loadSuperAdminLoginAccount($conn);
$setupRegistrationPending = false;
$setupOtpActive = false;
$setupCsrf = "";
$setupOtpNotice = $_SESSION["superadmin_setup_login_notice"] ?? null;
unset($_SESSION["superadmin_setup_login_notice"]);
if (!is_array($setupOtpNotice)) {
    $setupOtpNotice = null;
}

if (!$admin) {
    $setupAccountCount = (int)$conn->query(
        "SELECT COUNT(*) FROM superadmin_accounts"
    )->fetchColumn();
    if ($setupAccountCount === 0) {
        $setupState = $conn->query(
            "SELECT code_consumed_at, otp_hash, pending_email, pending_password_hash,
                    (otp_expires_at IS NOT NULL AND otp_expires_at > NOW()) AS otp_is_active
             FROM superadmin_bootstrap_state
             WHERE singleton_id = 1"
        )->fetch(PDO::FETCH_ASSOC);
        $setupRegistrationPending = $setupState
            && empty($setupState["code_consumed_at"])
            && !empty($setupState["pending_email"])
            && !empty($setupState["pending_password_hash"]);
        $setupOtpActive = $setupRegistrationPending
            && !empty($setupState["otp_hash"])
            && (int)$setupState["otp_is_active"] === 1;
    }

    if ($setupRegistrationPending) {
        if (empty($_SESSION["superadmin_setup_csrf"])) {
            $_SESSION["superadmin_setup_csrf"] = bin2hex(random_bytes(32));
        }
        $setupCsrf = $_SESSION["superadmin_setup_csrf"];
    }
}

$challenge = $_SESSION["superadmin_login_challenge"] ?? "";
$otpPending = false;
if (
    $admin &&
    is_string($challenge) &&
    preg_match('/^[a-f0-9]{64}$/', $challenge) === 1 &&
    is_string($admin["login_otp_session_hash"] ?? null) &&
    hash_equals($admin["login_otp_session_hash"], superAdminLoginSessionHash($challenge))
) {
    $otpPending = true;
} elseif (!empty($_SESSION["superadmin_login_challenge"])) {
    unset($_SESSION["superadmin_login_challenge"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedCsrf = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";
    if (!is_string($postedCsrf) || !hash_equals($_SESSION["superadmin_login_csrf"], $postedCsrf)) {
        $error = "Your session expired. Reload the page and try again.";
    } elseif (!$admin) {
        $error = "Super Admin setup is not complete. Use the owner setup page first.";
    } elseif (!is_string($action)) {
        $error = "Choose a valid sign-in action.";
    } elseif ($action === "send_login_otp") {
        if ($otpPending) {
            $error = "Enter the SMS code already sent, or cancel that sign-in attempt.";
        } else {
            $emailInput = $_POST["email"] ?? "";
            $password = $_POST["password"] ?? "";
            $email = is_string($emailInput) ? strtolower(trim($emailInput)) : "";
            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !is_string($password) ||
                !hash_equals(strtolower($admin["email"]), $email) ||
                !password_verify($password, $admin["password_hash"])
            ) {
                $error = "Invalid email or password.";
            } elseif (!$smsConfigured) {
                $error = "SMS verification is unavailable. Contact the site operator.";
            } else {
                session_regenerate_id(true);
                $challenge = bin2hex(random_bytes(32));
                $_SESSION["superadmin_login_challenge"] = $challenge;
                $_SESSION["superadmin_login_csrf"] = bin2hex(random_bytes(32));
                $sessionHash = superAdminLoginSessionHash($challenge);
                $otp = (string)random_int(100000, 999999);
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);

                if (!$otpHash) {
                    unset($_SESSION["superadmin_login_challenge"]);
                    $error = "Unable to prepare a sign-in code. Please try again.";
                } else {
                    try {
                        prepareSuperAdminLoginOtp($conn, $otpHash, $sessionHash);
                        try {
                            sendAfricaTalkingSms(
                                $settings,
                                trim($ownerPhone),
                                "Your EduTrack Super Admin sign-in code is {$otp}. It expires in 5 minutes. Do not share it."
                            );
                            $message = "A sign-in code was sent to the platform owner's phone. Enter it to complete login.";
                        } catch (RuntimeException $e) {
                            $error = "SMS delivery could not be confirmed. If you received a code, enter it below; otherwise wait 60 seconds and request another.";
                        }
                    } catch (RuntimeException $e) {
                        unset($_SESSION["superadmin_login_challenge"]);
                        $error = loginOtpErrorMessage($e->getMessage());
                    } catch (PDOException $e) {
                        unset($_SESSION["superadmin_login_challenge"]);
                        error_log("Unable to prepare Super Admin sign-in OTP: " . $e->getMessage());
                        $error = "Unable to prepare a sign-in code. Please try again.";
                    }
                }
            }
        }
    } elseif ($action === "resend_login_otp") {
        if (!$otpPending) {
            $error = "There is no active sign-in attempt. Enter your email and password first.";
        } elseif (!$smsConfigured) {
            $error = "SMS verification is unavailable. Contact the site operator.";
        } else {
            $challenge = (string)$_SESSION["superadmin_login_challenge"];
            $sessionHash = superAdminLoginSessionHash($challenge);
            $otp = (string)random_int(100000, 999999);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);
            if (!$otpHash) {
                $error = "Unable to prepare a sign-in code. Please try again.";
            } else {
                try {
                    prepareSuperAdminLoginOtp($conn, $otpHash, $sessionHash);
                    try {
                        sendAfricaTalkingSms(
                            $settings,
                            trim($ownerPhone),
                            "Your EduTrack Super Admin sign-in code is {$otp}. It expires in 5 minutes. Do not share it."
                        );
                        $message = "A new sign-in code was sent to the platform owner's phone.";
                    } catch (RuntimeException $e) {
                        $error = "SMS delivery could not be confirmed. If you received a code, enter it below; otherwise wait 60 seconds and request another.";
                    }
                } catch (RuntimeException $e) {
                    $error = loginOtpErrorMessage($e->getMessage());
                } catch (PDOException $e) {
                    error_log("Unable to resend Super Admin sign-in OTP: " . $e->getMessage());
                    $error = "Unable to prepare a sign-in code. Please try again.";
                }
            }
        }
    } elseif ($action === "verify_login_otp") {
        $otp = $_POST["otp"] ?? "";
        if (!$otpPending) {
            $error = "There is no active sign-in attempt. Enter your email and password first.";
        } elseif (!is_string($otp) || preg_match('/^\d{6}$/', $otp) !== 1) {
            $error = "Enter the six-digit SMS code.";
        } else {
            $challenge = (string)$_SESSION["superadmin_login_challenge"];
            $sessionHash = superAdminLoginSessionHash($challenge);
            try {
                $conn->beginTransaction();
                $lockedAccount = $conn->query(
                    "SELECT login_otp_hash, login_otp_attempts, login_otp_session_hash,
                            (login_otp_expires_at IS NOT NULL AND login_otp_expires_at > NOW()) AS otp_is_active
                     FROM superadmin_accounts
                     WHERE id = 1
                     FOR UPDATE"
                )->fetch(PDO::FETCH_ASSOC);
                if (
                    !$lockedAccount ||
                    !is_string($lockedAccount["login_otp_session_hash"] ?? null) ||
                    !hash_equals($lockedAccount["login_otp_session_hash"], $sessionHash)
                ) {
                    throw new RuntimeException("challenge_invalid");
                }
                if ((int)$lockedAccount["login_otp_attempts"] >= 5) {
                    clearSuperAdminLoginOtp($conn, $sessionHash);
                    $conn->commit();
                    unset($_SESSION["superadmin_login_challenge"]);
                    $error = "Too many incorrect codes. Sign in again after the 60-second cooldown.";
                } elseif (empty($lockedAccount["login_otp_hash"])) {
                    throw new RuntimeException("challenge_invalid");
                } elseif ((int)$lockedAccount["otp_is_active"] !== 1) {
                    $conn->commit();
                    $error = "That sign-in code has expired. Request a new code.";
                } elseif (!password_verify($otp, $lockedAccount["login_otp_hash"])) {
                    $attempts = (int)$lockedAccount["login_otp_attempts"] + 1;
                    if ($attempts >= 5) {
                        clearSuperAdminLoginOtp($conn, $sessionHash);
                        $error = "Too many incorrect codes. Sign in again after the 60-second cooldown.";
                        unset($_SESSION["superadmin_login_challenge"]);
                    } else {
                        $updateAttempts = $conn->prepare(
                            "UPDATE superadmin_accounts SET login_otp_attempts = ? WHERE id = 1 AND login_otp_session_hash = ?"
                        );
                        $updateAttempts->execute([$attempts, $sessionHash]);
                        $error = "The sign-in code is incorrect.";
                    }
                    $conn->commit();
                } else {
                    clearSuperAdminLoginOtp($conn, $sessionHash, true);
                    $conn->commit();
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION["superadmin_authenticated"] = true;
                    $_SESSION["superadmin_2fa_verified"] = true;
                    $_SESSION["superadmin_id"] = 1;
                    $_SESSION["role"] = "superadmin";
                    $_SESSION["superadmin_name"] = $admin["full_name"] ?: "Super Admin";
                    $_SESSION["superadmin_last_activity"] = time();
                    $_SESSION["superadmin_csrf"] = bin2hex(random_bytes(32));
                    header("Location: admin/dashboard.php");
                    exit();
                }
            } catch (RuntimeException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                unset($_SESSION["superadmin_login_challenge"]);
                $error = loginOtpErrorMessage($e->getMessage());
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Unable to verify Super Admin sign-in OTP: " . $e->getMessage());
                $error = "Unable to verify the sign-in code. Please try again.";
            }
        }
    } elseif ($action === "cancel_login") {
        if ($otpPending) {
            $challenge = (string)$_SESSION["superadmin_login_challenge"];
            try {
                clearSuperAdminLoginOtp($conn, superAdminLoginSessionHash($challenge));
            } catch (PDOException $e) {
                error_log("Unable to cancel Super Admin sign-in OTP: " . $e->getMessage());
                $error = "Unable to cancel this sign-in attempt. Please try again.";
            }
            if ($error === "") {
                unset($_SESSION["superadmin_login_challenge"]);
                $message = "Sign-in attempt cancelled.";
            }
        } else {
            $error = "There is no active sign-in attempt.";
        }
    } else {
        $error = "Choose a valid sign-in action.";
    }
}

$admin = loadSuperAdminLoginAccount($conn);
$challenge = $_SESSION["superadmin_login_challenge"] ?? "";
$otpPending = $admin &&
    is_string($challenge) &&
    preg_match('/^[a-f0-9]{64}$/', $challenge) === 1 &&
    is_string($admin["login_otp_session_hash"] ?? null) &&
    hash_equals($admin["login_otp_session_hash"], superAdminLoginSessionHash($challenge));
if (!$otpPending && !empty($_SESSION["superadmin_login_challenge"])) {
    unset($_SESSION["superadmin_login_challenge"]);
}
$csrf = $_SESSION["superadmin_login_csrf"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin Login | EDUTRACK</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6fa;color:#17243b;font-family:Arial,sans-serif}
        .login-card{width:min(430px,calc(100% - 40px));padding:30px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 28px rgba(16,38,75,.08)}
        h1{margin:0 0 8px;color:#10264b;font-size:24px}p{color:#64748b;font-size:14px;line-height:1.5}
        label{display:block;margin-top:16px;font-size:13px;font-weight:bold}
        input{width:100%;box-sizing:border-box;margin-top:7px;padding:11px;border:1px solid #cbd5e1;border-radius:7px;font-size:14px}
        button{width:100%;margin-top:20px;padding:12px;border:0;border-radius:7px;background:#10264b;color:white;font-weight:bold;cursor:pointer}
        .error,.success,.notice{padding:11px;border-radius:7px;font-size:13px;line-height:1.45}.error{background:#fee2e2;color:#991b1b}.success{background:#ecfdf5;color:#166534}.notice{background:#eff6ff;color:#1e3a8a}
        .secondary{background:#e8eef8;color:#183d70}.actions{display:flex;gap:10px}.actions form{flex:1}
        a{display:inline-block;margin-top:18px;color:#183d70;font-size:13px}
    </style>
</head>
<body>
    <main class="login-card">
        <h1>Platform Admin</h1>
        <?php if ($success !== ""): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($message !== ""): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error !== ""): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($setupOtpNotice && isset($setupOtpNotice["message"])): ?>
            <?php $setupNoticeClass = ($setupOtpNotice["type"] ?? "") === "success" ? "success" : "error"; ?>
            <div class="<?= $setupNoticeClass ?>"><?= htmlspecialchars((string)$setupOtpNotice["message"]) ?></div>
        <?php endif; ?>
        <?php if ($admin && $otpPending): ?>
            <p>Enter the six-digit SMS sign-in code sent to the platform owner's privately configured phone. It expires in five minutes.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="verify_login_otp">
                <label for="otp">SMS sign-in code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                <button type="submit">Verify Code &amp; Sign In</button>
            </form>
            <div class="actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="resend_login_otp">
                    <button class="secondary" type="submit">Resend Code</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="cancel_login">
                    <button class="secondary" type="submit">Cancel</button>
                </form>
            </div>
        <?php elseif ($admin): ?>
            <p>Enter your Super Admin email and password. We will send a one-time sign-in code to the platform owner's phone.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="send_login_otp">
                <label for="email">Admin email</label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="username" required>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Send Sign-in Code</button>
            </form>
        <?php elseif (!$admin && $setupRegistrationPending): ?>
            <?php if ($setupOtpActive): ?>
                <p>Enter the SMS code already sent to complete your one-time Super Admin setup and sign in. After setup, future logins require your email, password, and a new SMS code.</p>
                <form method="post" action="superadmin/setup.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf) ?>">
                    <input type="hidden" name="action" value="verify_otp">
                    <input type="hidden" name="return_to_login" value="1">
                    <label for="setup_otp">SMS verification code</label>
                    <input id="setup_otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                    <button type="submit">Verify Code &amp; Complete Setup</button>
                </form>
            <?php else: ?>
                <p>Your registration code has expired or is no longer active. Request a new code here to complete setup and sign in.</p>
            <?php endif; ?>
            <form method="post" action="superadmin/setup.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf) ?>">
                <input type="hidden" name="action" value="resend_otp">
                <input type="hidden" name="return_to_login" value="1">
                <button class="secondary" type="submit"><?= $setupOtpActive ? "Resend Setup Code" : "Send New Setup Code" ?></button>
            </form>
        <?php else: ?>
            <p>No Super Admin account is registered yet. Start the one-time setup; after requesting the SMS code, return here to enter it and complete sign-in.</p>
            <a href="superadmin/setup.php">Start one-time Super Admin setup →</a>
        <?php endif; ?>
        <a href="index.php">← Back to EDUTRACK</a>
    </main>
</body>
</html>
