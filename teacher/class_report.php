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

$classId = (int)($_GET["class_id"] ?? 0);
$subjectId = (int)($_GET["subject_id"] ?? 0);
$yearId = (int)($_GET["year_id"] ?? 0);
$termId = (int)($_GET["term_id"] ?? 0);
$assignment = $conn->prepare(
    "SELECT c.class_name, s.subject_name FROM teacher_assignments ta
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     JOIN subjects s ON s.id = ta.subject_id AND s.school_id = ta.school_id
     WHERE ta.teacher_id = ? AND ta.school_id = ? AND ta.class_id = ? AND ta.subject_id = ?"
);
$assignment->execute([$teacherId, $schoolId, $classId, $subjectId]);
$assignment = $assignment->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
    http_response_code(403);
    exit("You do not have access to this class and subject.");
}
$yearsStmt = $conn->prepare("SELECT id, year_name FROM academic_years WHERE school_id = ? ORDER BY id DESC");
$yearsStmt->execute([$schoolId]);
$years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);
$termsStmt = $conn->prepare("SELECT id, term_name, academic_year_id FROM academic_terms WHERE school_id = ? ORDER BY id DESC");
$termsStmt->execute([$schoolId]);
$terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
$rows = [];
if ($yearId && $termId) {
    $termCheck = $conn->prepare("SELECT id FROM academic_terms WHERE id = ? AND academic_year_id = ? AND school_id = ?");
    $termCheck->execute([$termId, $yearId, $schoolId]);
    if ($termCheck->fetchColumn()) {
        $rowsStmt = $conn->prepare(
            "SELECT st.student_id, st.first_name, st.other_names, st.last_name, ss.class_score, ss.exam_score,
                    ss.total_score, ss.grade
             FROM students st
             LEFT JOIN student_scores ss ON ss.student_id = st.id AND ss.school_id = st.school_id
                AND ss.class_id = st.class_id AND ss.subject_id = ?
                AND ss.academic_year_id = ? AND ss.academic_term_id = ?
             WHERE st.school_id = ? AND st.class_id = ?
             ORDER BY st.first_name, st.last_name"
        );
        $rowsStmt->execute([$subjectId, $yearId, $termId, $schoolId, $classId]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Class Report – EduTrack</title>
<style>*{box-sizing:border-box}body{margin:0;padding:25px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:1000px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px}h1{color:#10264b}.filters{display:flex;gap:12px;flex-wrap:wrap;margin:15px 0}.filters select,.filters button{padding:10px;border:1px solid #cbd5e1;border-radius:7px}.filters button,.back{background:#183d70;color:#fff;border:0;border-radius:7px;padding:10px 14px;text-decoration:none}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase}.tools{margin:15px 0}.tools a{margin-right:12px;color:#2563ab;text-decoration:none}@media print{body{background:#fff;padding:0}.tools,.filters{display:none}.card{border:0}}</style>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
</head>
<body>
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page">
<div class="wrap"><section class="card">
<div class="tools"><a href="dashboard.php">← Teacher Dashboard</a><a href="scores.php?class_id=<?= $classId ?>&amp;subject_id=<?= $subjectId ?>">Enter Scores</a><button type="button" onclick="window.print()">Print</button></div>
<h1><?= htmlspecialchars($assignment["class_name"] . " — " . $assignment["subject_name"]) ?> Class Report</h1>
<p class="teacher-page-intro">Scores for students in this class and your assigned subject.</p>
<form class="filters" method="get"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="subject_id" value="<?= $subjectId ?>">
<select name="year_id" required><option value="">Academic year</option><?php foreach ($years as $year): ?><option value="<?= (int)$year["id"] ?>" <?= $yearId === (int)$year["id"] ? "selected" : "" ?>><?= htmlspecialchars($year["year_name"]) ?></option><?php endforeach; ?></select>
<select name="term_id" required><option value="">Term</option><?php foreach ($terms as $term): ?><option value="<?= (int)$term["id"] ?>" data-year="<?= (int)$term["academic_year_id"] ?>" <?= $termId === (int)$term["id"] ? "selected" : "" ?>><?= htmlspecialchars($term["term_name"]) ?></option><?php endforeach; ?></select>
<button type="submit">View Report</button></form>
<?php if ($rows): ?><div class="table-scroll"><table><thead><tr><th>Admission No.</th><th>Student</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Grade</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row["student_id"]) ?></td><td><?= htmlspecialchars(trim($row["first_name"] . " " . ($row["other_names"] ?? "") . " " . $row["last_name"])) ?></td><td><?= $row["class_score"] === null ? "—" : number_format((float)$row["class_score"], 1) ?></td><td><?= $row["exam_score"] === null ? "—" : number_format((float)$row["exam_score"], 1) ?></td><td><?= $row["total_score"] === null ? "—" : number_format((float)$row["total_score"], 1) ?></td><td><?= htmlspecialchars($row["grade"] ?? "—") ?></td></tr><?php endforeach; ?></tbody></table></div><?php elseif ($yearId && $termId): ?><p>No students or scores found for the selected academic period.</p><?php else: ?><p>Select an academic year and term to view scores for your assigned class and subject.</p><?php endif; ?>
</section></div></main></div>
<script>const y=document.querySelector('[name="year_id"]'),t=document.querySelector('[name="term_id"]');function filter(){for(const o of t.options){if(o.value)o.hidden=o.dataset.year!==y.value}}y.addEventListener("change",filter);filter();</script>
</body></html>
