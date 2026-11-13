<?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (empty($_SESSION["portal_registration_csrf"])) {
    $_SESSION["portal_registration_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["portal_registration_csrf"];
$verifyStep = isset($_GET["verify"]) &&
    ($_SESSION["portal_registration_pending_role"] ?? "") === "student" &&
    !empty($_SESSION["portal_registration_pending_email"]);
$email = $_SESSION["portal_registration_pending_email"] ?? "";
$error = $_SESSION["portal_registration_error"] ?? ($_GET["error"] ?? "");
unset($_SESSION["portal_registration_error"]);
$schools = $conn->query("SELECT id, school_name FROM schools ORDER BY school_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Student Registration - EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--blue-light:#2563ab;--gold:#e9b949;--white:#fff;--border:#e6ebf2;--red:#b42318;--red-light:#fff0f0;--muted:#68778d}
*{box-sizing:border-box}body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,var(--blue-light),var(--navy));min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{background:var(--white);border-radius:18px;box-shadow:0 24px 64px rgba(10,26,53,.35);width:100%;max-width:460px;overflow:hidden}.header{background:linear-gradient(135deg,var(--blue-light),var(--navy));padding:30px;text-align:center;color:#fff}.header a{color:var(--gold);text-decoration:none;font-weight:800;letter-spacing:2px}.header h1{font-size:1.35rem;margin:16px 0 6px}.header p{color:rgba(255,255,255,.75);font-size:.9rem}.body{padding:28px 32px}.error{background:var(--red-light);border:1px solid var(--red);color:var(--red);padding:11px 14px;border-radius:8px;font-size:.88rem;margin-bottom:16px}.field{margin-bottom:16px}label{display:block;font-size:.85rem;font-weight:600;color:var(--navy);margin:0 0 6px}input,select{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.93rem}.primary,.google{display:block;text-align:center;width:100%;padding:12px;border:0;border-radius:8px;font-size:.96rem;font-weight:700;cursor:pointer;text-decoration:none}.primary{background:var(--blue-light);color:#fff}.google{margin-top:12px;background:#fff;border:1px solid var(--border);color:var(--navy)}.links{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;font-size:.87rem}.links a{color:var(--blue-light);text-decoration:none}.note{text-align:center;color:var(--muted);font-size:.82rem;padding:0 28px 24px;line-height:1.5}.divider{text-align:center;color:var(--muted);font-size:.82rem;margin-top:14px}
</style>
</head>
<body>
<main class="card">
  <header class="header">
    <a href="../index.php">EDUTRACK</a>
    <h1><?= $verifyStep ? "Verify your email" : "Create Student Account" ?></h1>
    <p><?= $verifyStep ? "Enter the code we sent to your school-record email." : "Create a password or continue with Google." ?></p>
  </header>
  <section class="body">
    <?php if ($error !== ""): ?><div class="error"><?= htmlspecialchars(is_string($error) ? $error : "") ?></div><?php endif; ?>
    <?php if ($verifyStep): ?>
      <p>Verification code sent to <strong><?= htmlspecialchars($email) ?></strong>. It expires in 10 minutes.</p>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="student">
        <input type="hidden" name="action" value="verify_code">
        <div class="field"><label for="verification_code">Six-digit email code</label><input id="verification_code" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
        <button class="primary" type="submit">Verify and Create Account</button>
      </form>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="student">
        <input type="hidden" name="action" value="resend_code">
        <button class="google" type="submit">Resend verification code</button>
      </form>
    <?php else: ?>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="student">
        <input type="hidden" name="action" value="request_code">
        <div class="field"><label for="school_id">School</label><select id="school_id" name="school_id" required><option value="">Select your school</option><?php foreach ($schools as $school): ?><option value="<?= (int)$school["id"] ?>"><?= htmlspecialchars($school["school_name"]) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="student_id">Admission number</label><input id="student_id" name="student_id" maxlength="50" required></div>
        <div class="field"><label for="email">Email recorded by your school</label><input id="email" name="email" type="email" autocomplete="email" required></div>
        <div class="field"><label for="password">Create password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div>
        <button class="primary" type="submit">Create account with email</button>
      </form>
      <div class="divider">or verify with Google</div>
      <button class="google" type="button" onclick="startStudentGoogle()">Continue with Google</button>
    <?php endif; ?>
    <nav class="links"><a href="login.php">Already registered? Login</a><a href="../index.php">Back to home</a></nav>
  </section>
  <footer class="note">Your school, admission number, and email must match an existing student record. Email signup requires a one-time email code.</footer>
</main>
<script>
function startStudentGoogle() {
  const schoolId = document.getElementById("school_id").value;
  const admissionNumber = document.getElementById("student_id").value.trim();
  if (!schoolId) { document.getElementById("school_id").reportValidity(); return; }
  if (!admissionNumber) { document.getElementById("student_id").reportValidity(); return; }
  const url = new URL("../school/google_login.php", window.location.href);
  url.searchParams.set("role", "student");
  url.searchParams.set("action", "register");
  url.searchParams.set("school_id", schoolId);
  url.searchParams.set("student_id", admissionNumber);
  window.location.assign(url.toString());
}
</script>
</body>
</html>
