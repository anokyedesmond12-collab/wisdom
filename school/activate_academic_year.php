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

// Activate selected academic year
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $year_id = $_POST["year_id"] ?? "";

    if (!is_numeric($year_id)) {

        $message = "Please select a valid academic year.";
        $message_type = "error";

    } else {

        try {

            $conn->beginTransaction();

            // Check whether the academic year belongs to this school
            $check = $conn->prepare(
                "SELECT id FROM academic_years
                 WHERE id = ? AND school_id = ?"
            );

            $check->execute([$year_id, $school_id]);

            if (!$check->fetch()) {
                throw new Exception("Academic year not found.");
            }

            // Deactivate previous academic years
            $deactivate = $conn->prepare(
                "UPDATE academic_years
                 SET status = 'Inactive'
                 WHERE school_id = ?"
            );

            $deactivate->execute([$school_id]);

            // Activate selected academic year
            $activate = $conn->prepare(
                "UPDATE academic_years
                 SET status = 'Active'
                 WHERE id = ? AND school_id = ?"
            );

            $activate->execute([$year_id, $school_id]);

            $conn->commit();

            $message = "Academic year activated successfully!";
            $message_type = "success";

        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $message = "Activation failed: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch academic years belonging to this school
$stmt = $conn->prepare(
    "SELECT id, year_name, start_date, end_date, status
     FROM academic_years
     WHERE school_id = ?
     ORDER BY start_date DESC"
);

$stmt->execute([$school_id]);

$academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activate Academic Year | EduTrack</title>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        background: #f4f6fa;
        color: #172554;
    }

    .container {
        max-width: 700px;
        margin: 60px auto;
        padding: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    h2 {
        color: #102a56;
        margin-bottom: 10px;
    }

    p {
        color: #64748b;
    }

    label {
        display: block;
        margin-top: 25px;
        margin-bottom: 10px;
        font-weight: bold;
    }

    select {
        width: 100%;
        padding: 13px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        font-size: 15px;
        margin-bottom: 20px;
    }

    button {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 7px;
        background: #102a56;
        color: white;
        font-size: 16px;
        cursor: pointer;
    }

    button:hover {
        background: #d4a017;
    }

    .message {
        padding: 12px;
        margin: 20px 0;
        border-radius: 6px;
    }

    .success {
        background: #dcfce7;
        color: #166534;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
    }

    .year-list {
        margin-top: 25px;
    }

    .year-item {
        padding: 15px;
        margin-bottom: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
    }

    .active {
        color: #15803d;
        font-weight: bold;
    }

    .inactive {
        color: #64748b;
 }

    .back {
        display: inline-block;
        margin-top: 25px;
        color: #102a56;
        text-decoration: none;
    }
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
    color: #b7c481;
    margin-bottom: 0;
}
</style>
</head>
<body>
    <div class="header">

    <h1>EduTrack</h1>

    <p>ACADEMIC YEAR ACTIVATION</p>

</div>
<div class="container">
<h2>Activate Academic Year</h2>

<p>Select the academic year your school is currently using.</p>

<?php if (!empty($message)): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (count($academic_years) > 0): ?>

    <form method="POST" action="">

        <label>Select Academic Year</label>

        <select name="year_id" required>

            <option value="">-- Select Academic Year --</option>

            <?php foreach ($academic_years as $year): ?>

                <option value="<?php echo $year['id']; ?>">
                    <?php echo htmlspecialchars($year['year_name']); ?>
                    (<?php echo htmlspecialchars($year['status']); ?>)
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            Activate Academic Year
        </button>

    </form>

    <div class="year-list">

        <h3>Available Academic Years</h3>

        <?php foreach ($academic_years as $year): ?>

            <div class="year-item">

                <strong>
                    <?php echo htmlspecialchars($year['year_name']); ?>
                </strong>

                <p>
                    Opening:
                    <?php echo htmlspecialchars($year['start_date']); ?>
                </p>

                <p>
                    Closing:
                    <?php echo htmlspecialchars($year['end_date']); ?>
                </p>

                <span class="<?php echo strtolower($year['status']); ?>">
                    <?php echo htmlspecialchars($year['status']); ?>
                </span>

            </div>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <p>You have not created any academic year yet.</p>

    <a href="create_academic_year.php">
        Create Academic Year
    </a>

<?php endif; ?>

<a href="dashboard.php" class="back">
    ← Back to Main Page
</a>
</div>
</body>
</html>