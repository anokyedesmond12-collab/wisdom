<?php
session_start();
if (empty($_SESSION["portal_registration_csrf"])) {
    $_SESSION["portal_registration_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["portal_registration_csrf"];
$verifyStep = isset($_GET["verify"]) &&
    ($_SESSION["portal_registration_pending_role"] ?? "") === "parent" &&
    !empty($_SESSION["portal_registration_pending_email"]);
$email = $_SESSION["portal_registration_pending_email"] ?? "";
$error = $_SESSION["portal_registration_error"] ?? ($_GET["error"] ?? "");
unset($_SESSION["portal_registration_error"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Parent Registration - EduTrack</title>
<style>
:root{--navy:#10264b;--green:#16865b;--gold:#e9b949;--white:#fff;--border:#e6ebf2;--red:#b42318;--red-light:#fff0f0;--muted:#68778d}
*{box-sizing:border-box}body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,var(--green),var(--navy));min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{background:var(--white);border-radius:18px;box-shadow:0 24px 64px rgba(10,26,53,.35);width:100%;max-width:460px;overflow:hidden}.header{background:linear-gradient(135deg,var(--green),var(--navy));padding:30px;text-align:center;color:#fff}.header a{color:var(--gold);text-decoration:none;font-weight:800;letter-spacing:2px}.header h1{font-size:1.35rem;margin:16px 0 6px}.header p{color:rgba(255,255,255,.75);font-size:.9rem}.body{padding:28px 32px}.error{background:var(--red-light);border:1px solid var(--red);color:var(--red);padding:11px 14px;border-radius:8px;font-size:.88rem;margin-bottom:16px}.field{margin-bottom:16px}label{display:block;font-size:.85rem;font-weight:600;color:var(--navy);margin:0 0 6px}input{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.93rem}.primary,.google{display:block;text-align:center;width:100%;padding:12px;border:0;border-radius:8px;font-size:.96rem;font-weight:700;cursor:pointer;text-decoration:none}.primary{background:var(--green);color:#fff}.google{margin-top:12px;background:#fff;border:1px solid var(--border);color:var(--navy)}.links{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;font-size:.87rem}.links a{color:var(--green);text-decoration:none}.note{text-align:center;color:var(--muted);font-size:.82rem;padding:0 28px 24px;line-height:1.5}
</style>
</head>
<body>
<main class="card">
  <header class="header">
    <a href="../index.php">EDUTRACK</a>
    <h1><?= $verifyStep ? "Verify your email" : "Create Parent Account" ?></h1>
    <p><?= $verifyStep ? "Enter the code we sent to your linked email." : "Create a password or continue with Google." ?></p>
  </header>
  <section class="body">
    <?php if ($error !== ""): ?><div class="error"><?= htmlspecialchars(is_string($error) ? $error : "") ?></div><?php endif; ?>
    <?php if ($verifyStep): ?>
      <p>Verification code sent to <strong><?= htmlspecialchars($email) ?></strong>. It expires in 10 minutes.</p>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="parent">
        <input type="hidden" name="action" value="verify_code">
        <div class="field"><label for="verification_code">Six-digit email code</label><input id="verification_code" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
        <button class="primary" type="submit">Verify and Create Account</button>
      </form>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="parent">
        <input type="hidden" name="action" value="resend_code">
        <button class="google" type="submit">Resend verification code</button>
      </form>
    <?php else: ?>
      <form method="post" action="../portal/register_process.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="role" value="parent">
        <input type="hidden" name="action" value="request_code">
        <div class="field"><label for="email">Email linked to your child's school record</label><input id="email" name="email" type="email" autocomplete="email" required></div>
        <div class="field"><label for="password">Create password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="field"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div>
        <button class="primary" type="submit">Create account with email</button>
      </form>
      <a class="google" href="../school/google_login.php?role=parent&amp;action=register">Continue with Google</a>
    <?php endif; ?>
    <nav class="links"><a href="login.php">Already registered? Login</a><a href="../index.php">Back to home</a></nav>
  </section>
  <footer class="note">Your email must already be linked to a student by the school. Email signup requires a one-time email code.</footer>
</main>
</body>
</html>
