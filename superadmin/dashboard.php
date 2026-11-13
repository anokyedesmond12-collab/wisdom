<?php
require_once __DIR__ . "/../admin/auth.php";
requireSuperAdmin();
header("Location: ../admin/dashboard.php");
exit();
