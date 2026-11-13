<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: classes.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $school_id = $_SESSION["school_id"];

    $class_name = trim($_POST["class_name"]);
    $class_level = $_POST["class_level"];

    if (!in_array($class_level, ["Primary", "JHS", "SHS"])) {
        die("Invalid class level.");
    }

    if ($class_name == "") {
        die("Please enter a class name.");
    }

    try {

        $sql = "INSERT INTO classes
                (school_id, class_name, class_level)
                VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $school_id,
            $class_name,
            $class_level
        ]);

        header("Location: classes.php");
        exit();

    } catch (PDOException $e) {

        if ($e->getCode() == "23000") {
            die("This class has already been registered.");
        }

        die("Unable to create class.");

    }

} else {

    die("Invalid request.");

}

?>