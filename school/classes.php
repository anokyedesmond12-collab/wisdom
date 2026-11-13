<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION["school_id"];
$message = "";
$message_type = "";

$class_name = "";
$class_level = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $class_name = trim($_POST["class_name"] ?? "");
    $class_level = $_POST["class_level"] ?? "";

    if ($class_name === "") {

        $message = "Please enter a class name.";
        $message_type = "error";

    } elseif (!in_array($class_level, ["Primary", "JHS", "SHS"])) {

        $message = "Please select a valid class level.";
        $message_type = "error";

    } else {

        try {

            $sql = "INSERT INTO classes
                    (school_id, class_name, class_level)
                    VALUES (?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $school_id,
                $class_name,
                $class_level
            ]);

            $message = "Class created successfully!";
            $message_type = "success";

            $class_name = "";
            $class_level = "";

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                $message = "This class has already been registered.";
            } else {
                $message = "Unable to create class. Please try again.";
            }

            $message_type = "error";
        }
    }
}

// Get registered classes
$stmt = $conn->prepare(
    "SELECT id, class_name, class_level
     FROM classes
     WHERE school_id = ?
     ORDER BY class_level, class_name"
);

$stmt->execute([$school_id]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Class | EduTrack</title>

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
    padding: 22px 7%;
}

.header h2 {
    margin: 0;
}

.header p {
    margin-bottom: 0;
    color: #dbeafe;
}

.container {
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    margin-bottom: 30px;
}

h3 {
    color: #102a56;
    margin-top: 0;
}

.description {
    color: #64748b;
    margin-bottom: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 9px;
}

input, select {
    width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
    background: white;
}

input:focus, select:focus {
    outline: none;
    border-color: #d4a017;
}

button {
    margin-top: 25px;
    padding: 14px 25px;
    background: #102a56;
    color: white;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    background: #d4a017;
}

.message {
    padding: 14px;
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

.table-wrapper {
    overflow-x: auto;
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

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.primary {
    background: #dbeafe;
    color: #1d4ed8;
}

.jhs {
    background: #fef3c7;
    color: #92400e;
}

.shs {
    background: #dcfce7;
    color: #166534;
}

.back {
    display: inline-block;
    margin: 20px 0 35px;
    color: #102a56;
    text-decoration: none;
font-weight: bold;
}

@media(max-width: 650px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 20px;
    }
}

</style>
</head>

<body>

<div class="header">
    <h2>EduTrack</h2>
    <p>Class Management</p>
</div>

<div class="container">

    <div class="card">

        <h3>Create New Class</h3>

        <p class="description">
            Register a new class for your school by providing its name and educational level.
        </p>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-grid">

                <div>
                    <label>Class Name</label>

                    <input
                        type="text"
                        name="class_name"
                        placeholder="e.g. Basic 1, JHS 1, SHS 1"
                        value="<?php echo htmlspecialchars($class_name); ?>"
                        required
                    >
                </div>

                <div>
                    <label>Class Level</label>

                    <select name="class_level" required>

                        <option value="">Select Level</option>

                        <option value="Primary"
                        <?php echo ($class_level == "Primary") ? "selected" : ""; ?>>
                            Primary
                        </option>

                        <option value="JHS"
                        <?php echo ($class_level == "JHS") ? "selected" : ""; ?>>
                            Junior High School (JHS)
                        </option>

                        <option value="SHS"
                        <?php echo ($class_level == "SHS") ? "selected" : ""; ?>>
                            Senior High School (SHS)
                        </option>

                    </select>
                </div>

            </div>

            <button type="submit">+ Create Class</button>

        </form>

    </div>

    <div class="card">

        <h3>Registered Classes</h3>

        <p class="description">
            View the classes currently registered under your school.
        </p>

        <?php if (count($classes) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Class Name</th>
                            <th>Class Level</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php $number = 1; ?>

                        <?php foreach ($classes as $class): ?>

                            <tr>
                                <td><?php echo $number++; ?></td>

                                <td>
                                    <?php echo htmlspecialchars($class["class_name"]); ?>
                                </td>

                                <td>
                                    <span class="badge <?php echo strtolower($class["class_level"]); ?>">
                                        <?php echo htmlspecialchars($class["class_level"]); ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>No classes have been registered yet.</p>

        <?php endif; ?>

    </div>

    <a href="dashboard.php" class="back">← Back to Main Page</a>

</div>

</body>
</html>