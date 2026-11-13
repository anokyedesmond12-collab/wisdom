<?php
session_start();

require_once __DIR__ . "/../config/google_oauth.php";

$role = $_GET["role"] ?? "school_admin";
$action = $_GET["action"] ?? "login";
$allowedRoles = ["school_admin", "student", "parent"];

if (!in_array($role, $allowedRoles, true) || !in_array($action, ["login", "create_password", "register"], true)) {
    http_response_code(400);
    exit("Invalid Google sign-in request.");
}

if ($role === "school_admin" && $action !== "login") {
    http_response_code(400);
    exit("Invalid Google sign-in request.");
}

$schoolId = null;
$studentAdmission = null;
if ($role === "student") {
    $schoolId = filter_var($_GET["school_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$schoolId || $schoolId < 1) {
        header("Location: ../student/login.php?error=Select+your+school+before+continuing+with+Google");
        exit();
    }

    $studentAdmission = trim($_GET["student_id"] ?? "");
    if ($studentAdmission === "" || strlen($studentAdmission) > 50) {
        $target = $action === "register" ? "register.php" : "login.php";
        header("Location: ../student/" . $target . "?error=Enter+your+admission+number");
        exit();
    }
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['google_oauth_role'] = $role;
$_SESSION['google_oauth_action'] = $action;
$_SESSION['google_oauth_school_id'] = $schoolId;
$_SESSION['google_oauth_student_id'] = $studentAdmission;

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['google_oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$google_url = 'https://accounts.google.com/o/oauth2/v2/auth?' .
              http_build_query($params);

header("Location: " . $google_url);
exit();
?>