 <?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION["school_id"];

// Fetch registered subjects
$stmt = $conn->prepare(
    "SELECT * FROM subjects
     WHERE school_id = ?
     ORDER BY subject_name ASC"
);

$stmt->execute([$school_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Subject Management | EduTrack</title>

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

/* HEADER */

.header {
    background: #102a56;
    color: white;
    padding: 28px 7%;
}

.header h1 {
    margin: 0;
    font-size: 27px;
}

.header p {
    color: #dbeafe;
    margin-bottom: 0;
}

/* MAIN CONTAINER */

.container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

/* CARDS */

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.card h2 {
    color: #102a56;
    margin-top: 0;
}

.description {
    color: #64748b;
    margin-bottom: 25px;
}

/* FORM */

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 9px;
}

input {
    width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #d4a017;
}

.add-btn {
    background: #102a56;
    color: white;
    padding: 14px 25px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.add-btn:hover {
    background: #1e40af;
}

/* SUMMARY */

.summary {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    flex: 1;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.summary-card h3 {
    color: #d4a017;
    font-size: 30px;
    margin: 0 0 8px;
}

.summary-card p {
    color: #64748b;
    margin: 0;
}

/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #f8fafc;
    color: #102a56;
}

td {
    color: #334155;
}

/* ASSIGNED CLASS BADGES */

.class-badge {
    display: inline-block;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    padding: 8px 12px;
    border-radius: 7px;
    margin: 3px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.class-badge:hover {
    background: #dbeafe;
}

/* DELETE BUTTON */

.delete-btn {
    background: #dc2626;
    color: white;
    border: none;
    padding: 10px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.delete-btn:hover {
    background: #991b1b;
}

/* EMPTY STATE */

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}

/* BACK LINK */

.back {
    display: inline-block;
    margin: 5px 0 30px;
    text-decoration: none;
    color: #102a56;
    font-weight: bold;
}

@media(max-width: 650px) {

    .card {
        padding: 20px;
    }

    .summary {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<!-- HEADER -->

<div class="header">

    <h1>EduTrack</h1>

    <p>Subject Management / Manage School Subjects</p>

</div>


<div class="container">

    <!-- ADD SUBJECT -->

    <div class="card">

        <h2>Add New Subject</h2>

        <p class="description">
            Register a new subject for your school.
        </p>

        <form action="create_subject.php" method="POST">

            <div class="form-group">

                <label>Subject Name</label>

                <input
                    type="text"
                    name="subject_name"
[12/11/2026 3:16 am] Wisdom De Fame: placeholder="e.g. Mathematics"
                    required
                >

            </div>

            <div class="form-group">

                <label>Subject Code (Optional)</label>

                <input
                    type="text"
                    name="subject_code"
                    placeholder="e.g. MATH101"
                >

            </div>

            <button type="submit" class="add-btn">
                + Add Subject
            </button>

        </form>

    </div>


    <!-- SUBJECT SUMMARY -->

    <div class="summary">

        <div class="summary-card">

            <h3><?php echo count($subjects); ?></h3>

            <p>Total Registered Subjects</p>

        </div>

    </div>


    <!-- REGISTERED SUBJECTS -->

    <div class="card">

        <h2>Registered Subjects</h2>

        <p class="description">
            Manage subjects and see the classes offering each subject.
        </p>

        <?php if (!empty($subjects)): ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Assigned Classes</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php $number = 1; ?>

                <?php foreach ($subjects as $subject): ?>

                    <?php

                    // Get classes assigned to this subject
                    $classStmt = $conn->prepare(
                        "SELECT classes.id, classes.class_name
                         FROM class_subjects
                         INNER JOIN classes
                         ON class_subjects.class_id = classes.id
                         WHERE class_subjects.subject_id = ?
                         AND class_subjects.school_id = ?
                         AND classes.school_id = ?"
                    );

                    $classStmt->execute([
                        $subject["id"],
                        $school_id,
                        $school_id
                    ]);

                    $assignedClasses = $classStmt->fetchAll(PDO::FETCH_ASSOC);

                    ?>

                    <tr>

                        <td><?php echo $number++; ?></td>

                        <td>
                            <strong>
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($subject["subject_code"] ?? "N/A"); ?>
                        </td>

                        <td>

                            <?php if (empty($assignedClasses)): ?>

                                <span style="color:#94a3b8;">
                                    Not assigned
                                </span>

                            <?php else: ?>

                                <?php foreach ($assignedClasses as $class): ?>

                                    <form
                                        action="remove_subject_from_class.php"
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Remove this subject from this class?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="class_id"
                                            value="<?php echo $class['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="subject_id"
[12/11/2026 3:16 am] Wisdom De Fame: value="<?php echo $subject['id']; ?>"
                                        >

                                        <button type="submit" class="class-badge">

                                            <?php echo htmlspecialchars($class["class_name"]); ?> ×

                                        </button>

                                    </form>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </td>

                        <td>

                            <form
                                action="delete_subject.php"
                                method="POST"
                                onsubmit="return confirm('Permanently delete this subject?');"
                            >

                                <input
                                    type="hidden"
                                    name="subject_id"
                                    value="<?php echo $subject['id']; ?>"
                                >

                                <button type="submit" class="delete-btn">
                                    Delete Completely
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php else: ?>

            <div class="empty">

                <h3>No Subjects Registered</h3>

                <p>
                    Add your first subject using the form above.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <a href="dashboard.php" class="back">
        ← Back to Main Page
    </a>

</div>

</body>

</html>