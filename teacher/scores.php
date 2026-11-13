<?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (($_SESSION["role"] ?? "") !== "teacher" || empty($_SESSION["teacher_id"]) || empty($_SESSION["teacher_school_id"])) {
    header("Location: login.php");
    exit();
}

$teacherId = (int)$_SESSION["teacher_id"];
$schoolId = (int)$_SESSION["teacher_school_id"];
$account = $conn->prepare("SELECT account_status FROM teachers WHERE id = ? AND school_id = ?");
$account->execute([$teacherId, $schoolId]);
if ($account->fetchColumn() !== "active") {
    session_unset();
    session_destroy();
    header("Location: login.php?error=Your+teacher+account+is+no+longer+active");
    exit();
}

if (empty($_SESSION["teacher_scores_csrf"])) {
    $_SESSION["teacher_scores_csrf"] = bin2hex(random_bytes(32));
}
$message = "";
$messageType = "";
$classId = (int)($_POST["class_id"] ?? $_GET["class_id"] ?? 0);
$subjectId = (int)($_POST["subject_id"] ?? $_GET["subject_id"] ?? 0);
$studentId = (int)($_POST["student_id"] ?? 0);
$yearId = (int)($_POST["academic_year_id"] ?? 0);
$termId = (int)($_POST["academic_term_id"] ?? 0);
$classScore = trim($_POST["class_score"] ?? "");
$examScore = trim($_POST["exam_score"] ?? "");

$assignmentQuery = $conn->prepare(
    "SELECT ta.class_id, ta.subject_id, c.class_name, s.subject_name
     FROM teacher_assignments ta
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     JOIN subjects s ON s.id = ta.subject_id AND s.school_id = ta.school_id
     WHERE ta.teacher_id = ? AND ta.school_id = ?
     ORDER BY c.class_name, s.subject_name"
);
$assignmentQuery->execute([$teacherId, $schoolId]);
$assignments = $assignmentQuery->fetchAll(PDO::FETCH_ASSOC);

$selectedAssignment = null;
foreach ($assignments as $assignment) {
    if ((int)$assignment["class_id"] === $classId && (int)$assignment["subject_id"] === $subjectId) {
        $selectedAssignment = $assignment;
        break;
    }
}
if (!$selectedAssignment && $assignments) {
    $selectedAssignment = $assignments[0];
    $classId = (int)$selectedAssignment["class_id"];
    $subjectId = (int)$selectedAssignment["subject_id"];
}

$students = [];
$years = [];
$terms = [];
if ($selectedAssignment) {
    $stmt = $conn->prepare(
        "SELECT id, student_id, first_name, last_name
         FROM students WHERE school_id = ? AND class_id = ?
         ORDER BY first_name, last_name"
    );
    $stmt->execute([$schoolId, $classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT id, year_name FROM academic_years WHERE school_id = ? ORDER BY id DESC");
    $stmt->execute([$schoolId]);
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT id, term_name, academic_year_id FROM academic_terms WHERE school_id = ? ORDER BY id DESC");
    $stmt->execute([$schoolId]);
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["teacher_scores_csrf"], $csrf)) {
        $message = "Your session expired. Reload the page and try again.";
        $messageType = "error";
    } elseif (!$selectedAssignment || $studentId < 1 || $yearId < 1 || $termId < 1 || $classScore === "" || $examScore === "") {
        $message = "Complete all score fields.";
        $messageType = "error";
    } elseif (!is_numeric($classScore) || !is_numeric($examScore) || (float)$classScore < 0 || (float)$classScore > 40 || (float)$examScore < 0 || (float)$examScore > 60) {
        $message = "Class score must be 0–40 and examination score 0–60.";
        $messageType = "error";
    } else {
        $studentCheck = $conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND school_id = ?");
        $studentCheck->execute([$studentId, $classId, $schoolId]);
        $yearCheck = $conn->prepare("SELECT id FROM academic_years WHERE id = ? AND school_id = ?");
        $yearCheck->execute([$yearId, $schoolId]);
        $termCheck = $conn->prepare("SELECT id FROM academic_terms WHERE id = ? AND academic_year_id = ? AND school_id = ?");
        $termCheck->execute([$termId, $yearId, $schoolId]);

        if (!$studentCheck->fetchColumn() || !$yearCheck->fetchColumn() || !$termCheck->fetchColumn()) {
            $message = "The selected student or academic period is not valid for this class.";
            $messageType = "error";
        } else {
            $classValue = (float)$classScore;
            $examValue = (float)$examScore;
            $total = $classValue + $examValue;
            if ($total >= 80) { $grade = "A"; $remarks = "Excellent"; }
            elseif ($total >= 70) { $grade = "B"; $remarks = "Very Good"; }
            elseif ($total >= 60) { $grade = "C"; $remarks = "Good"; }
            elseif ($total >= 50) { $grade = "D"; $remarks = "Credit"; }
            elseif ($total >= 40) { $grade = "E"; $remarks = "Pass"; }
            else { $grade = "F"; $remarks = "Fail"; }
            try {
                $save = $conn->prepare(
                    "INSERT INTO student_scores
                     (school_id, student_id, class_id, subject_id, academic_year_id, academic_term_id,
                      class_score, exam_score, total_score, grade, remarks)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE class_score = VALUES(class_score),
                        exam_score = VALUES(exam_score), total_score = VALUES(total_score),
                        grade = VALUES(grade), remarks = VALUES(remarks)"
                );
                $save->execute([$schoolId, $studentId, $classId, $subjectId, $yearId, $termId, $classValue, $examValue, $total, $grade, $remarks]);
                header("Location: scores.php?class_id=" . $classId . "&subject_id=" . $subjectId . "&saved=1");
                exit();
            } catch (PDOException $e) {
                error_log("Teacher score entry failed: " . $e->getMessage());
                $message = "Unable to save this score. Please try again.";
                $messageType = "error";
            }
        }
    }
}
if (isset($_GET["saved"])) {
    $message = "Score saved successfully.";
    $messageType = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Enter Scores – EduTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:25px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:900px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:18px}h1,h2{color:#10264b;margin-top:0}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}.field input,.field select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px}.button{padding:12px 18px;border:0;border-radius:7px;background:#183d70;color:#fff;font-weight:700;cursor:pointer;margin-top:16px}.notice{padding:12px;border-radius:7px;margin-bottom:15px}.error{background:#fff0f0;color:#b42318}.success{background:#ecfdf5;color:#166534}.actions a{color:#2563ab;text-decoration:none;margin-right:15px}@media(max-width:600px){.grid{grid-template-columns:1fr}body{padding:14px}}
</style>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
</head>
<body>
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page">
<div class="wrap">
  <section class="card"><h1>Enter Scores</h1><div class="actions"><a href="dashboard.php">← Teacher Dashboard</a><a href="class_report.php?class_id=<?= $classId ?>&amp;subject_id=<?= $subjectId ?>">View Class Report</a></div>
    <p class="teacher-page-intro">Record or update marks only for the classes and subjects assigned to you.</p>
    <?php if ($message): ?><div class="notice <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!$assignments): ?><p>You have no assigned classes or subjects. Contact your school administrator.</p>
    <?php else: ?>
    <form method="get" class="field"><label for="assignment">Assigned class and subject</label><select id="assignment"><?php foreach ($assignments as $assignment): ?><option value="class_id=<?= (int)$assignment["class_id"] ?>&amp;subject_id=<?= (int)$assignment["subject_id"] ?>" <?= ((int)$assignment["class_id"] === $classId && (int)$assignment["subject_id"] === $subjectId) ? "selected" : "" ?>><?= htmlspecialchars($assignment["class_name"] . " — " . $assignment["subject_name"]) ?></option><?php endforeach; ?></select></form>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["teacher_scores_csrf"]) ?>">
      <input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="subject_id" value="<?= $subjectId ?>">
      <div class="grid" style="margin-top:18px">
        <div class="field"><label for="student_id">Student</label><select id="student_id" name="student_id" required><option value="">Select student</option><?php foreach ($students as $student): ?><option value="<?= (int)$student["id"] ?>" <?= $studentId === (int)$student["id"] ? "selected" : "" ?>><?= htmlspecialchars($student["first_name"] . " " . $student["last_name"] . " (" . $student["student_id"] . ")") ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="academic_year_id">Academic year</label><select id="academic_year_id" name="academic_year_id" required><option value="">Select year</option><?php foreach ($years as $year): ?><option value="<?= (int)$year["id"] ?>" <?= $yearId === (int)$year["id"] ? "selected" : "" ?>><?= htmlspecialchars($year["year_name"]) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="academic_term_id">Term</label><select id="academic_term_id" name="academic_term_id" required><option value="">Select term</option><?php foreach ($terms as $term): ?><option value="<?= (int)$term["id"] ?>" data-year="<?= (int)$term["academic_year_id"] ?>" <?= $termId === (int)$term["id"] ? "selected" : "" ?>><?= htmlspecialchars($term["term_name"]) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="class_score">Class score (0–40)</label><input id="class_score" name="class_score" type="number" min="0" max="40" step="0.01" value="<?= htmlspecialchars($classScore) ?>" required></div>
        <div class="field"><label for="exam_score">Examination score (0–60)</label><input id="exam_score" name="exam_score" type="number" min="0" max="60" step="0.01" value="<?= htmlspecialchars($examScore) ?>" required></div>
      </div>
      <button class="button" type="submit">Save Score</button>
    </form>
    <?php endif; ?>
  </section>
</div>
</main>
</div>
<script>
const assignmentSelect=document.getElementById("assignment");
if(assignmentSelect){assignmentSelect.addEventListener("change",function(){location.href="scores.php?"+this.value});}
const yearSelect=document.getElementById("academic_year_id"),termSelect=document.getElementById("academic_term_id");
if(yearSelect&&termSelect){function filterTerms(){for(const option of termSelect.options){if(option.value)option.hidden=option.dataset.year!==yearSelect.value;}}yearSelect.addEventListener("change",filterTerms);filterTerms();}
</script>
</body></html>
