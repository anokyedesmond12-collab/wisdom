<?php
// Shared Google OAuth initiator for role-based portals
// Usage: include and call startGoogleOAuth('teacher')
session_start();

require_once __DIR__ . "/../config/google_oauth.php";

$role = $_GET['role'] ?? 'teacher';
$allowed = ['teacher', 'student', 'parent'];
if (!in_array($role, $allowed)) $role = 'teacher';

$redirectUri = 'http://localhost/edutrack/' . $role . '/google_callback.php';

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['oauth_role']         = $role;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['google_oauth_state'],
    'access_type'   => 'online',
    'prompt'        => 'select_account',
];

header("Location: https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params));
exit();

