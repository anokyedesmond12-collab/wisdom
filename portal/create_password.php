<?php
session_start();
require_once __DIR__ . "/../config/database.php";

$setup = $_SESSION["portal_password_setup"] ?? null;
$role = is_array($setup) ? ($setup["role"] ?? "") : "";
$loginPath = $role === "parent" ? "../parent/login.php" : "../student/login.php";

if (
    !is_array($setup) ||
    !in_array($role, ["student", "parent"], true) ||
    (int)($setup["expires_at"] ?? 0) < time()
) {
    unset($_SESSION["portal_password_setup"], $_SESSION["portal_password_csrf"]);
    header("Location: " . $loginPath . "?error=Password+setup+expired");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $password = $_POST["password"] ?? null;
    $confirmPassword = $_POST["confirm_password"] ?? null;

    if (
        !is_string($csrfToken) ||
        !isset($_SESSION["portal_password_csrf"]) ||
        !hash_equals($_SESSION["portal_password_csrf"], $csrfToken)
    ) {
        $error = "Your session expired. Start password setup again.";
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $error = "Enter a valid password.";
    } elseif (strlen($password) < 8) {
        $error = "Choose a password with at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "The passwords do not match.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($role === "student") {
            $update = $conn->prepare(
                "UPDATE students
                 SET password = ?, email_verified_at = NOW()
                 WHERE id = ? AND school_id = ? AND email = ?"
            );
            $update->execute([
                $passwordHash,
                (int)$setup["record_id"],
                (int)$setup["school_id"],
                strtolower($setup["email"])
            ]);
        } else {
            $update = $conn->prepare(
                "UPDATE parents
                 SET password = ?, email_verified_at = NOW()
                 WHERE id = ? AND email = ?"
            );
            $update->execute([
                $passwordHash,
                (int)$setup["record_id"],
                $setup["email"]
            ]);
        }

        if ($update->rowCount() !== 1) {
            $error = "Your school account could not be found. Contact your school for help.";
        } else {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION["role"] = $role;

            if ($role === "student") {
                $_SESSION["student_id"] = (int)$setup["record_id"];
                $_SESSION["student_school_id"] = (int)$setup["school_id"];
                $student = $conn->prepare(
                    "SELECT first_name, last_name
                     FROM students
                     WHERE id = ? AND school_id = ?
                     LIMIT 1"
                );
                $student->execute([(int)$setup["record_id"], (int)$setup["school_id"]]);
                $studentName = $student->fetch(PDO::FETCH_ASSOC);
                $_SESSION["student_name"] = $studentName
                    ? trim($studentName["first_name"] . " " . $studentName["last_name"])
                    : "Student";
                header("Location: ../student/dashboard.php");
            } else {
                $_SESSION["parent_id"] = (int)$setup["record_id"];
                $_SESSION["parent_email"] = $setup["email"];
                $parent = $conn->prepare(
                    "SELECT full_name FROM parents WHERE id = ? LIMIT 1"
                );
                $parent->execute([(int)$setup["record_id"]]);
                $_SESSION["parent_name"] = $parent->fetchColumn() ?: "Parent";
                header("Location: ../parent/dashboard.php");
            }

            exit();
        }
    }
}

$csrfToken = $_SESSION["portal_password_csrf"];
$portalName = $role === "student" ? "Student" : "Parent";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Portal Password – EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--gold:#e9b949;--white:#fff;--border:#e6ebf2;--muted:#68778d;--red:#b42318;}
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#10264b 0%,#183d70 60%,#2563ab 100%);font-family:'Segoe UI',Arial,sans-serif;color:#17243b;}
.card{width:min(440px,100%);overflow:hidden;border-radius:18px;background:var(--white);box-shadow:0 24px 64px rgba(10,26,53,.35);}
.card-header{padding:30px;text-align:center;background:linear-gradient(135deg,var(--navy),var(--blue));color:var(--white);}
.logo{display:inline-flex;align-items:center;gap:10px;margin-bottom:16px;color:var(--white);text-decoration:none;font-weight:800;letter-spacing:2px;}
.logo-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:9px;background:var(--gold);color:var(--navy);font-size:18px;}
h1{margin:0 0 6px;font-size:1.35rem;}
.card-header p{margin:0;color:rgba(255,255,255,.72);font-size:.9rem;}
.card-body{padding:30px;}
label{display:block;margin:16px 0 6px;color:var(--navy);font-size:.86rem;font-weight:700;}
input{width:100%;padding:12px;border:1.5px solid var(--border);border-radius:8px;font:inherit;}
input:focus{outline:2px solid #2563ab;outline-offset:1px;}
.error{padding:11px 13px;border:1px solid #f3cccc;border-radius:8px;background:#fff0f0;color:var(--red);font-size:.88rem;}
button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:8px;background:var(--navy);color:var(--white);font:inherit;font-weight:700;cursor:pointer;}
button:hover{background:var(--blue);}
.back{display:block;margin-top:18px;text-align:center;color:#2563ab;font-size:.88rem;text-decoration:none;}
</style>
</head>
<body>
<main class="card">
  <header class="card-header">
    <a class="logo" href="../index.php"><span class="logo-icon">E</span> EDUTRACK</a>
    <h1>Create your <?= htmlspecialchars($portalName) ?> password</h1>
    <p>Google verified <?= htmlspecialchars($setup["email"]) ?> for this school account.</p>
  </header>
  <div class="card-body">
    <?php if ($error !== ""): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <label for="password">New password</label>
      <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
      <label for="confirm_password">Confirm password</label>
      <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
      <button type="submit">Save Password</button>
    </form>
    <a class="back" href="<?= htmlspecialchars($loginPath) ?>">Back to <?= htmlspecialchars($portalName) ?> login</a>
  </div>
</main>
</body>
</html>
