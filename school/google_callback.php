<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/google_oauth.php";

if (
    !isset($_GET["state"], $_SESSION["google_oauth_state"]) ||
    !hash_equals($_SESSION["google_oauth_state"], (string)$_GET["state"])
) {
    http_response_code(400);
    exit("Invalid Google login request. Please try again.");
}

$role = $_SESSION["google_oauth_role"] ?? "school_admin";
$action = $_SESSION["google_oauth_action"] ?? "login";
$schoolId = $_SESSION["google_oauth_school_id"] ?? null;
$studentAdmission = $_SESSION["google_oauth_student_id"] ?? null;
unset(
    $_SESSION["google_oauth_state"],
    $_SESSION["google_oauth_role"],
    $_SESSION["google_oauth_action"],
    $_SESSION["google_oauth_school_id"],
    $_SESSION["google_oauth_student_id"]
);

$loginPath = $role === "student"
    ? (($action === "register") ? "../student/register.php" : "../student/login.php")
    : ($role === "parent"
        ? (($action === "register") ? "../parent/register.php" : "../parent/login.php")
        : "login.php");
$fail = static function ($message) use ($loginPath) {
    header("Location: " . $loginPath . "?error=" . rawurlencode($message));
    exit();
};

if (isset($_GET["error"])) {
    $fail("Google sign-in was cancelled or could not be completed.");
}

if (!isset($_GET["code"])) {
    $fail("Google sign-in was not completed.");
}
if (!is_string($_GET["code"]) || $_GET["code"] === "") {
    $fail("Google sign-in was not completed.");
}

$tokenRequest = curl_init("https://oauth2.googleapis.com/token");
curl_setopt_array($tokenRequest, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "code" => $_GET["code"],
        "client_id" => GOOGLE_CLIENT_ID,
        "client_secret" => GOOGLE_CLIENT_SECRET,
        "redirect_uri" => GOOGLE_REDIRECT_URI,
        "grant_type" => "authorization_code"
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);
$tokenResponse = curl_exec($tokenRequest);
$tokenStatus = curl_getinfo($tokenRequest, CURLINFO_HTTP_CODE);
curl_close($tokenRequest);

if ($tokenResponse === false || $tokenStatus !== 200) {
    $fail("Google authentication failed. Please try again.");
}

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData["access_token"])) {
    $fail("Google authentication failed. Please try again.");
}

$userRequest = curl_init("https://www.googleapis.com/oauth2/v3/userinfo");
curl_setopt_array($userRequest, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $tokenData["access_token"]
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);
$userResponse = curl_exec($userRequest);
$userStatus = curl_getinfo($userRequest, CURLINFO_HTTP_CODE);
curl_close($userRequest);

if ($userResponse === false || $userStatus !== 200) {
    $fail("Unable to verify your Google account. Please try again.");
}

$googleUser = json_decode($userResponse, true);
if (
    !is_array($googleUser) ||
    !isset($googleUser["email"], $googleUser["email_verified"]) ||
    !is_string($googleUser["email"]) ||
    $googleUser["email_verified"] !== true ||
    !filter_var($googleUser["email"], FILTER_VALIDATE_EMAIL)
) {
    $fail("Your Google email could not be verified.");
}

$email = strtolower($googleUser["email"]);
$identity = null;

if ($role === "student" && filter_var($schoolId, FILTER_VALIDATE_INT)) {
    $studentSql = "SELECT id, school_id, first_name, last_name
                   FROM students
                   WHERE school_id = ? AND email = ?";
    $studentParams = [(int)$schoolId, $email];
    $studentSql .= " AND student_id = ?";
    $studentParams[] = $studentAdmission;
    $studentSql .= " LIMIT 1";
    $stmt = $conn->prepare($studentSql);
    $stmt->execute($studentParams);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $identity = [
            "role" => "student",
            "id" => (int)$student["id"],
            "school_id" => (int)$student["school_id"],
            "name" => trim($student["first_name"] . " " . $student["last_name"]),
            "email" => $email
        ];
    }
} elseif ($role === "parent") {
    $stmt = $conn->prepare("SELECT id, full_name, email FROM parents WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action === "register") {
        $rosterCheck = $conn->prepare(
            "SELECT id FROM students WHERE LOWER(parent_email) = ? LIMIT 1"
        );
        $rosterCheck->execute([$email]);
        if (!$rosterCheck->fetchColumn()) {
            $parent = null;
        }
    }

    if (!$parent) {
        $linkedStudent = $conn->prepare(
            "SELECT parent_name, parent_phone
             FROM students
             WHERE LOWER(parent_email) = ?
             ORDER BY id
             LIMIT 1"
        );
        $linkedStudent->execute([$email]);
        $studentLink = $linkedStudent->fetch(PDO::FETCH_ASSOC);

        if ($studentLink) {
            try {
                $createParent = $conn->prepare(
                    "INSERT INTO parents (full_name, email, phone, password)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
                );
                $createParent->execute([
                    $studentLink["parent_name"] ?: "Parent",
                    $email,
                    $studentLink["parent_phone"],
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)
                ]);
                $parentId = (int)$conn->lastInsertId();
                $stmt = $conn->prepare("SELECT id, full_name, email FROM parents WHERE id = ? LIMIT 1");
                $stmt->execute([$parentId]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Unable to provision Google parent account: " . $e->getMessage());
                $fail("Unable to create your parent portal account. Please contact your school.");
            }
        }
    }

    if ($parent) {
        $identity = [
            "role" => "parent",
            "id" => (int)$parent["id"],
            "name" => $parent["full_name"] ?: "Parent",
            "email" => $email
        ];
    }
} elseif ($role === "school_admin") {
    $stmt = $conn->prepare(
        "SELECT id, school_name, admin_name, email
         FROM schools
         WHERE email = ? AND approval_status = 'Approved' AND payment_status = 'Paid'
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($school) {
        $identity = [
            "role" => "school_admin",
            "id" => (int)$school["id"],
            "name" => $school["admin_name"],
            "school_name" => $school["school_name"],
            "email" => $school["email"]
        ];
    }
}

if (!$identity) {
    $fail(
        $role === "student"
            ? "No student record matches this school, admission number, and Google email. Ask your school to update your record."
            : ($role === "parent"
                ? ($action === "register"
                    ? "This Google email is not linked to a student at a school. Ask the school to update the parent email."
                    : "This Google email is not linked to a student at this school.")
                : "No approved and paid school account matches this Google email.")
    );
}

if ($role === "student") {
    $verifyEmail = $conn->prepare(
        "UPDATE students SET email_verified_at = NOW() WHERE id = ? AND school_id = ?"
    );
    $verifyEmail->execute([$identity["id"], $identity["school_id"]]);
} elseif ($role === "parent") {
    $verifyEmail = $conn->prepare("UPDATE parents SET email_verified_at = NOW() WHERE id = ?");
    $verifyEmail->execute([$identity["id"]]);
}

session_regenerate_id(true);
$_SESSION = [];

if (in_array($action, ["create_password", "register"], true) && in_array($role, ["student", "parent"], true)) {
    $_SESSION["portal_password_setup"] = [
        "role" => $identity["role"],
        "record_id" => $identity["id"],
        "school_id" => $identity["school_id"] ?? null,
        "email" => $identity["email"],
        "expires_at" => time() + 900
    ];
    $_SESSION["portal_password_csrf"] = bin2hex(random_bytes(32));
    header("Location: ../portal/create_password.php");
    exit();
}

$_SESSION["role"] = $identity["role"];
if ($role === "student") {
    $_SESSION["student_id"] = $identity["id"];
    $_SESSION["student_school_id"] = $identity["school_id"];
    $_SESSION["student_name"] = $identity["name"];
} elseif ($role === "parent") {
    $_SESSION["parent_id"] = $identity["id"];
    $_SESSION["parent_name"] = $identity["name"];
    $_SESSION["parent_email"] = $identity["email"];
} else {
    $_SESSION["role"] = "school_admin";
    $_SESSION["school_id"] = $identity["id"];
    $_SESSION["school_name"] = $identity["school_name"];
    $_SESSION["admin_name"] = $identity["name"];
    $_SESSION["school_email"] = $identity["email"];
}

header("Location: " . ($role === "student" ? "../student/dashboard.php" : ($role === "parent" ? "../parent/dashboard.php" : "dashboard.php")));
exit();
