 <?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: ../school/login.php");
    exit;
}

$school_id = (int)$_SESSION["school_id"];
http_response_code(403);
exit("Class report generation is now available to teachers for their assigned classes.");

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// SCHOOL INFORMATION
$stmt = $conn->prepare("
    SELECT school_name, school_logo
    FROM schools
    WHERE id = ?
");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    die("School information not found.");
}

// DROPDOWN DATA
$stmt = $conn->prepare("
    SELECT id, class_name
    FROM classes
    WHERE school_id = ?
    ORDER BY class_name
");
$stmt->execute([$school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT id, year_name
    FROM academic_years
    WHERE school_id = ?
    ORDER BY id DESC
");
$stmt->execute([$school_id]);
$years = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT id, term_name, academic_year_id
    FROM academic_terms
    WHERE school_id = ?
    ORDER BY id DESC
");
$stmt->execute([$school_id]);
$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// REPORT VARIABLES
$class_id = (int)($_GET["class_id"] ?? 0);
$year_id = (int)($_GET["year_id"] ?? 0);
$term_id = (int)($_GET["term_id"] ?? 0);

$class = null;
$year = null;
$term = null;
$students = [];
$subjects = [];
$reportRows = [];
$error = "";

if ($class_id && $year_id && $term_id) {

    // VALIDATE CLASS
    $stmt = $conn->prepare("
        SELECT class_name
        FROM classes
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$class_id, $school_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    // VALIDATE YEAR
    $stmt = $conn->prepare("
        SELECT year_name
        FROM academic_years
        WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$year_id, $school_id]);
    $year = $stmt->fetch(PDO::FETCH_ASSOC);

    // VALIDATE TERM
    $stmt = $conn->prepare("
        SELECT term_name
        FROM academic_terms
        WHERE id = ?
        AND school_id = ?
        AND academic_year_id = ?
    ");
    $stmt->execute([$term_id, $school_id, $year_id]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class || !$year || !$term) {
        $error = "Invalid class, academic year or term selected.";
    } else {

        // GET STUDENTS IN THE SELECTED CLASS
        $stmt = $conn->prepare("
            SELECT
                id,
                student_id AS admission_number,
                first_name,
                last_name,
                other_names
            FROM students
            WHERE school_id = ?
            AND class_id = ?
            ORDER BY first_name, last_name
        ");

        $stmt->execute([$school_id, $class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // GET SUBJECTS AND SCORES
        $stmt = $conn->prepare("
            SELECT
                ss.student_id,
                sub.id AS subject_id,
                sub.subject_name,
                ss.class_score,
                ss.exam_score,
                COALESCE(
                    ss.total_score,
                    ss.class_score + ss.exam_score
                ) AS total_score,
                ss.grade
            FROM student_scores ss
            INNER JOIN subjects sub
                ON ss.subject_id = sub.id
            WHERE ss.school_id = ?
            AND ss.class_id = ?
            AND ss.academic_year_id = ?
            AND ss.academic_term_id = ?
            ORDER BY sub.subject_name
        ");

        $stmt->execute([
            $school_id,
            $class_id,
            $year_id,
            $term_id
        ]);

        $scoreData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ORGANISE SUBJECTS AND STUDENT SCORES
        $scoreMap = [];

        foreach ($scoreData as $score) {

            $sid = (int)$score["student_id"];
            $subid = (int)$score["subject_id"];

            $subjects[$subid] = $score["subject_name"];

            $scoreMap[$sid][$subid] = $score;
 }

        // CALCULATE EACH STUDENT'S AVERAGE
        foreach ($students as $student) {

            $sid = (int)$student["id"];

            $studentTotal = 0;
            $subjectCount = 0;

            foreach ($subjects as $subid => $subjectName) {

                if (isset($scoreMap[$sid][$subid])) {

                    $studentTotal += (float)$scoreMap[$sid][$subid]["total_score"];
                    $subjectCount++;
                }
            }

            $average = $subjectCount > 0
                ? $studentTotal / $subjectCount
                : null;

            $reportRows[] = [
                "id" => $sid,
                "admission_number" => $student["admission_number"],
                "name" => trim(
                    $student["first_name"] . " " .
                    $student["other_names"] . " " .
                    $student["last_name"]
                ),
                "scores" => $scoreMap[$sid] ?? [],
                "total" => $studentTotal,
                "average" => $average,
                "subject_count" => $subjectCount,
                "position" => "N/A"
            ];
        }

        // RANK STUDENTS BY AVERAGE
        usort($reportRows, function ($a, $b) {

            if ($a["average"] === null) return 1;
            if ($b["average"] === null) return -1;

            return $b["average"] <=> $a["average"];
        });

        $previousAverage = null;
        $rank = 0;

        foreach ($reportRows as $index => &$row) {

            if ($row["average"] === null) {
                $row["position"] = "N/A";
                continue;
            }

            if (
                $previousAverage === null ||
                abs($row["average"] - $previousAverage) > 0.00001
            ) {
                $rank = $index + 1;
            }

            $row["position"] = $rank;
            $previousAverage = $row["average"];
        }

        unset($row);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Class Academic Report | <?php echo e($school["school_name"]); ?></title>
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
    max-width: 1200px;
    margin: auto;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h2 {
    color: #142b4a;
    margin-bottom: 7px;
}

.page-header p {
    color: #78869b;
}

/* SELECTION FORM */

.selection-box {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.05);
}

.selection-box h3 {
    color: #142b4a;
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    font-size: 13px;
}

.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce2eb;
    border-radius: 7px;
    background: white;
    font-size: 14px;
}

.generate-btn {
    background: #142b4a;
    color: white;
    border: none;
    border-radius: 7px;
    padding: 13px 25px;
    margin-top: 18px;
    cursor: pointer;
    font-weight: bold;
}

/* REPORT */

.report-card {
    background: white;
    padding: 35px;
    border-radius: 10px;
    box-shadow: 0 3px 20px rgba(0,0,0,0.06);
}

.school-header {
    text-align: center;
    border-bottom: 3px solid #c69b36;
    padding-bottom: 20px;
}

.school-logo {
    width: 75px;
    height: 75px;
    object-fit: contain;
    margin-bottom: 10px;
}

.school-header h1 {
    color: #142b4a;
    font-size: 24px;
    text-transform: uppercase;
    margin: 5px 0;
}

.school-header p {
    color: #78869b;
    margin: 5px 0;
}

.report-title {
    background: #142b4a;
    color: white;
    text-align: center;
    padding: 13px;
    margin: 20px 0;
    border-radius: 5px;
}

.report-title h2 {
    margin: 0;
    font-size: 18px;
}

.report-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.info-item {
    border-bottom: 1px solid #dce2eb;
    padding: 10px 0;
}

.info-item small {
    display: block;
    color: #78869b;
    margin-bottom: 5px;
}

.info-item strong {
    color: #142b4a;
}

.table-heading {
    color: #142b4a;
    border-left: 4px solid #c69b36;
    padding-left: 10px;
    margin: 25px 0 15px;
    font-size: 16px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th {
    background: #142b4a;
    color: white;
    padding: 12px 8px;
    font-size: 12px;
}

td {
    padding: 11px 8px;
    border: 1px solid #e0e5ed;
    text-align: center;
    font-size: 12px;
}

td.name {
    text-align: left;
    min-width: 140px;
}

tbody tr:nth-child(even) {
    background: #f6f8fb;
}

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
}

.summary-box small {
    display: block;
    color: #78869b;
    margin-bottom: 8px;
}

.summary-box strong {
    color: #142b4a;
    font-size: 22px;
}

.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.action-buttons a,
.action-buttons button {
    text-decoration: none;
    padding: 12px 18px;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
    font-size: 13px;
}

.back-btn {
    background: #142b4a;
    color: white;
}

.print-btn {
    background: #c69b36;
    color: white;
}

.error {
    background: #ffe8e8;
    color: #a32626;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
}

.footer {
    text-align: center;
    border-top: 1px solid #dce2eb;
    padding-top: 15px;
    margin-top: 30px;
    font-size: 11px;
    color: #78869b;
}

@media(max-width: 700px) {
    body {
        padding: 12px;
    }

    .form-grid,
    .report-info,
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .report-card {
        padding: 18px;
    }
}

@media print {

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    body {
        padding: 0;
        background: white;
    }

    .page-header,
    .selection-box,
    .action-buttons {
        display: none !important;
    }

    .container {
        max-width: 100%;
    }

    .report-card {
        padding: 0;
        box-shadow: none;
        border-radius: 0;
    }

    .table-wrapper {
        overflow: visible;
    }

    table {
        min-width: 0;
    }

    th, td {
        font-size: 9px;
        padding: 6px 4px;
    }

    tr {
        break-inside: avoid;
    }
}
</style>
</head>
<body>
<div class="container">
<div class="page-header">
    <h2>Class Academic Report</h2>
    <p>Generate academic performance reports for an entire class.</p>
</div>

<div class="selection-box">

    <h3>Select Academic Information</h3>

    <form method="GET">

        <div class="form-grid">

            <div class="form-group">
                <label>Select Class</label>

                <select name="class_id" required>
                    <option value="">Choose class</option>

                    <?php foreach ($classes as $item): ?>
                        <option value="<?php echo (int)$item["id"]; ?>"
                            <?php echo $class_id == $item["id"] ? "selected" : ""; ?>>
                            <?php echo e($item["class_name"]); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Academic Year</label>

                <select name="year_id" id="year_id" required>
                    <option value="">Choose academic year</option>

                    <?php foreach ($years as $item): ?>
 <option value="<?php echo (int)$item["id"]; ?>"
                            <?php echo $year_id == $item["id"] ? "selected" : ""; ?>>
                            <?php echo e($item["year_name"]); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Academic Term</label>

                <select name="term_id" id="term_id" required>
                    <option value="">Choose academic term</option>

                    <?php foreach ($terms as $item): ?>
                        <option
                            value="<?php echo (int)$item["id"]; ?>"
                            data-year="<?php echo (int)$item["academic_year_id"]; ?>"
                            <?php echo $term_id == $item["id"] ? "selected" : ""; ?>>
                            <?php echo e($item["term_name"]); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <button type="submit" class="generate-btn">
            Generate Class Report →
        </button>

    </form>

</div>

<?php if ($error): ?>
    <div class="error"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if ($class && $year && $term): ?>

    <div class="action-buttons">

        <a href="../school/dashboard.php" class="back-btn">
            ← Back to Main Page
        </a>

        <button onclick="window.print()" class="print-btn">
            Download / Print PDF
        </button>

    </div>

    <div class="report-card">

        <div class="school-header">

            <?php if (!empty($school["school_logo"])): ?>
                <img
                    src="../../<?php echo e($school["school_logo"]); ?>"
                    class="school-logo"
                    alt="School Logo"
                >
            <?php endif; ?>

            <h1><?php echo e($school["school_name"]); ?></h1>

            <p>OFFICIAL CLASS ACADEMIC PERFORMANCE REPORT</p>

            <p>
                <?php echo e($year["year_name"]); ?>
                |
                <?php echo e($term["term_name"]); ?>
            </p>

        </div>

        <div class="report-title">
            <h2>CLASS PERFORMANCE REPORT</h2>
        </div>

        <div class="report-info">

            <div class="info-item">
                <small>Class</small>
                <strong><?php echo e($class["class_name"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Academic Year</small>
                <strong><?php echo e($year["year_name"]); ?></strong>
            </div>

            <div class="info-item">
                <small>Academic Term</small>
                <strong><?php echo e($term["term_name"]); ?></strong>
            </div>

        </div>

        <h3 class="table-heading">Students' Academic Performance</h3>

        <?php if (count($reportRows) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Admission Number</th>
                            <th>Student Name</th>

                            <?php foreach ($subjects as $subjectName): ?>
                                <th><?php echo e($subjectName); ?></th>
                            <?php endforeach; ?>

                            <th>Total Marks</th>
                            <th>Average (%)</th>
                            <th>Subjects</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reportRows as $row): ?>

                            <tr>

                                <td><strong><?php echo e($row["position"]); ?></strong></td>

                                <td><?php echo e($row["admission_number"]); ?></td>

                                <td class="name"><?php echo e($row["name"]); ?></td>
<?php foreach ($subjects as $subid => $subjectName): ?>

                                    <td>
                                        <?php
                                        echo isset($row["scores"][$subid])
                                            ? number_format((float)$row["scores"][$subid]["total_score"], 1)
                                            : "-";
                                        ?>
                                    </td>

                                <?php endforeach; ?>

                                <td><?php echo number_format($row["total"], 1); ?></td>

                                <td>
                                    <?php
                                    echo $row["average"] !== null
                                        ? number_format($row["average"], 2) . "%"
                                        : "N/A";
                                    ?>
                                </td>

                                <td><?php echo (int)$row["subject_count"]; ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="summary-grid">

                <div class="summary-box">
                    <small>Total Students</small>
                    <strong><?php echo count($students); ?></strong>
                </div>

                <div class="summary-box">
                    <small>Subjects Recorded</small>
                    <strong><?php echo count($subjects); ?></strong>
                </div>

                <div class="summary-box">
                    <small>Students With Scores</small>
                    <strong>
                        <?php
                        $withScores = 0;
                        foreach ($reportRows as $row) {
                            if ($row["subject_count"] > 0) {
                                $withScores++;
                            }
                        }
                        echo $withScores;
                        ?>
                    </strong>
                </div>

            </div>

        <?php else: ?>

            <div class="error">
                No students were found in this class.
            </div>

        <?php endif; ?>

        <div class="footer">
            <?php echo e($school["school_name"]); ?>
            <br>
            Official Class Academic Report
        </div>

    </div>

<?php endif; ?>
</div>
<script>
const yearSelect = document.getElementById("year_id");
const termSelect = document.getElementById("term_id");

function filterTerms() {

    const selectedYear = yearSelect.value;

    Array.from(termSelect.options).forEach(option => {

        if (option.value === "") return;

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