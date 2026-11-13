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

$academic_year_id = "";
$term_name = "";
$start_date = "";
$end_date = "";

// Process submitted form
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $academic_year_id = $_POST["academic_year_id"] ?? "";
    $term_name = $_POST["term_name"] ?? "";
    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";

    if (!is_numeric($academic_year_id)) {

        $message = "Please select a valid academic year.";
        $message_type = "error";

    } elseif (!in_array($term_name, ["Term 1", "Term 2", "Term 3"])) {

        $message = "Please select a valid term.";
        $message_type = "error";

    } elseif (empty($start_date) || empty($end_date)) {

        $message = "Please enter both opening and closing dates.";
        $message_type = "error";

    } elseif ($end_date <= $start_date) {

        $message = "Closing date must be after opening date.";
        $message_type = "error";

    } else {

        try {

            // Confirm academic year belongs to this school
            $check = $conn->prepare(
                "SELECT id FROM academic_years
                 WHERE id = ? AND school_id = ?"
            );

            $check->execute([$academic_year_id, $school_id]);

            if (!$check->fetch()) {

                $message = "Academic year not found.";
                $message_type = "error";

            } else {

                // Save academic term
                $sql = "INSERT INTO academic_terms
                        (school_id, academic_year_id, term_name, start_date, end_date)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);

                $stmt->execute([
                    $school_id,
                    $academic_year_id,
                    $term_name,
                    $start_date,
                    $end_date
                ]);

                $message = "Academic term created successfully!";
                $message_type = "success";

                // Clear form after successful submission
                $academic_year_id = "";
                $term_name = "";
                $start_date = "";
                $end_date = "";
            }

        } catch (PDOException $e) {

            if ($e->getCode() == "23000") {
                $message = "This term already exists for the selected academic year.";
            } else {
                $message = "Unable to create academic term. Check your database configuration.";
            }

            $message_type = "error";
        }
    }
}

// Fetch academic years belonging to this school
$stmt = $conn->prepare(
    "SELECT id, year_name
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

    <title>Create Academic Term | EduTrack</title>

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

        .container {
            max-width: 650px;
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

        .description {
            color: #64748b;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select,
 input {
            width: 100%;
            padding: 13px;
            margin-bottom: 20px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
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
            margin-bottom: 20px;
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

        .back {
            display: inline-block;
            margin-top: 25px;
            color: #74510f;
            text-decoration: none;
        }
       .heades {
    background: #26345c;
    color: white;
    padding: 28px 7%;
}

.heades h1{
    margin: 0;
    font-size: 27px;
}

.heades p{
    color: #b7c481;
    margin-bottom: 0;
}
    </style>
</head>
<body>
    
    <div class="heades">

    <h1>EduTrack</h1>

    <p>ACADEMIC TERM DASHBOARD</p>

     
<div class="container">

    <h2>Create Academic Term</h2>

    <p class="description">
        Create a new academic term under an existing academic year.
    </p>

    <?php if (!empty($message)): ?>

        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <?php if (count($academic_years) > 0): ?>

        <form method="POST" action="">

            <label>Select Academic Year</label>

            <select name="academic_year_id" required>

                <option value="">-- Select Academic Year --</option>

                <?php foreach ($academic_years as $year): ?>

                    <option
                        value="<?php echo $year['id']; ?>"
                        <?php echo ($academic_year_id == $year['id']) ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($year['year_name']); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label>Select Term</label>

            <select name="term_name" required>

                <option value="">-- Select Term --</option>

                <option value="Term 1" <?php echo ($term_name == "Term 1") ? 'selected' : ''; ?>>
                    Term 1
                </option>

                <option value="Term 2" <?php echo ($term_name == "Term 2") ? 'selected' : ''; ?>>
                    Term 2
                </option>

                <option value="Term 3" <?php echo ($term_name == "Term 3") ? 'selected' : ''; ?>>
                    Term 3
                </option>

            </select>

            <label>Opening Date</label>

            <input
                type="date"
                name="start_date"
                value="<?php echo htmlspecialchars($start_date); ?>"
                required
            >

            <label>Closing Date</label>

            <input
                type="date"
                name="end_date"
                value="<?php echo htmlspecialchars($end_date); ?>"
                required
            >

            <button type="submit">
                Create Academic Term
            </button>

        </form>

    <?php else: ?>

        <p>You need to create an academic year before creating an academic term.</p>

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