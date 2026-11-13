<?php

session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/google_oauth.php";

// Verify Google login state
if (
    !isset($_GET['state'], $_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])
) {
    die("Invalid Google login request. Please try again.");
}

unset($_SESSION['google_oauth_state']);

// Check authorization code
if (!isset($_GET['code'])) {
    die("Google login was not completed.");
}

// Exchange authorization code for access token
$ch = curl_init('https://oauth2.googleapis.com/token');

$postData = [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    die("Connection error: " . curl_error($ch));
}

curl_close($ch);

if ($httpCode !== 200) {
    die("Google authentication failed.");
}

$tokenData = json_decode($response, true);

if (empty($tokenData['access_token'])) {
    die("Google authentication failed. Please try again.");
}

// Retrieve Google account details
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');

curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $tokenData['access_token']
    ],
    CURLOPT_RETURNTRANSFER => true
]);

$userResponse = curl_exec($ch);
$userCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($userResponse === false) {
    die("Could not retrieve Google account details.");
}

curl_close($ch);

if ($userCode !== 200) {
    die("Unable to verify your Google account.");
}

$googleUser = json_decode($userResponse, true);

// Verify Google email
if (
    empty($googleUser['email']) ||
    empty($googleUser['email_verified'])
) {
    die("Your Google email could not be verified.");
}

$email = $googleUser['email'];

// Find registered, approved and paid school
$stmt = $conn->prepare("
    SELECT id, school_name, admin_name, email
    FROM schools
    WHERE email = ?
    AND approval_status = 'Approved'
    AND payment_status = 'Paid'
    LIMIT 1
");

$stmt->execute([$email]);

$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    die("No approved and paid EduTrack school account matches this Google email.");
}

// Create login session
session_regenerate_id(true);
$_SESSION = [];

$_SESSION['role'] = 'school_admin';
$_SESSION['school_id'] = $school['id'];
$_SESSION['school_name'] = $school['school_name'];
$_SESSION['admin_name'] = $school['admin_name'];
$_SESSION['school_email'] = $school['email'];

// Redirect to school dashboard
header("Location: dashboard.php");
exit();

?>
