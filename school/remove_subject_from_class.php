<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$school_id = (int) $_SESSION["school_id"];
$class_id = (int) ($_POST["class_id"] ?? 0);
$subject_id = (int) ($_POST["subject_id"] ?? 0);

if ($class_id <= 0 || $subject_id <= 0) {
    die("Invalid class or subject.");
}

$sql = "DELETE FROM class_subjects
        WHERE school_id = ?
        AND class_id = ?
        AND subject_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$school_id, $class_id, $subject_id]);

header("Location: subject.php?removed=1");
exit();