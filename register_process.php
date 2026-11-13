<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/mailer.php";

function registrationRedirect($role, $message, $verify = false)
{
    $_SESSION["portal_registration_error"] = $message;
    header("Location: ../" . $role . "/register.php" . ($verify ? "?verify=1" : ""));
    exit();
}

function issueRegistrationCode($conn, $role, $email, $sessionHash, $schoolId, $admission, $passwordHash)
{
    $priorStmt = $conn->prepare(
        "SELECT request_count, request_window_started_at, last_sent_at
         FROM portal_email_verifications
         WHERE role = ? AND email = ?
         LIMIT 1"
    );
    $priorStmt->execute([$role, $email]);
    $prior = $priorStmt->fetch(PDO::FETCH_ASSOC);
    $now = time();

    if ($prior) {
        if ($now - strtotime($prior["last_sent_at"]) < 60) {
            throw new RuntimeException("Wait one minute before requesting another verification code.");
        }
        if (
            $now - strtotime($prior["request_window_started_at"]) < 3600 &&
            (int)$prior["request_count"] >= 5
        ) {
            throw new RuntimeException("Too many codes were requested. Try again in an hour.");
        }
    }

    $code = (string)random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    if (!$codeHash) {
        throw new RuntimeException("Unable to create a verification code.");
    }

    if ($prior) {
        $save = $conn->prepare(
            "UPDATE portal_email_verifications
             SET session_hash = ?, school_id = ?, student_admission = ?,
                 password_hash = ?, code_hash = ?, attempt_count = 0,
                 request_count = IF(request_window_started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, request_count + 1),
                 request_window_started_at = IF(request_window_started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), request_window_started_at),
                 expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                 last_sent_at = NOW()
             WHERE role = ? AND email = ?"
        );
        $save->execute([
            $sessionHash,
            $schoolId,
            $admission,
            $passwordHash,
            $codeHash,
            $role,
            $email
        ]);
    } else {
        $save = $conn->prepare(
            "INSERT INTO portal_email_verifications
             (role, email, session_hash, school_id, student_admission, password_hash,
              code_hash, request_count, request_window_started_at, expires_at, last_sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())"
        );
        $save->execute([
            $role,
            $email,
            $sessionHash,
            $schoolId,
            $admission,
            $passwordHash,
            $codeHash
        ]);
    }

    try {
        edutrackSendVerificationCode($email, $code, ucfirst($role) . " portal registration");
    } catch (Throwable $e) {
        $remove = $conn->prepare(
            "DELETE FROM portal_email_verifications
             WHERE role = ? AND email = ? AND session_hash = ? AND code_hash = ?"
        );
        $remove->execute([$role, $email, $sessionHash, $codeHash]);
        error_log("EduTrack registration email delivery failed: " . $e->getMessage());
        throw new RuntimeException("Could not send the verification email. Check the private SMTP configuration.");
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

$role = $_POST["role"] ?? "";
if (!in_array($role, ["student", "parent"], true)) {
    http_response_code(400);
    exit("Invalid registration role.");
}

$csrf = $_POST["csrf_token"] ?? "";
if (
    !is_string($csrf) ||
    empty($_SESSION["portal_registration_csrf"]) ||
    !hash_equals($_SESSION["portal_registration_csrf"], $csrf)
) {
    registrationRedirect($role, "Your registration session expired. Reload the page and try again.");
}

$action = $_POST["action"] ?? "request_code";
$sessionHash = hash("sha256", session_id());

if ($action === "request_code") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $schoolId = null;
    $admission = null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        registrationRedirect($role, "Enter a valid email address.");
    }
    if (!is_string($password) || strlen($password) < 8) {
        registrationRedirect($role, "Choose a password with at least 8 characters.");
    }
    if (!is_string($confirmPassword) || $password !== $confirmPassword) {
        registrationRedirect($role, "The passwords do not match.");
    }

    if ($role === "student") {
        $schoolId = filter_var($_POST["school_id"] ?? null, FILTER_VALIDATE_INT);
        $admission = trim($_POST["student_id"] ?? "");
        if (!$schoolId || $schoolId < 1 || $admission === "" || strlen($admission) > 50) {
            registrationRedirect($role, "Select your school and enter your admission number.");
        }
        $record = $conn->prepare(
            "SELECT id FROM students
             WHERE school_id = ? AND student_id = ? AND LOWER(email) = ?
             LIMIT 1"
        );
        $record->execute([$schoolId, $admission, $email]);
        if (!$record->fetchColumn()) {
            registrationRedirect($role, "No school record matches that school, admission number, and email.");
        }
    } else {
        $record = $conn->prepare(
            "SELECT id FROM students WHERE LOWER(parent_email) = ? LIMIT 1"
        );
        $record->execute([$email]);
        if (!$record->fetchColumn()) {
            registrationRedirect($role, "This email is not linked to a student record. Ask the school to update it.");
        }
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!$passwordHash) {
        error_log("Unable to hash an EduTrack portal registration password.");
        registrationRedirect($role, "Unable to prepare account registration. Please try again.");
    }

    try {
        issueRegistrationCode($conn, $role, $email, $sessionHash, $schoolId, $admission, $passwordHash);
    } catch (RuntimeException $e) {
        registrationRedirect($role, $e->getMessage());
    } catch (PDOException $e) {
        error_log("EduTrack registration challenge could not be saved: " . $e->getMessage());
        registrationRedirect($role, "Unable to start registration. Please try again.");
    }

    $_SESSION["portal_registration_pending_role"] = $role;
    $_SESSION["portal_registration_pending_email"] = $email;
    registrationRedirect($role, "A six-digit code was sent to your email. It expires in 10 minutes.", true);
}

$pendingRole = $_SESSION["portal_registration_pending_role"] ?? "";
$email = $_SESSION["portal_registration_pending_email"] ?? "";
if ($pendingRole !== $role || !is_string($email) || $email === "") {
    registrationRedirect($role, "Start registration again to request a verification code.");
}

if ($action === "resend_code") {
    $challenge = $conn->prepare(
        "SELECT school_id, student_admission, password_hash
         FROM portal_email_verifications
         WHERE role = ? AND email = ? AND session_hash = ?
         LIMIT 1"
    );
    $challenge->execute([$role, $email, $sessionHash]);
    $registration = $challenge->fetch(PDO::FETCH_ASSOC);
    if (!$registration) {
        registrationRedirect($role, "Your verification session expired. Start registration again.");
    }

    try {
        issueRegistrationCode(
            $conn,
            $role,
            $email,
            $sessionHash,
            $registration["school_id"],
            $registration["student_admission"],
            $registration["password_hash"]
        );
    } catch (RuntimeException $e) {
        registrationRedirect($role, $e->getMessage(), true);
    } catch (PDOException $e) {
        error_log("EduTrack registration code resend failed: " . $e->getMessage());
        registrationRedirect($role, "Unable to resend the code. Please try again.", true);
    }
    registrationRedirect($role, "A new verification code was sent.", true);
}

if ($action !== "verify_code") {
    registrationRedirect($role, "Invalid registration action.", true);
}

$code = trim($_POST["verification_code"] ?? "");
if (!preg_match('/^\d{6}$/', $code)) {
    registrationRedirect($role, "Enter the six-digit verification code.", true);
}

$challengeStmt = $conn->prepare(
    "SELECT id, school_id, student_admission, password_hash, code_hash,
            attempt_count, expires_at
     FROM portal_email_verifications
     WHERE role = ? AND email = ? AND session_hash = ?
     LIMIT 1"
);
$challengeStmt->execute([$role, $email, $sessionHash]);
$challenge = $challengeStmt->fetch(PDO::FETCH_ASSOC);
if (!$challenge || strtotime($challenge["expires_at"]) < time()) {
    registrationRedirect($role, "The verification code expired. Start registration again.", true);
}
if ((int)$challenge["attempt_count"] >= 5) {
    $delete = $conn->prepare("DELETE FROM portal_email_verifications WHERE id = ?");
    $delete->execute([(int)$challenge["id"]]);
    registrationRedirect($role, "Too many incorrect codes. Start registration again.", true);
}
if (!password_verify($code, $challenge["code_hash"])) {
    $attempts = (int)$challenge["attempt_count"] + 1;
    if ($attempts >= 5) {
        $delete = $conn->prepare("DELETE FROM portal_email_verifications WHERE id = ?");
        $delete->execute([(int)$challenge["id"]]);
        registrationRedirect($role, "Too many incorrect codes. Start registration again.", true);
    }
    $update = $conn->prepare("UPDATE portal_email_verifications SET attempt_count = ? WHERE id = ?");
    $update->execute([$attempts, (int)$challenge["id"]]);
    registrationRedirect($role, "The verification code is incorrect.", true);
}

try {
    $conn->beginTransaction();
    if ($role === "student") {
        $student = $conn->prepare(
            "SELECT id, school_id, first_name, last_name
             FROM students
             WHERE school_id = ? AND student_id = ? AND LOWER(email) = ?
             LIMIT 1
             FOR UPDATE"
        );
        $student->execute([(int)$challenge["school_id"], $challenge["student_admission"], $email]);
        $record = $student->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            throw new RuntimeException("The student record changed. Contact your school.");
        }
        $update = $conn->prepare(
            "UPDATE students SET password = ?, email_verified_at = NOW()
             WHERE id = ? AND school_id = ?"
        );
        $update->execute([$challenge["password_hash"], (int)$record["id"], (int)$record["school_id"]]);
        $identity = [
            "id" => (int)$record["id"],
            "school_id" => (int)$record["school_id"],
            "name" => trim($record["first_name"] . " " . $record["last_name"])
        ];
    } else {
        $linkedStudent = $conn->prepare(
            "SELECT parent_name, parent_phone
             FROM students
             WHERE LOWER(parent_email) = ?
             ORDER BY id
             LIMIT 1"
        );
        $linkedStudent->execute([$email]);
        $parentRecord = $linkedStudent->fetch(PDO::FETCH_ASSOC);
        if (!$parentRecord) {
            throw new RuntimeException("The parent email is no longer linked to a student.");
        }

        $existing = $conn->prepare("SELECT id FROM parents WHERE email = ? LIMIT 1 FOR UPDATE");
        $existing->execute([$email]);
        $parentId = (int)$existing->fetchColumn();
        if ($parentId) {
            $update = $conn->prepare(
                "UPDATE parents SET password = ?, email_verified_at = NOW() WHERE id = ?"
            );
            $update->execute([$challenge["password_hash"], $parentId]);
        } else {
            $insert = $conn->prepare(
                "INSERT INTO parents (full_name, email, phone, password, email_verified_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $insert->execute([
                $parentRecord["parent_name"] ?: "Parent",
                $email,
                $parentRecord["parent_phone"],
                $challenge["password_hash"]
            ]);
            $parentId = (int)$conn->lastInsertId();
        }
        $identity = [
            "id" => $parentId,
            "name" => $parentRecord["parent_name"] ?: "Parent"
        ];
    }

    $delete = $conn->prepare("DELETE FROM portal_email_verifications WHERE id = ?");
    $delete->execute([(int)$challenge["id"]]);
    $conn->commit();
} catch (RuntimeException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    registrationRedirect($role, $e->getMessage(), true);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Unable to complete EduTrack portal registration: " . $e->getMessage());
    registrationRedirect($role, "Unable to finish registration. Please try again.", true);
}

session_regenerate_id(true);
$_SESSION = [];
$_SESSION["role"] = $role;
if ($role === "student") {
    $_SESSION["student_id"] = $identity["id"];
    $_SESSION["student_school_id"] = $identity["school_id"];
    $_SESSION["student_name"] = $identity["name"];
    header("Location: ../student/dashboard.php");
} else {
    $_SESSION["parent_id"] = $identity["id"];
    $_SESSION["parent_name"] = $identity["name"];
    $_SESSION["parent_email"] = $email;
    header("Location: ../parent/dashboard.php");
}
exit();
