<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $school_id = $_SESSION["school_id"];
    $class_id = $_POST["class_id"] ?? "";
    $subject_id = $_POST["subject_id"] ?? "";

    if (!filter_var($class_id, FILTER_VALIDATE_INT) ||
        !filter_var($subject_id, FILTER_VALIDATE_INT)) {
        die("Please select a valid class and subject.");
    }

    // Confirm that the class belongs to this school
    $checkClass = $conn->prepare(
        "SELECT id FROM classes WHERE id = ? AND school_id = ?"
    );

    $checkClass->execute([$class_id, $school_id]);

    if (!$checkClass->fetch()) {
        die("Invalid class selected.");
    }

    // Confirm that the subject belongs to this school
    $checkSubject = $conn->prepare(
        "SELECT id FROM subjects WHERE id = ? AND school_id = ?"
    );

    $checkSubject->execute([$subject_id, $school_id]);

    if (!$checkSubject->fetch()) {
        die("Invalid subject selected.");
    }

    // Check whether the subject is already assigned
    $checkAssignment = $conn->prepare(
        "SELECT id FROM class_subjects
         WHERE school_id = ? AND class_id = ? AND subject_id = ?"
    );

    $checkAssignment->execute([
        $school_id,
        $class_id,
        $subject_id
    ]);

    if ($checkAssignment->fetch()) {
        header("Location: assign_subjects.php?error=exists");
        exit();
    }

    // Assign the registered subject to the class
    $assign = $conn->prepare(
        "INSERT INTO class_subjects
         (school_id, class_id, subject_id)
         VALUES (?, ?, ?)"
    );

    $assign->execute([
        $school_id,
        $class_id,
        $subject_id
    ]);

    header("Location: assign_subjects.php?success=1");
    exit();

} else {
    die("Invalid request.");
}

?>