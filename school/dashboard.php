<?php
session_start();

require_once "../config/database.php";

// Handle logout
if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Protect dashboard
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int)$_SESSION["school_id"];

// Fetch school record
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Counts from database
$stmt = $conn->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$stmt->execute([$school_id]);
$totalClasses = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE school_id = ?");
$stmt->execute([$school_id]);
$totalSubjects = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
$stmt->execute([$school_id]);
$totalStudents = (int)$stmt->fetchColumn();

// Active Academic Year
$stmt = $conn->prepare("SELECT year_name FROM academic_years WHERE school_id = ? AND status = 'Active' LIMIT 1");
$stmt->execute([$school_id]);
$activeYear = $stmt->fetchColumn();
$activeYearDisplay = $activeYear ? $activeYear : "Not Set";

// Active Academic Term
$stmt = $conn->prepare("SELECT term_name FROM academic_terms WHERE school_id = ? AND status = 'Active' LIMIT 1");
$stmt->execute([$school_id]);
$activeTerm = $stmt->fetchColumn();
$activeTermDisplay = $activeTerm ? $activeTerm : "Not Set";

// Recent Students
$stmt = $conn->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.gender, c.class_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE s.school_id = ?
    ORDER BY s.id DESC
    LIMIT 5
");
$stmt->execute([$school_id]);
$recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adminName = !empty($school["admin_name"]) ? $school["admin_name"] : ($_SESSION["admin_name"] ?? "Administrator");
$schoolName = !empty($school["school_name"]) ? $school["school_name"] : ($_SESSION["school_name"] ?? "My School");
$schoolLogo = !empty($school["school_logo"]) && file_exists(__DIR__ . "/../" . $school["school_logo"]) ? "../" . $school["school_logo"] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDUTRACK | School Dashboard - <?php echo htmlspecialchars($schoolName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 270px;
            background: var(--navy);
            color: white;
            padding: 20px 14px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 100vh;
            position: sticky;
            top: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 15px;
        }

        .sidebar-logo-icon {
            width: 38px;
            height: 38px;
            background: var(--gold);
            color: var(--navy);
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .sidebar-brand-text {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            color: white;
        }

        .sidebar-nav {
            flex: 1;
        }

        .nav-item,
        .menu-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 11px 14px;
            margin-bottom: 4px;
            color: #d1d9e6;
            text-decoration: none;
            background: transparent;
            border: none;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 7px;
            transition: 0.2s;
        }

        .nav-item:hover,
        .menu-toggle:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        .nav-item.active {
            background: var(--gold);
            color: var(--navy);
            font-weight: bold;
        }

        .submenu {
            display: none;
            padding-left: 10px;
            margin-bottom: 6px;
        }

        .submenu.show {
            display: block;
        }

        .submenu a {
            display: block;
            padding: 9px 14px;
            margin-bottom: 2px;
            font-size: 13px;
            color: #b0bfd4;
            text-decoration: none;
            border-radius: 6px;
            background: rgba(0,0,0,0.15);
            transition: 0.2s;
        }

        .submenu a:hover {
            background: rgba(233,185,73,0.2);
            color: var(--gold);
        }

        .sidebar-footer {
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px;
            background: #b91c1c;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background: #991b1b;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .top-bar {
            background: white;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-bar-left h2 {
            font-size: 20px;
            color: var(--navy);
        }

        .session-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            background: #eef4ff;
            color: #1e40af;
            font-size: 12px;
            font-weight: 600;
        }

        .session-badge.active {
            background: #ecfdf5;
            color: #065f46;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .top-site-link {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            transition: 0.2s;
        }

        .top-site-link:hover {
            color: var(--navy);
            border-color: var(--navy);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--navy);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: bold;
            font-size: 14px;
            color: var(--navy);
        }

        .user-role {
            font-size: 12px;
            color: var(--muted);
        }

        .content-area {
            padding: 28px;
            flex: 1;
        }

        /* DASHBOARD OVERVIEW */
        .welcome-card {
            background: linear-gradient(135deg, var(--navy), #1d4ed8);
            color: white;
            padding: 26px 30px;
            border-radius: 14px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(16,38,75,0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-card h1 {
            font-size: 25px;
            margin-bottom: 6px;
        }

        .welcome-card p {
            color: #e2e8f0;
            font-size: 14px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .metric-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.06);
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .icon-students { background: #e0f2fe; color: #0284c7; }
        .icon-classes { background: #fef3c7; color: #d97706; }
        .icon-subjects { background: #ede9fe; color: #7c3aed; }
        .icon-session { background: #dcfce7; color: #16a34a; }

        .metric-details small {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .metric-details strong {
            font-size: 26px;
            color: var(--navy);
        }

        .section-title {
            font-size: 18px;
            color: var(--navy);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border: 1px solid var(--border);
            padding: 18px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: 0.2s;
            cursor: pointer;
        }

        .action-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233,185,73,0.15);
        }

        .action-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-card-icon {
            font-size: 20px;
        }

        .action-card-title {
            font-size: 15px;
            font-weight: bold;
            color: var(--navy);
        }

        .action-card-desc {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }

        /* RECENT STUDENTS TABLE */
        .table-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .table-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-card-header h3 {
            font-size: 16px;
            color: var(--navy);
        }

        .view-all-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 12px 24px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            color: #334155;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .empty-state {
            padding: 30px;
            text-align: center;
            color: var(--muted);
        }

        /* FEATURE CONTAINER (IFRAME) */
        #featureContainer {
            display: none;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .feature-header {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feature-header h2 {
            font-size: 17px;
            color: var(--navy);
            margin: 0;
        }

        .feature-controls {
            display: flex;
            gap: 10px;
        }

        .feature-btn {
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
            transition: 0.2s;
        }

        .feature-btn:hover {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
        }

        .feature-btn-close {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .feature-btn-close:hover {
            background: #dc2626;
            color: white;
        }

        #featureFrame {
            width: 100%;
            height: calc(100vh - 180px);
            min-height: 650px;
            border: none;
            display: block;
            background: white;
        }

        /* RESPONSIVE */
        @media(max-width: 1100px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 768px) {
            .dashboard-layout {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: relative;
                max-height: none;
            }
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .top-bar-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
    <style>
        :root {
            --navy: #183153;
            --navy-dark: #10243d;
            --blue: #4268c9;
            --gold: #f0c66e;
            --bg: #f3f5f9;
            --text: #26364b;
            --muted: #78869a;
            --border: #e7ebf1;
            --green: #159b79;
        }

        body.dashboard-page {
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .dashboard-layout { min-height: 100vh; }

        .sidebar {
            width: 258px;
            padding: 22px 16px 18px;
            background: linear-gradient(165deg, #1d385c 0%, #142946 62%, #10243d 100%);
            box-shadow: 8px 0 28px rgba(20, 41, 70, .08);
        }

        .sidebar-brand {
            padding: 7px 9px 22px;
            margin-bottom: 20px;
            border-bottom-color: rgba(255,255,255,.12);
        }

        .sidebar-logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: var(--gold);
            box-shadow: 0 5px 14px rgba(240,198,110,.2);
        }

        .sidebar-brand-text {
            font-size: 17px;
            letter-spacing: 1.4px;
        }

        .sidebar-nav { display: flex; flex-direction: column; gap: 4px; }

        .nav-item,
        .menu-toggle {
            min-height: 44px;
            padding: 10px 12px;
            margin: 0;
            border-radius: 9px;
            color: #c3cfde;
            font-size: 13px;
            transition: background .2s ease, color .2s ease, transform .2s ease;
        }

        .nav-item:hover,
        .menu-toggle:hover {
            background: rgba(255,255,255,.09);
            color: white;
            transform: translateX(2px);
        }

        .nav-item.active {
            color: #fff;
            background: rgba(240,198,110,.17);
            box-shadow: inset 3px 0 var(--gold);
        }

        .submenu {
            padding: 4px 0 6px 12px;
            margin: 0 0 3px 12px;
            border-left: 1px solid rgba(255,255,255,.14);
        }

        .submenu a {
            padding: 8px 12px;
            margin-bottom: 2px;
            border-radius: 7px;
            background: transparent;
            color: #aebdd0;
            font-size: 12px;
        }

        .submenu a:hover {
            color: #fff;
            background: rgba(255,255,255,.08);
        }

        .sidebar-footer {
            padding-top: 16px;
            border-top-color: rgba(255,255,255,.12);
        }

        .logout-btn {
            padding: 11px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.07);
            color: #d7e0eb;
            border-radius: 9px;
        }

        .logout-btn:hover { background: rgba(255,255,255,.13); }

        .main-content { background: var(--bg); }

        .top-bar {
            min-height: 76px;
            padding: 14px 34px;
            gap: 18px;
            box-shadow: 0 4px 16px rgba(24,49,83,.035);
            border-bottom-color: #edf0f5;
        }

        .top-bar-left { flex-wrap: wrap; gap: 12px; }
        .top-bar-left h2 { color: var(--navy); font-size: 17px; }

        .session-badge {
            padding: 6px 10px;
            background: #f1f3f7;
            color: #66758a;
            font-size: 11px;
        }

        .session-badge.active {
            background: #e8f6f1;
            color: #14785f;
        }

        .top-bar-right { gap: 16px; }

        .top-site-link {
            padding: 8px 12px;
            border-color: var(--border);
            color: #5d6c80;
            border-radius: 8px;
            font-size: 12px;
        }

        .top-site-link:hover {
            color: var(--blue);
            border-color: #c7d2ef;
            background: #f7f8fc;
        }

        .user-profile { gap: 9px; }

        .user-avatar {
            width: 39px;
            height: 39px;
            background: #edf1fa;
            color: var(--blue);
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px var(--border);
        }

        .user-name { color: var(--navy); font-size: 13px; }
        .user-role { color: var(--muted); font-size: 11px; }

        .content-area {
            width: 100%;
            max-width: 1600px;
            padding: 30px 34px 40px;
            margin: 0 auto;
        }

        .welcome-card {
            position: relative;
            overflow: hidden;
            min-height: 166px;
            padding: 30px 34px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 17px;
            background:
                radial-gradient(circle at 82% 18%, rgba(255,255,255,.12), transparent 25%),
                linear-gradient(115deg, #1b385d 0%, #315c91 64%, #3b6ba2 100%);
            box-shadow: 0 13px 28px rgba(27,56,93,.13);
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            width: 245px;
            height: 245px;
            right: 9%;
            bottom: -190px;
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 50%;
            box-shadow: 0 0 0 25px rgba(255,255,255,.035), 0 0 0 52px rgba(255,255,255,.025);
            pointer-events: none;
        }

        .welcome-card > div { position: relative; z-index: 1; }

        .welcome-card h1 {
            margin-bottom: 8px;
            font-size: clamp(22px, 2.4vw, 29px);
            letter-spacing: -.5px;
        }

        .welcome-card p {
            max-width: 690px;
            color: #dbe5f1;
            font-size: 13px;
        }

        .welcome-card .feature-btn {
            min-height: 42px;
            padding: 11px 17px !important;
            border-radius: 9px;
            color: #26364b !important;
            background: var(--gold) !important;
            box-shadow: 0 5px 13px rgba(10,30,55,.16);
        }

        .welcome-card .feature-btn:hover {
            background: #f6d58f !important;
            color: #26364b !important;
            transform: translateY(-1px);
        }

        .metrics-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .metric-card {
            min-height: 112px;
            padding: 19px;
            gap: 14px;
            border: 1px solid #e9edf3;
            border-radius: 13px;
            box-shadow: 0 4px 15px rgba(31,49,74,.035);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(31,49,74,.08);
        }

        .metric-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 13px;
            font-size: 21px;
        }

        .icon-students { background: #e7f2fc; color: #287ab5; }
        .icon-classes { background: #fff4df; color: #bb821c; }
        .icon-subjects { background: #efebfb; color: #7354ba; }
        .icon-session { background: #e5f5ee; color: #18845e; }

        .metric-details small {
            margin-bottom: 2px;
            color: #8692a3;
            font-size: 10px;
            letter-spacing: .75px;
        }

        .metric-details strong {
            color: var(--navy);
            font-size: 25px;
            letter-spacing: -.4px;
        }

        .metric-details strong[style] { font-size: 16px !important; }

        .section-title {
            margin: 0 0 15px;
            color: var(--navy);
            font-size: 16px;
            letter-spacing: -.2px;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 13px;
            margin-bottom: 28px;
        }

        .action-card {
            min-height: 112px;
            padding: 16px;
            gap: 10px;
            border-color: #e9edf3;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(31,49,74,.025);
        }

        .action-card:hover {
            border-color: #ccd7ee;
            background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 9px 19px rgba(31,49,74,.07);
        }

        .action-card-header { gap: 9px; }

        .action-card-icon {
            width: 33px;
            height: 33px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: #f0f3f9;
            font-size: 17px;
        }

        .action-card-title { color: #273b58; font-size: 13px; }
        .action-card-desc { color: #7a8798; font-size: 11px; line-height: 1.55; }

        .table-card {
            border-color: #e8ecf2;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(31,49,74,.04);
        }

        .table-card-header {
            min-height: 65px;
            padding: 16px 21px;
            gap: 12px;
        }

        .table-card-header h3 {
            color: var(--navy);
            font-size: 14px;
        }

        .view-all-link {
            color: var(--blue);
            font-size: 12px;
        }

        .view-all-link:hover { color: #294b9e; }

        .table-card table { min-width: 610px; }

        th {
            padding: 12px 21px;
            background: #f7f8fb;
            color: #8290a1;
            font-size: 10px;
            letter-spacing: .8px;
        }

        td {
            padding: 14px 21px;
            color: #46566b;
            font-size: 12px;
        }

        tbody tr:hover { background: #fafbfd; }
        td strong { color: #344a66; }

        .empty-state {
            padding: 38px 20px;
            color: #7b899b;
            font-size: 13px;
        }

        #featureContainer {
            border-color: #e8ecf2;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(31,49,74,.04);
        }

        .feature-header {
            min-height: 62px;
            padding: 13px 18px;
            gap: 12px;
        }

        .feature-header h2 { color: var(--navy); font-size: 15px; }

        .feature-btn {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 11px;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(66,104,201,.45);
            outline-offset: 2px;
        }

        @media (max-width: 1100px) {
            .metrics-grid,
            .quick-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .content-area { padding-right: 24px; padding-left: 24px; }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                max-height: 48vh;
                padding: 15px 14px;
                position: relative;
            }

            .sidebar-brand { padding-bottom: 13px; margin-bottom: 10px; }
            .sidebar-nav { overflow-y: auto; }
            .sidebar-footer { padding-top: 10px; }
            .top-bar { padding: 13px 18px; }
            .content-area { padding: 20px 18px 30px; }
            .welcome-card { padding: 24px; }
            .welcome-card::after { right: -80px; }
            .table-card { overflow-x: auto; }
            #featureFrame { min-height: 520px; }
        }

        @media (max-width: 520px) {
            .metrics-grid,
            .quick-actions-grid { grid-template-columns: 1fr; }
            .metric-card { min-height: 92px; }
            .top-bar-right { align-items: center; gap: 10px; }
            .user-info { text-align: left; }
            .welcome-card { align-items: flex-start; flex-direction: column; }
            .welcome-card h1 { font-size: 22px; }
            .table-card-header { align-items: flex-start; flex-direction: column; }
            .feature-header { align-items: flex-start; flex-direction: column; }
            .feature-controls { width: 100%; flex-wrap: wrap; }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
            }
        }
    </style>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime(__DIR__ . "/dashboard.css"); ?>">
</head>
<body class="dashboard-page">

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-logo-icon">E</div>
            <div class="sidebar-brand-text">EDUTRACK</div>
        </div>

        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" id="overviewMenuLink" onclick="showDashboard(event)">
                <span>📊 Dashboard</span>
            </a>

            <!-- ACADEMIC MANAGEMENT -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('academicMenu')">
                <span>📅 Academic Session</span>
                <span id="academicMenuArrow">▼</span>
            </button>
            <div class="submenu" id="academicMenu">
                <a href="create_academic_year.php" target="featureFrame" onclick="showFeature('Create Academic Year', this.href)">Create Academic Year</a>
                <a href="activate_academic_year.php" target="featureFrame" onclick="showFeature('Activate Academic Year', this.href)">Activate Academic Year</a>
                <a href="academic_years.php" target="featureFrame" onclick="showFeature('Academic Years List', this.href)">View Academic Years</a>
                <a href="create_academic_term.php" target="featureFrame" onclick="showFeature('Create Academic Term', this.href)">Create Academic Term</a>
                <a href="academic_terms.php" target="featureFrame" onclick="showFeature('Academic Terms List', this.href)">View Academic Terms</a>
            </div>

            <!-- CLASS MANAGEMENT -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('classMenu')">
                <span>🏫 Class Management</span>
                <span id="classMenuArrow">▼</span>
            </button>
            <div class="submenu" id="classMenu">
                <a href="classes.php" target="featureFrame" onclick="showFeature('Create Class', this.href)">Create Class</a>
                <a href="classes.php" target="featureFrame" onclick="showFeature('View Classes', this.href)">View All Classes</a>
                <a href="classes_class.php" target="featureFrame" onclick="showFeature('Class Details & Stats', this.href)">Class Roster & Counts</a>
            </div>

            <!-- SUBJECT MANAGEMENT -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('subjectMenu')">
                <span>📚 Subject Management</span>
                <span id="subjectMenuArrow">▼</span>
            </button>
            <div class="submenu" id="subjectMenu">
                <a href="create_subject.php" target="featureFrame" onclick="showFeature('Create Subject', this.href)">Create Subject</a>
                <a href="subject.php" target="featureFrame" onclick="showFeature('Manage Subjects', this.href)">Manage Subjects</a>
                <a href="assign_subjects.php" target="featureFrame" onclick="showFeature('Assign Subjects to Classes', this.href)">Assign to Classes</a>
            </div>

            <!-- STUDENT MANAGEMENT -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('studentMenu')">
                <span>🎓 Student Management</span>
                <span id="studentMenuArrow">▼</span>
            </button>
            <div class="submenu" id="studentMenu">
                <a href="../student/student.php" target="featureFrame" onclick="showFeature('Student Management & Registration', this.href)">Manage Students</a>
            </div>

            <!-- TEACHER MANAGEMENT -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('teacherMenu')">
                <span>👨‍🏫 Teacher Management</span>
                <span id="teacherMenuArrow">▼</span>
            </button>
            <div class="submenu" id="teacherMenu">
                <a href="teachers.php" target="featureFrame" onclick="showFeature('Teacher Management & Assignments', this.href)">Manage Teachers & Assignments</a>
            </div>

            <!-- SUBSCRIPTION -->
            <a href="pay_subscription.php" target="featureFrame" onclick="showFeature('Paystack Subscription & Billing', this.href)" class="nav-item">
                <span>💳 Subscription & Billing</span>
            </a>

            <!-- SETTINGS -->
            <button type="button" class="menu-toggle" onclick="toggleMenu('settingsMenu')">
                <span>⚙️ School Settings</span>
                <span id="settingsMenuArrow">▼</span>
            </button>
            <div class="submenu" id="settingsMenu">
                <a href="upload_logo.php" target="featureFrame" onclick="showFeature('Upload School Logo', this.href)">Upload School Logo</a>
                <a href="pay_subscription.php" target="featureFrame" onclick="showFeature('Paystack Subscription & Billing', this.href)">Subscription & Paystack</a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <span>🚪 Sign Out</span>
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOP BAR -->
        <header class="top-bar">
            <div class="top-bar-left">
                <h2><?php echo htmlspecialchars($schoolName); ?></h2>
                <span class="session-badge <?php echo ($activeYear && $activeTerm) ? 'active' : ''; ?>">
                    📅 <?php echo htmlspecialchars($activeYearDisplay); ?> &bull; <?php echo htmlspecialchars($activeTermDisplay); ?>
                </span>
            </div>

            <div class="top-bar-right">
                <a href="../index.php" class="top-site-link">Public Site ↗</a>

                <div class="user-profile">
                    <?php if ($schoolLogo): ?>
                        <img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="School Logo" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar"><?php echo strtoupper(substr($schoolName, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($adminName); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($school["school_type"]); ?> Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT AREA -->
        <div class="content-area">

            <!-- OVERVIEW -->
            <div id="dashboardOverview">

                <!-- WELCOME BANNER -->
                <div class="welcome-card">
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($adminName); ?>! 👋</h1>
                        <p>Managing <?php echo htmlspecialchars($schoolName); ?> (<?php echo htmlspecialchars($school["school_type"]); ?>) &bull; Location: <?php echo htmlspecialchars($school["location"]); ?></p>
                    </div>
                    <div>
                        <a href="../student/student.php" target="featureFrame" onclick="showFeature('Student Management', this.href)" class="feature-btn" style="background:var(--gold); border:none; color:var(--navy); padding:10px 18px; font-weight:bold;">
                            + Register Student
                        </a>
                    </div>
                </div>

                <!-- METRICS -->
                <div class="metrics-grid">

                    <div class="metric-card">
                        <div class="metric-icon icon-students">🎓</div>
                        <div class="metric-details">
                            <small>TOTAL STUDENTS</small>
                            <strong><?php echo $totalStudents; ?></strong>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon icon-classes">🏫</div>
                        <div class="metric-details">
                            <small>TOTAL CLASSES</small>
                            <strong><?php echo $totalClasses; ?></strong>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon icon-subjects">📚</div>
                        <div class="metric-details">
                            <small>TOTAL SUBJECTS</small>
                            <strong><?php echo $totalSubjects; ?></strong>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon icon-session">📅</div>
                        <div class="metric-details">
                            <small>ACTIVE SESSION</small>
                            <strong style="font-size:18px;"><?php echo htmlspecialchars($activeYearDisplay); ?></strong>
                        </div>
                    </div>

                </div>

                <!-- QUICK ACTIONS -->
                <h3 class="section-title">⚡ Quick Management Actions</h3>
                <div class="quick-actions-grid">

                    <a href="../student/student.php" target="featureFrame" onclick="showFeature('Student Management', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">🎓</span>
                            <span class="action-card-title">Manage Students</span>
                        </div>
                        <span class="action-card-desc">Register new students, update records, and view class lists.</span>
                    </a>

                    <a href="teachers.php" target="featureFrame" onclick="showFeature('Teacher Management & Assignments', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">👨‍🏫</span>
                            <span class="action-card-title">Teacher Assignments</span>
                        </div>
                        <span class="action-card-desc">Assign classes and subjects; teachers manage attendance, scores and academic reports.</span>
                    </a>

                    <a href="classes.php" target="featureFrame" onclick="showFeature('Class Management', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">🏫</span>
                            <span class="action-card-title">Class Setup</span>
                        </div>
                        <span class="action-card-desc">Add new class streams and view student enrollments.</span>
                    </a>

                    <a href="subject.php" target="featureFrame" onclick="showFeature('Subject Management', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">📚</span>
                            <span class="action-card-title">Subjects</span>
                        </div>
                        <span class="action-card-desc">Configure subjects and map curriculum to specific classes.</span>
                    </a>

                    <a href="academic_years.php" target="featureFrame" onclick="showFeature('Academic Calendar', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">📅</span>
                            <span class="action-card-title">Academic Calendar</span>
                        </div>
                        <span class="action-card-desc">Manage academic years, terms, and current active period.</span>
                    </a>

                    <a href="upload_logo.php" target="featureFrame" onclick="showFeature('Upload Logo', this.href)" class="action-card">
                        <div class="action-card-header">
                            <span class="action-card-icon">🖼️</span>
                            <span class="action-card-title">School Emblem</span>
                        </div>
                        <span class="action-card-desc">Upload or update your official school logo for report cards.</span>
                    </a>

                </div>

                <!-- RECENT STUDENTS TABLE -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>Recently Registered Students</h3>
                        <a href="../student/student.php" target="featureFrame" onclick="showFeature('Student Management', this.href)" class="view-all-link">
                            View All Students →
                        </a>
                    </div>
                    <?php if (count($recentStudents) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Gender</th>
                                    <th>Class</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student["student_id"]); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></td>
                                        <td><?php echo htmlspecialchars($student["gender"]); ?></td>
                                        <td><?php echo htmlspecialchars($student["class_name"] ?? "Unassigned"); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No students registered yet. Click <strong>+ Register Student</strong> to add your first student.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- FEATURE EMBED (IFRAME) -->
            <div id="featureContainer">
                <div class="feature-header">
                    <h2 id="featureTitle">Feature Page</h2>
                    <div class="feature-controls">
                        <a id="featureDirectLink" href="#" target="_blank" class="feature-btn">
                            Open in New Tab ↗
                        </a>
                        <button type="button" class="feature-btn feature-btn-close" onclick="showDashboard(event)">
                            ✕ Close / Back to Overview
                        </button>
                    </div>
                </div>
                <iframe name="featureFrame" id="featureFrame" src="about:blank"></iframe>
            </div>

        </div>

    </main>

</div>

<script>
// Toggle Submenus in Sidebar
function toggleMenu(menuId) {
    const menu = document.getElementById(menuId);
    const arrow = document.getElementById(menuId + "Arrow");
    if (!menu) return;

    if (menu.classList.contains("show")) {
        menu.classList.remove("show");
        if (arrow) arrow.innerText = "▼";
    } else {
        menu.classList.add("show");
        if (arrow) arrow.innerText = "▲";
    }
}

// Show Feature Frame
function showFeature(title, url) {
    document.getElementById("dashboardOverview").style.display = "none";
    document.getElementById("featureContainer").style.display = "block";
    document.getElementById("featureTitle").innerText = title;
    if (url) {
        document.getElementById("featureDirectLink").href = url;
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
}

// Return to Dashboard Overview
function showDashboard(event) {
    if (event) event.preventDefault();
    document.getElementById("featureContainer").style.display = "none";
    document.getElementById("dashboardOverview").style.display = "block";
    document.getElementById("featureFrame").src = "about:blank";
}
</script>
<script src="dashboard.js?v=<?php echo filemtime(__DIR__ . "/dashboard.js"); ?>" defer></script>

</body>
</html>
