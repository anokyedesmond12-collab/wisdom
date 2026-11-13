<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);

if ($email === '' || $password === '' || !$schoolId || $schoolId < 1) {
    header("Location: login.php?error=Select+your+school+and+enter+your+credentials");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$email, $schoolId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher && ($teacher['account_status'] ?? 'active') !== 'active') {
        $statusMessage = ($teacher['account_status'] ?? '') === 'declined'
            ? 'Your+registration+was+declined.+Contact+your+school+administrator'
            : 'Your+registration+is+pending+school+admin+approval';
        header("Location: login.php?error=" . $statusMessage);
        exit();
    }

    if ($teacher && password_verify($password, $teacher['password'])) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['teacher_id']   = $teacher['id'];
        $_SESSION['teacher_school_id'] = $teacher['school_id'];
        $_SESSION['role']         = 'teacher';
        $_SESSION['teacher_name'] = $teacher['full_name'] ?? $teacher['name'] ?? 'Teacher';
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=Invalid+email+or+password");
        exit();
    }
} catch (PDOException $e) {
    header("Location: login.php?error=A+database+error+occurred");
    exit();
}

