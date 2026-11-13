<?php
require_once __DIR__ . "/auth.php";
requireSuperAdmin();
require_once __DIR__ . "/../config/database.php";

$totalSchools = (int)$conn->query("SELECT COUNT(*) FROM schools")->fetchColumn();
$pendingSchools = (int)$conn->query("SELECT COUNT(*) FROM schools WHERE approval_status = 'Pending'")->fetchColumn();
$approvedSchools = (int)$conn->query("SELECT COUNT(*) FROM schools WHERE approval_status = 'Approved'")->fetchColumn();
$rejectedSchools = (int)$conn->query("SELECT COUNT(*) FROM schools WHERE approval_status = 'Rejected'")->fetchColumn();
$paymentSummary = $conn->query("
    SELECT
        SUM(CASE WHEN status = 'Success' THEN 1 ELSE 0 END) AS success_count,
        COALESCE(SUM(CASE WHEN status = 'Success' THEN amount ELSE 0 END), 0) AS received_total
    FROM payments
")->fetch(PDO::FETCH_ASSOC);

$schools = $conn->query("SELECT * FROM schools ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$payments = $conn->query("
    SELECT p.*, s.school_name, s.email
    FROM payments p
    LEFT JOIN schools s ON s.id = p.school_id
    ORDER BY p.created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
$csrfToken = superAdminCsrfToken();

function adminEscape($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDUTRACK - Platform Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime(__DIR__ . "/dashboard.css"); ?>">
</head>
<body class="superadmin-body">
    <div class="superadmin-app">
        <aside class="superadmin-sidebar" id="admin-navigation">
            <a class="admin-brand" href="#overview">
                <span class="admin-brand-mark" aria-hidden="true">E</span>
                <span>EduTrack<small>PLATFORM CONTROL</small></span>
            </a>
            <div class="admin-sidebar-label">MANAGEMENT</div>
            <nav class="admin-nav" aria-label="Platform administration">
                <a class="active" href="#overview" aria-current="page"><span aria-hidden="true">OV</span>Overview</a>
                <a href="#schools"><span aria-hidden="true">SC</span>Schools</a>
                <a href="#payments"><span aria-hidden="true">PY</span>Payments</a>
            </nav>
            <div class="admin-sidebar-footer">
                <span class="admin-live"><i aria-hidden="true"></i> Secure session active</span>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                    <button class="admin-logout" type="submit"><span aria-hidden="true">↗</span> Sign out</button>
                </form>
            </div>
        </aside>
        <button class="admin-menu-toggle" type="button" aria-controls="admin-navigation" aria-expanded="false" aria-label="Open dashboard menu">
            <span aria-hidden="true"><i></i><i></i><i></i></span>
        </button>
        <button class="admin-backdrop" type="button" aria-label="Close dashboard menu" hidden></button>

        <main class="superadmin-main">
            <header class="admin-topbar">
                <div>
                    <span class="admin-eyebrow">PLATFORM ADMINISTRATION</span>
                    <h1>Overview</h1>
                    <p>Monitor school access and verified payments from one workspace.</p>
                </div>
                <div class="admin-topbar-tools">
                    <div class="admin-today"><span>Today</span><strong data-admin-date></strong></div>
                    <form class="admin-mobile-logout" action="logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                        <button class="admin-logout" type="submit">Sign out</button>
                    </form>
                </div>
            </header>

            <section class="admin-hero" id="overview">
                <div>
                    <span class="admin-hero-kicker">EDUTRACK OPERATIONS</span>
                    <h2>Platform at a glance</h2>
                    <p>Review registrations, manage school approvals, and track verified Paystack receipts.</p>
                </div>
                <div class="admin-hero-mark" aria-hidden="true">ET</div>
            </section>

            <section class="admin-metrics" aria-label="Platform statistics">
                <article class="admin-metric metric-total">
                    <div class="metric-top"><span>Total schools</span><span class="metric-icon" aria-hidden="true">TS</span></div>
                    <strong data-count="<?php echo $totalSchools; ?>"><?php echo $totalSchools; ?></strong>
                    <small>Registered on EduTrack</small>
                </article>
                <article class="admin-metric metric-pending">
                    <div class="metric-top"><span>Pending review</span><span class="metric-icon" aria-hidden="true">PR</span></div>
                    <strong data-count="<?php echo $pendingSchools; ?>"><?php echo $pendingSchools; ?></strong>
                    <small>Awaiting an approval decision</small>
                </article>
                <article class="admin-metric metric-approved">
                    <div class="metric-top"><span>Approved schools</span><span class="metric-icon" aria-hidden="true">AP</span></div>
                    <strong data-count="<?php echo $approvedSchools; ?>"><?php echo $approvedSchools; ?></strong>
                    <small>Cleared for platform access</small>
                </article>
                <article class="admin-metric metric-rejected">
                    <div class="metric-top"><span>Rejected schools</span><span class="metric-icon" aria-hidden="true">RJ</span></div>
                    <strong data-count="<?php echo $rejectedSchools; ?>"><?php echo $rejectedSchools; ?></strong>
                    <small>Not approved for access</small>
                </article>
                <article class="admin-metric metric-payments">
                    <div class="metric-top"><span>Verified receipts</span><span class="metric-icon" aria-hidden="true">VP</span></div>
                    <strong data-count="<?php echo (int)$paymentSummary["success_count"]; ?>"><?php echo (int)$paymentSummary["success_count"]; ?></strong>
                    <small>GH₵<?php echo number_format((float)$paymentSummary["received_total"], 2); ?> received</small>
                </article>
            </section>

            <section class="admin-section" id="schools">
                <div class="admin-section-heading">
                    <div>
                        <span class="admin-eyebrow">SCHOOL ACCESS</span>
                        <h2>Registered schools</h2>
                        <p>Search records and review each school’s approval and payment status.</p>
                    </div>
                    <div class="admin-filters">
                        <label class="admin-search">
                            <span class="sr-only">Search schools</span>
                            <span aria-hidden="true">⌕</span>
                            <input type="search" id="school-search" placeholder="Search schools">
                        </label>
                        <label class="sr-only" for="school-status-filter">Filter by approval status</label>
                        <select id="school-status-filter">
                            <option value="">All approval statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="admin-table-card">
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>School</th><th>Type</th><th>Email</th><th>Subscription</th>
                                    <th>Payment</th><th>Approval</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="schools-table-body">
                                <?php if (!$schools): ?>
                                    <tr><td class="admin-empty" colspan="7">No schools have registered yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($schools as $school): ?>
                                        <?php
                                        $approval = (string)($school["approval_status"] ?? "");
                                        $approvalClass = in_array($approval, ["Pending", "Approved", "Rejected"], true)
                                            ? strtolower($approval)
                                            : "pending";
                                        $payment = (string)($school["payment_status"] ?? "");
                                        $paymentClass = strtolower($payment) === "paid" ? "paid" : "unpaid";
                                        $schoolSearch = strtolower(implode(" ", [
                                            $school["school_name"] ?? "",
                                            $school["school_type"] ?? "",
                                            $school["email"] ?? "",
                                            $school["subscription_plan"] ?? "",
                                            $payment,
                                            $approval
                                        ]));
                                        ?>
                                        <tr data-school-row data-status="<?php echo adminEscape($approvalClass); ?>" data-search="<?php echo adminEscape($schoolSearch); ?>">
                                            <td><strong class="school-name"><?php echo adminEscape($school["school_name"]); ?></strong></td>
                                            <td><?php echo adminEscape($school["school_type"]); ?></td>
                                            <td><?php echo adminEscape($school["email"]); ?></td>
                                            <td><?php echo adminEscape($school["subscription_plan"]); ?></td>
                                            <td><span class="status-pill payment-<?php echo adminEscape($paymentClass); ?>"><?php echo adminEscape($payment); ?></span></td>
                                            <td><span class="status-pill approval-<?php echo adminEscape($approvalClass); ?>"><?php echo adminEscape($approval); ?></span></td>
                                            <td>
                                                <div class="school-actions">
                                                    <?php foreach (["Approved", "Rejected"] as $action): ?>
                                                        <form action="update_school_status.php" method="post" data-confirm-action="<?php echo adminEscape(strtolower($action)); ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo adminEscape($csrfToken); ?>">
                                                            <input type="hidden" name="school_id" value="<?php echo (int)$school["id"]; ?>">
                                                            <button class="status-action <?php echo strtolower($action); ?>" type="submit" name="action" value="<?php echo $action; ?>"><?php echo $action; ?></button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="admin-filter-empty" id="school-filter-empty" hidden>No schools match those filters.</p>
                    <div class="admin-table-footer"><span id="school-result-count"><?php echo count($schools); ?></span> school records</div>
                </div>
            </section>

            <section class="admin-section" id="payments">
                <div class="admin-section-heading">
                    <div>
                        <span class="admin-eyebrow">BILLING ACTIVITY</span>
                        <h2>Paystack transactions</h2>
                        <p>Latest 100 transactions. Successful rows are confirmed against Paystack.</p>
                    </div>
                    <label class="admin-search payment-search">
                        <span class="sr-only">Search transactions</span>
                        <span aria-hidden="true">⌕</span>
                        <input type="search" id="payment-search" placeholder="Search transactions">
                    </label>
                </div>
                <div class="admin-table-card">
                    <div class="admin-table-scroll">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>School</th><th>Billing Email</th><th>Reference</th>
                                    <th>Plan</th><th>Amount</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="payments-table-body">
                                <?php if (!$payments): ?>
                                    <tr><td class="admin-empty" colspan="7">No transactions received yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): ?>
                                        <?php
                                        $paymentStatus = (string)($payment["status"] ?? "");
                                        $paymentSearch = strtolower(implode(" ", [
                                            $payment["created_at"] ?? "",
                                            $payment["school_name"] ?? "",
                                            $payment["email"] ?? "",
                                            $payment["reference"] ?? "",
                                            $payment["plan"] ?? "",
                                            $paymentStatus
                                        ]));
                                        ?>
                                        <tr data-payment-row data-search="<?php echo adminEscape($paymentSearch); ?>">
                                            <td><?php echo adminEscape($payment["created_at"]); ?></td>
                                            <td><?php echo adminEscape($payment["school_name"]); ?></td>
                                            <td><?php echo adminEscape($payment["email"]); ?></td>
                                            <td><code><?php echo adminEscape($payment["reference"]); ?></code></td>
                                            <td><?php echo adminEscape($payment["plan"]); ?></td>
                                            <td><?php echo adminEscape($payment["currency"]); ?> <?php echo number_format((float)$payment["amount"], 2); ?></td>
                                            <td><span class="status-pill payment-<?php echo strtolower($paymentStatus) === "success" ? "paid" : "unpaid"; ?>"><?php echo adminEscape($paymentStatus); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="admin-filter-empty" id="payment-filter-empty" hidden>No transactions match your search.</p>
                    <div class="admin-table-footer">Latest <?php echo count($payments); ?> transactions</div>
                </div>
            </section>
        </main>
    </div>
    <script src="dashboard.js?v=<?php echo filemtime(__DIR__ . "/dashboard.js"); ?>" defer></script>
</body>
</html>
