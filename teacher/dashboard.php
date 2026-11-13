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

$stmt = $conn->prepare(
    "SELECT ta.class_id, ta.subject_id, c.class_name, s.subject_name,
            (SELECT COUNT(*) FROM students st WHERE st.school_id = ta.school_id AND st.class_id = ta.class_id) AS student_count,
            (SELECT COUNT(*) FROM student_scores ss WHERE ss.school_id = ta.school_id AND ss.class_id = ta.class_id AND ss.subject_id = ta.subject_id) AS score_count
     FROM teacher_assignments ta
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     JOIN subjects s ON s.id = ta.subject_id AND s.school_id = ta.school_id
     WHERE ta.teacher_id = ? AND ta.school_id = ?
     ORDER BY c.class_name, s.subject_name"
);
$stmt->execute([$teacherId, $schoolId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$classStmt = $conn->prepare(
    "SELECT DISTINCT c.id, c.class_name,
            (SELECT COUNT(*) FROM students st WHERE st.school_id = ta.school_id AND st.class_id = ta.class_id) AS student_count
     FROM teacher_assignments ta
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     WHERE ta.teacher_id = ? AND ta.school_id = ?
     ORDER BY c.class_name"
);
$classStmt->execute([$teacherId, $schoolId]);
$assignedClasses = $classStmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT st.id)
     FROM students st
     WHERE st.school_id = ?
       AND st.class_id IN (
           SELECT DISTINCT class_id FROM teacher_assignments WHERE teacher_id = ? AND school_id = ?
       )"
);
$stmt->execute([$schoolId, $teacherId, $schoolId]);
$studentCount = (int)$stmt->fetchColumn();
$scoreCount = 0;
foreach ($assignments as $assignment) {
    $scoreCount += (int)$assignment["score_count"];
}
$teacherName = htmlspecialchars($_SESSION["teacher_name"] ?? "Teacher");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Dashboard – EduTrack</title>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
</head>
<body class="teacher-dashboard-page">
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page teacher-dashboard">
  <div class="dashboard-content">
    <header class="dashboard-welcome">
      <div>
        <span class="dashboard-eyebrow">TEACHER WORKSPACE</span>
        <h1 data-greeting data-name="<?= $teacherName ?>">Welcome back, <?= $teacherName ?></h1>
        <p>Your teaching tools and student data, all in one place.</p>
        <span class="dashboard-access"><span aria-hidden="true"></span> Access limited to your assigned classes</span>
      </div>
      <div class="dashboard-date">
        <span>Today</span>
        <strong data-current-date></strong>
      </div>
    </header>

    <section class="dashboard-metrics" aria-label="Teaching overview">
      <article class="metric-card metric-blue">
        <div class="metric-heading"><span>Class &amp; subject assignments</span><span class="metric-mark" aria-hidden="true">AS</span></div>
        <strong class="metric-value" data-count="<?= count($assignments) ?>"><?= count($assignments) ?></strong>
        <span class="metric-foot">Assigned by your administrator</span>
      </article>
      <article class="metric-card metric-teal">
        <div class="metric-heading"><span>Students in your classes</span><span class="metric-mark" aria-hidden="true">ST</span></div>
        <strong class="metric-value" data-count="<?= $studentCount ?>"><?= $studentCount ?></strong>
        <span class="metric-foot">Across your assigned classes</span>
      </article>
      <article class="metric-card metric-gold">
        <div class="metric-heading"><span>Score entries</span><span class="metric-mark" aria-hidden="true">SC</span></div>
        <strong class="metric-value" data-count="<?= $scoreCount ?>"><?= $scoreCount ?></strong>
        <span class="metric-foot">Recorded for your subjects</span>
      </article>
    </section>

    <section class="dashboard-section">
      <div class="dashboard-section-heading">
        <div>
          <span class="dashboard-eyebrow">GET THINGS DONE</span>
          <h2>Quick actions</h2>
        </div>
        <p>Jump directly to a teaching tool.</p>
      </div>
      <div class="quick-action-grid">
        <a class="quick-action action-attendance" href="attendance.php">
          <span class="quick-action-icon" aria-hidden="true">AT</span>
          <span class="quick-action-copy"><strong>Take attendance</strong><small>Record today’s class presence</small></span>
          <span class="quick-action-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="quick-action action-students" href="students.php">
          <span class="quick-action-icon" aria-hidden="true">ST</span>
          <span class="quick-action-copy"><strong>My students</strong><small>Open your class rosters</small></span>
          <span class="quick-action-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="quick-action action-scores" href="scores.php">
          <span class="quick-action-icon" aria-hidden="true">SC</span>
          <span class="quick-action-copy"><strong>Manage scores</strong><small>Enter and update marks</small></span>
          <span class="quick-action-arrow" aria-hidden="true">↗</span>
        </a>
        <a class="quick-action action-reports" href="individual_report.php">
          <span class="quick-action-icon" aria-hidden="true">RP</span>
          <span class="quick-action-copy"><strong>Student reports</strong><small>Generate individual reports</small></span>
          <span class="quick-action-arrow" aria-hidden="true">↗</span>
        </a>
      </div>
    </section>

    <section class="dashboard-section">
      <div class="dashboard-section-heading">
        <div>
          <span class="dashboard-eyebrow">YOUR ROSTERS</span>
          <h2>My classes</h2>
        </div>
        <a class="text-link" href="students.php">View all students <span aria-hidden="true">→</span></a>
      </div>
      <?php if ($assignedClasses): ?>
        <div class="class-card-grid">
          <?php foreach ($assignedClasses as $class): ?>
            <article class="class-card">
              <div class="class-card-top">
                <span class="class-card-icon" aria-hidden="true">CL</span>
                <span class="class-card-label">ASSIGNED CLASS</span>
              </div>
              <h3><?= htmlspecialchars($class["class_name"]) ?></h3>
              <p><strong><?= (int)$class["student_count"] ?></strong> registered students</p>
              <div class="class-card-actions">
                <a class="button" href="attendance.php?class_id=<?= (int)$class["id"] ?>">Attendance</a>
                <a class="button light" href="students.php?class_id=<?= (int)$class["id"] ?>">Roster</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="dashboard-empty">
          <span class="empty-mark" aria-hidden="true">CL</span>
          <div>
            <h3>No classes assigned yet</h3>
            <p>Ask your school administrator to assign your classes and subjects. They will appear here automatically.</p>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="dashboard-section" id="assignments">
      <div class="dashboard-section-heading">
        <div>
          <span class="dashboard-eyebrow">TEACHING LOAD</span>
          <h2>Classes &amp; subjects</h2>
        </div>
        <?php if ($assignments): ?>
          <label class="assignment-search">
            <span class="sr-only">Search assigned classes and subjects</span>
            <span class="search-icon" aria-hidden="true">⌕</span>
            <input id="assignment-search" type="search" placeholder="Search classes or subjects">
          </label>
        <?php endif; ?>
      </div>
      <?php if ($assignments): ?>
        <div class="assignment-grid" id="assignment-list">
          <?php foreach ($assignments as $assignment): ?>
            <?php $searchText = strtolower($assignment["class_name"] . " " . $assignment["subject_name"]); ?>
            <article class="assignment-card" data-assignment-card data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, "UTF-8") ?>">
              <div class="assignment-card-heading">
                <span class="assignment-subject-mark" aria-hidden="true">SB</span>
                <span class="assignment-label">SUBJECT ASSIGNMENT</span>
              </div>
              <h3><?= htmlspecialchars($assignment["class_name"]) ?></h3>
              <p class="assignment-subject"><?= htmlspecialchars($assignment["subject_name"]) ?></p>
              <div class="assignment-stats">
                <span><strong><?= (int)$assignment["student_count"] ?></strong> students</span>
                <span><strong><?= (int)$assignment["score_count"] ?></strong> score entries</span>
              </div>
              <div class="assignment-actions">
                <a class="button" href="scores.php?class_id=<?= (int)$assignment["class_id"] ?>&amp;subject_id=<?= (int)$assignment["subject_id"] ?>">Manage scores</a>
                <a class="button light" href="class_report.php?class_id=<?= (int)$assignment["class_id"] ?>&amp;subject_id=<?= (int)$assignment["subject_id"] ?>">Class report</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <p class="dashboard-empty-filter" id="assignment-empty-filter" hidden>No assigned class or subject matches your search.</p>
      <?php else: ?>
        <div class="dashboard-empty">
          <span class="empty-mark" aria-hidden="true">SB</span>
          <div>
            <h3>No subject assignments yet</h3>
            <p>Once your administrator assigns a subject, score entry and class reports will be available here.</p>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
</div>
</body>
</html>
