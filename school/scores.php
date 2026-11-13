 <?php
session_start();

require_once __DIR__ . "/../config/database.php";
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit;
}

http_response_code(403);
exit("Score review is now available through each assigned teacher's portal.");

$school_id = (int)$_SESSION["school_id"];

$error = "";
$success = "";

// HELPER FUNCTION
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// FETCH CLASSES
$stmt = $conn->prepare("
    SELECT id, class_name
    FROM classes
    WHERE school_id = ?
    ORDER BY class_name
");
$stmt->execute([$school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH STUDENTS
$stmt = $conn->prepare("
    SELECT id, student_id, first_name, last_name, other_names, class_id
    FROM students
    WHERE school_id = ?
    ORDER BY first_name, last_name
");
$stmt->execute([$school_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH SUBJECTS
$stmt = $conn->prepare("
    SELECT id, subject_name
    FROM subjects
    WHERE school_id = ?
    ORDER BY subject_name
");
$stmt->execute([$school_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH ACADEMIC YEARS
$stmt = $conn->prepare("
    SELECT id, year_name
    FROM academic_years
    WHERE school_id = ?
    ORDER BY id DESC
");
$stmt->execute([$school_id]);
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH ACADEMIC TERMS
$stmt = $conn->prepare("
    SELECT id, term_name, academic_year_id
    FROM academic_terms
    WHERE school_id = ?
    ORDER BY id DESC
");
$stmt->execute([$school_id]);
$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEFAULT FORM VALUES
$class_id = "";
$student_id = "";
$subject_id = "";
$year_id = "";
$term_id = "";
$class_score = "";
$exam_score = "";
$grade = "";
$remarks = "";

// SAVE SCORES
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_score"])) {

    $class_id = (int)($_POST["class_id"] ?? 0);
    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $year_id = (int)($_POST["year_id"] ?? 0);
    $term_id = (int)($_POST["term_id"] ?? 0);

    $classRaw = trim($_POST["class_score"] ?? "");
    $examRaw = trim($_POST["exam_score"] ?? "");

    $grade = trim($_POST["grade"] ?? "");
    $remarks = trim($_POST["remarks"] ?? "");

    // VALIDATE EMPTY FIELDS
    if (
        !$class_id ||
        !$student_id ||
        !$subject_id ||
        !$year_id ||
        !$term_id ||
        $classRaw === "" ||
        $examRaw === ""
    ) {
        $error = "Please complete all required fields.";
    }

    // VALIDATE NUMBERS
    elseif (!is_numeric($classRaw) || !is_numeric($examRaw)) {
        $error = "Scores must be valid numbers.";
    }

    else {

        $class_score = (float)$classRaw;
        $exam_score = (float)$examRaw;

        if (
            $class_score < 0 ||
            $class_score > 40 ||
            $exam_score < 0 ||
            $exam_score > 60
        ) {
            $error = "Class score must be between 0 and 40. Examination score must be between 0 and 60.";
        }

        else {

            $total_score = $class_score + $exam_score;

            try {

                // CHECK CLASS
                $check = $conn->prepare("
                    SELECT id
                    FROM classes
                    WHERE id = ? AND school_id = ?
                ");
                $check->execute([$class_id, $school_id]);

                if (!$check->fetch()) {
                    throw new Exception("Selected class does not belong to your school.");
                }

                // CHECK STUDENT
                $check = $conn->prepare("
                    SELECT id
                    FROM students
                    WHERE id = ?
                    AND school_id = ?
                    AND class_id = ?
                ");
                $check->execute([$student_id, $school_id, $class_id]);

                if (!$check->fetch()) {
                    throw new Exception("Selected student does not belong to the selected class.");
                }

                // CHECK SUBJECT
                $check = $conn->prepare("
[12/11/2026 5:33 am] Wisdom De Fame: SELECT id
                    FROM subjects
                    WHERE id = ? AND school_id = ?
                ");
                $check->execute([$subject_id, $school_id]);

                if (!$check->fetch()) {
                    throw new Exception("Selected subject does not belong to your school.");
                }

                // CHECK SUBJECT ASSIGNMENT
                $check = $conn->prepare("
                    SELECT id
                    FROM class_subjects
                    WHERE school_id = ?
                    AND class_id = ?
                    AND subject_id = ?
                ");
                $check->execute([$school_id, $class_id, $subject_id]);

                if (!$check->fetch()) {
                    throw new Exception("This subject has not been assigned to the selected class.");
                }

                // CHECK ACADEMIC YEAR
                $check = $conn->prepare("
                    SELECT id
                    FROM academic_years
                    WHERE id = ? AND school_id = ?
                ");
                $check->execute([$year_id, $school_id]);

                if (!$check->fetch()) {
                    throw new Exception("Invalid academic year selected.");
                }

                // CHECK ACADEMIC TERM
                $check = $conn->prepare("
                    SELECT id
                    FROM academic_terms
                    WHERE id = ?
                    AND school_id = ?
                    AND academic_year_id = ?
                ");
                $check->execute([$term_id, $school_id, $year_id]);

                if (!$check->fetch()) {
                    throw new Exception("Selected term does not belong to the selected academic year.");
                }

                // CHECK WHETHER SCORE ALREADY EXISTS
                $check = $conn->prepare("
                    SELECT id
                    FROM student_scores
                    WHERE school_id = ?
                    AND student_id = ?
                    AND class_id = ?
                    AND subject_id = ?
                    AND academic_year_id = ?
                    AND academic_term_id = ?
                    LIMIT 1
                ");

                $check->execute([
                    $school_id,
                    $student_id,
                    $class_id,
                    $subject_id,
                    $year_id,
                    $term_id
                ]);

                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {

                    // UPDATE EXISTING SCORE
                    $save = $conn->prepare("
                        UPDATE student_scores
                        SET class_score = ?,
                            exam_score = ?,
                            total_score = ?,
                            grade = ?,
                            remarks = ?
                        WHERE id = ?
                        AND school_id = ?
                    ");

                    $save->execute([
                        $class_score,
                        $exam_score,
                        $total_score,
                        $grade,
                        $remarks,
                        $existing["id"],
                        $school_id
                    ]);

                    $success = "Student scores updated successfully.";

                } else {

                    // INSERT NEW SCORE
                    $save = $conn->prepare("
                        INSERT INTO student_scores
                        (
                            school_id,
                            student_id,
                            class_id,
                            subject_id,
                            academic_year_id,
                            academic_term_id,
                            class_score,
                            exam_score,
                            total_score,
                            grade,
                            remarks
[12/11/2026 5:33 am] Wisdom De Fame: )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $save->execute([
                        $school_id,
                        $student_id,
                        $class_id,
                        $subject_id,
                        $year_id,
                        $term_id,
                        $class_score,
                        $exam_score,
                        $total_score,
                        $grade,
                        $remarks
                    ]);

                    $success = "Student scores saved successfully.";
                }

                // CLEAR SCORE INPUTS AFTER SUCCESS
                $class_score = "";
                $exam_score = "";
                $grade = "";
                $remarks = "";

            } catch (PDOException $e) {

                error_log("EduTrack Score Error: " . $e->getMessage());

                $error = "Database error: " . $e->getMessage();

            } catch (Exception $e) {

                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Score Management</title>
<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #f1f4f9;
    font-family: Arial, sans-serif;
    color: #263445;
}

.container {
    max-width: 1050px;
    margin: auto;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    color: #142b4a;
    font-size: 29px;
    margin-bottom: 8px;
}

.page-header p {
    color: #78869b;
    font-size: 15px;
}

.form-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.form-card h2 {
    color: #142b4a;
    margin-top: 0;
    margin-bottom: 8px;
}

.form-description {
    color: #78869b;
    margin-bottom: 25px;
}

.message {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.error {
    background: #ffe8e8;
    color: #a32626;
    border-left: 4px solid #d9534f;
}

.success {
    background: #e4f8eb;
    color: #247a43;
    border-left: 4px solid #35a765;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
}

.form-group label {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 9px;
    color: #263445;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #dce2eb;
    border-radius: 7px;
    font-size: 14px;
    background: white;
    font-family: Arial, sans-serif;
}

.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #c69b36;
    box-shadow: 0 0 4px rgba(198,155,54,0.2);
}

.form-group textarea {
    resize: vertical;
    min-height: 90px;
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

.button-row {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.save-btn {
    background: #142b4a;
    color: white;
    padding: 14px 25px;
    border: none;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.save-btn:hover {
    background: #c69b36;
}

.back-btn {
    display: inline-block;
    text-decoration: none;
    background: #edf0f5;
    color: #142b4a;
    padding: 14px 20px;
    border-radius: 7px;
    font-weight: bold;
    font-size: 14px;
}

.back-btn:hover {
    background: #dce2eb;
}

@media(max-width: 650px) {

    body {
        padding: 12px;
    }

    .form-card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

</style>
</head>
[12/11/2026 5:33 am] Wisdom De Fame: <body>
<div class="container">
<div class="page-header">

    <h1>Student Score Management</h1>

    <p>
        Record and manage students' class and examination scores.
    </p>

</div>

<div class="form-card">

    <h2>Enter Student Marks</h2>

    <p class="form-description">
        Select the student's class, subject and academic period,
        then enter the scores.
    </p>

    <?php if ($error): ?>

        <div class="message error">
            <?php echo e($error); ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="message success">
            <?php echo e($success); ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="scores.php">

        <div class="form-grid">

            <div class="form-group">

                <label>Select Class</label>

                <select name="class_id" id="class_id" required>

                    <option value="">Choose a class</option>

                    <?php foreach ($classes as $item): ?>

                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            <?php echo $class_id == $item["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($item["class_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Select Student</label>

                <select name="student_id" id="student_id" required>

                    <option value="">Choose a student</option>

                    <?php foreach ($students as $item): ?>

                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            data-class="<?php echo (int)$item["class_id"]; ?>"
                            <?php echo $student_id == $item["id"] ? "selected" : ""; ?>
                        >
                            <?php
                            echo e(
                                trim(
                                    $item["first_name"] . " " .
                                    $item["other_names"] . " " .
                                    $item["last_name"]
                                )
                            );
                            ?>
                            (<?php echo e($item["student_id"]); ?>)
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Select Subject</label>

                <select name="subject_id" id="subject_id" required>

                    <option value="">Choose a subject</option>

                    <?php foreach ($subjects as $item): ?>

                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            <?php echo $subject_id == $item["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($item["subject_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Academic Year</label>

                <select name="year_id" id="year_id" required>

                    <option value="">Choose academic year</option>

                    <?php foreach ($years as $item): ?>

                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            <?php echo $year_id == $item["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($item["year_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Academic Term</label>

                <select name="term_id" id="term_id" required>
[12/11/2026 5:33 am] Wisdom De Fame: <option value="">Choose academic term</option>

                    <?php foreach ($terms as $item): ?>

                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            data-year="<?php echo (int)$item["academic_year_id"]; ?>"
                            <?php echo $term_id == $item["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($item["term_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Class Score (0–40)</label>

                <input
                    type="number"
                    name="class_score"
                    id="class_score"
                    min="0"
                    max="40"
                    step="0.01"
                    placeholder="Enter class score"
                    value="<?php echo e($class_score); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Examination Score (0–60)</label>

                <input
                    type="number"
                    name="exam_score"
                    id="exam_score"
                    min="0"
                    max="60"
                    step="0.01"
                    placeholder="Enter examination score"
                    value="<?php echo e($exam_score); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Grade</label>

                <input
                    type="text"
                    name="grade"
                    maxlength="20"
                    placeholder="Enter grade"
                    value="<?php echo e($grade); ?>"
                >

            </div>

            <div class="form-group" style="grid-column:1/-1;">

                <label>Teacher's Remarks</label>

                <textarea
                    name="remarks"
                    placeholder="Enter teacher's remarks..."
                ><?php echo e($remarks); ?></textarea>

            </div>

        </div>

        <div class="total-box">

            <small>Automatically Calculated Total Score</small>

            <strong id="total_display">0 / 100</strong>

        </div>

        <div class="button-row">

            <button
                type="submit"
                name="save_score"
                value="1"
                class="save-btn"
            >
                Save Student Score
            </button>

            <a href="dashboard.php" class="back-btn">
                ← Back to Main Page
            </a>

        </div>

    </form>

</div>
</div>
<script>

// FILTER STUDENTS BY SELECTED CLASS

const classSelect = document.getElementById("class_id");
const studentSelect = document.getElementById("student_id");

function filterStudents() {

    const selectedClass = classSelect.value;

    Array.from(studentSelect.options).forEach(option => {

        if (option.value === "") {
            option.hidden = false;
            return;
        }

        option.hidden = option.dataset.class !== selectedClass;

        if (option.hidden && option.selected) {
            studentSelect.value = "";
        }

    });

}

classSelect.addEventListener("change", filterStudents);
filterStudents();


// FILTER TERMS BY ACADEMIC YEAR

const yearSelect = document.getElementById("year_id");
const termSelect = document.getElementById("term_id");

function filterTerms() {

    const selectedYear = yearSelect.value;

    Array.from(termSelect.options).forEach(option => {

        if (option.value === "") {
            option.hidden = false;
            return;
        }

        option.hidden = option.dataset.year !== selectedYear;

        if (option.hidden && option.selected) {
            termSelect.value = "";
        }

    });

}

yearSelect.addEventListener("change", filterTerms);
 filterTerms();


// CALCULATE TOTAL SCORE

const classScore = document.getElementById("class_score");
const examScore = document.getElementById("exam_score");
const totalDisplay = document.getElementById("total_display");

function calculateTotal() {

    const first = parseFloat(classScore.value) || 0;
    const second = parseFloat(examScore.value) || 0;

    totalDisplay.textContent = (first + second).toFixed(2) + " / 100";

}

classScore.addEventListener("input", calculateTotal);
examScore.addEventListener("input", calculateTotal);

calculateTotal();

</script>
</body>
</html>