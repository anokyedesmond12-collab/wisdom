<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if (empty($email) || empty($password)) {
    die("Please enter your email and password.");
}

$stmt = $conn->prepare("SELECT * FROM schools WHERE email = ?");
$stmt->execute([$email]);

$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school || !password_verify($password, $school["password"])) {
    die("Invalid email or password.");
}

// Log the school in immediately
session_regenerate_id(true);
$_SESSION = [];

$_SESSION["role"] = "school_admin";
$_SESSION["school_id"] = $school["id"];
$_SESSION["school_name"] = $school["school_name"];
$_SESSION["admin_name"] = $school["admin_name"];
$_SESSION["school_email"] = $school["email"];

header("Location: dashboard.php");
exit();
?>