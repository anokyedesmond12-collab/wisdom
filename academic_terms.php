<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = $_SESSION["school_id"];

// Retrieve academic years belonging to this school
$stmt = $conn->prepare(
    "SELECT * FROM academic_years
     WHERE school_id = ?
     ORDER BY start_date DESC"
);

$stmt->execute([$school_id]);

$academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve academic terms belonging to this school
$stmt = $conn->prepare(
    "SELECT academic_terms.*, academic_years.year_name
     FROM academic_terms
     INNER JOIN academic_years
     ON academic_terms.academic_year_id = academic_years.id
     WHERE academic_terms.school_id = ?
     ORDER BY academic_terms.start_date DESC"
);

$stmt->execute([$school_id]);

$academicTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EDUTRACK | Academic Terms</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="dashboard-page">

    <div class="dashboard-header">

        <div>
            <h1>Academic Term Management</h1>
            <p>Create and manage your school's academic terms.</p>
        </div>

        <div class="admin-profile">
            <?php echo htmlspecialchars($_SESSION["school_name"]); ?>
        </div>

    </div>
    <div class="academic-year-form">

    <h2>Create New Academic Term</h2>

    <form action="create_academic_term.php" method="POST">

        <label>Select Academic Year</label>

        <select name="academic_year_id" required>

            <option value="">Choose Academic Year</option>

            <?php foreach ($academicYears as $year): ?>

                <option value="<?php echo $year['id']; ?>">
                    <?php echo htmlspecialchars($year['year_name']); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label>Select Term</label>

        <select name="term_name" required>

            <option value="">Choose Term</option>
            <option value="Term 1">Term 1</option>
            <option value="Term 2">Term 2</option>
            <option value="Term 3">Term 3</option>

        </select>

        <label>Opening Date</label>
        <input type="date" name="start_date" required>

        <label>Closing Date</label>
        <input type="date" name="end_date" required>

        <button type="submit">Create Academic Term</button>

    </form>

</div>

    <h2>Registered Academic Terms</h2>

    <table class="school-table">

        <thead>

            <tr>
                <th>Academic Year</th>
                <th>Term</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>actions

                </th>
            </tr>

        </thead>

        <tbody>

            <?php foreach ($academicTerms as $term): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($term['year_name']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($term['term_name']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($term['start_date']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($term['end_date']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($term['status']); ?>
                    </td>
<td>

    <?php if ($term['status'] == 'Inactive'): ?>

        <form action="activate_academic_term.php" method="POST">

            <input type="hidden" name="term_id"
                   value="<?php echo $term['id']; ?>">

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

            <?php if (empty($academicTerms)): ?>

                <tr>
                    <td colspan="5">No academic terms have been created yet.</td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</body>

</html>