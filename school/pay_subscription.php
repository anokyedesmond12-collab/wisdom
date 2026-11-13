<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/paystack.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int)$_SESSION["school_id"];

// Fetch school information
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Select an available billing period, defaulting legacy subscriptions to monthly.
$requestedPlan = trim($_GET["plan"] ?? "");
$storedPlan = (string)($school["subscription_plan"] ?? "");
$selectedPlan = array_key_exists($requestedPlan, $PAYSTACK_PLANS)
    ? $requestedPlan
    : (array_key_exists($storedPlan, $PAYSTACK_PLANS) ? $storedPlan : "Basic Monthly");

$planDetails = $PAYSTACK_PLANS[$selectedPlan];

// Fetch recent payment history
$payStmt = $conn->prepare("
    SELECT * FROM payments 
    WHERE school_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$payStmt->execute([$school_id]);
$paymentHistory = $payStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystack Subscription | EDUTRACK</title>
    <style>
        :root {
            --navy: #10264b;
            --navy-dark: #0b1c36;
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
            padding: 30px 15px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header-nav {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--navy);
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            padding: 8px 14px;
            background: white;
            border-radius: 6px;
            border: 1px solid var(--border);
            transition: 0.2s;
        }

        .back-link:hover {
            border-color: var(--navy);
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            color: var(--navy);
            font-size: 26px;
            margin-bottom: 6px;
        }

        .page-title p {
            color: var(--muted);
            font-size: 14px;
        }

        .status-banner {
            background: white;
            padding: 18px 24px;
            border-radius: 10px;
            border-left: 5px solid var(--gold);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-paid { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-failed { background: #fee2e2; color: #b91c1c; }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
            margin-bottom: 35px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .card h2 {
            font-size: 18px;
            color: var(--navy);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }

        .plan-tab {
            padding: 14px 10px;
            border-radius: 8px;
            border: 2px solid var(--border);
            background: #f8fafc;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
            transition: 0.2s;
        }

        .plan-tab:hover {
            border-color: var(--navy);
        }

        .plan-tab.active {
            border-color: var(--navy);
            background: #eef4ff;
        }

        .plan-tab strong {
            display: block;
            font-size: 15px;
            color: var(--navy);
            margin-bottom: 4px;
        }

        .plan-tab span {
            color: #2563eb;
            font-weight: bold;
            font-size: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--muted);
        }

        .info-val {
            font-weight: bold;
            color: var(--navy);
        }

        .price-highlight {
            font-size: 32px;
            font-weight: 800;
            color: var(--navy);
            margin: 15px 0 5px;
        }

        .price-period {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .paystack-btn {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: none;
            background: #0ba4db;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(11, 164, 219, 0.25);
        }

        .paystack-btn:hover {
            background: #0882ad;
            transform: translateY(-2px);
        }

        .paystack-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            color: var(--muted);
            font-size: 12px;
        }

        .features-list {
            list-style: none;
            margin-top: 15px;
        }

        .features-list li {
            padding: 8px 0;
            font-size: 13px;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .features-list li::before {
            content: "✓";
            color: var(--green);
            font-weight: bold;
        }

        /* PAYMENT HISTORY */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }

        th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
        }

        @media(max-width: 800px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            .plan-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header-nav">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        <span style="font-size: 13px; color: var(--muted);">EduTrack Subscription Billing</span>
    </div>

    <div class="page-title">
        <h1>School Subscription & Paystack Billing</h1>
        <p>Subscribe, renew, or upgrade your EduTrack school platform license securely.</p>
    </div>

    <?php if (!empty($_GET["error"])): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:14px; border-radius:8px; margin-bottom:20px; font-weight:600;">
            ⚠️ <?php echo htmlspecialchars($_GET["error"]); ?>
        </div>
    <?php endif; ?>

    <!-- CURRENT STATUS BANNER -->
    <div class="status-banner">
        <div>
            <div style="font-size: 12px; color: var(--muted); margin-bottom: 3px; font-weight: bold;">CURRENT SUBSCRIPTION</div>
            <strong style="font-size: 18px; color: var(--navy);"><?php echo htmlspecialchars($school["school_name"]); ?></strong>
            <span style="margin-left: 10px;" class="status-badge status-<?php echo strtolower($school["payment_status"] ?? 'pending'); ?>">
                <?php echo htmlspecialchars($school["payment_status"] ?? 'Pending'); ?>
            </span>
        </div>
        <div>
            <span style="font-size: 13px; color: var(--muted);">Active Plan:</span>
            <strong style="color: #2563eb; font-size: 15px; margin-left: 4px;"><?php echo htmlspecialchars($school["subscription_plan"] ?? 'Basic'); ?></strong>
            <?php if (!empty($school["subscription_expires_at"])): ?>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                    Valid until: <?php echo date("M d, Y", strtotime($school["subscription_expires_at"])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHECKOUT SECTION -->
    <div class="checkout-grid">

        <!-- PLAN DETAILS -->
        <div class="card">
            <h2>Select Subscription Plan</h2>

            <div class="plan-selector">
                <?php foreach ($PAYSTACK_PLANS as $key => $p): ?>
                    <a href="pay_subscription.php?plan=<?php echo urlencode($key); ?>" 
                       class="plan-tab <?php echo ($selectedPlan === $key) ? 'active' : ''; ?>">
                        <strong><?php echo htmlspecialchars($key); ?></strong>
                        <span>GH₵<?php echo number_format($p["price"], 0); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <h3 style="font-size: 16px; color: var(--navy); margin-bottom: 6px;">
                <?php echo htmlspecialchars($planDetails["name"]); ?>
            </h3>
            <p style="font-size: 13px; color: var(--muted); margin-bottom: 15px;">
                <?php echo htmlspecialchars($planDetails["description"]); ?>
            </p>

            <div class="info-row">
                <span class="info-label">School Name</span>
                <span class="info-val"><?php echo htmlspecialchars($school["school_name"]); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Administrator</span>
                <span class="info-val"><?php echo htmlspecialchars($school["admin_name"]); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Billing Email</span>
                <span class="info-val"><?php echo htmlspecialchars($school["email"]); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Billing Currency</span>
                <span class="info-val"><?php echo PAYSTACK_CURRENCY; ?> (Ghana Cedis)</span>
            </div>
            <div class="info-row">
                <span class="info-label">Duration</span>
                <span class="info-val"><?php echo htmlspecialchars($planDetails["duration_label"], ENT_QUOTES, "UTF-8"); ?> access</span>
            </div>

            <ul class="features-list">
                <li>Primary, JHS and SHS curriculum support</li>
                <li>Unlimited student marks and grading entry</li>
                <li>Automatic rankings, averages and terminals</li>
                <li>Instant activation via Paystack Mobile Money & Cards</li>
            </ul>
        </div>

        <!-- PAYMENT ACTION CARD -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h2>Order Summary</h2>
                <div class="price-highlight">
                    GH₵<?php echo number_format($planDetails["price"], 2); ?>
                </div>
                <div class="price-period">
                    Billed for <?php echo htmlspecialchars($planDetails["duration_label"], ENT_QUOTES, "UTF-8"); ?>
                </div>

                <div style="background: #f8fafc; border-radius: 8px; padding: 14px; margin-bottom: 20px; font-size: 13px; color: #475569;">
                    <div style="display:flex; justify-content:space-between; margin-bottom: 6px;">
                        <span>Subtotal:</span>
                        <strong>GH₵<?php echo number_format($planDetails["price"], 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom: 6px;">
                        <span>Setup Fee:</span>
                        <strong style="color:var(--green);">FREE</strong>
                    </div>
                    <hr style="border:none; border-top:1px solid #e2e8f0; margin:8px 0;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; color:var(--navy);">
                        <span>Total Due:</span>
                        <strong>GH₵<?php echo number_format($planDetails["price"], 2); ?></strong>
                    </div>
                </div>
            </div>

            <div>
                <!-- REAL PAYSTACK INITIALIZE FORM -->
                <form action="initialize_payment.php" method="POST">
                    <input type="hidden" name="plan" value="<?php echo htmlspecialchars($selectedPlan); ?>">
                    <button type="submit" class="paystack-btn">
                        <span>💳 Pay GH₵<?php echo number_format($planDetails["price"], 2); ?> with Paystack</span>
                    </button>
                </form>

                <div class="paystack-badge">
                    <span>🔒 Secured by Paystack (MTN MoMo, Telecel, AT, Visa/Mastercard)</span>
                </div>
            </div>
        </div>

    </div>

    <!-- PAYMENT HISTORY -->
    <div class="card">
        <h2>Payment & Billing History</h2>
        <?php if (count($paymentHistory) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentHistory as $pay): ?>
                        <tr>
                            <td><?php echo date("M d, Y H:i", strtotime($pay["created_at"])); ?></td>
                            <td style="font-family: monospace;"><?php echo htmlspecialchars($pay["reference"]); ?></td>
                            <td><strong><?php echo htmlspecialchars($pay["plan"]); ?></strong></td>
                            <td><?php echo htmlspecialchars($pay["currency"] . " " . number_format((float)$pay["amount"], 2)); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($pay["status"]); ?>">
                                    <?php echo htmlspecialchars($pay["status"]); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: var(--muted); font-size: 13px; padding: 15px 0;">No previous payments found for this school account.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
