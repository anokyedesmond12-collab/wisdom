<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/paystack.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int)$_SESSION["school_id"];

// Fetch school
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    die("School account not found.");
}

$plan = trim($_POST["plan"] ?? $_GET["plan"] ?? "Basic Monthly");
if (!array_key_exists($plan, $PAYSTACK_PLANS)) {
    $plan = "Basic Monthly";
}

$planDetails = $PAYSTACK_PLANS[$plan];
$planReference = strtoupper(str_replace(" ", "_", $plan));
$reference = "EDU_" . $school_id . "_" . $planReference . "_" . time() . "_" . mt_rand(100, 999);

// Determine callback URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$callbackUrl = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") . "/verify_payment.php";

$metadata = [
    'school_id' => $school_id,
    'school_name' => $school['school_name'],
    'plan' => $plan,
    'custom_fields' => [
        [
            'display_name' => 'School Name',
            'variable_name' => 'school_name',
            'value' => $school['school_name']
        ],
        [
            'display_name' => 'Subscription Plan',
            'variable_name' => 'plan',
            'value' => $plan
        ]
    ]
];

$initResponse = initializePaystackTransaction(
    $school['email'],
    $planDetails['amount_pesewas'],
    $reference,
    $callbackUrl,
    $metadata
);

if ($initResponse['success']) {
    try {
        $ins = $conn->prepare("
            INSERT INTO payments (school_id, plan, amount, currency, reference, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $ins->execute([
            $school_id,
            $plan,
            $planDetails["price"],
            PAYSTACK_CURRENCY,
            $reference
        ]);
        header("Location: " . $initResponse['authorization_url']);
        exit();
    } catch (PDOException $e) {
        error_log("Unable to record Paystack payment intent: " . $e->getMessage());
        header("Location: pay_subscription.php?plan=" . urlencode($plan) . "&error=" . urlencode("Unable to record the payment. Please try again."));
        exit();
    }
} else {
    header("Location: pay_subscription.php?plan=" . urlencode($plan) . "&error=" . urlencode($initResponse['message']));
    exit();
}
?>
