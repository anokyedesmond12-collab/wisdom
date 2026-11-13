<?php

session_start();

if (!isset($_SESSION["registration_success"])) {
    header("Location: register.php");
    exit();
}

$school = $_SESSION["registration_success"];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Successful | EduTrack</title>
<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f4f9;
    font-family: Arial, sans-serif;
}

.success-card {
    background: white;
    width: 100%;
    max-width: 480px;
    padding: 35px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 5px 25px rgba(0,0,0,0.07);
}

.success-icon {
    width: 75px;
    height: 75px;
    background: #e6f8ed;
    color: #159447;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin: 0 auto 20px;
}

h1 {
    color: #142b52;
    font-size: 25px;
}

.description {
    color: #667085;
    line-height: 1.7;
}

.school-logo {
    width: 100px;
    height: 100px;
    object-fit: contain;
    border-radius: 50%;
    border: 1px solid #eee;
    padding: 5px;
    margin: 15px auto;
}

.school-name {
    color: #142b52;
    font-size: 20px;
    font-weight: bold;
}

.details {
    background: #f7f9fc;
    border-radius: 10px;
    padding: 18px;
    text-align: left;
    margin: 20px 0;
}

.details p {
    color: #475467;
    margin: 12px 0;
    overflow-wrap: anywhere;
}

.status {
    color: #b7791f;
    font-weight: bold;
}

.button {
    display: block;
    width: 100%;
    padding: 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    margin-top: 12px;
}

.login {
    background: #142b52;
    color: white;
}

.home {
    background: #edf1f7;
    color: #142b52;
}

</style>
</head>
<body>
<div class="success-card">
<div class="success-icon">✓</div>

<h1>Account Created Successfully!</h1>

<p class="description">
    Congratulations! Your school has been successfully
    registered on EduTrack.
</p>

<img
    src="../<?= htmlspecialchars($school['logo']) ?>"
    class="school-logo"
    alt="School Logo"
>

<div class="school-name">
    <?= htmlspecialchars($school['school_name']) ?>
</div>

<div class="details">

    <p>
        <strong>School Type:</strong>
        <?= htmlspecialchars($school['school_type']) ?>
    </p>

    <p>
        <strong>Email:</strong>
        <?= htmlspecialchars($school['email']) ?>
    </p>

    <p>
        <strong>Account:</strong>
        <span style="color:green;">Successfully Created</span>
    </p>

    <p>
        <strong>Approval Status:</strong>
        <span class="status">Pending Approval</span>
    </p>

</div>

<p class="description">
    Thank you for choosing EduTrack.
    Your school registration details have been saved successfully.
</p>

<a href="login.php?redirect=subscription" class="button" style="background:#0ba4db; color:white;">
    💳 Complete Paystack Subscription Payment
</a>

<a href="login.php" class="button login">
    Go to Login
</a>

<a href="../index.php" class="button home">
    Return to Homepage
</a>
</div>
</body>
</html>