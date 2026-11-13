<?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (empty($_SESSION["teacher_registration_csrf"])) {
    $_SESSION["teacher_registration_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["teacher_registration_csrf"];
$error = $_GET["error"] ?? "";
$submitted = isset($_GET["submitted"]);
$schools = $conn->query("SELECT id, school_name FROM schools ORDER BY school_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Registration - EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--light:#2563ab;--gold:#e9b949;--white:#fff;--border:#e6ebf2;--red:#b42318;--red-bg:#fff0f0;--green:#166534;--green-bg:#ecfdf5}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,var(--light),var(--navy));font-family:'Segoe UI',Arial,sans-serif}.card{width:min(460px,100%);background:var(--white);border-radius:18px;overflow:hidden;box-shadow:0 24px 64px rgba(10,26,53,.35)}header{padding:30px;text-align:center;background:linear-gradient(135deg,var(--light),var(--navy));color:#fff}header a{color:var(--gold);text-decoration:none;font-weight:800;letter-spacing:2px}h1{font-size:1.35rem;margin:16px 0 6px}header p{font-size:.9rem;color:rgba(255,255,255,.75);margin:0}.body{padding:28px 32px}.notice{padding:11px 14px;border-radius:8px;font-size:.88rem;margin-bottom:16px}.error{background:var(--red-bg);color:var(--red)}.success{background:var(--green-bg);color:var(--green)}.field{margin-bottom:16px}label{display:block;color:var(--navy);font-size:.85rem;font-weight:700;margin-bottom:6px}input,select{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.93rem}.submit{width:100%;padding:12px;border:0;border-radius:8px;background:var(--blue);color:#fff;font-size:.96rem;font-weight:700;cursor:pointer}.links{display:flex;justify-content:space-between;gap:12px;margin-top:18px;font-size:.87rem}.links a{color:var(--light);text-decoration:none}
</style>
</head>
<body>
<main class="card">
  <header><a href="../index.php">EDUTRACK</a><h1>Create Teacher Account</h1><p>Submit your details for school administrator approval.</p></header>
  <section class="body">
    <?php if ($error !== ""): ?><div class="notice error"><?= htmlspecialchars(urldecode($error)) ?></div><?php endif; ?>
    <?php if ($submitted): ?><div class="notice success">Registration submitted. Your school administrator must approve your account and assign your classes before you can sign in.</div><?php endif; ?>
    <form method="post" action="register_process.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field"><label for="school_id">School</label><select id="school_id" name="school_id" required><option value="">Select your school</option><?php foreach ($schools as $school): ?><option value="<?= (int)$school["id"] ?>"><?= htmlspecialchars($school["school_name"]) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="150" autocomplete="name" required></div>
      <div class="field"><label for="email">School email</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required></div>
      <div class="field"><label for="password">Create password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
      <div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div>
      <button class="submit" type="submit">Submit Registration</button>
    </form>
    <nav class="links"><a href="login.php">Already approved? Login</a><a href="../index.php">Back to home</a></nav>
  </section>
</main>
</body>
</html>
