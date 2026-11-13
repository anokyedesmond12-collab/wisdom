<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $school_id = $_SESSION["school_id"];

    $year_name = trim($_POST["year_name"] ?? "");
    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";

    if (!preg_match('/^\d{4}\/\d{4}$/', $year_name)) {

        $message = "Invalid academic year format. Use 2026/2027.";
        $message_type = "error";

    } elseif (empty($start_date) || empty($end_date)) {

        $message = "Please enter both dates.";
        $message_type = "error";

    } elseif ($end_date <= $start_date) {

        $message = "Closing date must be after opening date.";
        $message_type = "error";

    } else {

        try {

            $sql = "INSERT INTO academic_years
                    (school_id, year_name, start_date, end_date)
                    VALUES (?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $school_id,
                $year_name,
                $start_date,
                $end_date
            ]);

            $message = "Academic year created successfully!";
            $message_type = "success";

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                $message = "This academic year already exists.";
            } else {
                $message = "Unable to create academic year: " . $e->getMessage();
            }

            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Academic Year | EduTrack</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f4f6fa; color: #172554; }
        .container { max-width: 650px; margin: 60px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h2 { margin-bottom: 10px; color: #102a56; }
        .description { color: #64748b; margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input { width: 100%; padding: 13px; margin-bottom: 20px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 15px; }
        button { width: 100%; padding: 14px; border: none; border-radius: 7px; background: #102a56; color: white; font-size: 16px; cursor: pointer; }
        button:hover { background: #d4a017; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .back { display: inline-block; margin-top: 20px; color: #102a56; text-decoration: none; }
         .header {
    background: #102a56;
    color: white;
    padding: 28px 7%;
}

.header h1 {
    margin: 0;
    font-size: 27px;
}

.header p {
    color: #a3b65e;
    margin-bottom: 0;
}
    </style>
</head>
<body>
    
    <div class="header">

    <h1>EduTrack</h1>

    <p>ACADEMIC YEAR DASHBOARD</p>

</div>
<div class="container">
    <h2>Create Academic Year</h2>
    <p class="description">Set up a new academic year for your school.</p>
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="">
        <label>Academic Year</label>
        <input type="text" name="year_name" placeholder="Example: 2026/2027" required pattern="\d{4}/\d{4}">
        <label>Opening Date</label>
        <input type="date" name="start_date" required>
        <label>Closing Date</label>
        <input type="date" name="end_date" required>
        <button type="submit">Create Academic Year</button>
    </form>
    <a href="dashboard.php" class="back">← Back to Dashboard</a>
</div>
</body>
</html>