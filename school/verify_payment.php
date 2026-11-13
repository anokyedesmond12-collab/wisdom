<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/paystack.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int)$_SESSION["school_id"];

// Paystack returns the reference to this callback; all payment details come from
// the saved payment intent and Paystack's server-to-server verification.
$reference = trim($_GET["reference"] ?? $_POST["reference"] ?? "");

if (empty($reference)) {
    die("Payment reference is missing.");
}

$paymentVerified = false;
$errorMessage = "";
$plan = "";
$amount = 0;
$durationLabel = "";

$paymentStmt = $conn->prepare("SELECT * FROM payments WHERE reference = ? AND school_id = ? LIMIT 1");
$paymentStmt->execute([$reference, $school_id]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || !array_key_exists($payment["plan"], $PAYSTACK_ALL_PLANS)) {
    $errorMessage = "No matching payment was found for this school account.";
} else {
    $plan = $payment["plan"];
    $planDetails = $PAYSTACK_ALL_PLANS[$plan];
    $amount = (float)$payment["amount"];
    $durationLabel = $planDetails["duration_label"];

    if ($payment["status"] === "Success") {
        $paymentVerified = true;
    } elseif ($payment["status"] !== "Pending") {
        $errorMessage = "This payment is not available for verification.";
    } else {
        $verification = verifyPaystackTransaction($reference);
        $transaction = $verification["data"] ?? [];

        if (
            $verification["success"] &&
            ($transaction["reference"] ?? "") === $reference &&
            ($transaction["currency"] ?? "") === PAYSTACK_CURRENCY &&
            (int)($transaction["amount"] ?? 0) === (int)$planDetails["amount_pesewas"] &&
            (float)$payment["amount"] === (float)$planDetails["price"] &&
            ($payment["currency"] ?? "") === PAYSTACK_CURRENCY
        ) {
            try {
                $conn->beginTransaction();
                $lockStmt = $conn->prepare("SELECT status FROM payments WHERE reference = ? AND school_id = ? FOR UPDATE");
                $lockStmt->execute([$reference, $school_id]);
                $lockedStatus = $lockStmt->fetchColumn();

                if ($lockedStatus === "Pending") {
                    $upPay = $conn->prepare("
                        UPDATE payments
                        SET status = 'Success', paid_at = NOW()
                        WHERE reference = ? AND school_id = ? AND status = 'Pending'
                    ");
                    $upPay->execute([$reference, $school_id]);

                    if (isset($planDetails["duration_months"])) {
                        $upSchool = $conn->prepare("
                            UPDATE schools
                            SET payment_status = 'Paid',
                                subscription_plan = ?,
                                approval_status = 'Approved',
                                subscription_expires_at = DATE_ADD(NOW(), INTERVAL ? MONTH)
                            WHERE id = ?
                        ");
                        $upSchool->execute([$plan, (int)$planDetails["duration_months"], $school_id]);
                    } else {
                        $upSchool = $conn->prepare("
                            UPDATE schools
                            SET payment_status = 'Paid',
                                subscription_plan = ?,
                                approval_status = 'Approved',
                                subscription_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
                            WHERE id = ?
                        ");
                        $upSchool->execute([$plan, (int)$planDetails["duration_days"], $school_id]);
                    }
                    $paymentVerified = true;
                } elseif ($lockedStatus === "Success") {
                    $paymentVerified = true;
                } else {
                    $errorMessage = "This payment has already been processed or is no longer pending.";
                }

                $conn->commit();
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Paystack payment update failed: " . $e->getMessage());
                $errorMessage = "We could not save the verified payment. Please contact support.";
                $paymentVerified = false;
            }
        } else {
            $errorMessage = !empty($verification["message"])
                ? $verification["message"]
                : "Paystack could not verify the expected amount and currency for this transaction.";
        }
    }
}

if ($paymentVerified) {
    $_SESSION["subscription_plan"] = $plan;
    $_SESSION["payment_status"] = "Paid";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation | EDUTRACK</title>
    <style>
        :root {
            --navy: #10264b;
            --blue: #183d70;
            --gold: #e9b949;
            --white: #ffffff;
            --bg: #f4f6fa;
            --text: #17243b;
            --muted: #64748b;
            --border: #e2e8f0;
            --green: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .receipt-card {
            background: white;
            width: 100%;
            max-width: 520px;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(16,38,75,0.08);
            border: 1px solid var(--border);
            text-align: center;
        }

        .status-icon {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: 0 auto 20px;
        }

        .status-success {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-failed {
            background: #fee2e2;
            color: #dc2626;
        }

        h1 {
            color: var(--navy);
            font-size: 24px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 25px;
        }

        .receipt-table {
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px 20px;
            text-align: left;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        .receipt-label {
            color: var(--muted);
        }

        .receipt-value {
            font-weight: bold;
            color: var(--navy);
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 13px 22px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--navy);
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a6a;
        }

        .btn-outline {
            border: 1px solid var(--border);
            color: var(--text);
            background: white;
        }

        .btn-outline:hover {
            border-color: var(--navy);
        }
    </style>
</head>
<body>

<div class="receipt-card">

    <?php if ($paymentVerified): ?>

        <div class="status-icon status-success">✓</div>
        <h1>Subscription Activated!</h1>
        <p class="subtitle">Your Paystack payment was successfully processed and confirmed.</p>

        <div class="receipt-table">
            <div class="receipt-row">
                <span class="receipt-label">Subscription Plan:</span>
                <span class="receipt-value"><?php echo htmlspecialchars($plan); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Amount Paid:</span>
                <span class="receipt-value">GH₵<?php echo number_format($amount, 2); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Payment Gateway:</span>
                <span class="receipt-value">Paystack</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Reference:</span>
                <span class="receipt-value" style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($reference); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Status:</span>
                <span class="receipt-value" style="color: var(--green);">PAID & APPROVED</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Active For:</span>
                <span class="receipt-value"><?php echo htmlspecialchars($durationLabel, ENT_QUOTES, "UTF-8"); ?> Access</span>
            </div>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn btn-primary">Open School Dashboard →</a>
            <a href="../index.php" class="btn btn-outline">Go to Homepage</a>
        </div>

    <?php else: ?>

        <div class="status-icon status-failed">✕</div>
        <h1>Payment Verification Failed</h1>
        <p class="subtitle"><?php echo htmlspecialchars($errorMessage ?: "Unable to confirm your transaction with Paystack."); ?></p>

        <div class="actions">
            <a href="pay_subscription.php?plan=<?php echo urlencode($plan); ?>" class="btn btn-primary">Try Again</a>
            <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
