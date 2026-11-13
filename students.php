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
if (empty($_SESSION["teacher_students_csrf"])) {
    $_SESSION["teacher_students_csrf"] = bin2hex(random_bytes(32));
}

$classesStmt = $conn->prepare(
    "SELECT DISTINCT c.id, c.class_name
     FROM teacher_assignments ta
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     WHERE ta.teacher_id = ? AND ta.school_id = ?
     ORDER BY c.class_name"
);
$classesStmt->execute([$teacherId, $schoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    $studentRecordId = filter_var($_POST["student_record_id"] ?? null, FILTER_VALIDATE_INT);
    $targetClassId = filter_var($_POST["target_class_id"] ?? null, FILTER_VALIDATE_INT);
    if (!is_string($csrf) || !hash_equals($_SESSION["teacher_students_csrf"], $csrf)) {
        $message = "Your session expired. Reload the page and try again.";
        $messageType = "error";
    } elseif (!$studentRecordId || $studentRecordId < 1 || !$targetClassId || $targetClassId < 1) {
        $message = "Choose a student and one of your assigned classes.";
        $messageType = "error";
    } else {
        $classCheck = $conn->prepare(
            "SELECT c.id
             FROM teacher_assignments ta
             JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
             WHERE ta.teacher_id = ? AND ta.school_id = ? AND ta.class_id = ?
             LIMIT 1"
        );
        $classCheck->execute([$teacherId, $schoolId, $targetClassId]);
        $studentCheck = $conn->prepare("SELECT id FROM students WHERE id = ? AND school_id = ? LIMIT 1");
        $studentCheck->execute([$studentRecordId, $schoolId]);

        if (!$classCheck->fetchColumn() || !$studentCheck->fetchColumn()) {
            $message = "That student or destination class is not available to your account.";
            $messageType = "error";
        } else {
            try {
                $moveStudent = $conn->prepare("UPDATE students SET class_id = ? WHERE id = ? AND school_id = ?");
                $moveStudent->execute([$targetClassId, $studentRecordId, $schoolId]);
                header("Location: students.php?class_id=" . $targetClassId . "&assigned=1");
                exit();
            } catch (PDOException $e) {
                error_log("Teacher student class assignment failed: " . $e->getMessage());
                $message = "Unable to update the student's class. Please try again.";
                $messageType = "error";
            }
        }
    }
}

$schoolStudentsStmt = $conn->prepare(
    "SELECT st.id, st.student_id, st.first_name, st.other_names, st.last_name, c.class_name
     FROM students st
     LEFT JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
     WHERE st.school_id = ?
     ORDER BY st.first_name, st.last_name"
);
$schoolStudentsStmt->execute([$schoolId]);
$schoolStudents = $schoolStudentsStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET["assigned"])) {
    $message = "Student assigned to the selected class.";
    $messageType = "success";
}

$requestedClassId = filter_var($_GET["class_id"] ?? null, FILTER_VALIDATE_INT);
$classId = $requestedClassId && $requestedClassId > 0 ? $requestedClassId : 0;
$selectedClass = null;
foreach ($classes as $class) {
    if ((int)$class["id"] === $classId) {
        $selectedClass = $class;
        break;
    }
}

$students = [];
if ($selectedClass) {
    $studentsStmt = $conn->prepare(
        "SELECT student_id, first_name, other_names, last_name, gender
         FROM students
         WHERE school_id = ? AND class_id = ?
         ORDER BY first_name, last_name"
    );
    $studentsStmt->execute([$schoolId, $classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Students – EduTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:1000px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:18px}h1{color:#10264b;margin-top:0}.nav{display:flex;gap:14px;margin-bottom:18px}.nav a{color:#2563ab;text-decoration:none}.filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.filters label{display:grid;gap:6px;font-weight:700;font-size:13px}.filters select,.filters button{padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.filters button{background:#183d70;color:#fff;border:0;font-weight:700;cursor:pointer}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase}.empty{text-align:center;color:#64748b;padding:20px}@media(max-width:600px){body{padding:12px}.card{padding:16px}table{font-size:13px}th,td{padding:8px}}
</style>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
</head>
<body>
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page">
<div class="wrap">
  <section class="card">
    <h1>My Registered Students</h1>
    <p class="teacher-page-intro">View your assigned class rosters and place students in classes you teach.</p>
    <?php if ($message !== ""): ?><div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <section class="card">
      <h2>Assign a Student to Your Class</h2>
      <p>Changes the student's official class roster. Existing scores and attendance records are retained.</p>
      <?php if ($classes): ?>
        <form class="filters" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["teacher_students_csrf"]) ?>">
          <label for="student_record_id">Student
            <select id="student_record_id" name="student_record_id" required>
              <option value="">Select a student</option>
              <?php foreach ($schoolStudents as $schoolStudent): ?>
                <option value="<?= (int)$schoolStudent["id"] ?>"><?= htmlspecialchars(trim($schoolStudent["first_name"] . " " . ($schoolStudent["other_names"] ?? "") . " " . $schoolStudent["last_name"]) . " (" . $schoolStudent["student_id"] . ") — " . ($schoolStudent["class_name"] ?? "No class")) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label for="target_class_id">Assigned class
            <select id="target_class_id" name="target_class_id" required>
              <option value="">Select a class</option>
              <?php foreach ($classes as $class): ?>
                <option value="<?= (int)$class["id"] ?>"><?= htmlspecialchars($class["class_name"]) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="submit">Assign Student</button>
        </form>
      <?php else: ?>
        <p>No classes have been assigned to your account yet.</p>
      <?php endif; ?>
    </section>
    <?php if (!$classes): ?>
      <p>No classes have been assigned to your account yet.</p>
    <?php else: ?>
      <form class="filters" method="get">
        <label for="class_id">Assigned class
          <select id="class_id" name="class_id" required>
            <option value="">Select a class</option>
            <?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class["id"] ?>" <?= $classId === (int)$class["id"] ? "selected" : "" ?>><?= htmlspecialchars($class["class_name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit">View Students</button>
      </form>
      <?php if ($selectedClass): ?>
        <h2><?= htmlspecialchars($selectedClass["class_name"]) ?></h2>
        <div class="table-scroll"><table>
          <thead><tr><th>Admission Number</th><th>Student</th><th>Gender</th><th>Individual Report</th></tr></thead>
          <tbody>
            <?php foreach ($students as $student): ?>
              <tr>
                <td><?= htmlspecialchars($student["student_id"]) ?></td>
                <td><?= htmlspecialchars(trim($student["first_name"] . " " . ($student["other_names"] ?? "") . " " . $student["last_name"])) ?></td>
                <td><?= htmlspecialchars($student["gender"]) ?></td>
                <td><a class="button light" href="individual_report.php?student_id=<?= (int)$student["id"] ?>">Generate Report</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?><tr><td class="empty" colspan="4">No students are registered in this class.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
</main>
</div>
</body>
</html>
