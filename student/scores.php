 <?php
session_start();

require_once "../config/database.php";

// Protect the page
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: ../school/login.php");
    exit();
}

$school_id = (int) $_SESSION["school_id"];
http_response_code(403);
exit("Score entry has moved to the teacher portal. Contact your school administrator to assign teachers.");
$message = "";
$message_type = "";

// SAVE STUDENT SCORES
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $class_id = (int)($_POST["class_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $year_id = (int)($_POST["academic_year_id"] ?? 0);
    $term_id = (int)($_POST["academic_term_id"] ?? 0);

    $classRaw = $_POST["class_score"] ?? "";
    $examRaw = $_POST["exam_score"] ?? "";

    if (
        $student_id <= 0 ||
        $class_id <= 0 ||
        $subject_id <= 0 ||
        $year_id <= 0 ||
        $term_id <= 0 ||
        !is_numeric($classRaw) ||
        !is_numeric($examRaw)
    ) {

        $message = "Please complete all fields correctly.";
        $message_type = "error";

    } else {

        $class_score = (float)$classRaw;
        $exam_score = (float)$examRaw;

        if (
            $class_score < 0 ||
            $class_score > 40 ||
            $exam_score < 0 ||
            $exam_score > 60
        ) {

            $message = "Class score must be between 0 and 40. Examination score must be between 0 and 60.";
            $message_type = "error";

        } else {

            // Check student
            $studentCheck = $conn->prepare(
                "SELECT id FROM students
                 WHERE id = ? AND class_id = ? AND school_id = ?"
            );

            $studentCheck->execute([
                $student_id,
                $class_id,
                $school_id
            ]);

            // Check assigned subject
            $subjectCheck = $conn->prepare(
                "SELECT id FROM class_subjects
                 WHERE class_id = ? AND subject_id = ? AND school_id = ?"
            );

            $subjectCheck->execute([
                $class_id,
                $subject_id,
                $school_id
            ]);

            // Check academic year
            $yearCheck = $conn->prepare(
                "SELECT id FROM academic_years
                 WHERE id = ? AND school_id = ?"
            );

            $yearCheck->execute([$year_id, $school_id]);

            // Check academic term
            $termCheck = $conn->prepare(
                "SELECT id FROM academic_terms
                 WHERE id = ?
                 AND academic_year_id = ?
                 AND school_id = ?"
            );

            $termCheck->execute([
                $term_id,
                $year_id,
                $school_id
            ]);

            if (
                !$studentCheck->fetch() ||
                !$subjectCheck->fetch() ||
                !$yearCheck->fetch() ||
                !$termCheck->fetch()
            ) {

                $message = "Invalid selection. Check the student, class, subject, academic year and term.";
                $message_type = "error";

            } else {

                $total = $class_score + $exam_score;

                // GRADING SYSTEM
                if ($total >= 80) {
                    $grade = "A";
                    $remarks = "Excellent";
                } elseif ($total >= 70) {
                    $grade = "B";
                    $remarks = "Very Good";
                } elseif ($total >= 60) {
                    $grade = "C";
                    $remarks = "Good";
                } elseif ($total >= 50) {
                    $grade = "D";
                    $remarks = "Credit";
                } elseif ($total >= 40) {
                    $grade = "E";
                    $remarks = "Pass";
                } else {
                    $grade = "F";
                    $remarks = "Fail";
                }

                try {

                    $stmt = $conn->prepare(
                        "INSERT INTO student_scores
                        (school_id, student_id, class_id, subject_id,
academic_year_id, academic_term_id,
                         class_score, exam_score, total_score, grade, remarks)

                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

                        ON DUPLICATE KEY UPDATE
                        class_score = VALUES(class_score),
                        exam_score = VALUES(exam_score),
                        total_score = VALUES(total_score),
                        grade = VALUES(grade),
                        remarks = VALUES(remarks)"
                    );

                    $stmt->execute([
                        $school_id,
                        $student_id,
                        $class_id,
                        $subject_id,
                        $year_id,
                        $term_id,
                        $class_score,
                        $exam_score,
                        $total,
                        $grade,
                        $remarks
                    ]);

                    header("Location: scores.php?saved=1");
                    exit();

                } catch (PDOException $e) {

                    $message = "Unable to save scores. Please check your database configuration.";
                    $message_type = "error";
                }
            }
        }
    }
}

// SUCCESS MESSAGE
if (isset($_GET["saved"])) {
    $message = "Student scores have been saved successfully!";
    $message_type = "success";
}

// GET CLASSES
$stmt = $conn->prepare(
    "SELECT id, class_name
     FROM classes
     WHERE school_id = ?
     ORDER BY class_name"
);

$stmt->execute([$school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET STUDENTS
$stmt = $conn->prepare(
    "SELECT id, student_id, first_name, last_name, class_id
     FROM students
     WHERE school_id = ?
     ORDER BY first_name, last_name"
);

$stmt->execute([$school_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET ASSIGNED SUBJECTS
$stmt = $conn->prepare(
    "SELECT DISTINCT s.id, s.subject_name, cs.class_id
     FROM subjects s
     INNER JOIN class_subjects cs ON cs.subject_id = s.id
     WHERE s.school_id = ?
     AND cs.school_id = ?
     ORDER BY s.subject_name"
);

$stmt->execute([$school_id, $school_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET ACADEMIC YEARS
$stmt = $conn->prepare(
    "SELECT id, year_name
     FROM academic_years
     WHERE school_id = ?
     ORDER BY id DESC"
);

$stmt->execute([$school_id]);
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET ACADEMIC TERMS
$stmt = $conn->prepare(
    "SELECT id, term_name, academic_year_id
     FROM academic_terms
     WHERE school_id = ?
     ORDER BY id DESC"
);

$stmt->execute([$school_id]);
$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>EduTrack | Student Score Entry</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

/* HEADER */

.header {
    background: #102a56;
    padding: 28px 7%;
    color: white;
}

.header h1 {
    font-size: 26px;
    margin-bottom: 8px;
}

.header p {
    color: #cbd5e1;
    font-size: 14px;
}

/* MAIN CONTAINER */

.container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
}

.page-title {
    margin-bottom: 25px;
}

.page-title h2 {
    color: #102a56;
    font-size: 25px;
    margin-bottom: 8px;
}

.page-title p {
    color: #64748b;
    font-size: 14px;
}

/* CARD */

.card {
    background: white;
    padding: 30px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.card h3 {
    color: #102a56;
    margin-bottom: 8px;
    font-size: 20px;
}

.card-description {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 25px;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 9px;
    color: #334155;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #d4a017;
    box-shadow: 0 0 0 3px rgba(212,160,23,0.12);
}

/* SCORE INFORMATION */

.score-info {
    background: #eff6ff;
    border-left: 4px solid #d4a017;
    padding: 16px;
    border-radius: 7px;
    margin: 25px 0;
    color: #334155;
    font-size: 14px;
    line-height: 1.8;
}

/* BUTTON */

.submit-btn {
    width: 100%;
    padding: 15px;
    border: none;
    border-radius: 8px;
    background: #102a56;
    color: white;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    margin-top: 25px;
    transition: 0.3s;
}

.submit-btn:hover {
    background: #d4a017;
    color: #102a56;
}

/* NOTIFICATIONS */

.notice {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.success {
    background: #dcfce7;
    color: #166534;
}

/* BACK LINK */

.back {
    display: inline-block;
    margin-top: 10px;
    text-decoration: none;
    color: #102a56;
    font-weight: bold;
}

.back:hover {
    color: #d4a017;
}

/* MOBILE */

@media(max-width: 650px) {

    .header {
        padding: 25px 20px;
    }

    .container {
        margin: 25px auto;
        padding: 0 12px;
    }

    .card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}
.total-box {
    background: #f1f4f9;
    padding: 17px;
    border-radius: 8px;
    border-left: 4px solid #c69b36;
    margin-top: 22px;
}

.total-box small {
    color: #78869b;
    display: block;
    margin-bottom: 5px;
}

.total-box strong {
    color: #142b4a;
    font-size: 25px;
}

</style>

</head>

<body>

<div class="header">

    <h1>EduTrack</h1>

    <p>Academic Performance / Student Score Entry</p>

</div>


<div class="container">

    <div class="page-title">

        <h2>Student Score Management</h2>

        <p>
            Record and manage students' class and examination scores.
        </p>

    </div>


    <div class="card">

        <h3>Enter Student Marks</h3>

        <p class="card-description">
            Select the student's class, subject and academic period,
            then enter the scores.
        </p>


        <?php if ($message !== ""): ?>

            <div class="notice <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <form method="POST" action="scores.php">

            <div class="form-grid">

                <div class="form-group">

                    <label for="class_id">Select Class</label>

                    <select name="class_id" id="class_id" required>

                        <option value="">Choose a class</option>

                        <?php foreach ($classes as $class): ?>

                            <option value="<?php echo (int)$class["id"]; ?>">

                                <?php echo htmlspecialchars($class["class_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="student_id">Select Student</label>

                    <select name="student_id" id="student_id" required>

                        <option value="">Choose a student</option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?php echo (int)$student["id"]; ?>"
                                data-class="<?php echo (int)$student["class_id"]; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $student["first_name"] . " " .
                                    $student["last_name"] . " (" .
                                    $student["student_id"] . ")"
                                );
 ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="subject_id">Select Subject</label>

                    <select name="subject_id" id="subject_id" required>

                        <option value="">Choose a subject</option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?php echo (int)$subject["id"]; ?>"
                                data-class="<?php echo (int)$subject["class_id"]; ?>"
                            >

                                <?php echo htmlspecialchars($subject["subject_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="academic_year_id">Academic Year</label>

                    <select name="academic_year_id" id="academic_year_id" required>

                        <option value="">Choose academic year</option>

                        <?php foreach ($years as $year): ?>

                            <option value="<?php echo (int)$year["id"]; ?>">

                                <?php echo htmlspecialchars($year["year_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="academic_term_id">Academic Term</label>

                    <select name="academic_term_id" id="academic_term_id" required>

                        <option value="">Choose academic term</option>

                        <?php foreach ($terms as $term): ?>

                            <option
                                value="<?php echo (int)$term["id"]; ?>"
                                data-year="<?php echo (int)$term["academic_year_id"]; ?>"
                            >

                                <?php echo htmlspecialchars($term["term_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="class_score">Class Score (0–40)</label>

                    <input
                        type="number"
                        name="class_score"
                        id="class_score"
                        min="0"
                        max="40"
                        step="0.01"
                        placeholder="Enter class score"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="exam_score">Examination Score (0–60)</label>

                    <input
                        type="number"
                        name="exam_score"
                        id="exam_score"
                        min="0"
                        max="60"
                        step="0.01"
                        placeholder="Enter examination score"
                        required
                    >

                </div>

            </div>


            <div class="score-info">

                <strong>Score Information</strong><br>

                Class Assessment: 40 Marks Maximum<br>

                Examination: 60 Marks Maximum<br>

                Total Score: 100 Marks

            </div>


            <button type="submit" class="submit-btn">

                Save Student Scores

            </button>

        </form>

    </div>
    <div class="total-box">

            <small>Automatically Calculated Total Score</small>

            <strong id="total_display">0 / 100</strong>

        </div>


    <a href="../school/dashboard.php" class="back">
        ← Back to Dashboard
    </a>

</div>


<script>

// FILTER STUDENTS AND SUBJECTS BY CLASS

const classSelect = document.getElementById("class_id");
const studentSelect = document.getElementById("student_id");
 const subjectSelect = document.getElementById("subject_id");

function filterOptions(select, attribute, value) {

    Array.from(select.options).forEach(option => {

        if (option.value === "") {
            option.hidden = false;
            return;
        }

        option.hidden = option.dataset[attribute] !== value;

    });

    select.value = "";

}

classSelect.addEventListener("change", function() {

    filterOptions(studentSelect, "class", this.value);

    filterOptions(subjectSelect, "class", this.value);

});


// FILTER TERMS BY ACADEMIC YEAR

const yearSelect = document.getElementById("academic_year_id");
const termSelect = document.getElementById("academic_term_id");

yearSelect.addEventListener("change", function() {

    filterOptions(termSelect, "year", this.value);

});

</script>

</body>
</html>