<?php

session_start();

require_once __DIR__ . "/../config/database.php";
$subscriptionPlanConfig = require __DIR__ . "/../config/subscription_plans.php";
$allowedPlans = array_keys($subscriptionPlanConfig["available"]);

// Check request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}

// Collect school information
$school_name = trim($_POST["school_name"] ?? "");
$school_type = trim($_POST["school_type"] ?? "");
$location = trim($_POST["location"] ?? "");
$admin_name = trim($_POST["admin_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$subscription_plan = trim($_POST["subscription_plan"] ?? "");

// Validate required fields
if (
    $school_name === "" ||
    $school_type === "" ||
    $location === "" ||
    $admin_name === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $phone === "" ||
    strlen($password) < 8 ||
    $subscription_plan === ""
) {
    exit("Please complete all registration fields correctly. Password must contain at least 8 characters.");
}

// Validate school type
$allowedTypes = ["Primary", "JHS", "SHS", "Combined"];

if (!in_array($school_type, $allowedTypes, true)) {
    exit("Invalid school type selected.");
}

// Validate subscription plan selection
if (!in_array($subscription_plan, $allowedPlans, true)) {
    exit("Invalid subscription plan selected.");
}

// Check logo upload
if (
    !isset($_FILES["school_logo"]) ||
    $_FILES["school_logo"]["error"] !== UPLOAD_ERR_OK
) {
    exit("Please upload your school logo.");
}

$logo = $_FILES["school_logo"];

// Check file size
if ($logo["size"] > 2 * 1024 * 1024) {
    exit("Your school logo must not exceed 2MB.");
}

// Check actual image type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($logo["tmp_name"]);

$allowedImages = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];

if (!isset($allowedImages[$mime])) {
    exit("Invalid logo format. Upload JPG, PNG or WEBP.");
}

// Create upload directory
// Create upload directory
$uploadDirectory = __DIR__ . "/../uploads/school_logos/";

if (
    !is_dir($uploadDirectory) &&
    !mkdir($uploadDirectory, 0755, true) &&
    !is_dir($uploadDirectory)
) 
    exit("Unable to create the school logo upload folder.");
// Generate unique logo name
$logoName = "school_" . bin2hex(random_bytes(16)) .
            "." . $allowedImages[$mime];

$logoDestination = $uploadDirectory . $logoName;

$logoPath = "uploads/school_logos/" . $logoName;

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {

    // Upload logo
    if (!move_uploaded_file($logo["tmp_name"], $logoDestination)) {
        throw new Exception("Unable to upload school logo.");
    }

    // Save school information
    $sql = "
        INSERT INTO schools
        (
            school_name,
            school_type,
            location,
            admin_name,
            email,
            phone,
            password,
            subscription_plan,
            school_logo
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $school_name,
        $school_type,
        $location,
        $admin_name,
        $email,
        $phone,
        $hashed_password,
        $subscription_plan,
        $logoPath
    ]);

    // Save registration details for confirmation page
    $_SESSION["registration_success"] = [
        "school_name" => $school_name,
        "school_type" => $school_type,
        "email" => $email,
        "logo" => $logoPath
    ];

    // Redirect to success page
    header("Location: registration_success.php");
    exit();

} catch (PDOException $e) {

    if (isset($logoDestination) && is_file($logoDestination)) {
        unlink($logoDestination);
    }

    error_log("School Registration Error: " . $e->getMessage());

    if ($e->getCode() == "23000") {
        exit("This email address has already been registered.");
    }

    exit("Registration failed. Please check your information and try again.");

} catch (Exception $e) {
 if (isset($logoDestination) && is_file($logoDestination)) {
        unlink($logoDestination);
    }

    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
}

?>