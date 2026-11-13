<?php
$error = $_GET['error'] ?? '';
require_once __DIR__ . "/../config/database.php";
$schools = $conn->query("SELECT id, school_name FROM schools ORDER BY school_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Teacher Login – EduTrack</title>
<style>
:root{--navy:#10264b;--blue:#183d70;--blue-light:#2563ab;--gold:#e9b949;--white:#ffffff;--background:#f5f7fb;--muted:#68778d;--border:#e6ebf2;--red:#b42318;--red-light:#fff0f0;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,var(--blue-light) 0%,var(--blue) 60%,var(--navy) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.card{background:var(--white);border-radius:18px;box-shadow:0 24px 64px rgba(10,26,53,.35);width:100%;max-width:430px;overflow:hidden;}
.card-header{background:linear-gradient(135deg,var(--blue-light),var(--blue));padding:36px 36px 28px;text-align:center;}
.logo{display:inline-flex;align-items:center;gap:10px;text-decoration:none;margin-bottom:20px;}
.logo-icon{width:40px;height:40px;background:var(--gold);border-radius:9px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;color:var(--navy);}
.logo-text{font-size:1.3rem;font-weight:800;color:var(--white);letter-spacing:2px;}
.card-header h1{color:var(--white);font-size:1.3rem;font-weight:700;margin-bottom:4px;}
.card-header p{color:rgba(255,255,255,.65);font-size:.88rem;}
.card-body{padding:32px 36px;}
.error-box{background:var(--red-light);border:1px solid var(--red);color:var(--red);padding:11px 14px;border-radius:8px;font-size:.88rem;margin-bottom:20px;}
.form-group{margin-bottom:20px;}
label{display:block;font-size:.85rem;font-weight:600;color:var(--navy);margin-bottom:6px;}
input,select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:.93rem;color:#1a2535;outline:none;transition:border-color .2s;font-family:inherit;background:#fff;}
input:focus{border-color:var(--blue-light);}
.btn-submit{width:100%;background:var(--blue-light);color:var(--white);border:none;padding:13px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:4px;font-family:inherit;}
.btn-submit:hover{background:var(--blue);}
.form-links{margin-top:18px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:.85rem;}
.form-links a{color:var(--blue-light);text-decoration:none;}
.form-links a:hover{text-decoration:underline;}
.card-footer{padding:0 36px 28px;text-align:center;font-size:.85rem;color:var(--muted);}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <a href="../index.php" class="logo">
      <div class="logo-icon">E</div>
      <span class="logo-text">EDUTRACK</span>
    </a>
    <h1>👨‍🏫 Teacher Portal</h1>
    <p>Sign in to manage your classes and scores</p>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars(urldecode($error)) ?></div>
    <?php endif; ?>
    <form method="POST" action="login_process.php">
      <div class="form-group">
        <label for="school_id">School</label>
        <select id="school_id" name="school_id" required>
          <option value="">Select your school</option>
          <?php foreach ($schools as $school): ?>
            <option value="<?= (int)$school["id"] ?>"><?= htmlspecialchars($school["school_name"]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="teacher@school.edu.gh" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn-submit">Login to Teacher Portal</button>
      <div class="form-links">
        <span>Contact your school admin to reset access.</span>
        <a href="register.php">Register teacher account</a>
        <a href="../index.php">← Back to Home</a>
      </div>
    </form>
  </div>
  <div class="card-footer">Contact your school admin if you have login issues.</div>
</div>
</body>
</html>

