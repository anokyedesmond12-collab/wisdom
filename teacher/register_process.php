<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

$csrf = $_POST["csrf_token"] ?? "";
if (
    !is_string($csrf) ||
    empty($_SESSION["teacher_registration_csrf"]) ||
    !hash_equals($_SESSION["teacher_registration_csrf"], $csrf)
) {
    header("Location: register.php?error=Registration+session+expired");
    exit();
}

$schoolId = filter_var($_POST["school_id"] ?? null, FILTER_VALIDATE_INT);
$name = trim($_POST["full_name"] ?? "");
$email = strtolower(trim($_POST["email"] ?? ""));
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if (!$schoolId || $schoolId < 1 || $name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=Enter+valid+school+and+account+details");
    exit();
}
if (!is_string($password) || strlen($password) < 8) {
    header("Location: register.php?error=Choose+a+password+with+at+least+8+characters");
    exit();
}
if (!is_string($confirmPassword) || $password !== $confirmPassword) {
    header("Location: register.php?error=The+passwords+do+not+match");
    exit();
}

$school = $conn->prepare("SELECT id FROM schools WHERE id = ? LIMIT 1");
$school->execute([$schoolId]);
if (!$school->fetchColumn()) {
    header("Location: register.php?error=Select+a+valid+school");
    exit();
}

try {
    $existing = $conn->prepare(
        "SELECT account_status FROM teachers WHERE school_id = ? AND email = ? LIMIT 1"
    );
    $existing->execute([$schoolId, $email]);
    $existingStatus = $existing->fetchColumn();
    if ($existingStatus !== false) {
        if ($existingStatus === "pending") {
            $message = "A+registration+for+this+email+is+already+pending";
        } elseif ($existingStatus === "declined") {
            $message = "This+registration+was+declined.+Contact+your+school+administrator";
        } else {
            $message = "An+account+already+exists.+Contact+your+school+administrator";
        }
        header("Location: register.php?error=" . $message);
        exit();
    }

    $insert = $conn->prepare(
        "INSERT INTO teachers (school_id, full_name, email, password, account_status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $insert->execute([$schoolId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    unset($_SESSION["teacher_registration_csrf"]);
    header("Location: register.php?submitted=1");
    exit();
} catch (PDOException $e) {
    if ($e->getCode() === "23000") {
        header("Location: register.php?error=An+account+already+exists+for+this+school+email");
        exit();
    }
    error_log("Teacher self-registration failed: " . $e->getMessage());
    header("Location: register.php?error=Unable+to+submit+registration");
    exit();
}
