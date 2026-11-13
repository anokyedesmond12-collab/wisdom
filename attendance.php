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
if (empty($_SESSION["teacher_attendance_csrf"])) {
    $_SESSION["teacher_attendance_csrf"] = bin2hex(random_bytes(32));
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

$classInput = $_POST["class_id"] ?? $_GET["class_id"] ?? null;
$requestedClassId = filter_var($classInput, FILTER_VALIDATE_INT);
$classId = $requestedClassId && $requestedClassId > 0 ? $requestedClassId : 0;
$dateInput = $_POST["attendance_date"] ?? $_GET["attendance_date"] ?? date("Y-m-d");
$dateInput = is_string($dateInput) ? trim($dateInput) : "";
$parsedDate = DateTimeImmutable::createFromFormat("!Y-m-d", $dateInput);
$dateErrors = DateTimeImmutable::getLastErrors();
$validDate = $parsedDate !== false
    && $parsedDate->format("Y-m-d") === $dateInput
    && ($dateErrors === false || ($dateErrors["warning_count"] === 0 && $dateErrors["error_count"] === 0));
$attendanceDate = $validDate ? $parsedDate->format("Y-m-d") : date("Y-m-d");
$message = "";
$messageType = "";

$classStmt = $conn->prepare(
    "SELECT c.id, c.class_name
     FROM classes c
     WHERE c.id = ? AND c.school_id = ?
       AND EXISTS (
           SELECT 1 FROM teacher_assignments ta
           WHERE ta.teacher_id = ? AND ta.school_id = c.school_id AND ta.class_id = c.id
       )
     LIMIT 1"
);
$classStmt->execute([$classId, $schoolId, $teacherId]);
$selectedClass = $classStmt->fetch(PDO::FETCH_ASSOC);
$students = [];
$attendanceByStudent = [];

if ($selectedClass) {
    $studentsStmt = $conn->prepare(
        "SELECT id, student_id, first_name, other_names, last_name
         FROM students
         WHERE school_id = ? AND class_id = ?
         ORDER BY first_name, last_name"
    );
    $studentsStmt->execute([$schoolId, $classId]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $attendanceStmt = $conn->prepare(
        "SELECT student_id, status
         FROM teacher_attendance
         WHERE school_id = ? AND class_id = ? AND attendance_date = ?"
    );
    $attendanceStmt->execute([$schoolId, $classId, $attendanceDate]);
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $attendanceByStudent[(int)$record["student_id"]] = $record["status"];
    }
}

if (
    $_SERVER["REQUEST_METHOD"] === "GET" &&
    ($_GET["download"] ?? "") === "csv"
) {
    if (!$validDate) {
        http_response_code(400);
        exit("Choose a valid attendance date.");
    }
    if (!$selectedClass) {
        http_response_code(403);
        exit("That class is not assigned to your account.");
    }

    $safeCsvCell = static function ($value) {
        $value = (string)$value;
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    };

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"attendance-class-" . $classId . "-" . $attendanceDate . ".csv\"");
    header("Cache-Control: no-store, private");
    header("X-Content-Type-Options: nosniff");
    $output = fopen("php://output", "wb");
    if ($output === false) {
        throw new RuntimeException("Unable to create attendance export.");
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ["Class", "Date", "Admission Number", "Student", "Attendance"]);
    $statusLabels = [
        "present" => "Present",
        "absent" => "Absent",
        "late" => "Late",
        "excused" => "Excused"
    ];
    foreach ($students as $student) {
        $studentName = trim(
            $student["first_name"] . " " .
            ($student["other_names"] ?? "") . " " .
            $student["last_name"]
        );
        $status = $attendanceByStudent[(int)$student["id"]] ?? "";
        fputcsv($output, [
            $safeCsvCell($selectedClass["class_name"]),
            $attendanceDate,
            $safeCsvCell($student["student_id"]),
            $safeCsvCell($studentName),
            $statusLabels[$status] ?? "Not marked"
        ]);
    }
    fclose($output);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["teacher_attendance_csrf"], $csrf)) {
        $message = "Your session expired. Reload the page and try again.";
        $messageType = "error";
    } elseif (!$validDate) {
        $message = "Choose a valid attendance date.";
        $messageType = "error";
    } elseif (!$selectedClass) {
        $message = "Choose a class assigned to your account.";
        $messageType = "error";
    } elseif (!$students) {
        $message = "There are no registered students in this class.";
        $messageType = "error";
    } else {
        $statuses = $_POST["attendance"] ?? null;
        $allowedStatuses = ["present", "absent", "late", "excused"];
        $rosterIds = array_map(static function ($student) {
            return (int)$student["id"];
        }, $students);
        $validStatuses = is_array($statuses) && count($statuses) === count($rosterIds);
        if ($validStatuses) {
            foreach ($rosterIds as $studentId) {
                if (!isset($statuses[$studentId]) || !in_array($statuses[$studentId], $allowedStatuses, true)) {
                    $validStatuses = false;
                    break;
                }
            }
        }

        if (!$validStatuses) {
            $message = "Mark an attendance status for every student in the class.";
            $messageType = "error";
        } else {
            try {
                $conn->beginTransaction();
                $saveAttendance = $conn->prepare(
                    "INSERT INTO teacher_attendance
                     (school_id, class_id, student_id, teacher_id, attendance_date, status)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), status = VALUES(status)"
                );
                foreach ($rosterIds as $studentId) {
                    $saveAttendance->execute([
                        $schoolId,
                        $classId,
                        $studentId,
                        $teacherId,
                        $attendanceDate,
                        $statuses[$studentId]
                    ]);
                }
                $conn->commit();
                header("Location: attendance.php?class_id=" . $classId . "&attendance_date=" . $attendanceDate . "&saved=1");
                exit();
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Teacher attendance save failed: " . $e->getMessage());
                $message = "Unable to save attendance. Please try again.";
                $messageType = "error";
            }
        }
    }
}

if (isset($_GET["saved"])) {
    $message = "Class attendance saved.";
    $messageType = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Class Attendance – EduTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:1000px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:18px}h1{color:#10264b;margin-top:0}.nav{display:flex;gap:14px;margin-bottom:18px}.nav a{color:#2563ab;text-decoration:none}.filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:15px 0}.filters label{display:grid;gap:6px;font-weight:700;font-size:13px}.filters input,.filters select,.filters button{padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.filters button,.button{background:#183d70;color:#fff;border:0;border-radius:7px;padding:10px 14px;font-weight:700;cursor:pointer}.button.secondary{background:#475569}.message{padding:12px;border-radius:7px;margin:12px 0}.error{background:#fff0f0;color:#b42318}.success{background:#ecfdf5;color:#166534}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase}td select{padding:8px;border:1px solid #cbd5e1;border-radius:6px}.actions{display:flex;gap:10px;justify-content:space-between;margin-top:16px}.empty{text-align:center;color:#64748b;padding:20px}@media(max-width:600px){body{padding:12px}.card{padding:16px}table{font-size:13px}th,td{padding:8px}.actions{flex-direction:column}}
</style>
<link rel="stylesheet" href="portal.css?v=<?php echo filemtime(__DIR__ . "/portal.css"); ?>">
<style>
.attendance-options{display:flex;flex-wrap:wrap;gap:7px}.attendance-option{position:relative;cursor:pointer}.attendance-option input{position:absolute;width:1px;height:1px;opacity:0}.attendance-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:7px;color:#475569;background:#fff;font-size:.82rem;font-weight:700}.attendance-option input:checked+.attendance-pill.present{border-color:#16a34a;color:#166534;background:#ecfdf5}.attendance-option input:checked+.attendance-pill.absent{border-color:#dc2626;color:#b42318;background:#fff0f0}.attendance-option input:checked+.attendance-pill.late,.attendance-option input:checked+.attendance-pill.excused{border-color:#d6a52e;color:#76550b;background:#fffbeb}.attendance-option input:focus-visible+.attendance-pill{outline:3px solid rgba(24,61,112,.2)}.attendance-symbol{font-size:1.05rem}
</style>
</head>
<body>
<div class="teacher-app">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="teacher-main teacher-page">
<div class="wrap">
  <section class="card">
    <h1>Mark Class Attendance</h1>
    <p class="teacher-page-intro">Mark each student's presence with a check or an X for absent.</p>
    <?php if ($message !== ""): ?><div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
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
        <label for="attendance_date">Date
          <input id="attendance_date" name="attendance_date" type="date" value="<?= htmlspecialchars($attendanceDate) ?>" required>
        </label>
        <button type="submit">Load Class</button>
      </form>
      <?php if ($selectedClass): ?>
        <div class="attendance-heading">
          <div>
            <span class="dashboard-eyebrow">CLASS ROLL CALL</span>
            <h2><?= htmlspecialchars($selectedClass["class_name"]) ?> <span>· <?= htmlspecialchars($attendanceDate) ?></span></h2>
          </div>
          <a class="button button-download" href="attendance.php?class_id=<?= (int)$classId ?>&amp;attendance_date=<?= rawurlencode($attendanceDate) ?>&amp;download=csv">Download attendance CSV</a>
        </div>
        <?php if ($students): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["teacher_attendance_csrf"]) ?>">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendanceDate) ?>">
            <div class="table-scroll"><table>
              <thead><tr><th>Admission Number</th><th>Student</th><th>Attendance</th></tr></thead>
              <tbody>
                <?php foreach ($students as $student): ?>
                  <?php $currentStatus = $attendanceByStudent[(int)$student["id"]] ?? ""; ?>
                  <tr>
                    <td><?= htmlspecialchars($student["student_id"]) ?></td>
                    <td><?= htmlspecialchars(trim($student["first_name"] . " " . ($student["other_names"] ?? "") . " " . $student["last_name"])) ?></td>
                    <td>
                      <div class="attendance-options" role="radiogroup" aria-label="Attendance for <?= htmlspecialchars(trim($student["first_name"] . " " . $student["last_name"])) ?>">
                        <?php foreach (["present" => ["✓", "Present"], "absent" => ["X", "Absent"], "late" => ["L", "Late"], "excused" => ["E", "Excused"]] as $value => $status): ?>
                          <label class="attendance-option">
                            <input type="radio" name="attendance[<?= (int)$student["id"] ?>]" value="<?= $value ?>" <?= $currentStatus === $value ? "checked" : "" ?> required>
                            <span class="attendance-pill <?= $value ?>"><span class="attendance-symbol"><?= $status[0] ?></span><?= $status[1] ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
            <div class="actions">
              <button class="button secondary" type="button" id="markAllPresent">Mark all present</button>
              <button class="button" type="submit">Save Attendance</button>
            </div>
          </form>
        <?php else: ?>
          <p class="empty">No students are registered in this class.</p>
        <?php endif; ?>
      <?php elseif ($classId): ?>
        <p class="message error">That class is not assigned to your account.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
</main>
</div>
<script>
const markAllPresent = document.getElementById("markAllPresent");
if (markAllPresent) {
  markAllPresent.addEventListener("click", function () {
    document.querySelectorAll('input[type="radio"][name^="attendance["][value="present"]').forEach(function (input) {
      input.checked = true;
    });
  });
}
</script>
</body>
</html>
