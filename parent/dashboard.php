<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . "/../config/database.php";

$parentStmt = $conn->prepare("SELECT id, full_name, email FROM parents WHERE id = ? LIMIT 1");
$parentStmt->execute([(int)$_SESSION["parent_id"]]);
$parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?error=Account+not+found");
    exit();
}

$parentName = htmlspecialchars($parent["full_name"]);
$childrenStmt = $conn->prepare(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, c.class_name, sc.school_name
     FROM students s
     LEFT JOIN classes c ON c.id = s.class_id AND c.school_id = s.school_id
     LEFT JOIN schools sc ON sc.id = s.school_id
     WHERE s.parent_email = ?
     ORDER BY s.first_name, s.last_name"
);
$childrenStmt->execute([$parent["email"]]);
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

$averageStmt = $conn->prepare(
    "SELECT AVG(ss.total_score)
     FROM student_scores ss
     JOIN students s ON s.id = ss.student_id AND s.school_id = ss.school_id
     WHERE s.parent_email = ?"
);
$averageStmt->execute([$parent["email"]]);
$averageScore = $averageStmt->fetchColumn();

$gradesStmt = $conn->prepare(
    "SELECT s.first_name, s.last_name, sub.subject_name, ay.year_name, at.term_name,
            ss.class_score, ss.exam_score, ss.total_score, ss.grade
     FROM student_scores ss
     JOIN students s ON s.id = ss.student_id AND s.school_id = ss.school_id
     JOIN subjects sub ON sub.id = ss.subject_id AND sub.school_id = ss.school_id
     JOIN academic_years ay ON ay.id = ss.academic_year_id AND ay.school_id = ss.school_id
     JOIN academic_terms at ON at.id = ss.academic_term_id AND at.school_id = ss.school_id
     WHERE s.parent_email = ?
     ORDER BY ss.created_at DESC
     LIMIT 30"
);
$gradesStmt->execute([$parent["email"]]);
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);
$attendanceStmt = $conn->prepare(
    "SELECT s.first_name, s.last_name, ta.attendance_date, ta.status, c.class_name
     FROM teacher_attendance ta
     JOIN students s ON s.id = ta.student_id AND s.school_id = ta.school_id
     LEFT JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     WHERE LOWER(s.parent_email) = LOWER(?) AND s.school_id = ta.school_id
     ORDER BY ta.attendance_date DESC, s.first_name, s.last_name
     LIMIT 30"
);
$attendanceStmt->execute([$parent["email"]]);
$attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Parent Dashboard – EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--green:#16865b;--gold:#e9b949;--white:#ffffff;--background:#f5f7fb;--muted:#68778d;--border:#e6ebf2;--red:#b42318;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:var(--background);}
.topnav{background:var(--navy);height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:fixed;top:0;left:0;right:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.2);}
.topnav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.topnav-logo .li{width:34px;height:34px;background:var(--gold);border-radius:7px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;color:var(--navy);}
.topnav-logo span{font-size:1.1rem;font-weight:800;color:var(--white);letter-spacing:2px;}
.topnav-right{display:flex;align-items:center;gap:18px;font-size:.88rem;}
.topnav-right .uname{color:rgba(255,255,255,.8);}
.topnav-right a{color:var(--red);text-decoration:none;font-weight:700;background:rgba(180,35,24,.12);padding:6px 14px;border-radius:6px;}
.topnav-right a:hover{background:rgba(180,35,24,.22);}
.sidebar{position:fixed;top:60px;left:0;bottom:0;width:240px;background:var(--navy);padding:20px 0;overflow-y:auto;}
.sidebar-label{padding:0 20px 6px;font-size:.7rem;font-weight:700;letter-spacing:1px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-top:14px;}
.sidebar a{display:flex;align-items:center;gap:12px;padding:11px 20px;color:rgba(255,255,255,.75);text-decoration:none;font-size:.9rem;transition:all .2s;}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.08);color:var(--white);}
.sidebar a.active{border-left:3px solid var(--gold);padding-left:17px;}
.sidebar .s-icon{font-size:1rem;width:20px;text-align:center;}
.sidebar .logout-link{color:#ff8080 !important;}
.main{margin-left:240px;margin-top:60px;padding:30px;}
.welcome-card{background:linear-gradient(135deg,var(--green),var(--navy));border-radius:14px;padding:26px 30px;color:var(--white);margin-bottom:24px;}
.welcome-card h2{font-size:1.4rem;font-weight:800;margin-bottom:5px;}
.welcome-card p{color:rgba(255,255,255,.75);font-size:.9rem;}
.info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.info-card{background:var(--white);border-radius:12px;padding:20px;border:1px solid var(--border);}
.info-card .lbl{font-size:.75rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.info-card .val{font-size:1.4rem;font-weight:800;color:var(--navy);}
.section-card{background:var(--white);border-radius:12px;padding:22px;border:1px solid var(--border);margin-bottom:20px;}
.section-card h3{font-size:.95rem;font-weight:700;color:var(--navy);margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border);}
table{width:100%;border-collapse:collapse;font-size:.87rem;}
th{text-align:left;padding:10px 12px;background:var(--background);color:var(--muted);font-weight:600;font-size:.78rem;text-transform:uppercase;}
td{padding:12px;border-bottom:1px solid var(--border);color:#1a2535;}
.empty-note{color:var(--muted);font-size:.88rem;padding:20px 0;text-align:center;}
@media(max-width:768px){.info-grid{grid-template-columns:1fr 1fr;}.sidebar{display:none;}.main{margin-left:0;}}
</style>
<link rel="stylesheet" href="../Assets/css/family-dashboard.css?v=<?php echo filemtime(__DIR__ . "/../Assets/css/family-dashboard.css"); ?>">
</head>
<body class="family-dashboard parent-dashboard">
<nav class="topnav">
  <a href="../index.php" class="topnav-logo">
    <div class="li">E</div>
    <span>EDUTRACK</span>
  </a>
  <div class="topnav-right">
    <span class="uname">👨‍👩‍👧 <?= $parentName ?></span>
    <a href="logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar">
  <div class="sidebar-label">Menu</div>
  <a href="dashboard.php" class="active"><span class="s-icon">🏠</span> Dashboard</a>
  <a href="#children"><span class="s-icon">📈</span> Linked Students</a>
  <a href="#recent-grades"><span class="s-icon">📄</span> Recent Grades</a>
  <a href="#attendance"><span class="s-icon">📅</span> Attendance</a>
  <div class="sidebar-label">Account</div>
  <a href="#"><span class="s-icon">👤</span> My Profile</a>
  <a href="logout.php" class="logout-link"><span class="s-icon">🚪</span> Logout</a>
</div>
<main class="main">
  <div class="welcome-card">
    <h2>Welcome, <?= $parentName ?>! 👨‍👩‍👧</h2>
    <p>Only students linked to <?= htmlspecialchars($parent["email"]) ?> are shown here.</p>
  </div>
  <div class="info-grid">
    <div class="info-card"><div class="lbl">Linked Students</div><div class="val"><?= count($children) ?></div></div>
    <div class="info-card"><div class="lbl">Recent Scores</div><div class="val"><?= count($grades) ?></div></div>
    <div class="info-card"><div class="lbl">Average Score</div><div class="val"><?= $averageScore !== false && $averageScore !== null ? number_format((float)$averageScore, 1) . "%" : "–" ?></div></div>
    <div class="info-card"><div class="lbl">Recent Attendance</div><div class="val"><?= count($attendanceRecords) ?></div></div>
  </div>
  <div class="section-card" id="children">
    <h3>Linked Students</h3>
    <?php if ($children): ?>
      <table>
        <thead><tr><th>Student</th><th>Admission Number</th><th>Class</th><th>School</th><th>Report</th></tr></thead>
        <tbody>
          <?php foreach ($children as $child): ?>
            <tr>
              <td><?= htmlspecialchars($child["first_name"] . " " . $child["last_name"]) ?></td>
              <td><?= htmlspecialchars($child["student_id"]) ?></td>
              <td><?= htmlspecialchars($child["class_name"] ?? "Unassigned") ?></td>
              <td><?= htmlspecialchars($child["school_name"] ?? "—") ?></td>
              <td><a href="../portal/report_card.php?student_id=<?= (int)$child["id"] ?>">View report card</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-note">No students are linked to your account yet. Contact your school admin.</div>
    <?php endif; ?>
  </div>
  <div class="section-card" id="recent-grades">
    <h3>Recent Grades</h3>
    <table>
      <thead><tr><th>Student</th><th>Subject</th><th>Academic Period</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Grade</th></tr></thead>
      <tbody>
        <?php if ($grades): ?>
          <?php foreach ($grades as $grade): ?>
            <tr>
              <td><?= htmlspecialchars($grade["first_name"] . " " . $grade["last_name"]) ?></td>
              <td><?= htmlspecialchars($grade["subject_name"]) ?></td>
              <td><?= htmlspecialchars($grade["year_name"] . " · " . $grade["term_name"]) ?></td>
              <td><?= number_format((float)$grade["class_score"], 1) ?></td>
              <td><?= number_format((float)$grade["exam_score"], 1) ?></td>
              <td><strong><?= number_format((float)$grade["total_score"], 1) ?></strong></td>
              <td><?= htmlspecialchars($grade["grade"] ?? "—") ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="empty-note">Grades will appear here when the school records scores for your linked students.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="section-card" id="attendance">
    <h3>Recent Attendance</h3>
    <table>
      <thead><tr><th>Student</th><th>Date</th><th>Class</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($attendanceRecords): ?>
          <?php foreach ($attendanceRecords as $record): ?>
            <tr>
              <td><?= htmlspecialchars($record["first_name"] . " " . $record["last_name"]) ?></td>
              <td><?= htmlspecialchars($record["attendance_date"]) ?></td>
              <td><?= htmlspecialchars($record["class_name"] ?? "Class") ?></td>
              <td><?= htmlspecialchars(ucfirst($record["status"])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" class="empty-note">Attendance will appear here when a teacher marks a linked student's class.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<script src="../Assets/js/family-dashboard.js?v=<?php echo filemtime(__DIR__ . "/../Assets/js/family-dashboard.js"); ?>" defer></script>
</body>
</html>

