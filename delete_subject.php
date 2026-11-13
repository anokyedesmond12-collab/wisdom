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
$subject_id = (int) ($_POST["subject_id"] ?? 0);

if ($subject_id <= 0) {
    die("Invalid subject.");
}

try {
    $conn->beginTransaction();

    // Remove all class assignments
    $stmt = $conn->prepare(
        "DELETE FROM class_subjects
         WHERE subject_id = ? AND school_id = ?"
    );
    $stmt->execute([$subject_id, $school_id]);

    // Delete the registered subject
    $stmt = $conn->prepare(
        "DELETE FROM subjects
         WHERE id = ? AND school_id = ?"
    );
    $stmt->execute([$subject_id, $school_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Subject not found.");
    }

    $conn->commit();

    header("Location: subject.php?deleted=1");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die("Unable to delete subject: " . htmlspecialchars($e->getMessage()));
}
?>