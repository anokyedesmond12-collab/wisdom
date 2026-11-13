<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$schoolId = (int)$_SESSION["school_id"];
if (empty($_SESSION["school_teachers_csrf"])) {
    $_SESSION["school_teachers_csrf"] = bin2hex(random_bytes(32));
}
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["school_teachers_csrf"], $csrf)) {
        $message = "Your session expired. Reload the page and try again.";
        $messageType = "error";
    } else {
        $action = $_POST["action"] ?? "";
        if (in_array($action, ["approve", "decline", "delete"], true)) {
            $teacherId = filter_var($_POST["teacher_id"] ?? null, FILTER_VALIDATE_INT);
            if (!$teacherId || $teacherId < 1) {
                $message = "Select a valid teacher.";
                $messageType = "error";
            } else {
                try {
                    if ($action === "delete") {
                        $deleteTeacher = $conn->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
                        $deleteTeacher->execute([$teacherId, $schoolId]);
                        if ($deleteTeacher->rowCount() === 1) {
                            header("Location: teachers.php?notice=deleted");
                            exit();
                        }
                        $message = "That teacher could not be found in your school.";
                    } else {
                        $status = $action === "approve" ? "active" : "declined";
                        $updateStatus = $conn->prepare(
                            "UPDATE teachers SET account_status = ?
                             WHERE id = ? AND school_id = ? AND account_status = 'pending'"
                        );
                        $updateStatus->execute([$status, $teacherId, $schoolId]);
                        if ($updateStatus->rowCount() === 1) {
                            header("Location: teachers.php?notice=" . ($action === "approve" ? "approved" : "declined"));
                            exit();
                        }
                        $message = "That request is no longer pending or could not be found.";
                    }
                    $messageType = "error";
                } catch (PDOException $e) {
                    error_log("Unable to update teacher registration: " . $e->getMessage());
                    $message = "Unable to update this teacher. Please try again.";
                    $messageType = "error";
                }
            }
        } elseif ($action === "save_assignment") {
            $name = trim($_POST["full_name"] ?? "");
            $email = strtolower(trim($_POST["email"] ?? ""));
            $password = $_POST["password"] ?? "";
            $classId = (int)($_POST["class_id"] ?? 0);
            $subjectId = (int)($_POST["subject_id"] ?? 0);

            if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || $classId < 1 || $subjectId < 1) {
                $message = "Enter the teacher details and select a valid class and subject.";
                $messageType = "error";
            } else {
                try {
                    $classCheck = $conn->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
                    $classCheck->execute([$classId, $schoolId]);
                    $subjectCheck = $conn->prepare(
                        "SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND school_id = ?"
                    );
                    $subjectCheck->execute([$classId, $subjectId, $schoolId]);

                    if (!$classCheck->fetchColumn() || !$subjectCheck->fetchColumn()) {
                        $message = "Choose a class and subject already assigned to each other in your school.";
                        $messageType = "error";
                    } else {
                        $findTeacher = $conn->prepare(
                            "SELECT id, account_status FROM teachers WHERE school_id = ? AND email = ? LIMIT 1"
                        );
                        $findTeacher->execute([$schoolId, $email]);
                        $existingTeacher = $findTeacher->fetch(PDO::FETCH_ASSOC);
                        $teacherId = $existingTeacher ? (int)$existingTeacher["id"] : 0;

                        if ($existingTeacher && $existingTeacher["account_status"] === "declined") {
                            $message = "This teacher's registration was declined. Delete the record before creating a new account.";
                            $messageType = "error";
                        } elseif (!$teacherId && strlen($password) < 8) {
                            $message = "Set an initial password with at least 8 characters for a new teacher.";
                            $messageType = "error";
                        } else {
                            $conn->beginTransaction();
                            if ($teacherId) {
                                $updateTeacher = $conn->prepare(
                                    "UPDATE teachers SET full_name = ? WHERE id = ? AND school_id = ?"
                                );
                                $updateTeacher->execute([$name, $teacherId, $schoolId]);
                            } else {
                                $createTeacher = $conn->prepare(
                                    "INSERT INTO teachers (school_id, full_name, email, password) VALUES (?, ?, ?, ?)"
                                );
                                $createTeacher->execute([
                                    $schoolId,
                                    $name,
                                    $email,
                                    password_hash($password, PASSWORD_DEFAULT)
                                ]);
                                $teacherId = (int)$conn->lastInsertId();
                            }

                            $assign = $conn->prepare(
                                "INSERT INTO teacher_assignments (school_id, teacher_id, class_id, subject_id)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $assign->execute([$schoolId, $teacherId, $classId, $subjectId]);
                            $conn->commit();
                            header("Location: teachers.php?notice=assignment_saved");
                            exit();
                        }
                    }
                } catch (PDOException $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    if ($e->getCode() === "23000") {
                        $message = "This teacher already has that class and subject assignment.";
                    } else {
                        error_log("Unable to save teacher assignment: " . $e->getMessage());
                        $message = "Unable to save the teacher assignment. Please try again.";
                    }
                    $messageType = "error";
                }
            }
        } else {
            $message = "Choose a valid teacher-management action.";
            $messageType = "error";
        }
    }
}

$notices = [
    "approved" => "Teacher registration approved.",
    "declined" => "Teacher registration declined.",
    "deleted" => "Teacher deleted, along with their class assignments.",
    "assignment_saved" => "Teacher and class assignment saved."
];
$notice = $_GET["notice"] ?? "";
if (is_string($notice) && isset($notices[$notice])) {
    $message = $notices[$notice];
    $messageType = "success";
}

$stmt = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare(
    "SELECT DISTINCT s.id, s.subject_name, cs.class_id
     FROM subjects s
     JOIN class_subjects cs ON cs.subject_id = s.id AND cs.school_id = s.school_id
     WHERE s.school_id = ?
     ORDER BY s.subject_name"
);
$stmt->execute([$schoolId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare(
    "SELECT t.id, t.full_name, t.email, c.class_name, s.subject_name
     FROM teacher_assignments ta
     JOIN teachers t ON t.id = ta.teacher_id AND t.school_id = ta.school_id
     JOIN classes c ON c.id = ta.class_id AND c.school_id = ta.school_id
     JOIN subjects s ON s.id = ta.subject_id AND s.school_id = ta.school_id
     WHERE ta.school_id = ?
     ORDER BY t.full_name, c.class_name, s.subject_name"
);
$stmt->execute([$schoolId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare(
    "SELECT id, full_name, email, created_at
     FROM teachers
     WHERE school_id = ? AND account_status = 'pending'
     ORDER BY created_at"
);
$stmt->execute([$schoolId]);
$pendingTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare(
    "SELECT id, full_name, email, account_status
     FROM teachers
     WHERE school_id = ?
     ORDER BY full_name, email"
);
$stmt->execute([$schoolId]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Management – EduTrack</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:28px;background:#f4f6fa;font-family:Arial,sans-serif;color:#17243b}.wrap{max-width:1000px;margin:auto}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:20px}.card h1,.card h2{color:#10264b;margin-top:0}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:7px}.field input,.field select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.button{margin-top:18px;padding:10px 14px;border:0;border-radius:7px;background:#183d70;color:#fff;font-weight:700;cursor:pointer}.button.secondary{background:#475569}.button.danger{background:#b42318}.action-form{display:inline-block;margin:2px}.action-form .button{margin:0}.message{padding:12px;border-radius:7px;margin-bottom:15px}.error{background:#fff0f0;color:#b42318}.success{background:#ecfdf5;color:#166534}.status{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize}.status-pending{background:#fff7ed;color:#9a3412}.status-active{background:#ecfdf5;color:#166534}.status-declined{background:#fff0f0;color:#b42318}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase}@media(max-width:650px){.grid{grid-template-columns:1fr}body{padding:14px}.card{padding:18px}table{font-size:13px}th,td{padding:8px}}
</style>
</head>
<body>
<main class="wrap">
  <section class="card">
    <h1>Teacher Management</h1>
    <p>Create or approve teacher accounts and assign classes and subjects. Teachers can then mark attendance, manage scores and prepare class reports for their assigned classes.</p>
    <?php if ($message !== ""): ?><div class="message <?= $messageType === "success" ? "success" : "error" ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["school_teachers_csrf"]) ?>">
      <input type="hidden" name="action" value="save_assignment">
      <div class="grid">
        <div class="field"><label for="full_name">Teacher name</label><input id="full_name" name="full_name" required maxlength="150"></div>
        <div class="field"><label for="email">Teacher email</label><input id="email" name="email" type="email" required maxlength="190"></div>
        <div class="field"><label for="password">Initial password (new teachers only)</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="class_id">Class</label><select id="class_id" name="class_id" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class["id"] ?>"><?= htmlspecialchars($class["class_name"]) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="subject_id">Subject (must be assigned to class)</label><select id="subject_id" name="subject_id" required><option value="">Select subject</option><?php foreach ($subjects as $subject): ?><option value="<?= (int)$subject["id"] ?>" data-class="<?= (int)$subject["class_id"] ?>"><?= htmlspecialchars($subject["subject_name"]) ?></option><?php endforeach; ?></select></div>
      </div>
      <button class="button" type="submit">Save Teacher & Assignment</button>
    </form>
  </section>
  <section class="card">
    <h2>Pending Teacher Registrations</h2>
    <p>Accept or decline each teacher's request here. Accepted teachers can sign in; assign their classes and subjects above.</p>
    <table><thead><tr><th>Teacher</th><th>Email</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($pendingTeachers as $teacher): ?><tr>
        <td><?= htmlspecialchars($teacher["full_name"]) ?></td>
        <td><?= htmlspecialchars($teacher["email"]) ?></td>
        <td><?= htmlspecialchars($teacher["created_at"]) ?></td>
        <td>
          <form class="action-form" method="post" onsubmit="return confirm('Accept this teacher registration?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["school_teachers_csrf"]) ?>">
            <input type="hidden" name="teacher_id" value="<?= (int)$teacher["id"] ?>">
            <button class="button" type="submit" name="action" value="approve">Accept</button>
          </form>
          <form class="action-form" method="post" onsubmit="return confirm('Decline this teacher registration?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["school_teachers_csrf"]) ?>">
            <input type="hidden" name="teacher_id" value="<?= (int)$teacher["id"] ?>">
            <button class="button danger" type="submit" name="action" value="decline">Decline</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
      <?php if (!$pendingTeachers): ?><tr><td colspan="4">No pending teacher registrations.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h2>Teacher Accounts</h2>
    <table><thead><tr><th>Teacher</th><th>Email</th><th>Status</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($teachers as $teacher): ?><tr>
        <td><?= htmlspecialchars($teacher["full_name"]) ?></td>
        <td><?= htmlspecialchars($teacher["email"]) ?></td>
        <td><span class="status status-<?= htmlspecialchars($teacher["account_status"]) ?>"><?= htmlspecialchars($teacher["account_status"]) ?></span></td>
        <td>
          <form class="action-form" method="post" onsubmit="return confirm('Permanently delete this teacher and remove their class assignments?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["school_teachers_csrf"]) ?>">
            <input type="hidden" name="teacher_id" value="<?= (int)$teacher["id"] ?>">
            <button class="button danger" type="submit" name="action" value="delete">Delete</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
      <?php if (!$teachers): ?><tr><td colspan="4">No teacher accounts yet.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h2>Teacher Assignments</h2>
    <table><thead><tr><th>Teacher</th><th>Email</th><th>Class</th><th>Subject</th></tr></thead><tbody>
      <?php foreach ($assignments as $assignment): ?><tr><td><?= htmlspecialchars($assignment["full_name"]) ?></td><td><?= htmlspecialchars($assignment["email"]) ?></td><td><?= htmlspecialchars($assignment["class_name"]) ?></td><td><?= htmlspecialchars($assignment["subject_name"]) ?></td></tr><?php endforeach; ?>
      <?php if (!$assignments): ?><tr><td colspan="4">No teacher assignments yet.</td></tr><?php endif; ?>
    </tbody></table>
  </section>
</main>
<script>
const classSelect = document.getElementById("class_id");
const subjectSelect = document.getElementById("subject_id");
function filterSubjects() {
  const classId = classSelect.value;
  for (const option of subjectSelect.options) {
    if (!option.value) continue;
    option.hidden = option.dataset.class !== classId;
  }
  if (subjectSelect.selectedOptions.length && subjectSelect.selectedOptions[0].hidden) subjectSelect.value = "";
}
classSelect.addEventListener("change", filterSubjects);
filterSubjects();
</script>
</body>
</html>
