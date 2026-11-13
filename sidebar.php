<?php
$currentPage = basename($_SERVER["PHP_SELF"] ?? "");
$teacherName = trim((string)($_SESSION["teacher_name"] ?? "Teacher"));
$teacherSidebarName = htmlspecialchars(
    $teacherName,
    ENT_QUOTES,
    "UTF-8"
);
$teacherInitial = htmlspecialchars(
    strtoupper(substr($teacherName !== "" ? $teacherName : "T", 0, 1)),
    ENT_QUOTES,
    "UTF-8"
);
?>
<aside class="teacher-sidebar">
    <a class="teacher-brand" href="dashboard.php">
        <span class="teacher-brand-mark" aria-hidden="true">E</span>
        <span class="teacher-brand-copy">EduTrack<small>TEACHER PORTAL</small></span>
    </a>
    <div class="teacher-user">
        <span class="teacher-avatar" aria-hidden="true"><?= $teacherInitial ?></span>
        <span class="teacher-user-copy"><small>Signed in as</small><strong><?= $teacherSidebarName ?></strong></span>
    </div>
    <nav class="teacher-nav" id="teacher-navigation" aria-label="Teacher navigation">
        <span class="teacher-nav-heading">WORKSPACE</span>
        <a class="<?= $currentPage === "dashboard.php" ? "active" : "" ?>" href="dashboard.php" <?= $currentPage === "dashboard.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">DB</span><span>Dashboard</span>
        </a>
        <a class="<?= $currentPage === "attendance.php" ? "active" : "" ?>" href="attendance.php" <?= $currentPage === "attendance.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">AT</span><span>Attendance</span>
        </a>
        <a class="<?= $currentPage === "students.php" ? "active" : "" ?>" href="students.php" <?= $currentPage === "students.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">ST</span><span>Students &amp; Classes</span>
        </a>
        <a class="<?= $currentPage === "scores.php" ? "active" : "" ?>" href="scores.php" <?= $currentPage === "scores.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">SC</span><span>Manage Scores</span>
        </a>
        <a class="<?= $currentPage === "class_report.php" ? "active" : "" ?>" href="dashboard.php#assignments" <?= $currentPage === "class_report.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">CR</span><span>Class Reports</span>
        </a>
        <a class="<?= $currentPage === "individual_report.php" ? "active" : "" ?>" href="individual_report.php" <?= $currentPage === "individual_report.php" ? 'aria-current="page"' : "" ?>>
            <span class="teacher-nav-icon" aria-hidden="true">IR</span><span>Individual Reports</span>
        </a>
    </nav>
    <div class="teacher-sidebar-footer">
        <span class="teacher-sidebar-status"><span aria-hidden="true"></span>Portal active</span>
        <a class="teacher-signout" href="logout.php"><span aria-hidden="true">↗</span> Sign out</a>
    </div>
</aside>
<button class="teacher-menu-toggle" type="button" aria-controls="teacher-navigation" aria-expanded="false" aria-label="Open teacher menu">
    <span class="menu-toggle-lines" aria-hidden="true"><i></i><i></i><i></i></span>
</button>
<button class="teacher-sidebar-backdrop" type="button" aria-label="Close teacher menu" hidden></button>
<script src="portal.js?v=<?php echo filemtime(__DIR__ . "/portal.js"); ?>" defer></script>
