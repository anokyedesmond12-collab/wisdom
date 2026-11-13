<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EDUTRACK | School Login</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="login-page">

    <div class="login-container">

        <div class="login-heading">
            <h1>EDUTRACK</h1>
            <p>School Administrator Portal</p>
        </div>

        <form action="login_process.php" method="POST"
              class="login-form">

            <h2>WELCOME TO EDUTRACK</h2>

            <p>Sign in to manage your school's academic activities.</p>

            <label>Email Address</label>
            <input type="email" name="email"
                   placeholder="Enter your school email" required>

            <label>Password</label>
            <input type="password" name="password"
                   placeholder="Enter your password" required>

            <button type="submit">Sign In</button>
            <div class="login-divider">
                <span>OR</span>
            </div>

            <a href="google_login.php" class="google-login-btn" style="display:flex; align-items:center; justify-content:center; text-decoration:none; padding:12px; border-radius:6px; font-weight:bold;">
                Continue with Google
            </a>

            <p class="login-footer">
                Not registered yet?
                <a href="register.php">Register your school</a>
            </p>

        </form>

    </div>

</body>
</html>