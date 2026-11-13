<?php

session_start();

require_once "../config/database.php";

// Protect the page
if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

// Retrieve academic years belonging to this school
$stmt = $conn->prepare(
    "SELECT * FROM academic_years
     WHERE school_id = ?
     ORDER BY start_date DESC"
);

$stmt->execute([$_SESSION["school_id"]]);

$academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EDUTRACK | Academic Years</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="dashboard-page">

    <div class="dashboard-header">

        <div>
            <h1>Academic Year Management</h1>
            <p>Manage your school's academic years.</p>
        </div>

        <div class="admin-profile">
            <?php echo htmlspecialchars($_SESSION["school_name"]); ?>
        </div>

    </div>
<div class="academic-year-form">

    <h2>Create New Academic Year</h2>

    <form action="create_academic_year.php" method="POST">

        <label>Academic Year</label>
        <input
            type="text"
            name="year_name"
            placeholder="e.g. 2026/2027"
            pattern="[0-9]{4}/[0-9]{4}"
            required
        >

        <label>Opening Date</label>
        <input type="date" name="start_date" required>

        <label>Closing Date</label>
        <input type="date" name="end_date" required>

        <button type="submit">Create Academic Year</button>

    </form>

</div>
    <h2>Registered Academic Years</h2>

    <table class="school-table">

        <thead>
            <tr>
                <th>Academic Year</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>actions</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($academicYears as $year): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($year['year_name']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($year['start_date']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($year['end_date']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($year['status']); ?>
                    </td>
                    <td>

    <?php if ($year['status'] == 'Inactive'): ?>

        <form action="activate_academic_year.php" method="POST">

            <input type="hidden" name="year_id"
                   value="<?php echo $year['id']; ?>">

            <button type="submit">Activate</button>

        </form>

    <?php else: ?>

        <span style="color: green; font-weight: bold;">
            Currently Active
        </span>

    <?php endif; ?>

</td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</body>
</html>