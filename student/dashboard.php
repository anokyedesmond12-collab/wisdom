<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . "/../config/database.php";

$studentStmt = $conn->prepare(
    "SELECT s.id, s.school_id, s.student_id, s.first_name, s.last_name,
            s.other_names, c.class_name, sc.school_name
     FROM students s
     LEFT JOIN classes c ON c.id = s.class_id AND c.school_id = s.school_id
     LEFT JOIN schools sc ON sc.id = s.school_id
     WHERE s.id = ?
     LIMIT 1"
);
$studentStmt->execute([(int)$_SESSION["student_id"]]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?error=Account+not+found");
    exit();
}

$studentName = htmlspecialchars(trim(
    $student["first_name"] . " " .
    ($student["other_names"] ?? "") . " " .
    $student["last_name"]
));

$activeTermStmt = $conn->prepare(
    "SELECT ay.year_name, at.term_name
     FROM academic_years ay
     LEFT JOIN academic_terms at
       ON at.academic_year_id = ay.id AND at.school_id = ay.school_id AND at.status = 'Active'
     WHERE ay.school_id = ? AND ay.status = 'Active'
     LIMIT 1"
);
$activeTermStmt->execute([(int)$student["school_id"]]);
$activeTerm = $activeTermStmt->fetch(PDO::FETCH_ASSOC);

$averageStmt = $conn->prepare(
    "SELECT AVG(total_score)
     FROM student_scores
     WHERE student_id = ? AND school_id = ?"
);
$averageStmt->execute([(int)$student["id"], (int)$student["school_id"]]);
$averageScore = $averageStmt->fetchColumn();

$gradesStmt = $conn->prepare(
    "SELECT sub.subject_name, ay.year_name, at.term_name,
            ss.class_score, ss.exam_score, ss.total_score, ss.grade
     FROM student_scores ss
     JOIN subjects sub ON sub.id = ss.subject_id AND sub.school_id = ss.school_id
     JOIN academic_years ay ON ay.id = ss.academic_year_id AND ay.school_id = ss.school_id
     JOIN academic_terms at ON at.id = ss.academic_term_id AND at.school_id = ss.school_id
     WHERE ss.student_id = ? AND ss.school_id = ?
     ORDER BY ss.created_at DESC
     LIMIT 20"
);
$gradesStmt->execute([(int)$student["id"], (int)$student["school_id"]]);
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);
$attendanceStmt = $conn->prepare(
    "SELECT ta.attendance_date, ta.status, c.class_name
     FROM teacher_attendance ta
     LEFT JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     WHERE ta.student_id = ? AND ta.school_id = ?
     ORDER BY ta.attendance_date DESC
     LIMIT 20"
);
$attendanceStmt->execute([(int)$student["id"], (int)$student["school_id"]]);
$attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Student Dashboard – EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--blue-light:#2563ab;--gold:#e9b949;--white:#ffffff;--background:#f5f7fb;--muted:#68778d;--border:#e6ebf2;--red:#b42318;}
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
.welcome-card{background:linear-gradient(135deg,var(--blue-light),var(--navy));border-radius:14px;padding:26px 30px;color:var(--white);margin-bottom:24px;}
.welcome-card h2{font-size:1.4rem;font-weight:800;margin-bottom:5px;}
.welcome-card p{color:rgba(255,255,255,.75);font-size:.9rem;}
.info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.info-card{background:var(--white);border-radius:12px;padding:20px;border:1px solid var(--border);}
.info-card .lbl{font-size:.75rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.info-card .val{font-size:1.4rem;font-weight:800;color:var(--navy);}
.section-card{background:var(--white);border-radius:12px;padding:22px;border:1px solid var(--border);margin-bottom:20px;}
.quick-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
.quick-action{display:block;padding:15px;border:1px solid var(--border);border-radius:10px;background:#fff;color:var(--navy);text-decoration:none;font-weight:700}
.quick-action small{display:block;margin-top:5px;color:var(--muted);font-weight:400}
.quick-action:hover{border-color:var(--blue-light);box-shadow:0 4px 14px rgba(16,38,75,.08)}
.section-card h3{font-size:.95rem;font-weight:700;color:var(--navy);margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border);}
table{width:100%;border-collapse:collapse;font-size:.87rem;}
th{text-align:left;padding:10px 12px;background:var(--background);color:var(--muted);font-weight:600;font-size:.78rem;text-transform:uppercase;}
td{padding:12px;border-bottom:1px solid var(--border);color:#1a2535;}
.empty-note{color:var(--muted);font-size:.88rem;padding:20px 0;text-align:center;}
@media(max-width:768px){.info-grid{grid-template-columns:1fr 1fr;}.sidebar{display:none;}.main{margin-left:0;}}
@media(max-width:900px){.quick-actions{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.quick-actions{grid-template-columns:1fr;}}
</style>
<link rel="stylesheet" href="../Assets/css/family-dashboard.css?v=<?php echo filemtime(__DIR__ . "/../Assets/css/family-dashboard.css"); ?>">
</head>
<body class="family-dashboard student-dashboard">
<nav class="topnav">
  <a href="../index.php" class="topnav-logo">
    <div class="li">E</div>
    <span>EDUTRACK</span>
  </a>
  <div class="topnav-right">
    <span class="uname">🎓 <?= $studentName ?></span>
    <a href="logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar">
  <div class="sidebar-label">Menu</div>
  <a href="dashboard.php" class="active"><span class="s-icon">🏠</span> Dashboard</a>
  <a href="#recent-grades"><span class="s-icon">📊</span> My Grades</a>
  <a href="../portal/report_card.php"><span class="s-icon">📄</span> My Report Card</a>
  <a href="#attendance"><span class="s-icon">📅</span> My Attendance</a>
  <div class="sidebar-label">Account</div>
  <a href="profile.php"><span class="s-icon">👤</span> My Profile & Password</a>
  <a href="logout.php" class="logout-link"><span class="s-icon">🚪</span> Logout</a>
</div>
<main class="main">
  <div class="welcome-card">
    <h2>Welcome, <?= $studentName ?>! 🎓</h2>
    <p><?= htmlspecialchars($student["school_name"] ?? "Your school") ?> · <?= htmlspecialchars($student["class_name"] ?? "Class not assigned") ?> · Admission number <?= htmlspecialchars($student["student_id"]) ?></p>
    <p><a href="../portal/report_card.php" style="color:#fff;font-weight:700;">Open or print report card →</a></p>
  </div>
  <div class="info-grid">
    <div class="info-card"><div class="lbl">Current Term</div><div class="val" style="font-size:1rem;color:var(--muted);"><?= htmlspecialchars(trim(($activeTerm["year_name"] ?? "Not set") . " · " . ($activeTerm["term_name"] ?? ""))) ?></div></div>
    <div class="info-card"><div class="lbl">Average Score</div><div class="val"><?= $averageScore !== false && $averageScore !== null ? number_format((float)$averageScore, 1) . "%" : "–" ?></div></div>
    <div class="info-card"><div class="lbl">Recent Score Records</div><div class="val"><?= count($grades) ?></div></div>
    <div class="info-card"><div class="lbl">Recent Attendance</div><div class="val"><?= count($attendanceRecords) ?></div></div>
  </div>
  <section class="quick-actions" aria-label="Student tools">
    <a class="quick-action" href="#recent-grades">View My Grades<small>See your latest subject scores</small></a>
    <a class="quick-action" href="../portal/report_card.php">Open Report Card<small>Choose a term and print your report</small></a>
    <a class="quick-action" href="#attendance">View Attendance<small>Check your recent attendance record</small></a>
    <a class="quick-action" href="profile.php">Profile & Password<small>Review your details or change your password</small></a>
  </section>
  <div class="section-card" id="recent-grades">
    <h3>Recent Grades</h3>
    <table>
      <thead><tr><th>Subject</th><th>Academic Period</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Grade</th></tr></thead>
      <tbody>
        <?php if ($grades): ?>
          <?php foreach ($grades as $grade): ?>
            <tr>
              <td><?= htmlspecialchars($grade["subject_name"]) ?></td>
              <td><?= htmlspecialchars($grade["year_name"] . " · " . $grade["term_name"]) ?></td>
              <td><?= number_format((float)$grade["class_score"], 1) ?></td>
              <td><?= number_format((float)$grade["exam_score"], 1) ?></td>
              <td><strong><?= number_format((float)$grade["total_score"], 1) ?></strong></td>
              <td><?= htmlspecialchars($grade["grade"] ?? "—") ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="empty-note">Grades will appear here once your school records scores.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="section-card" id="attendance">
    <h3>Recent Attendance</h3>
    <table>
      <thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($attendanceRecords): ?>
          <?php foreach ($attendanceRecords as $record): ?>
            <tr>
              <td><?= htmlspecialchars($record["attendance_date"]) ?></td>
              <td><?= htmlspecialchars($record["class_name"] ?? "Class") ?></td>
              <td><?= htmlspecialchars(ucfirst($record["status"])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3" class="empty-note">Attendance records will appear here when your teacher marks the class register.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<script src="../Assets/js/family-dashboard.js?v=<?php echo filemtime(__DIR__ . "/../Assets/js/family-dashboard.js"); ?>" defer></script>
</body>
</html>

