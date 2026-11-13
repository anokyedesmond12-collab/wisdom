<?php
require_once __DIR__ . "/../admin/auth.php";
requireSuperAdmin();

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !validateSuperAdminCsrf($_POST["csrf_token"] ?? null)
) {
    http_response_code(400);
    exit("Invalid request.");
}

destroySuperAdminSession();
header("Location: ../index.php");
exit();
