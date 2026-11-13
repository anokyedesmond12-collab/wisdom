<?php
require_once __DIR__ . "/../config/superadmin_session.php";
startSuperAdminSession();
header("Cache-Control: no-store, private");

if (!empty($_SESSION["superadmin_authenticated"]) && ($_SESSION["role"] ?? "") === "superadmin") {
    header("Location: ../admin/dashboard.php");
    exit();
}
if (!empty($_SESSION["role"]) || !empty($_SESSION["superadmin_authenticated"])) {
    http_response_code(403);
    exit("Sign out of the current portal before opening Super Admin setup.");
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/private_settings.php";
require_once __DIR__ . "/../config/africastalking_sms.php";

function loadSuperAdminSetupState(PDO $conn)
{
    return $conn->query(
        "SELECT code_consumed_at, otp_hash, otp_expires_at, otp_sent_at,
                otp_window_started_at, otp_attempts, otp_send_count,
                pending_full_name, pending_email, pending_phone_number,
                pending_password_hash,
                (otp_expires_at IS NOT NULL AND otp_expires_at > NOW()) AS otp_is_active
         FROM superadmin_bootstrap_state
         WHERE singleton_id = 1"
    )->fetch(PDO::FETCH_ASSOC);
}

if (empty($_SESSION["superadmin_setup_csrf"])) {
    $_SESSION["superadmin_setup_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["superadmin_setup_csrf"];
$error = "";
$message = "";
$messageType = "";
$settingsError = false;
$settings = [];
try {
    $settings = edutrackPrivateSettings();
} catch (RuntimeException | ParseError $e) {
    error_log("Unable to load private Super Admin SMS settings: " . $e->getMessage());
    $settingsError = true;
}

$provider = $settings["africastalking"] ?? [];
$smsUsername = is_array($provider) ? trim((string)($provider["username"] ?? "")) : "";
$smsApiKey = is_array($provider) ? trim((string)($provider["api_key"] ?? "")) : "";
$ownerPhone = $settings["superadmin_owner_phone"] ?? "";
$ownerPhone = is_string($ownerPhone) ? trim($ownerPhone) : "";
$smsConfigurationMessage = "";
if ($settingsError) {
    $smsConfigurationMessage = "Private SMS configuration is unavailable. Contact the site operator.";
} elseif (preg_match('/^\+[1-9]\d{7,14}$/', $ownerPhone) !== 1) {
    $smsConfigurationMessage = "Configure the platform owner's phone in international format in the private configuration.";
} elseif ($smsUsername === "" || $smsApiKey === "") {
    $smsConfigurationMessage = "Add the Africa's Talking username and API key to the private configuration before requesting an SMS code.";
} elseif (!extension_loaded("curl")) {
    $smsConfigurationMessage = "Enable the PHP cURL extension to send SMS codes.";
}
$smsConfigured = $smsConfigurationMessage === "";
$argon2idAvailable = defined("PASSWORD_ARGON2ID");
$maxPasswordBytes = $argon2idAvailable ? 1024 : 72;
$accountCount = (int)$conn->query("SELECT COUNT(*) FROM superadmin_accounts")->fetchColumn();
$state = loadSuperAdminSetupState($conn);
$setupAvailable = $accountCount === 0 && $state && empty($state["code_consumed_at"]);
$otpPending = $setupAvailable
    && !empty($state["otp_hash"])
    && (int)$state["otp_is_active"] === 1
    && !empty($state["pending_email"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedCsrf = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";
    if (!is_string($postedCsrf) || !hash_equals($csrf, $postedCsrf)) {
        $error = "Your session expired. Reload the page and try again.";
    } elseif (!$setupAvailable) {
        $error = "Super Admin registration is unavailable or has already been completed.";
    } elseif (!is_string($action)) {
        $error = "Choose a valid registration action.";
    } elseif ($action === "send_otp" || $action === "resend_otp") {
        if (!$smsConfigured) {
            $error = $smsConfigurationMessage;
        } else {
            $fullName = "";
            $email = "";
            $passwordHash = "";

            if ($action === "send_otp") {
                $fullNameInput = $_POST["full_name"] ?? "";
                $emailInput = $_POST["email"] ?? "";
                $password = $_POST["password"] ?? "";
                $confirmPassword = $_POST["confirm_password"] ?? "";
                $fullName = is_string($fullNameInput) ? trim($fullNameInput) : "";
                $email = is_string($emailInput) ? strtolower(trim($emailInput)) : "";

                if ($fullName === "" || strlen($fullName) > 150) {
                    $error = "Enter your full name (up to 150 characters).";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    $error = "Enter a valid admin email address.";
                } elseif (!is_string($password) || strlen($password) < 12 || strlen($password) > $maxPasswordBytes) {
                    $error = "Choose a password between 12 and {$maxPasswordBytes} bytes.";
                } elseif (!is_string($confirmPassword) || $password !== $confirmPassword) {
                    $error = "The passwords do not match.";
                } else {
                    $hashAlgorithm = $argon2idAvailable ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                    $passwordHash = password_hash($password, $hashAlgorithm);
                    if (!$passwordHash) {
                        $error = "Unable to prepare the Super Admin account.";
                    }
                }
            } else {
                $fullName = is_string($state["pending_full_name"] ?? null) ? $state["pending_full_name"] : "";
                $email = is_string($state["pending_email"] ?? null) ? $state["pending_email"] : "";
                $passwordHash = is_string($state["pending_password_hash"] ?? null)
                    ? $state["pending_password_hash"]
                    : "";
                if ($fullName === "" || $email === "" || $passwordHash === "") {
                    $error = "There is no registration to resend. Enter your details to start again.";
                }
            }

            if ($error === "") {
                $otp = (string)random_int(100000, 999999);
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                if (!$otpHash) {
                    $error = "Unable to generate a verification code. Please try again.";
                } else {
                    $shouldSend = false;
                    try {
                        $conn->beginTransaction();
                        $lockedState = $conn->query(
                            "SELECT code_consumed_at, otp_sent_at, otp_window_started_at,
                                    otp_send_count, pending_full_name, pending_email,
                                    pending_phone_number, pending_password_hash,
                                    (otp_sent_at IS NOT NULL AND otp_sent_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)) AS cooldown_active,
                                    (otp_window_started_at IS NULL OR otp_window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS window_expired
                             FROM superadmin_bootstrap_state
                             WHERE singleton_id = 1
                             FOR UPDATE"
                        )->fetch(PDO::FETCH_ASSOC);
                        $lockedAccountCount = (int)$conn->query(
                            "SELECT COUNT(*) FROM superadmin_accounts"
                        )->fetchColumn();

                        if (!$lockedState || !empty($lockedState["code_consumed_at"]) || $lockedAccountCount > 0) {
                            throw new RuntimeException("setup_complete");
                        }
                        if ((int)$lockedState["cooldown_active"] === 1) {
                            throw new RuntimeException("otp_cooldown");
                        }
                        $windowExpired = (int)$lockedState["window_expired"] === 1;
                        $sendCount = $windowExpired ? 0 : (int)$lockedState["otp_send_count"];
                        if ($sendCount >= 3) {
                            throw new RuntimeException("otp_send_limit");
                        }

                        if ($action === "resend_otp") {
                            $fullName = (string)($lockedState["pending_full_name"] ?? "");
                            $email = (string)($lockedState["pending_email"] ?? "");
                            $passwordHash = (string)($lockedState["pending_password_hash"] ?? "");
                            if ($fullName === "" || $email === "" || $passwordHash === "") {
                                throw new RuntimeException("pending_registration_missing");
                            }
                        }

                        $updateSql =
                            "UPDATE superadmin_bootstrap_state
                             SET otp_hash = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                                 otp_sent_at = NOW(), otp_attempts = 0,
                                 pending_full_name = ?, pending_email = ?,
                                 pending_phone_number = ?, pending_password_hash = ?";
                        $updateSql .= $windowExpired
                            ? ", otp_window_started_at = NOW(), otp_send_count = 1"
                            : ", otp_send_count = otp_send_count + 1";
                        $updateSql .= " WHERE singleton_id = 1";
                        $update = $conn->prepare($updateSql);
                        $update->execute([$otpHash, $fullName, $email, $ownerPhone, $passwordHash]);
                        $conn->commit();
                        $shouldSend = true;
                    } catch (RuntimeException $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        $messages = [
                            "setup_complete" => "Super Admin registration has already been completed.",
                            "otp_cooldown" => "Wait 60 seconds before requesting another SMS code.",
                            "otp_send_limit" => "The hourly SMS-code limit has been reached. Try again later.",
                            "pending_registration_missing" => "There is no registration to resend. Enter your details to start again."
                        ];
                        $error = $messages[$e->getMessage()] ?? "Unable to prepare the SMS code. Please try again.";
                    } catch (PDOException $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        error_log("Unable to prepare Super Admin SMS verification: " . $e->getMessage());
                        $error = "Unable to prepare the SMS code. Please try again.";
                    }

                    if ($shouldSend) {
                        try {
                            sendAfricaTalkingSms(
                                $settings,
                                $ownerPhone,
                                "Your EduTrack Super Admin registration code is {$otp}. It expires in 5 minutes. Do not share it."
                            );
                            $message = "Verification SMS accepted. Enter the six-digit code sent to the platform owner's phone.";
                            $messageType = "success";
                        } catch (RuntimeException $e) {
                            $error = $e->getMessage() === "sms_not_configured"
                                ? $smsConfigurationMessage
                                : "SMS delivery could not be confirmed. If you received a code, enter it below; otherwise wait 60 seconds and request another.";
                        }
                    }
                }
            }
        }
    } elseif ($action === "verify_otp") {
        $otp = $_POST["otp"] ?? "";
        if (!is_string($otp) || !preg_match('/^\d{6}$/', $otp)) {
            $error = "Enter the six-digit code sent to the owner phone.";
        } else {
            try {
                $conn->beginTransaction();
                $lockedState = $conn->query(
                    "SELECT code_consumed_at, otp_hash, otp_attempts,
                            pending_full_name, pending_email, pending_phone_number,
                            pending_password_hash,
                            (otp_expires_at IS NOT NULL AND otp_expires_at > NOW()) AS otp_is_active
                     FROM superadmin_bootstrap_state
                     WHERE singleton_id = 1
                     FOR UPDATE"
                )->fetch(PDO::FETCH_ASSOC);
                $lockedAccountCount = (int)$conn->query(
                    "SELECT COUNT(*) FROM superadmin_accounts"
                )->fetchColumn();

                if (!$lockedState || !empty($lockedState["code_consumed_at"]) || $lockedAccountCount > 0) {
                    throw new RuntimeException("setup_complete");
                }
                if ((int)$lockedState["otp_attempts"] >= 5) {
                    throw new RuntimeException("otp_locked");
                }
                if (
                    empty($lockedState["otp_hash"]) ||
                    (int)$lockedState["otp_is_active"] !== 1 ||
                    empty($lockedState["pending_password_hash"])
                ) {
                    throw new RuntimeException("otp_expired");
                }

                if (!password_verify($otp, $lockedState["otp_hash"])) {
                    $attempts = (int)$lockedState["otp_attempts"] + 1;
                    if ($attempts >= 5) {
                        $clear = $conn->prepare(
                            "UPDATE superadmin_bootstrap_state
                             SET otp_hash = NULL, otp_expires_at = NULL, otp_attempts = ?,
                                 pending_full_name = NULL, pending_email = NULL,
                                 pending_phone_number = NULL, pending_password_hash = NULL
                             WHERE singleton_id = 1"
                        );
                        $clear->execute([$attempts]);
                        $error = "Too many incorrect codes. Request a new code after the 60-second cooldown.";
                    } else {
                        $attempt = $conn->prepare(
                            "UPDATE superadmin_bootstrap_state SET otp_attempts = ? WHERE singleton_id = 1"
                        );
                        $attempt->execute([$attempts]);
                        $error = "The verification code is incorrect.";
                    }
                    $conn->commit();
                } else {
                    $fullName = (string)($lockedState["pending_full_name"] ?? "");
                    $email = (string)($lockedState["pending_email"] ?? "");
                    $phone = (string)($lockedState["pending_phone_number"] ?? "");
                    $passwordHash = (string)($lockedState["pending_password_hash"] ?? "");
                    if (
                        $fullName === "" ||
                        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                        $phone === "" ||
                        $phone !== $ownerPhone ||
                        $passwordHash === ""
                    ) {
                        throw new RuntimeException("pending_registration_invalid");
                    }

                    $create = $conn->prepare(
                        "INSERT INTO superadmin_accounts
                         (id, full_name, email, phone_number, password_hash,
                          email_verified_at, last_email_verified_at)
                         VALUES (1, ?, ?, ?, ?, NULL, NULL)"
                    );
                    $create->execute([$fullName, $email, $phone, $passwordHash]);

                    $consume = $conn->prepare(
                        "UPDATE superadmin_bootstrap_state
                         SET code_consumed_at = NOW(), otp_hash = NULL,
                             otp_expires_at = NULL, otp_attempts = 0,
                             pending_full_name = NULL, pending_email = NULL,
                             pending_phone_number = NULL, pending_password_hash = NULL
                         WHERE singleton_id = 1 AND code_consumed_at IS NULL"
                    );
                    $consume->execute();
                    if ($consume->rowCount() !== 1) {
                        throw new RuntimeException("setup_complete");
                    }
                    $conn->commit();
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION["superadmin_authenticated"] = true;
                    $_SESSION["superadmin_2fa_verified"] = true;
                    $_SESSION["superadmin_id"] = 1;
                    $_SESSION["role"] = "superadmin";
                    $_SESSION["superadmin_name"] = $fullName ?: "Super Admin";
                    $_SESSION["superadmin_last_activity"] = time();
                    $_SESSION["superadmin_csrf"] = bin2hex(random_bytes(32));
                    header("Location: ../admin/dashboard.php");
                    exit();
                }
            } catch (RuntimeException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $messages = [
                    "setup_complete" => "Super Admin registration has already been completed.",
                    "otp_locked" => "Too many incorrect codes. Request a new code after the 60-second cooldown.",
                    "otp_expired" => "The code has expired. Request a new SMS code.",
                    "pending_registration_invalid" => "The pending registration is no longer valid. Start again."
                ];
                $error = $messages[$e->getMessage()] ?? "Unable to complete registration. Please try again.";
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Unable to create the first Super Admin account: " . $e->getMessage());
                $error = "Unable to complete registration. Please try again.";
            }
        }
    } else {
        $error = "Choose a valid registration action.";
    }

    $accountCount = (int)$conn->query("SELECT COUNT(*) FROM superadmin_accounts")->fetchColumn();
    $state = loadSuperAdminSetupState($conn);
    $setupAvailable = $accountCount === 0 && $state && empty($state["code_consumed_at"]);
    $otpPending = $setupAvailable
        && !empty($state["otp_hash"])
        && (int)$state["otp_is_active"] === 1
        && !empty($state["pending_email"]);
}

if ($setupAvailable && !$smsConfigured && $error === "") {
    $error = $smsConfigurationMessage;
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["return_to_login"] ?? "") === "1" &&
    in_array($_POST["action"] ?? "", ["verify_otp", "resend_otp"], true) &&
    ($error !== "" || $message !== "")
) {
    $_SESSION["superadmin_setup_login_notice"] = [
        "type" => $error !== "" ? "error" : "success",
        "message" => $error !== "" ? $error : $message
    ];
    header("Location: ../login_superadmin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Registration | EDUTRACK</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6fa;color:#17243b;font-family:Arial,sans-serif}
        .setup-card{width:min(480px,calc(100% - 40px));padding:30px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 28px rgba(16,38,75,.08)}
        h1{margin:0 0 8px;color:#10264b;font-size:24px}p{color:#64748b;font-size:14px;line-height:1.5}
        label{display:block;margin-top:16px;font-size:13px;font-weight:bold}
        input{width:100%;margin-top:7px;padding:11px;border:1px solid #cbd5e1;border-radius:7px;font-size:14px}
        button{width:100%;margin-top:18px;padding:12px;border:0;border-radius:7px;background:#10264b;color:white;font-weight:bold;cursor:pointer}
        .error,.success,.notice{padding:11px;border-radius:7px;font-size:13px;margin:12px 0}.error{background:#fee2e2;color:#991b1b}.success{background:#ecfdf5;color:#166534}.notice{background:#eff6ff;color:#1e3a8a}
        a{display:inline-block;margin-top:18px;color:#183d70;font-size:13px}
    </style>
</head>
<body>
    <main class="setup-card">
        <h1>Super Admin Registration</h1>
        <p>Only one platform Super Admin can register. A short-lived SMS code is sent only to the owner's phone configured privately.</p>
        <?php if ($message !== ""): ?><div class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error !== ""): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($setupAvailable && $otpPending): ?>
            <p>Enter the six-digit verification code to finish registering <?= htmlspecialchars($state["pending_email"]) ?>.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="verify_otp">
                <label for="otp">SMS verification code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                <button type="submit">Verify Code & Create Account</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit">Resend SMS Code</button>
            </form>
        <?php elseif ($setupAvailable): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="send_otp">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" maxlength="150" autocomplete="name" required>
                <label for="email">Admin email</label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required>
                <label for="password">Create password (12–<?= $maxPasswordBytes ?> bytes)</label>
                <input id="password" name="password" type="password" minlength="12" maxlength="<?= $maxPasswordBytes ?>" autocomplete="new-password" required>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="<?= $maxPasswordBytes ?>" autocomplete="new-password" required>
                <button type="submit">Send Owner SMS Code</button>
            </form>
        <?php else: ?>
            <div class="notice">Super Admin registration is already complete. Sign in with the registered email and password.</div>
        <?php endif; ?>
        <a href="../login_superadmin.php">Go to Super Admin login →</a>
        <br><a href="../index.php">← Back to EDUTRACK</a>
    </main>
</body>
</html>
