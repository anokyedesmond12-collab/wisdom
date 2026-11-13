<?php
require_once __DIR__ . "/auth.php";
requireSuperAdmin();
require_once __DIR__ . "/../config/database.php";

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !validateSuperAdminCsrf($_POST["csrf_token"] ?? null)
) {
    http_response_code(400);
    exit("Invalid request.");
}

$schoolId = filter_var($_POST["school_id"] ?? null, FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (!$schoolId || !in_array($action, ["Approved", "Rejected"], true)) {
    http_response_code(400);
    exit("Invalid school or action.");
}

try {
    $check = $conn->prepare("SELECT payment_status FROM schools WHERE id = ?");
    $check->execute([$schoolId]);
    $school = $check->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        http_response_code(404);
        exit("School not found.");
    }

    if ($action === "Approved" && $school["payment_status"] !== "Paid") {
        http_response_code(409);
        exit("A verified payment is required before approving this school.");
    }

    $update = $conn->prepare("UPDATE schools SET approval_status = ? WHERE id = ?");
    $update->execute([$action, $schoolId]);
    header("Location: dashboard.php");
    exit();
} catch (PDOException $e) {
    error_log("Unable to update school approval status: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to update the school status. Please try again.");
}
