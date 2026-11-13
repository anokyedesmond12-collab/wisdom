 <?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION["school_id"];
$message = "";
$message_type = "";

// Assign subject to class
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $class_id = (int)($_POST["class_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);

    if ($class_id <= 0 || $subject_id <= 0) {
        $message = "Please select both a class and a subject.";
        $message_type = "error";
    } else {
        try {
            // Confirm class belongs to this school
            $checkClass = $conn->prepare(
                "SELECT id FROM classes WHERE id = ? AND school_id = ?"
            );
            $checkClass->execute([$class_id, $school_id]);

            // Confirm subject belongs to this school
            $checkSubject = $conn->prepare(
                "SELECT id FROM subjects WHERE id = ? AND school_id = ?"
            );
            $checkSubject->execute([$subject_id, $school_id]);

            if (!$checkClass->fetch() || !$checkSubject->fetch()) {
                $message = "Invalid class or subject selected.";
                $message_type = "error";
            } else {
                $insert = $conn->prepare(
                    "INSERT INTO class_subjects
                    (school_id, class_id, subject_id)
                    VALUES (?, ?, ?)"
                );

                $insert->execute([
                    $school_id,
                    $class_id,
                    $subject_id
                ]);

                header("Location: assign_subjects.php?success=1");
                exit();
            }

        } catch (PDOException $e) {
            if ($e->getCode() == "23000") {
                $message = "This subject has already been assigned to that class.";
            } else {
                $message = "Unable to assign subject. Please try again.";
            }

            $message_type = "error";
        }
    }
}

if (isset($_GET["success"])) {
    $message = "Subject successfully assigned to class!";
    $message_type = "success";
}

// Get registered subjects
$stmt = $conn->prepare(
    "SELECT * FROM subjects
     WHERE school_id = ?
     ORDER BY subject_name ASC"
);
$stmt->execute([$school_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get registered classes
$stmt = $conn->prepare(
    "SELECT * FROM classes
     WHERE school_id = ?
     ORDER BY class_name ASC"
);
$stmt->execute([$school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned subjects
$stmt = $conn->prepare(
    "SELECT
        classes.class_name,
        subjects.subject_name
     FROM class_subjects
     INNER JOIN classes
        ON class_subjects.class_id = classes.id
     INNER JOIN subjects
        ON class_subjects.subject_id = subjects.id
     WHERE class_subjects.school_id = ?
     ORDER BY classes.class_name, subjects.subject_name"
);

$stmt->execute([$school_id]);
$assignedSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by class
$groupedSubjects = [];

foreach ($assignedSubjects as $item) {
    $groupedSubjects[$item["class_name"]][] = $item["subject_name"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Assign Subjects | EduTrack</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6fa;
    color: #172554;
}

.header {
    background: #102a56;
    color: white;
    padding: 25px 7%;
}

.header h2 {
    margin: 0;
}

.header p {
    color: #dbeafe;
    margin-bottom: 0;
}

.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.card h3 {
    color: #102a56;
    margin-top: 0;
}

.description {
    color: #64748b;
    margin-bottom: 25px;
}


 .form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 9px;
}

select {
    width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
    background: white;
}

select:focus {
    outline: none;
    border-color: #d4a017;
}

.submit-btn {
    background: #102a56;
    color: white;
    padding: 14px 25px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.submit-btn:hover {
    background: #1e40af;
}

.message {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.class-subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
}

.class-subject-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.class-subject-card h4 {
    background: #102a56;
    color: white;
    padding: 18px 20px;
    margin: 0;
    font-size: 18px;
}

.subject-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 20px;
}

.subject-item {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    padding: 10px 13px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: bold;
}

.empty {
    background: white;
    padding: 35px;
    text-align: center;
    color: #64748b;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
}

.back {
    display: inline-block;
    margin: 5px 0 30px;
    text-decoration: none;
    color: #102a56;
    font-weight: bold;
}

@media(max-width: 600px) {
    .card {
        padding: 20px;
    }

    .class-subject-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="header">

    <h2>EduTrack</h2>

    <p>Class Management / Assign Subjects</p>

</div>

<div class="container">

    <div class="card">

        <h3>Assign Subjects to Classes</h3>

        <p class="description">
            Select a class and assign a registered subject to it.
        </p>

        <?php if ($message != ""): ?>

            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <?php if (count($classes) > 0 && count($subjects) > 0): ?>

        <form method="POST" action="assign_subjects.php">

            <div class="form-group">

                <label>Select Class</label>

                <select name="class_id" required>

                    <option value="">Choose a Class</option>

                    <?php foreach ($classes as $class): ?>

                        <option value="<?php echo $class["id"]; ?>">
                            <?php echo htmlspecialchars($class["class_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Select Registered Subject</label>

                <select name="subject_id" required>

                    <option value="">Choose a Subject</option>

                    <?php foreach ($subjects as $subject): ?>

                        <option value="<?php echo $subject["id"]; ?>">
                            <?php echo htmlspecialchars($subject["subject_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button type="submit" class="submit-btn">
                + Assign Subject
            </button>

        </form>

        <?php elseif (count($classes) == 0): ?>

            <div class="empty">
                <h3>No Classes Registered</h3>
                <p>Create a class before assigning subjects.</p>
            </div>

        <?php else: ?>

            <div class="empty">
                <h3>No Subjects Registered</h3>
 <p>Register subjects before assigning them to classes.</p>
            </div>

        <?php endif; ?>

    </div>

    <div class="card">

        <h3>Assigned Subjects</h3>

        <p class="description">
            View the subjects currently offered by each class.
        </p>

        <?php if (count($groupedSubjects) > 0): ?>

            <div class="class-subject-grid">

                <?php foreach ($groupedSubjects as $className => $subjectNames): ?>

                    <div class="class-subject-card">

                        <h4>
                            <?php echo htmlspecialchars($className); ?>
                        </h4>

                        <div class="subject-list">

                            <?php foreach ($subjectNames as $subjectName): ?>

                                <span class="subject-item">
                                    <?php echo htmlspecialchars($subjectName); ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">
                <h3>No Subjects Assigned Yet</h3>
                <p>Assigned subjects will appear here.</p>
            </div>

        <?php endif; ?>

    </div>

    <a href="dashboard.php" class="back">
        ← Back to Main Page
    </a>

</div>

</body>
</html>