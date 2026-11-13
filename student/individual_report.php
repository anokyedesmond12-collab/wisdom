 <?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: ../school/login.php");
    exit;
}

$school_id = (int) $_SESSION["school_id"];
http_response_code(403);
exit("Student report cards are available to students and linked parents in their portals.");

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// SCHOOL INFORMATION
$schoolStmt = $conn->prepare("
    SELECT school_name
    FROM schools
    WHERE id = ?
");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

$school_name = $school["school_name"] ?? "School Name";

// DROPDOWN DATA
$studentsStmt = $conn->prepare("
    SELECT id, student_id, first_name, last_name, other_names
    FROM students
    WHERE school_id = ?
    ORDER BY first_name, last_name
");
$studentsStmt->execute([$school_id]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$yearsStmt = $conn->prepare("
    SELECT id, year_name
    FROM academic_years
    WHERE school_id = ?
    ORDER BY id DESC
");
$yearsStmt->execute([$school_id]);
$years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

$termsStmt = $conn->prepare("
    SELECT id, term_name, academic_year_id
    FROM academic_terms
    WHERE school_id = ?
    ORDER BY id DESC
");
$termsStmt->execute([$school_id]);
$terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);

// REPORT VARIABLES
$report = false;
$scoreRows = [];
$average = 0;
$position = "N/A";
$totalStudents = 0;
$error = "";

$student_id = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : 0;
$year_id = isset($_GET["year_id"]) ? (int)$_GET["year_id"] : 0;
$term_id = isset($_GET["term_id"]) ? (int)$_GET["term_id"] : 0;

$class_teacher_remarks = trim($_GET["class_teacher_remarks"] ?? "");
$head_teacher_remarks = trim($_GET["head_teacher_remarks"] ?? "");

if ($student_id && $year_id && $term_id) {

    // CHECK STUDENT AND GET CLASS INFORMATION
    $studentStmt = $conn->prepare("
        SELECT
            s.id,
            s.student_id AS admission_number,
            s.first_name,
            s.last_name,
            s.other_names,
            s.class_id,
            c.class_name,
            c.class_level
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.id = ? AND s.school_id = ?
    ");

    $studentStmt->execute([$student_id, $school_id]);
    $report = $studentStmt->fetch(PDO::FETCH_ASSOC);

    // CHECK ACADEMIC YEAR
    $yearCheck = $conn->prepare("
        SELECT year_name
        FROM academic_years
        WHERE id = ? AND school_id = ?
    ");
    $yearCheck->execute([$year_id, $school_id]);
    $selectedYear = $yearCheck->fetch(PDO::FETCH_ASSOC);

    // CHECK ACADEMIC TERM
    $termCheck = $conn->prepare("
        SELECT term_name
        FROM academic_terms
        WHERE id = ?
        AND school_id = ?
        AND academic_year_id = ?
    ");
    $termCheck->execute([$term_id, $school_id, $year_id]);
    $selectedTerm = $termCheck->fetch(PDO::FETCH_ASSOC);

    if (!$report || !$selectedYear || !$selectedTerm) {
        $report = false;
        $error = "Invalid student, academic year or academic term selected.";
    } else {

        // GET STUDENT SCORES
        $scoresStmt = $conn->prepare("
            SELECT
                sub.subject_name,
                ss.class_score,
                ss.exam_score,
                COALESCE(
                    ss.total_score,
                    ss.class_score + ss.exam_score
                ) AS total_score,
                ss.grade,
                ss.remarks
            FROM student_scores ss
            INNER JOIN subjects sub
                ON ss.subject_id = sub.id
            WHERE ss.school_id = ?
            AND ss.student_id = ?
            AND ss.class_id = ?
            AND ss.academic_year_id = ?
            AND ss.academic_term_id = ?
            ORDER BY sub.subject_name ASC
        ");

        $scoresStmt->execute([
            $school_id,
            $student_id,
            $report["class_id"],
            $year_id,
            $term_id
        ]);

        $scoreRows = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);

        // CALCULATE AVERAGE
 if (count($scoreRows) > 0) {

            $totalMarks = 0;

            foreach ($scoreRows as $row) {
                $totalMarks += (float)$row["total_score"];
            }

            $average = $totalMarks / count($scoreRows);

            // CALCULATE CLASS POSITION
            $rankStmt = $conn->prepare("
                SELECT
                    student_id,
                    AVG(
                        COALESCE(total_score, class_score + exam_score)
                    ) AS average_score
                FROM student_scores
                WHERE school_id = ?
                AND class_id = ?
                AND academic_year_id = ?
                AND academic_term_id = ?
                GROUP BY student_id
                ORDER BY average_score DESC
            ");

            $rankStmt->execute([
                $school_id,
                $report["class_id"],
                $year_id,
                $term_id
            ]);

            $rankings = $rankStmt->fetchAll(PDO::FETCH_ASSOC);

            $previousAverage = null;
            $currentRank = 0;

            foreach ($rankings as $index => $rankRow) {

                $studentAverage = (float)$rankRow["average_score"];

                if (
                    $previousAverage === null ||
                    abs($studentAverage - $previousAverage) > 0.00001
                ) {
                    $currentRank = $index + 1;
                }

                if ((int)$rankRow["student_id"] === $student_id) {
                    $position = $currentRank;
                }

                $previousAverage = $studentAverage;
            }

            $totalStudents = count($rankings);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Report Card | <?php echo e($school_name); ?></title>
<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f1f4f9;
    color: #263445;
}

.container {
    max-width: 1100px;
    margin: auto;
}

.page-title {
    margin-bottom: 20px;
}

.page-title h2 {
    margin: 0;
    color: #142b4a;
}

.page-title p {
    color: #738096;
    margin-top: 7px;
}

/* SELECTION FORM */

.selection-box {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.05);
}

.selection-box h3 {
    margin-top: 0;
    color: #142b4a;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.form-group label {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 8px;
}

.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce2eb;
    border-radius: 7px;
    font-size: 14px;
    background: white;
}

.generate-btn {
    margin-top: 18px;
    background: #142b4a;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.generate-btn:hover {
    background: #203e66;
}

.error {
    background: #ffe8e8;
    color: #a32626;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
}

/* REPORT CARD */

.report-card {
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 3px 20px rgba(0,0,0,0.07);
}

.school-header {
    text-align: center;
    border-bottom: 3px solid #c69b36;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.school-logo {
    width: 75px;
    height: 75px;
    border: 2px dashed #c69b36;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    color: #a17a25;
    font-size: 12px;
}

.school-header h1 {
    color: #142b4a;
    font-size: 25px;
    text-transform: uppercase;
    margin: 5px 0;
}

.school-header p {
    margin: 5px 0;
    color: #718096;
}

.report-heading {
    background: #142b4a;
    color: white;
    text-align: center;
 padding: 12px;
    margin-bottom: 20px;
    border-radius: 5px;
    letter-spacing: 1px;
}

.report-heading h2 {
    margin: 0;
    font-size: 18px;
}

/* STUDENT INFORMATION */

.student-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.info-item {
    border-bottom: 1px solid #dce2eb;
    padding: 8px 0;
}

.info-item small {
    display: block;
    color: #78869b;
    margin-bottom: 5px;
}

.info-item strong {
    color: #142b4a;
}

/* SCORES TABLE */

.section-title {
    color: #142b4a;
    font-size: 15px;
    margin: 25px 0 12px;
    border-left: 4px solid #c69b36;
    padding-left: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

th {
    background: #142b4a;
    color: white;
    padding: 12px 8px;
    font-size: 12px;
    text-align: center;
}

td {
    padding: 11px 8px;
    border: 1px solid #e0e5ed;
    text-align: center;
    font-size: 13px;
}

td:first-child {
    text-align: left;
}

tbody tr:nth-child(even) {
    background: #f6f8fb;
}

.no-scores {
    background: #fff6e5;
    padding: 15px;
    border-radius: 6px;
    color: #805b17;
}

/* PERFORMANCE SUMMARY */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 25px 0;
}

.summary-box {
    background: #f1f4f9;
    border-top: 4px solid #c69b36;
    padding: 18px;
    text-align: center;
    border-radius: 6px;
}

.summary-box small {
    display: block;
    color: #718096;
    margin-bottom: 8px;
}

.summary-box strong {
    font-size: 22px;
    color: #142b4a;
}

/* REMARKS */

.remarks-box {
    border: 1px solid #dce2eb;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    min-height: 75px;
}

.remarks-box h4 {
    color: #142b4a;
    margin: 0 0 10px;
}

.remarks-box p {
    margin: 0;
    white-space: pre-wrap;
    line-height: 1.5;
}

.signature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    margin-top: 55px;
}

.signature {
    border-top: 1px solid #263445;
    text-align: center;
    padding-top: 10px;
    font-size: 13px;
}

.report-footer {
    border-top: 1px solid #dce2eb;
    margin-top: 35px;
    padding-top: 15px;
    text-align: center;
    color: #8994a5;
    font-size: 11px;
}

.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin: 20px 0;
}

.action-buttons button {
    padding: 12px 20px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.print-btn {
    background: #c69b36;
    color: white;
}

.back-btn {
    background: #142b4a;
    color: white;
}

@media(max-width: 700px) {

    body {
        padding: 12px;
    }

    .form-grid,
    .student-info,
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .report-card {
        padding: 18px;
        overflow-x: auto;
    }

    .signature-grid {
        gap: 25px;
    }

    table {
        min-width: 600px;
    }

}

/* PRINT DESIGN */

@media print {

    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body {
        background: white;
        padding: 0;
    }

    .container {
        max-width: 100%;
    }

    .page-title,
    .selection-box,
    .action-buttons {
        display: none !important;
    }

    .report-card {
        box-shadow: none;
        border-radius: 0;
        padding: 0;
    }

    .school-header h1 {
        font-size: 22px;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
    }

    .remarks-box,
    .summary-box {
        break-inside: avoid;
    }

    .signature-grid {
        break-inside: avoid;
    }

}

</style>
</head>
<body>
<div class="container">
<div class="page-title">
    <a href="../school/dashboard.php" style="display:inline-block; margin-bottom:12px; color:#142b4a; text-decoration:none; font-weight:bold;">← Back to Dashboard</a>
    <h2>Individual Student Report</h2>
    <p>Generate and print an official student academic report.</p>
</div>

<?php if ($error): ?>
    <div class="error"><?php echo e($error); ?></div>
<?php endif; ?>

<!-- REPORT SELECTION FORM -->

<div class="selection-box">
 <h3>Select Student and Academic Period</h3>

    <form method="GET">

        <div class="form-grid">

            <div class="form-group">
                <label>Select Student</label>

                <select name="student_id" required>
                    <option value="">Choose student</option>

                    <?php foreach ($students as $student): ?>

                        <option
                            value="<?php echo (int)$student["id"]; ?>"
                            <?php echo $student_id == $student["id"] ? "selected" : ""; ?>
                        >
                            <?php
                            echo e(
                                trim(
                                    $student["first_name"] . " " .
                                    $student["other_names"] . " " .
                                    $student["last_name"]
                                )
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Academic Year</label>

                <select name="year_id" id="year_id" required>

                    <option value="">Choose academic year</option>

                    <?php foreach ($years as $year): ?>

                        <option
                            value="<?php echo (int)$year["id"]; ?>"
                            <?php echo $year_id == $year["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($year["year_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Academic Term</label>

                <select name="term_id" id="term_id" required>

                    <option value="">Choose academic term</option>

                    <?php foreach ($terms as $term): ?>

                        <option
                            value="<?php echo (int)$term["id"]; ?>"
                            data-year="<?php echo (int)$term["academic_year_id"]; ?>"
                            <?php echo $term_id == $term["id"] ? "selected" : ""; ?>
                        >
                            <?php echo e($term["term_name"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>
            <div class="form-group">
    <label>Class Teacher's Remarks</label>
    <textarea name="class_teacher_remarks"
    rows="4"
    placeholder="Type class teacher's remarks here..."
    required></textarea>
</div>

<div class="form-group">
    <label>Head Teacher's Remarks</label>
    <textarea name="head_teacher_remarks"
    rows="4"
    placeholder="Type head teacher's remarks here..."
    required></textarea>
</div>

        </div>

        <button type="submit" class="generate-btn">
            Generate Report
        </button>

    </form>

</div>

<?php if ($report): ?>

    <!-- REPORT CARD -->

    <div class="action-buttons">

        <button class="back-btn" onclick="window.location.href='individual_report.php'">
            New Report
        </button>

        <button class="print-btn" onclick="window.print()">
            Download / Print PDF
        </button>

    </div>

    <div class="report-card">

        <!-- SCHOOL HEADER -->

        <div class="school-header">

            <div class="school-logo">
                SCHOOL LOGO
            </div>

            <h1><?php echo e($school_name); ?></h1>

            <p>OFFICIAL STUDENT ACADEMIC REPORT</p>

            <p>
                <?php echo e($selectedYear["year_name"]); ?>
                |
                <?php echo e($selectedTerm["term_name"]); ?>
            </p>

        </div>

        <div class="report-heading">
            <h2>STUDENT REPORT CARD</h2>
        </div>

        <!-- STUDENT DETAILS -->

        <h3 class="section-title">Student Information</h3>

        <div class="student-info">

            <div class="info-item">
                <small>Student's Full Name</small>
                <strong>
                    <?php
                    echo e(
                        trim(
                            $report["first_name"] . " " .
                            $report["other_names"] . " " .
                            $report["last_name"]
 )
                    );
                    ?>
                </strong>
            </div>

            <div class="info-item">
                <small>Admission / Index Number</small>
                <strong><?php echo e($report["admission_number"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Class</small>
                <strong><?php echo e($report["class_name"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Class Level</small>
                <strong><?php echo e($report["class_level"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Academic Year</small>
                <strong><?php echo e($selectedYear["year_name"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Academic Term</small>
                <strong><?php echo e($selectedTerm["term_name"]); ?></strong>
            </div>

        </div>

        <!-- SUBJECT PERFORMANCE -->

        <h3 class="section-title">Academic Performance</h3>

        <?php if (count($scoreRows) > 0): ?>

            <table>

                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Class Score<br>(40)</th>
                        <th>Exam Score<br>(60)</th>
                        <th>Total<br>(100)</th>
                        <th>Grade</th>
                        <th>Subject Remarks</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($scoreRows as $row): ?>

                        <tr>

                            <td><?php echo e($row["subject_name"]); ?></td>

                            <td><?php echo e($row["class_score"]); ?></td>

                            <td><?php echo e($row["exam_score"]); ?></td>

                            <td>
                                <strong>
                                    <?php echo number_format((float)$row["total_score"], 1); ?>
                                </strong>
                            </td>

                            <td><?php echo e($row["grade"]); ?></td>

                            <td><?php echo e($row["remarks"]); ?></td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

            <!-- SUMMARY -->

            <h3 class="section-title">Overall Performance</h3>

            <div class="summary-grid">

                <div class="summary-box">
                    <small>Overall Average</small>
                    <strong><?php echo number_format($average, 2); ?>%</strong>
                </div>

                <div class="summary-box">
                    <small>Class Position</small>
                    <strong>
                        <?php echo e($position); ?>
                        <?php echo $position !== "N/A" ? " / " . $totalStudents : ""; ?>
                    </strong>
                </div>

                <div class="summary-box">
                    <small>Subjects Offered</small>
                    <strong><?php echo count($scoreRows); ?></strong>
                </div>

            </div>

        <?php else: ?>

            <div class="no-scores">
                No scores have been recorded for this student for the selected academic term.
            </div>

        <?php endif; ?>

        <!-- TEACHER REMARKS -->

        <h3 class="section-title">Class Teacher's Remarks</h3>

        <div class="remarks-box">
            <p><?php echo e($class_teacher_remarks ?: "No remarks entered."); ?></p>
        </div>

        <h3 class="section-title">Head Teacher's Remarks</h3>

        <div class="remarks-box">
            <p><?php echo e($head_teacher_remarks ?: "No remarks entered."); ?></p>
        </div>

        <!-- SIGNATURES -->

        <div class="signature-grid">

            <div class="signature">
                Class Teacher's Signature
            </div>
 <div class="signature">
                Head Teacher's Signature
            </div>

        </div>

        <div class="report-footer">

            <p>
                <?php echo e($school_name); ?>
            </p>

            <p>Official Academic Report Card</p>

        </div>

    </div>

<?php endif; ?>
</div>
<script>

// Show only terms belonging to the selected academic year.

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

</script>
</body>
</html>