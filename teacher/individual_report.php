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

$studentsStmt = $conn->prepare(
    "SELECT DISTINCT st.id, st.student_id, st.first_name, st.other_names, st.last_name,
            st.class_id, c.class_name, sc.school_name
     FROM students st
     JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
     JOIN schools sc ON sc.id = st.school_id
     WHERE st.school_id = ?
       AND EXISTS (
           SELECT 1 FROM teacher_assignments ta
           WHERE ta.teacher_id = ? AND ta.school_id = st.school_id AND ta.class_id = st.class_id
       )
     ORDER BY c.class_name, st.first_name, st.last_name"
);
$studentsStmt->execute([$schoolId, $teacherId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$yearsStmt = $conn->prepare("SELECT id, year_name FROM academic_years WHERE school_id = ? ORDER BY id DESC");
$yearsStmt->execute([$schoolId]);
$years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);
$termsStmt = $conn->prepare("SELECT id, term_name, academic_year_id FROM academic_terms WHERE school_id = ? ORDER BY id DESC");
$termsStmt->execute([$schoolId]);
$terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);

$studentId = filter_var($_GET["student_id"] ?? null, FILTER_VALIDATE_INT);
$yearId = filter_var($_GET["year_id"] ?? null, FILTER_VALIDATE_INT);
$termId = filter_var($_GET["term_id"] ?? null, FILTER_VALIDATE_INT);
$selectedStudent = null;
$period = null;
$rows = [];
$average = null;
$error = "";

if ($studentId && $studentId > 0) {
    foreach ($students as $student) {
        if ((int)$student["id"] === $studentId) {
            $selectedStudent = $student;
            break;
        }
    }
    if (!$selectedStudent) {
        http_response_code(403);
        exit("You do not have access to this student's report.");
    }
}

if ($selectedStudent && $yearId && $yearId > 0 && $termId && $termId > 0) {
    $periodStmt = $conn->prepare(
        "SELECT ay.year_name, at.term_name
         FROM academic_terms at
         JOIN academic_years ay ON ay.id = at.academic_year_id AND ay.school_id = at.school_id
         WHERE at.id = ? AND at.academic_year_id = ? AND at.school_id = ?"
    );
    $periodStmt->execute([$termId, $yearId, $schoolId]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
    if (!$period) {
        $error = "Choose a valid academic year and term.";
    } else {
        $reportStmt = $conn->prepare(
            "SELECT sub.subject_name, ss.class_score, ss.exam_score, ss.total_score, ss.grade, ss.remarks
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id = ta.subject_id AND sub.school_id = ta.school_id
             LEFT JOIN student_scores ss
               ON ss.school_id = ta.school_id
              AND ss.class_id = ta.class_id
              AND ss.subject_id = ta.subject_id
              AND ss.student_id = ?
              AND ss.academic_year_id = ?
              AND ss.academic_term_id = ?
             WHERE ta.teacher_id = ? AND ta.school_id = ? AND ta.class_id = ?
             ORDER BY sub.subject_name"
        );
        $reportStmt->execute([
            (int)$selectedStudent["id"],
            $yearId,
            $termId,
            $teacherId,
            $schoolId,
            (int)$selectedStudent["class_id"]
        ]);
        $rows = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
        $scoredRows = array_filter($rows, static function ($row) {
            return $row["total_score"] !== null;
        });
        if ($scoredRows) {
            $average = array_sum(array_map(static function ($row) {
                return (float)$row["total_score"];
            }, $scoredRows)) / count($scoredRows);
        }
    }
}

$teacherName = htmlspecialchars((string)($_SESSION["teacher_name"] ?? "Teacher"), ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Individual Student Report – EduTrack</title>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
<style>
.report-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-bottom:15px;margin-bottom:16px}.report-heading p{margin:5px 0;color:#64748b}.report-summary{display:flex;gap:12px;flex-wrap:wrap;margin:15px 0}.report-summary span{padding:10px 13px;border-radius:8px;color:#183d70;background:#eff6ff}.report-table{overflow-x:auto}.report-table table{min-width:650px}@media print{.report-heading{border-color:#94a3b8}}
</style>
</head>
<body>
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page">
<div class="wrap">
  <h1>Individual Student Report</h1>
  <p class="teacher-page-intro">Generate a printable report for students in your assigned classes and subjects.</p>
  <section class="card no-print">
    <h2>Select Student and Academic Period</h2>
    <?php if ($error !== ""): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$students): ?>
      <p class="empty">There are no students in your assigned classes yet.</p>
    <?php else: ?>
      <form class="filters" method="get">
        <label for="student_id">Student
          <select id="student_id" name="student_id" required>
            <option value="">Select a student</option>
            <?php foreach ($students as $student): ?>
              <option value="<?= (int)$student["id"] ?>" <?= $studentId === (int)$student["id"] ? "selected" : "" ?>>
                <?= htmlspecialchars($student["class_name"] . " — " . trim($student["first_name"] . " " . ($student["other_names"] ?? "") . " " . $student["last_name"]) . " (" . $student["student_id"] . ")") ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label for="year_id">Academic year
          <select id="year_id" name="year_id" required>
            <option value="">Select year</option>
            <?php foreach ($years as $year): ?>
              <option value="<?= (int)$year["id"] ?>" <?= $yearId === (int)$year["id"] ? "selected" : "" ?>><?= htmlspecialchars($year["year_name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label for="term_id">Term
          <select id="term_id" name="term_id" required>
            <option value="">Select term</option>
            <?php foreach ($terms as $term): ?>
              <option value="<?= (int)$term["id"] ?>" data-year="<?= (int)$term["academic_year_id"] ?>" <?= $termId === (int)$term["id"] ? "selected" : "" ?>><?= htmlspecialchars($term["term_name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit">Generate Report</button>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($selectedStudent): ?>
    <section class="card">
      <div class="report-heading">
        <div>
          <h2><?= htmlspecialchars(trim($selectedStudent["first_name"] . " " . ($selectedStudent["other_names"] ?? "") . " " . $selectedStudent["last_name"])) ?></h2>
          <p><?= htmlspecialchars($selectedStudent["school_name"] . " · " . $selectedStudent["class_name"]) ?> · Admission No. <?= htmlspecialchars($selectedStudent["student_id"]) ?></p>
          <?php if ($period): ?><p><?= htmlspecialchars($period["year_name"] . " · " . $period["term_name"]) ?></p><?php endif; ?>
        </div>
        <?php if ($period): ?><button class="button no-print" type="button" onclick="window.print()">Print Report</button><?php endif; ?>
      </div>
      <?php if ($period): ?>
        <?php $scoredCount = count(array_filter($rows, static function ($row) { return $row["total_score"] !== null; })); ?>
        <div class="report-summary">
          <span>Assigned subjects: <strong><?= count($rows) ?></strong></span>
          <span>Scores recorded: <strong><?= $scoredCount ?></strong></span>
          <?php if ($average !== null): ?><span>Average: <strong><?= number_format($average, 1) ?>%</strong></span><?php endif; ?>
        </div>
        <div class="report-table"><table>
          <thead><tr><th>Subject</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Grade</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["subject_name"]) ?></td>
                <td><?= $row["class_score"] === null ? "—" : number_format((float)$row["class_score"], 1) ?></td>
                <td><?= $row["exam_score"] === null ? "—" : number_format((float)$row["exam_score"], 1) ?></td>
                <td><?= $row["total_score"] === null ? "—" : number_format((float)$row["total_score"], 1) ?></td>
                <td><?= htmlspecialchars($row["grade"] ?? "—") ?></td>
                <td><?= htmlspecialchars($row["remarks"] ?? "—") ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td class="empty" colspan="6">There are no assigned subjects for this class.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
        <p class="teacher-page-intro">Prepared by <?= $teacherName ?>.</p>
      <?php else: ?>
        <p class="empty">Choose an academic year and term to generate this student's report.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
</main>
</div>
<script>
const yearSelect = document.getElementById("year_id");
const termSelect = document.getElementById("term_id");
if (yearSelect && termSelect) {
  function filterTerms() {
    for (const option of termSelect.options) {
      if (option.value) option.hidden = option.dataset.year !== yearSelect.value;
    }
    if (termSelect.selectedOptions.length && termSelect.selectedOptions[0].hidden) termSelect.value = "";
  }
  yearSelect.addEventListener("change", filterTerms);
  filterTerms();
}
</script>
</body>
</html>
