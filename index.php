 <?php

session_start();

require_once __DIR__. "/config/database.php";
$subscriptionPlanConfig = require __DIR__ . "/config/subscription_plans.php";
$availableSubscriptionPlans = $subscriptionPlanConfig["available"];

$superadminSessionValid = !empty($_SESSION["superadmin_authenticated"])
    && !empty($_SESSION["superadmin_2fa_verified"])
    && ($_SESSION["role"] ?? "") === "superadmin"
    && (int)($_SESSION["superadmin_id"] ?? 0) === 1;
if (
    (!empty($_SESSION["superadmin_authenticated"]) || ($_SESSION["role"] ?? "") === "superadmin")
    && !$superadminSessionValid
) {
    unset(
        $_SESSION["superadmin_authenticated"],
        $_SESSION["superadmin_2fa_verified"],
        $_SESSION["superadmin_id"],
        $_SESSION["superadmin_name"],
        $_SESSION["superadmin_last_activity"],
        $_SESSION["superadmin_csrf"]
    );
    if (($_SESSION["role"] ?? "") === "superadmin") {
        unset($_SESSION["role"]);
    }
}

if ($superadminSessionValid) {
    require_once __DIR__ . "/admin/auth.php";
    $superadminCsrfToken = superAdminCsrfToken();
} else {
    $superadminCsrfToken = "";
}

$accountRole = $_SESSION["role"] ?? null;
if ($superadminSessionValid) {
    $accountRole = "superadmin";
} elseif (!$accountRole && isset($_SESSION["school_id"])) {
    $accountRole = "school_admin";
}

$portals = [
    "school_admin" => [
        "label" => "School Admin",
        "dashboard" => "school/dashboard.php",
        "logout" => "school/logout.php",
        "description" => "Manage school records, academic sessions, students, classes, subjects, scores, reports, and billing."
    ],
    "teacher" => [
        "label" => "Teacher",
        "dashboard" => "teacher/dashboard.php",
        "logout" => "teacher/logout.php",
        "description" => "Open your teacher portal to manage classes and enter student scores."
    ],
    "student" => [
        "label" => "Student",
        "dashboard" => "student/dashboard.php",
        "logout" => "student/logout.php",
        "description" => "View your own grades and academic progress."
    ],
    "parent" => [
        "label" => "Parent",
        "dashboard" => "parent/dashboard.php",
        "logout" => "parent/logout.php",
        "description" => "View linked children and their academic progress."
    ],
    "superadmin" => [
        "label" => "Platform Admin",
        "dashboard" => "superadmin/dashboard.php",
        "logout" => "superadmin/logout.php",
        "description" => "Manage school registrations and platform payments."
    ]
];

$featuresByRole = [
    "school_admin" => [
        ["icon" => "📅", "title" => "Academic Calendar", "description" => "Set up school years and terms.", "url" => "school/academic_years.php"],
        ["icon" => "🎓", "title" => "Student Records", "description" => "Register students and manage portal access.", "url" => "student/student.php"],
        ["icon" => "🏫", "title" => "Classes & Subjects", "description" => "Manage classes and curriculum.", "url" => "school/classes.php"],
        ["icon" => "👨‍🏫", "title" => "Teacher Assignments", "description" => "Assign classes and subjects so teachers can manage attendance, scores, and reports.", "url" => "school/teachers.php"],
        ["icon" => "💳", "title" => "Subscription & Billing", "description" => "Manage your school's subscription.", "url" => "school/pay_subscription.php"]
    ],
    "teacher" => [
        ["icon" => "📅", "title" => "Class Attendance", "description" => "Mark attendance for your assigned classes.", "url" => "teacher/dashboard.php"],
        ["icon" => "🎓", "title" => "Student Rosters", "description" => "View registered students in your assigned classes.", "url" => "teacher/dashboard.php"],
        ["icon" => "📝", "title" => "Scores & Reports", "description" => "Manage scores and generate reports for assigned classes and subjects.", "url" => "teacher/dashboard.php"]
    ],
    "student" => [
        ["icon" => "🎓", "title" => "My Grades & Reports", "description" => "View your grades and academic report card.", "url" => "student/dashboard.php"],
        ["icon" => "📅", "title" => "My Attendance", "description" => "Check your class attendance records.", "url" => "student/dashboard.php"]
    ],
    "parent" => [
        ["icon" => "👨‍👩‍👧", "title" => "Linked Students", "description" => "View your linked students' grades, reports, and attendance.", "url" => "parent/dashboard.php"]
    ],
    "superadmin" => [
        ["icon" => "🛡️", "title" => "Platform Administration", "description" => "Review school access and verified payments.", "url" => "superadmin/dashboard.php"]
    ]
];

$portal = $portals[$accountRole] ?? null;
$isLoggedIn = $portal !== null;
$isSchoolAdmin = $accountRole === "school_admin";
$school = null;
$schoolName = "";
$schoolLogo = "";
$accountName = $portal["label"] ?? "";

if ($isSchoolAdmin && isset($_SESSION["school_id"])) {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt->execute([$_SESSION["school_id"]]);
    $school = $stmt->fetch();

    if (!$school) {
        $accountRole = null;
        $portal = null;
        $isLoggedIn = false;
        $isSchoolAdmin = false;
    } else {
        $schoolName = $school["school_name"];
        $schoolLogo = !empty($school["school_logo"]) && file_exists(__DIR__ . "/" . $school["school_logo"]) ? $school["school_logo"] : "";
        $accountName = $schoolName;
    }
} elseif ($accountRole === "teacher") {
    $accountName = $_SESSION["teacher_name"] ?? "Teacher";
} elseif ($accountRole === "student") {
    $accountName = $_SESSION["student_name"] ?? "Student";
} elseif ($accountRole === "parent") {
    $accountName = $_SESSION["parent_name"] ?? "Parent";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EDUTRACK | Smart School Management</title>
<style>
:root {
    --navy: #10264b;
    --blue: #183d70;
    --gold: #e9b949;
    --white: #ffffff;
    --background: #f5f7fb;
    --muted: #68778d;
    --border: #e6ebf2;
    --green: #16865b;
    --blue-light: #2563ab;
    --red: #b42318;
    --red-light: #fff0f0;
}

/* LOGIN DROPDOWN */
.login-dropdown-wrap {
    position: relative;
    display: inline-block;
}
.btn-login-toggle {
    color: var(--navy);
    border-color: var(--navy);
    background: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 11px 19px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    border: 1px solid var(--navy);
    transition: 0.3s;
}
.btn-login-toggle:hover { background: #f1f5f9; }
.role-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: white;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(16,38,75,.15);
    min-width: 200px;
    overflow: hidden;
    z-index: 200;
    border: 1px solid var(--border);
}
.role-dropdown.open { display: block; }
.role-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    text-decoration: none;
    color: #1a2535;
    font-size: 13px;
    font-weight: 600;
    transition: background .15s;
}
.role-dropdown a:hover { background: var(--background); color: var(--navy); }
.role-dropdown .rd-icon { font-size: 1.1rem; }
.role-dropdown .rd-divider { height: 1px; background: var(--border); margin: 3px 0; }

/* WHO IS EDUTRACK FOR */
.roles-section {
    background: white;
    padding: 80px 7%;
    text-align: center;
}
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
    gap: 22px;
    max-width: 1100px;
    margin: 36px auto 0;
}
.role-card {
    border: 1.5px solid var(--border);
    border-radius: 14px;
    padding: 28px 20px 22px;
    text-align: left;
    transition: 0.3s;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.role-card:hover {
    border-color: var(--gold);
    box-shadow: 0 8px 28px rgba(16,38,75,.09);
    transform: translateY(-3px);
}
.role-card .rc-icon { font-size: 2rem; }
.role-card h3 { color: var(--navy); font-size: 16px; }
.role-card p { color: var(--muted); font-size: 13px; line-height: 1.65; flex: 1; }
.btn-role-login {
    display: block;
    text-align: center;
    padding: 9px 0;
    border-radius: 7px;
    font-weight: bold;
    font-size: 13px;
    text-decoration: none;
    transition: 0.2s;
}
.btn-role-admin  { background: var(--navy); color: white; }
.btn-role-admin:hover  { background: var(--blue); }
.btn-role-teacher { background: var(--blue-light); color: white; }
.btn-role-teacher:hover { opacity: .88; }
.btn-role-student { background: var(--blue-light); color: white; }
.btn-role-student:hover { opacity: .88; }
.btn-role-parent  { background: var(--green); color: white; }
.btn-role-parent:hover  { opacity: .88; }

@media(max-width:900px) { .roles-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:560px) { .roles-grid { grid-template-columns: 1fr; } }

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

html {
    scroll-behavior: smooth;
}

body {
    background: var(--background);
    color: #17243b;
    line-height: 1.6;
}

a {
    text-decoration: none;
}

/* HEADER */

header {
    background: white;
    padding: 15px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--navy);
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 1px;
    white-space: nowrap;
}

.logo-icon {
    width: 42px;
    height: 42px;
    background: var(--navy);
    color: var(--gold);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}

nav {
    display: flex;
    align-items: center;
    gap: 23px;
    flex-wrap: wrap;
}

nav a {
    color: #46536a;
    font-size: 14px;
    font-weight: 600;
    transition: 0.3s;
}

nav a:hover {
    color: #c18b16;
}

.header-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.header-logout-form { margin: 0; }
.header-logout-form button { font: inherit; }

.welcome {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--navy);
    font-size: 13px;
    font-weight: bold;
    text-decoration: none;
    padding: 3px 12px 3px 4px;
    border-radius: 25px;
    background: #f1f5f9;
    border: 1px solid var(--border);
    transition: 0.2s;
}

.welcome:hover {
    background: #e2e8f0;
    border-color: var(--gold);
}

.account-pic {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--gold);
    background: white;
    display: block;
}

.account-initial {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--navy);
    color: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 15px;
    border: 2px solid var(--gold);
}

.btn {
    display: inline-block;
    padding: 11px 19px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
    transition: 0.3s;
    cursor: pointer;
    border: 1px solid transparent;
    text-align: center;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-login {
    color: var(--navy);
    border-color: var(--navy);
    background: white;
}

.btn-register,
.btn-gold {
    background: var(--gold);
    color: var(--navy);
}

.btn-dashboard {
    background: var(--navy);
    color: white;
}

.btn-logout {
    background: #fff0f0;
    color: #b42318;
    border-color: #f3cccc;
}

/* HERO */

.hero {
    background: linear-gradient(120deg, #041636 0%, #637018 60%, #42791e 100%);
    color: white;
    padding: 95px 8%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 50px;
    min-height: 510px;
}

.hero-content {
    max-width: 650px;
}

.hero-label {
    color: var(--gold);
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 2px;
    margin-bottom: 20px;
}

.hero h1 {
    font-size: clamp(37px, 5vw, 58px);
    line-height: 1.15;
    margin-bottom: 22px;
}

.hero h1 span {
    color: var(--gold);
}

.hero p {
    color: #d9e4f3;
    font-size: 17px;
    line-height: 1.8;
    margin-bottom: 30px;
}

.hero-actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}

.hero-actions .btn {
    padding: 14px 24px;
}

.btn-outline {
    border-color: white;
    color: white;
    background: transparent;
}

.hero-visual {
    width: 340px;
    min-width: 260px;
    height: 310px;
    border: 1px solid rgba(255,255,255,0.25);
    background: rgba(255,255,255,0.08);
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.hero-symbol {
    font-size: 95px;
    color: var(--gold);
}

.hero-visual h3 {
    font-size: 23px;
    margin-top: 10px;
}

.hero-visual p {
    font-size: 13px;
    margin-top: 8px;
}

/* SCHOOL LEVELS */

.trust {
    background: white;
    padding: 35px 7%;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 65px;
    text-align: center;
}

.trust-item strong {
 display: block;
    color: var(--navy);
    font-size: 20px;
    margin-bottom: 5px;
}

.trust-item span {
    color: var(--muted);
    font-size: 13px;
}

/* GENERAL SECTIONS */

.section {
    padding: 85px 7%;
    text-align: center;
}

.section-heading {
    max-width: 680px;
    margin: 0 auto 45px;
}

.section-heading h2 {
    color: var(--navy);
    font-size: 34px;
    margin-bottom: 12px;
}

.section-heading p {
    color: var(--muted);
    line-height: 1.8;
}

/* FEATURES */

#features {
    background: linear-gradient(135deg, #689929 0%, #ad9647 52%, #2379c0 100%);
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.feature-card {
    background: white;
    padding: 30px 25px;
    border: 1px solid var(--border);
    border-radius: 14px;
    text-align: left;
    transition: 0.3s;
    box-shadow: 0 5px 20px rgba(0,0,0,0.025);
}

.feature-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.07);
}

.feature-icon {
    width: 55px;
    height: 55px;
    background: #fff4d8;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 18px;
}

.feature-card h3 {
    color: var(--navy);
    font-size: 18px;
    margin-bottom: 10px;
}

.feature-card p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
}

.feature-cta {
    display: inline-block;
    margin-top: 16px;
    color: var(--blue);
    font-size: 13px;
    font-weight: 700;
}

.portal-access-note {
    max-width: 720px;
    margin: 0 auto;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: white;
    color: var(--muted);
}

.portal-access-note p { margin-bottom: 18px; }

.feature-links {
    list-style: none;
    margin-top: 18px;
}

.feature-links li {
    margin-bottom: 12px;
}

.feature-links a {
    color: var(--blue);
    font-size: 14px;
    font-weight: 600;
    transition: 0.3s;
}

.feature-links a:hover {
    color: #c18b16;
    padding-left: 5px;
}

.feature-links a::before {
    content: "→ ";
    color: var(--gold);
    font-weight: bold;
}

/* SUBSCRIPTION */

.packages {
    background: linear-gradient(135deg, #fff2d8 0%, #f3eaff 52%, #e2f5ff 100%);
    padding: 85px 7%;
    text-align: center;
}

.package-container {
    max-width: 1050px;
    margin: 40px auto 0;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}

.package-card {
    background: white;
    padding: 35px 25px;
    border-radius: 15px;
    border: 1px solid var(--border);
    transition: 0.3s;
}

.package-card:hover {
    transform: translateY(-6px);
}

.package-card.featured {
    border: 2px solid var(--gold);
}

.package-card h3 {
    color: var(--navy);
    font-size: 23px;
    margin-bottom: 12px;
}

.package-card .price {
    color: var(--green);
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 12px;
}

.package-card p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.7;
    margin-bottom: 23px;
}

.package-card a {
    background: var(--navy);
    color: white;
    display: inline-block;
    padding: 12px 25px;
    border-radius: 6px;
    font-weight: bold;
}

/* CALL TO ACTION */

.cta {
    background: var(--navy);
    color: white;
    text-align: center;
    padding: 75px 7%;
}

.cta h2 {
    font-size: 32px;
    margin-bottom: 15px;
}

.cta p {
    color: #d8e2f1;
    margin-bottom: 25px;
}

/* FOOTER */

footer {
    background:
        radial-gradient(circle at 88% 4%, rgba(233, 185, 73, 0.12), transparent 28%),
        linear-gradient(135deg, #07152a, #10264b);
    color: white;
    padding: 64px 7% 22px;
    scroll-margin-top: 120px;
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-contact-panel {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);
    align-items: center;
    gap: 36px;
    position: relative;
    overflow: hidden;
    margin-bottom: 48px;
    padding: clamp(26px, 5vw, 48px);
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 24px;
    background: linear-gradient(115deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.035));
    box-shadow: 0 22px 55px rgba(0, 0, 0, 0.18);
}

.footer-contact-copy,
.developer-contact {
    position: relative;
    z-index: 1;
}

.footer-eyebrow {
    color: var(--gold);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.footer-contact-copy h2 {
    max-width: 540px;
    margin: 12px 0 10px;
    color: white;
    font-size: clamp(26px, 3.2vw, 38px);
    line-height: 1.15;
}

.footer-contact-copy p {
    max-width: 520px;
    color: #c1ccdc;
    font-size: 15px;
    line-height: 1.75;
}

.developer-contact {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 18px;
    border: 1px solid rgba(16, 38, 75, 0.08);
    border-radius: 18px;
    background: white;
    color: var(--navy);
    text-decoration: none;
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.16);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.developer-contact:hover {
    color: var(--navy);
    transform: translateY(-3px);
    box-shadow: 0 19px 42px rgba(0, 0, 0, 0.23);
}

.developer-avatar {
    display: grid;
    flex: 0 0 54px;
    width: 54px;
    height: 54px;
    place-items: center;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8df98, var(--gold));
    color: #47340b;
    font-size: 16px;
    font-weight: 900;
}

.developer-details {
    display: grid;
    min-width: 0;
    gap: 4px;
}

.developer-label {
    color: #68778d;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.developer-details strong {
    color: var(--navy);
    font-size: 15px;
}

.developer-phone {
    color: #52647e;
    font-size: 13px;
}

.developer-call {
    flex: 0 0 auto;
    margin-left: auto;
    padding: 9px 12px;
    border-radius: 999px;
    background: var(--navy);
    color: white;
    font-size: 12px;
    font-weight: 700;
}

.footer-content {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr;
    gap: 40px;
    max-width: 1200px;
    margin: 0 auto 38px;
    padding: 0 4px;
}

.footer-brand .logo {
    width: auto;
    justify-content: flex-start;
    margin-bottom: 10px;
}

.footer-content h3 {
    color: var(--gold);
    margin-bottom: 12px;
}

.footer-content p {
    color: #b8c5d8;
    font-size: 14px;
    line-height: 1.8;
    margin-top: 12px;
}

.footer-content a {
    color: #b8c5d8;
    display: block;
    margin-top: 12px;
    font-size: 14px;
}

.footer-content a:hover {
    color: var(--gold);
}

.copyright {
    max-width: 1200px;
    margin: 0 auto;
    border-top: 1px solid #263b59;
    padding-top: 20px;
    text-align: center;
    color: #b8c5d8;
    font-size: 13px;
}

@media(max-width: 850px) {
    .footer-contact-panel {
        grid-template-columns: 1fr;
        gap: 24px;
    }
}

/* MOBILE */

@media(max-width: 1000px) {
    header {
        flex-wrap: wrap;
    }

    nav {
        order: 3;
        width: 100%;
        justify-content: center;
    }

    .feature-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {
    header {
        padding: 15px 5%;
        justify-content: center;
    }

    .logo {
        width: 100%;
 justify-content: center;
    }

    nav {
        gap: 15px;
    }

    .header-buttons {
        justify-content: center;
        width: 100%;
    }

    .hero {
        flex-direction: column;
        text-align: center;
        padding: 65px 6%;
    }

    .hero-actions {
        justify-content: center;
    }

    .hero-visual {
        width: 100%;
        max-width: 350px;
    }

    .feature-grid,
    .package-container {
        grid-template-columns: 1fr;
    }

    .trust {
        gap: 25px;
    }

    .section-heading h2,
    .cta h2 {
        font-size: 27px;
    }

    footer {
        padding: 48px 6% 20px;
    }

    .footer-contact-panel {
        margin-bottom: 36px;
        padding: 24px;
        border-radius: 18px;
    }

    .footer-contact-copy h2 {
        font-size: 27px;
    }

    .developer-contact {
        gap: 11px;
        padding: 15px;
    }

    .developer-avatar {
        flex-basis: 46px;
        width: 46px;
        height: 46px;
    }

    .footer-content {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 28px 20px;
        margin-bottom: 30px;
    }

    .footer-brand {
        grid-column: 1 / -1;
    }
}

@media(max-width: 420px) {
    .developer-contact {
        gap: 9px;
        padding: 12px;
    }

    .developer-call {
        padding: 8px 10px;
        font-size: 11px;
    }

    .footer-content {
        grid-template-columns: 1fr;
    }

    .footer-brand {
        grid-column: auto;
    }
}
</style>
</head>
<body>
<header>
<div class="logo">
    <div class="logo-icon">E</div>
    EDUTRACK
</div>

<nav>
    <a href="#home">Home</a>
    <a href="#about">About</a>
    <a href="#features">Features</a>
    <?php if (!$isLoggedIn || $isSchoolAdmin): ?>
        <a href="#plans">Subscription</a>
    <?php endif; ?>
    <a href="#contact">Contact</a>
</nav>

<div class="header-buttons">

    <?php if ($isLoggedIn): ?>

        <a href="<?= htmlspecialchars($portal["dashboard"]) ?>" class="welcome" title="View Dashboard">
            <?php if ($isSchoolAdmin && !empty($schoolLogo)): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="<?= htmlspecialchars($schoolName) ?> Logo" class="account-pic">
            <?php else: ?>
                <span class="account-initial"><?= strtoupper(substr($accountName, 0, 1)) ?></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($accountName) ?></span>
        </a>

        <a href="<?= htmlspecialchars($portal["dashboard"]) ?>" class="btn btn-dashboard">
            Dashboard
        </a>

        <?php if ($accountRole === "superadmin"): ?>
            <form action="superadmin/logout.php" method="post" class="header-logout-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($superadminCsrfToken) ?>">
                <button type="submit" class="btn btn-logout">Logout</button>
            </form>
        <?php else: ?>
            <a href="<?= htmlspecialchars($portal["logout"]) ?>" class="btn btn-logout">Logout</a>
        <?php endif; ?>

    <?php else: ?>

        <div class="login-dropdown-wrap">
            <button class="btn-login-toggle" onclick="toggleRoleDropdown()" type="button">
                Login ▾
            </button>
            <div class="role-dropdown" id="roleDropdown">
                <a href="school/login.php"><span class="rd-icon">🏫</span> School Admin Login</a>
                <div class="rd-divider"></div>
                <a href="teacher/login.php"><span class="rd-icon">👨‍🏫</span> Teacher Login</a>
                <a href="teacher/register.php"><span class="rd-icon">📝</span> Teacher Registration</a>
                <a href="student/login.php"><span class="rd-icon">🎓</span> Student Login</a>
                <a href="student/register.php"><span class="rd-icon">📝</span> Student Registration</a>
                <a href="parent/login.php"><span class="rd-icon">👨‍👩‍👧</span> Parent Login</a>
                <a href="parent/register.php"><span class="rd-icon">📝</span> Parent Registration</a>
                <a href="superadmin/login.php"><span class="rd-icon">🛡️</span> Super Admin Login</a>
            </div>
        </div>

        <a href="school/register.php" class="btn btn-register">
            Register School
        </a>

    <?php endif; ?>

</div>
</header>
<main>
<!-- HERO -->
<section class="hero" id="home">
<div class="hero-content">

    <div class="hero-label">
        SMARTER EDUCATION MANAGEMENT
    </div>

    <?php if ($isLoggedIn): ?>

        <h1>Welcome back, <span><?= htmlspecialchars($accountName) ?>.</span></h1>

        <p>
            You are signed in to the <?= htmlspecialchars(strtolower($portal["label"])) ?> account.
            Open your portal to access the features available to your role.
        </p>

        <div class="hero-actions">
            <a href="<?= htmlspecialchars($portal["dashboard"]) ?>" class="btn btn-gold">
                Open My Portal →
            </a>
            <a href="#features" class="btn btn-outline">
                View My Features
            </a>
        </div>

    <?php else: ?>

        <h1>Manage Your School <span>Smarter.</span></h1>

        <p>
            Simplify academic administration with EDUTRACK.
            Manage students, classes, subjects, academic records
            and reports in one convenient platform.
        </p>

        <div class="hero-actions">
            <a href="school/register.php" class="btn btn-gold">
                Create a School Admin Account →
            </a>

            <a href="#portals" class="btn btn-outline">
                Choose an Account
            </a>
        </div>

    <?php endif; ?>

</div>

<div class="hero-visual">
    <div>
        <?php if ($isSchoolAdmin && !empty($schoolLogo)): ?>
            <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="School Logo" style="width:110px; height:110px; border-radius:50%; object-fit:contain; background:white; padding:8px; border:3px solid var(--gold); margin-bottom:12px; box-shadow:0 6px 20px rgba(0,0,0,0.25);">
            <h3 style="font-size:20px;"><?= htmlspecialchars($schoolName) ?></h3>
            <p style="color:#e2e8f0; font-size:13px; margin-top:4px;">Signed In &bull; Plan: <strong><?= htmlspecialchars($school["subscription_plan"] ?? "Basic") ?></strong></p>
        <?php elseif ($isLoggedIn): ?>
            <div class="hero-symbol">✓</div>
            <h3><?= htmlspecialchars($portal["label"]) ?> Portal</h3>
            <p>Access is limited to your account role.</p>
        <?php else: ?>
            <div class="hero-symbol">✦</div>
            <h3>Education Made Easier</h3>
            <p>One platform. Better school management.</p>
        <?php endif; ?>
    </div>
</div>
</section>
<!-- SCHOOL LEVELS -->
<section class="trust" id="about">
<div class="trust-item">
    <strong>Primary</strong>
    <span>Academic Management</span>
</div>

<div class="trust-item">
    <strong>JHS</strong>
    <span>Academic Management</span>
</div>

<div class="trust-item">
    <strong>SHS</strong>
    <span>Academic Management</span>
</div>

<div class="trust-item">
    <strong>One Platform</strong>
    <span>Connected School Records</span>
</div>
</section>
<!-- WHO IS EDUTRACK FOR -->
<section class="roles-section" id="portals">
<div class="section-heading">
    <h2>Choose Your Account</h2>
    <p>Each account has its own sign-in and role-based tools. Students and parents can register with an email code or Google, using details already linked to a school record.</p>
</div>
<div class="roles-grid">
    <div class="role-card">
        <div class="rc-icon">🏫</div>
        <h3>School Admin</h3>
        <p>Register your school, then manage its academic records, students, staff, reports, and subscription.</p>
        <a href="school/login.php" class="btn-role-login btn-role-admin">Admin Login →</a>
        <a href="school/register.php" class="btn-role-login btn-role-teacher">Register School</a>
    </div>
    <div class="role-card">
        <div class="rc-icon">👨‍🏫</div>
        <h3>Teacher</h3>
        <p>View your assigned classes, enter scores for assigned subjects, and review class results.</p>
        <a href="teacher/login.php" class="btn-role-login btn-role-teacher">Teacher Login →</a>
        <a href="teacher/register.php" class="btn-role-login btn-role-teacher">Teacher Registration →</a>
    </div>
    <div class="role-card">
        <div class="rc-icon">🎓</div>
        <h3>Student</h3>
        <p>View your grades and academic progress. Register with your school-record email and an email code, or use Google.</p>
        <a href="student/login.php" class="btn-role-login btn-role-student">Student Login →</a>
        <a href="student/register.php" class="btn-role-login btn-role-student">Student Registration →</a>
    </div>
    <div class="role-card">
        <div class="rc-icon">👨‍👩‍👧</div>
        <h3>Parent</h3>
        <p>View the progress of students linked to your email on the school record. Verify by email code or Google.</p>
        <a href="parent/login.php" class="btn-role-login btn-role-parent">Parent Login →</a>
        <a href="parent/register.php" class="btn-role-login btn-role-parent">Parent Registration →</a>
    </div>
    <div class="role-card">
        <div class="rc-icon">🛡️</div>
        <h3>Super Admin</h3>
        <p>Manage school approvals and platform payments. Super admin access is configured securely by the platform owner.</p>
        <a href="superadmin/login.php" class="btn-role-login btn-role-admin">Platform Admin Login →</a>
    </div>
</div>
</section>
<!-- FEATURES -->
<section class="section" id="features">
<div class="section-heading">
    <h2><?= $isLoggedIn ? htmlspecialchars($portal["label"]) . " Features" : "Features by Account" ?></h2>
    <p>
        <?php if ($isLoggedIn && $accountRole === "school_admin"): ?>
            School setup, student records, classes, subjects, scores, reports, staff, and billing are managed from your school dashboard.
        <?php elseif ($isLoggedIn): ?>
            These are the tools available to your signed-in account. Other role features remain restricted.
        <?php else: ?>
            Sign in to the right account to access its tools. Private school, student, parent, and platform features are not exposed publicly.
        <?php endif; ?>
    </p>
</div>

<?php if ($isLoggedIn && $accountRole === "school_admin"): ?>
    <div class="portal-access-note">
        <a class="btn btn-dashboard" href="school/dashboard.php">Open School Admin Dashboard →</a>
    </div>
<?php elseif ($isLoggedIn): ?>
    <div class="feature-grid">
        <?php foreach ($featuresByRole[$accountRole] as $feature): ?>
            <a class="feature-card" href="<?= htmlspecialchars($feature["url"]) ?>">
                <div class="feature-icon"><?= $feature["icon"] ?></div>
                <h3><?= htmlspecialchars($feature["title"]) ?></h3>
                <p><?= htmlspecialchars($feature["description"]) ?></p>
                <span class="feature-cta">Open feature →</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="portal-access-note">
        <p>Choose your account above to sign in or register. Student and parent email/password registration verifies an email already linked to a school record.</p>
        <a class="btn btn-dashboard" href="#portals">Choose an Account →</a>
    </div>
<?php endif; ?>
</section>
<!-- SUBSCRIPTION -->
<?php if (!$isLoggedIn || $isSchoolAdmin): ?>
<section class="packages" id="plans">
<div class="section-heading">
    <h2>Choose Your Subscription Plan</h2>

    <p>
        Select the package that suits your school's needs.
        You can register your school and access your account.
    </p>
</div>

<div class="package-container">
    <?php foreach ($availableSubscriptionPlans as $planKey => $plan): ?>
        <div class="package-card<?php echo $planKey === "Basic Quarterly" ? " featured" : ""; ?>">
            <h3><?php echo htmlspecialchars($plan["name"], ENT_QUOTES, "UTF-8"); ?></h3>
            <p class="price">GH₵<?php echo number_format((float)$plan["price"], 0); ?></p>
            <p><?php echo htmlspecialchars($plan["description"], ENT_QUOTES, "UTF-8"); ?></p>
            <?php if ($isSchoolAdmin): ?>
                <a href="school/pay_subscription.php?plan=<?php echo rawurlencode($planKey); ?>" class="btn btn-gold" style="width:100%; margin-top:10px;">
                    Pay with Paystack (GH₵<?php echo number_format((float)$plan["price"], 0); ?>)
                </a>
            <?php else: ?>
                <a href="school/register.php?plan=<?php echo rawurlencode($planKey); ?>">Get Started</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<!-- CALL TO ACTION -->
<section class="cta">
<?php if ($isLoggedIn): ?>

    <h2>Your <?= htmlspecialchars($portal["label"]) ?> Portal</h2>
    <p>Continue with the tools available to your account.</p>

    <a href="<?= htmlspecialchars($portal["dashboard"]) ?>" class="btn btn-gold">
        Go to My Portal →
    </a>

<?php else: ?>

    <h2>Ready to Transform Your School Management?</h2>
    <p>Join EDUTRACK and bring your school's academic activities together.</p>

    <a href="school/register.php" class="btn btn-gold">
        Register Your School →
    </a>

<?php endif; ?>
</section>
</main>
<!-- FOOTER -->
<footer id="contact">
    <div class="footer-inner">
        <section class="footer-contact-panel" aria-labelledby="footer-contact-title">
            <div class="footer-contact-copy">
                <span class="footer-eyebrow">Get in touch</span>
                <h2 id="footer-contact-title">Need a hand with EduTrack?</h2>
                <p>Have a question or need help? Contact the site developer directly.</p>
            </div>
            <a class="developer-contact" href="tel:0257162643" aria-label="Call site developer Anokye Desmond at 0257162643">
                <span class="developer-avatar" aria-hidden="true">AD</span>
                <span class="developer-details">
                    <span class="developer-label">Site developer</span>
                    <strong>ANOKYE DESMOND</strong>
                    <span class="developer-phone">025 716 2643</span>
                </span>
                <span class="developer-call" aria-hidden="true">Call ↗</span>
            </a>
        </section>

        <div class="footer-content">
            <div class="footer-brand">
                <div class="logo" style="color:white;">
                    <div class="logo-icon">E</div>
                    EDUTRACK
                </div>
                <p>Smarter academic management<br>for Primary, JHS and SHS schools.</p>
            </div>

            <div>
                <h3>Quick Links</h3>
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#features">Features</a>
                <a href="#plans">Subscription Plans</a>
            </div>

            <div>
                <h3>Account</h3>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= htmlspecialchars($portal["dashboard"]) ?>">My <?= htmlspecialchars($portal["label"]) ?> Portal</a>
                    <?php if ($accountRole === "superadmin"): ?>
                        <form action="superadmin/logout.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($superadminCsrfToken) ?>">
                            <button type="submit">Logout</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($portal["logout"]) ?>">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="school/login.php">🏫 School Admin Login</a>
                    <a href="teacher/login.php">👨‍🏫 Teacher Login</a>
                    <a href="teacher/register.php">Teacher Registration</a>
                    <a href="student/login.php">🎓 Student Login</a>
                    <a href="student/register.php">Student Registration</a>
                    <a href="parent/login.php">👨‍👩‍👧 Parent Login</a>
                    <a href="parent/register.php">Parent Registration</a>
                    <a href="superadmin/login.php">🛡️ Super Admin Login</a>
                    <a href="school/register.php">Register School Admin Account</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="copyright">
            © <?= date("Y") ?> EDUTRACK. All rights reserved.
            &nbsp;·&nbsp;
            <a href="superadmin/login.php" style="color:#4a6080;font-size:12px;">Platform Admin</a>
        </div>
    </div>
</footer>
<script>
function toggleRoleDropdown() {
    document.getElementById('roleDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.login-dropdown-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('roleDropdown')?.classList.remove('open');
    }
});
</script>
</body>
</html>