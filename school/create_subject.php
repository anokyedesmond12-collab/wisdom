<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION["school_id"];
$message = "";
$message_type = "";

// REGISTER SUBJECT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_subject"])) {

    $subject_name = trim($_POST["subject_name"]);
    $subject_code = trim($_POST["subject_code"]);

    if ($subject_name == "") {
        $message = "Please enter a subject name.";
        $message_type = "error";
    } else {
        try {
            $sql = "INSERT INTO subjects
                    (school_id, subject_name, subject_code)
                    VALUES (?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $school_id,
                $subject_name,
                $subject_code ?: null
            ]);

            header("Location: create_subject.php?success=1");
            exit();

        } catch (PDOException $e) {
            $message = ($e->getCode() == "23000")
                ? "This subject has already been registered."
                : "Unable to register subject.";

            $message_type = "error";
        }
    }
}

// SUCCESS MESSAGE
if (isset($_GET["success"])) {
    $message = "Subject registered successfully!";
    $message_type = "success";
}

// FETCH SUBJECTS
$stmt = $conn->prepare(
    "SELECT id, subject_name, subject_code
     FROM subjects
     WHERE school_id = ?
     ORDER BY subject_name ASC"
);

$stmt->execute([$school_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Subject | EduTrack</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6fa;
    color: #172554;
}

.header {
    background: #102a56;
    color: white;
    padding: 25px 7%;
}

.header h2 {
    margin: 0;
}

.header p {
    color: #dbeafe;
    margin-bottom: 0;
}

.container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.card h3 {
    color: #102a56;
    margin-top: 0;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #d4a017;
}

.submit-btn {
    background: #102a56;
    color: white;
    padding: 14px 25px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.submit-btn:hover {
    background: #1e40af;
}

.message {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #f8fafc;
    color: #102a56;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #64748b;
}

.back {
    display: inline-block;
    margin: 10px 0 30px;
    text-decoration: none;
    color: #102a56;
    font-weight: bold;
}

@media(max-width: 600px) {
    .card {
        padding: 20px;
    }

    table {
        font-size: 13px;
    }
}
</style>

</head>

<body>

<div class="header">
    <h2>EduTrack</h2>
    <p>Subject Management / Create Subject</p>
</div>

<div class="container">

    <div class="card">

        <h3>Register New Subject</h3>

        <p>Add a new subject to your school's subject list.</p>

        <?php if ($message != ""): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
 <?php endif; ?>

        <form method="POST" action="create_subject.php">

            <div class="form-group">
                <label>Subject Name</label>

                <input
                    type="text"
                    name="subject_name"
                    placeholder="e.g. Mathematics"
                    required
                >
            </div>

            <div class="form-group">
                <label>Subject Code (Optional)</label>

                <input
                    type="text"
                    name="subject_code"
                    placeholder="e.g. MATH101"
                >
            </div>

            <button type="submit" name="create_subject" class="submit-btn">
                Register Subject
            </button>

        </form>

    </div>

    <div class="card">

        <h3>Registered Subjects</h3>

        <p>All subjects registered under your school.</p>

        <?php if (count($subjects) > 0): ?>

        <div style="overflow-x:auto;">

            <table>

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                    </tr>
                </thead>

                <tbody>

                <?php $number = 1; ?>

                <?php foreach ($subjects as $subject): ?>

                    <tr>
                        <td><?php echo $number++; ?></td>

                        <td>
                            <?php echo htmlspecialchars($subject["subject_name"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($subject["subject_code"] ?? "Not assigned"); ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php else: ?>

            <div class="empty">
                <h3>No Subjects Yet</h3>
                <p>Register your first subject to see it here.</p>
            </div>

        <?php endif; ?>

    </div>

    <a href="dashboard.php" class="back">
        ← Back to Main Page
    </a>

</div>

</body>
</html>