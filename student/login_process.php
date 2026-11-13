<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$studentId = trim($_POST['student_id'] ?? '');
$schoolId  = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT);
$password  = $_POST['password'] ?? '';

if (empty($studentId) || !$schoolId || empty($password)) {
    header("Location: login.php?error=Please+fill+in+all+fields");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? AND school_id = ? AND password IS NOT NULL");
    $stmt->execute([$studentId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student && password_verify($password, $student['password'])) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['student_id']   = $student['id'];
        $_SESSION['role']         = 'student';
        $_SESSION['student_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
        $_SESSION['student_school_id'] = $student['school_id'];
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=Invalid+credentials");
        exit();
    }
} catch (PDOException $e) {
    header("Location: login.php?error=A+database+error+occurred");
    exit();
}

