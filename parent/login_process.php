<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=Please+fill+in+all+fields");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM parents WHERE email = ?");
    $stmt->execute([$email]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($parent && password_verify($password, $parent['password'])) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['parent_id']   = $parent['id'];
        $_SESSION['role']        = 'parent';
        $_SESSION['parent_name'] = $parent['full_name'] ?? 'Parent';
        $_SESSION['parent_email'] = $parent['email'];
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

