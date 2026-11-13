<?php
session_start();
if (
    ($_SESSION["role"] ?? "") !== "student" ||
    empty($_SESSION["student_id"]) ||
    empty($_SESSION["student_school_id"])
) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . "/../config/database.php";

$studentStmt = $conn->prepare(
    "SELECT st.student_id, st.first_name, st.other_names, st.last_name,
            st.email, st.password, c.class_name, sc.school_name
     FROM students st
     LEFT JOIN classes c ON c.id = st.class_id AND c.school_id = st.school_id
     LEFT JOIN schools sc ON sc.id = st.school_id
     WHERE st.id = ? AND st.school_id = ?
     LIMIT 1"
);
$studentStmt->execute([
    (int)$_SESSION["student_id"],
    (int)$_SESSION["student_school_id"]
]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php?error=Account+not+found");
    exit();
}

if (empty($_SESSION["student_profile_csrf"])) {
    $_SESSION["student_profile_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["student_profile_csrf"];
$error = "";
$hasPassword = is_string($student["password"]) && $student["password"] !== "";
$argon2idAvailable = defined("PASSWORD_ARGON2ID");
$maxPasswordBytes = $argon2idAvailable ? 1024 : 72;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedCsrf = $_POST["csrf_token"] ?? "";
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!is_string($postedCsrf) || !hash_equals($csrf, $postedCsrf)) {
        $error = "Your session expired. Reload the page and try again.";
    } elseif ($hasPassword && (
        !is_string($currentPassword) ||
        !password_verify($currentPassword, $student["password"])
    )) {
        $error = "Enter your current password to change it.";
    } elseif (
        !is_string($newPassword) ||
        strlen($newPassword) < 8 ||
        strlen($newPassword) > $maxPasswordBytes
    ) {
        $error = "Choose a password between 8 and {$maxPasswordBytes} bytes.";
    } elseif (!is_string($confirmPassword) || $newPassword !== $confirmPassword) {
        $error = "The new passwords do not match.";
    } else {
        $hashAlgorithm = $argon2idAvailable ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $passwordHash = password_hash($newPassword, $hashAlgorithm);
        if (!$passwordHash) {
            $error = "Unable to update your password. Please try again.";
        } else {
            try {
                $update = $conn->prepare(
                    "UPDATE students SET password = ? WHERE id = ? AND school_id = ?"
                );
                $update->execute([
                    $passwordHash,
                    (int)$_SESSION["student_id"],
                    (int)$_SESSION["student_school_id"]
                ]);
                if ($update->rowCount() !== 1) {
                    $error = "Your password was not changed. Reload the page and try again.";
                } else {
                    session_regenerate_id(true);
                    header("Location: profile.php?updated=1");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Student password update failed: " . $e->getMessage());
                $error = "Unable to update your password. Please try again.";
            }
        }
    }
}

$updated = ($_GET["updated"] ?? "") === "1";
$studentName = trim(
    $student["first_name"] . " " .
    ($student["other_names"] ?? "") . " " .
    $student["last_name"]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile – EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--gold:#e9b949;--bg:#f5f7fb;--muted:#68778d;--border:#e6ebf2}
*{box-sizing:border-box}body{margin:0;padding:24px;background:var(--bg);font-family:'Segoe UI',Arial,sans-serif;color:#17243b}
.wrap{max-width:760px;margin:20px auto}.top{display:flex;justify-content:space-between;margin-bottom:18px}.top a{color:var(--blue);text-decoration:none;font-weight:700}
.card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:18px}.card h1,.card h2{margin-top:0;color:var(--navy)}
.profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field{padding:14px;background:#f8fafc;border-radius:8px;overflow-wrap:anywhere}
.field small{display:block;color:var(--muted);font-size:.75rem;text-transform:uppercase;font-weight:700;margin-bottom:6px}.field strong{color:var(--navy)}
label{display:block;margin:16px 0 6px;font-size:.86rem;font-weight:700;color:var(--navy)}input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px;font:inherit}
button{margin-top:18px;padding:11px 16px;border:0;border-radius:7px;background:var(--blue);color:#fff;font-weight:700;cursor:pointer}
.message{padding:11px 13px;border-radius:7px;margin:14px 0}.error{background:#fff0f0;color:#b42318}.success{background:#ecfdf5;color:#166534}
.hint{color:var(--muted);font-size:.87rem;line-height:1.5}@media(max-width:600px){body{padding:14px}.profile-grid{grid-template-columns:1fr}.card{padding:18px}}
</style>
</head>
<body>
<main class="wrap">
  <nav class="top"><a href="dashboard.php">← Student Dashboard</a><a href="logout.php">Logout</a></nav>
  <section class="card">
    <h1>My Profile</h1>
    <div class="profile-grid">
      <div class="field"><small>Name</small><strong><?= htmlspecialchars($studentName) ?></strong></div>
      <div class="field"><small>Admission number</small><strong><?= htmlspecialchars($student["student_id"]) ?></strong></div>
      <div class="field"><small>School</small><strong><?= htmlspecialchars($student["school_name"] ?? "—") ?></strong></div>
      <div class="field"><small>Class</small><strong><?= htmlspecialchars($student["class_name"] ?? "Not assigned") ?></strong></div>
      <div class="field"><small>Portal email</small><strong><?= htmlspecialchars($student["email"] ?? "Not recorded") ?></strong></div>
    </div>
    <p class="hint">Your name, admission number, school and class are maintained by your school. Contact the school office if these details need correction.</p>
  </section>
  <section class="card">
    <h2><?= $hasPassword ? "Change portal password" : "Create a portal password" ?></h2>
    <?php if ($updated): ?><div class="message success">Your password has been updated.</div><?php endif; ?>
    <?php if ($error !== ""): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($hasPassword): ?>
      <p class="hint">Confirm your current password before choosing a new one.</p>
    <?php else: ?>
      <p class="hint">You signed in without a password. Create one to also use email and password sign-in.</p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if ($hasPassword): ?>
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
      <?php endif; ?>
      <label for="new_password">New password (at least 8 characters)</label>
      <input id="new_password" name="new_password" type="password" minlength="8" maxlength="<?= $maxPasswordBytes ?>" autocomplete="new-password" required>
      <label for="confirm_password">Confirm new password</label>
      <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="<?= $maxPasswordBytes ?>" autocomplete="new-password" required>
      <button type="submit">Save Password</button>
    </form>
  </section>
</main>
</body>
</html>
