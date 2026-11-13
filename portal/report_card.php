<?php
session_start();
require_once __DIR__ . "/../config/database.php";
$role = $_SESSION["role"] ?? "";
if (!in_array($role, ["student", "parent"], true)) {
    header("Location: ../index.php");
    exit();
}

$studentId = $role === "student" ? (int)($_SESSION["student_id"] ?? 0) : (int)($_GET["student_id"] ?? 0);
$studentSql = "SELECT st.id, st.school_id, st.student_id AS admission_number,
                      st.first_name, st.last_name, st.other_names,
                      c.class_name, sc.school_name
               FROM students st
               LEFT JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
               LEFT JOIN schools sc ON sc.id = st.school_id
               WHERE st.id = ?";
$studentParams = [$studentId];

if ($role === "student") {
    $studentSql .= " AND st.school_id = ?";
    $studentParams[] = (int)($_SESSION["student_school_id"] ?? 0);
} else {
    $parentStmt = $conn->prepare("SELECT email FROM parents WHERE id = ? LIMIT 1");
    $parentStmt->execute([(int)($_SESSION["parent_id"] ?? 0)]);
    $parentEmail = $parentStmt->fetchColumn();
    if (!$parentEmail) {
        http_response_code(403);
        exit("Parent account was not found.");
    }
    $studentSql .= " AND LOWER(st.parent_email) = ?";
    $studentParams[] = strtolower($parentEmail);
}
$studentStmt = $conn->prepare($studentSql . " LIMIT 1");
$studentStmt->execute($studentParams);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(403);
    exit("You do not have access to this student report.");
}
$schoolId = (int)$student["school_id"];

$yearsStmt = $conn->prepare("SELECT id, year_name FROM academic_years WHERE school_id = ? ORDER BY id DESC");
$yearsStmt->execute([$schoolId]);
$years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);
$termsStmt = $conn->prepare("SELECT id, term_name, academic_year_id FROM academic_terms WHERE school_id = ? ORDER BY id DESC");
$termsStmt->execute([$schoolId]);
$terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
$yearId = (int)($_GET["year_id"] ?? 0);
$termId = (int)($_GET["term_id"] ?? 0);
$rows = [];
$period = null;
if ($yearId && $termId) {
    $periodStmt = $conn->prepare(
        "SELECT ay.year_name, at.term_name
         FROM academic_terms at
         JOIN academic_years ay ON ay.id = at.academic_year_id AND ay.school_id = at.school_id
         WHERE at.id = ? AND at.academic_year_id = ? AND at.school_id = ?"
    );
    $periodStmt->execute([$termId, $yearId, $schoolId]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
    if ($period) {
        $rowsStmt = $conn->prepare(
            "SELECT sub.subject_name, ss.class_score, ss.exam_score,
                    ss.total_score, ss.grade, ss.remarks
             FROM student_scores ss
             JOIN subjects sub ON sub.id = ss.subject_id AND sub.school_id = ss.school_id
             WHERE ss.student_id = ? AND ss.school_id = ?
               AND ss.academic_year_id = ? AND ss.academic_term_id = ?
             ORDER BY sub.subject_name"
        );
        $rowsStmt->execute([$studentId, $schoolId, $yearId, $termId]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
$average = null;
if ($rows) {
    $average = array_sum(array_map(static function ($row) {
        return (float)$row["total_score"];
    }, $rows)) / count($rows);
}
$back = $role === "student" ? "../student/dashboard.php" : "../parent/dashboard.php";
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Report Card – EduTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:950px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:26px}.nav{display:flex;gap:14px;align-items:center;margin-bottom:20px}.nav a{color:#2563ab;text-decoration:none}.heading{border-bottom:2px solid #e2e8f0;padding-bottom:16px;margin-bottom:20px}.heading h1{color:#10264b;margin:0 0 7px}.heading p{margin:4px 0;color:#64748b}.filters{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}.filters select,.filters button{padding:10px;border:1px solid #cbd5e1;border-radius:7px}.filters button,.print{background:#183d70;color:#fff;border:0;border-radius:7px;padding:10px 14px;text-decoration:none}.summary{margin:18px 0;padding:14px;background:#eff6ff;border-radius:8px;color:#183d70}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase}.empty{padding:20px;color:#64748b;text-align:center}@media print{body{background:#fff;padding:0}.nav,.filters{display:none}.card{border:0}}
</style></head>
<body><main class="wrap"><section class="card">
<nav class="nav"><a href="<?= htmlspecialchars($back) ?>">← Back to portal</a><button class="print" type="button" onclick="window.print()">Print report</button></nav>
<header class="heading"><h1>Academic Report Card</h1>
<p><strong><?= htmlspecialchars(trim(($student["first_name"] ?? "") . " " . ($student["other_names"] ?? "") . " " . ($student["last_name"] ?? ""))) ?></strong></p>
<p><?= htmlspecialchars($student["school_name"] ?? "School") ?> · <?= htmlspecialchars($student["class_name"] ?? "Class not assigned") ?> · Admission No. <?= htmlspecialchars($student["admission_number"]) ?></p>
<?php if ($period): ?><p><?= htmlspecialchars($period["year_name"] . " · " . $period["term_name"]) ?></p><?php endif; ?></header>
<form class="filters" method="get"><?php if ($role === "parent"): ?><input type="hidden" name="student_id" value="<?= $studentId ?>"><?php endif; ?>
<select name="year_id" required><option value="">Academic year</option><?php foreach ($years as $year): ?><option value="<?= (int)$year["id"] ?>" <?= $yearId === (int)$year["id"] ? "selected" : "" ?>><?= htmlspecialchars($year["year_name"]) ?></option><?php endforeach; ?></select>
<select name="term_id" required><option value="">Term</option><?php foreach ($terms as $term): ?><option value="<?= (int)$term["id"] ?>" data-year="<?= (int)$term["academic_year_id"] ?>" <?= $termId === (int)$term["id"] ? "selected" : "" ?>><?= htmlspecialchars($term["term_name"]) ?></option><?php endforeach; ?></select>
<button type="submit">View report</button></form>
<?php if ($period): ?><div class="summary">Subjects recorded: <strong><?= count($rows) ?></strong><?php if ($average !== null): ?> · Average score: <strong><?= number_format($average, 1) ?>%</strong><?php endif; ?></div>
<table><thead><tr><th>Subject</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Grade</th><th>Remarks</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row["subject_name"]) ?></td><td><?= number_format((float)$row["class_score"], 1) ?></td><td><?= number_format((float)$row["exam_score"], 1) ?></td><td><strong><?= number_format((float)$row["total_score"], 1) ?></strong></td><td><?= htmlspecialchars($row["grade"] ?? "—") ?></td><td><?= htmlspecialchars($row["remarks"] ?? "—") ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td class="empty" colspan="6">No scores have been recorded for this academic period.</td></tr><?php endif; ?></tbody></table>
<?php else: ?><p class="empty"><?= ($yearId || $termId) ? "Select a valid academic year and term." : "Choose an academic year and term to view the report card." ?></p><?php endif; ?>
</section></main>
<script>const y=document.querySelector('[name="year_id"]'),t=document.querySelector('[name="term_id"]');function filter(){for(const o of t.options){if(o.value)o.hidden=o.dataset.year!==y.value}}y.addEventListener("change",filter);filter();</script>
</body></html>
