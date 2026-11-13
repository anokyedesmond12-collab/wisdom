<?php
session_start();

require_once "../config/database.php";

// Check school login
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int) $_SESSION["school_id"];

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: academic_terms.php");
    exit();
}

// Get selected term ID
$term_id = (int) ($_POST["term_id"] ?? 0);

if ($term_id <= 0) {
    die("Invalid academic term.");
}

try {

    // Confirm that the term belongs to this school
    $stmt = $conn->prepare("
        SELECT id, academic_year_id
        FROM academic_terms
        WHERE id = ?
        AND school_id = ?
    ");

    $stmt->execute([$term_id, $school_id]);

    $term = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$term) {
        die("Academic term not found.");
    }

    $academic_year_id = $term["academic_year_id"];

    // Start transaction
    $conn->beginTransaction();

    // Deactivate other terms in the same academic year
    $stmt = $conn->prepare("
        UPDATE academic_terms
        SET status = 'Inactive'
        WHERE school_id = ?
        AND academic_year_id = ?
    ");

    $stmt->execute([$school_id, $academic_year_id]);

    // Activate selected term
    $stmt = $conn->prepare("
        UPDATE academic_terms
        SET status = 'Active'
        WHERE id = ?
        AND school_id = ?
    ");

    $stmt->execute([$term_id, $school_id]);

    $conn->commit();

    header("Location: academic_terms.php?activated=1");
    exit();

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die("Error activating academic term: " . $e->getMessage());
}
?>