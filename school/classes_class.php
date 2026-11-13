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

// Search functionality
$search = trim($_GET["search"] ?? "");

try {

    $sql = "
        SELECT 
            c.id,
            c.class_name,
            c.class_level,
            COUNT(s.id) AS total_students
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id
        WHERE c.school_id = ?
    ";

    $params = [$school_id];

    if ($search !== "") {
        $sql .= " AND c.class_name LIKE ?";
        $params[] = "%" . $search . "%";
    }

    $sql .= "
        GROUP BY c.id, c.class_name, c.class_level
        ORDER BY c.class_level, c.class_name
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Unable to load class details: " . $e->getMessage());

}

$total_classes = count($classes);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Class Details | EduTrack</title>

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
    padding: 25px 7%;
    color: white;
}

.header h2 {
    margin: 0;
}

.header p {
    color: #dbeafe;
    margin-bottom: 0;
}

.container {
    max-width: 1150px;
    margin: 40px auto;
    padding: 0 20px;
}

.summary {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    flex: 1;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.summary-card h3 {
    font-size: 30px;
    color: #d4a017;
    margin: 0 0 8px;
}

.summary-card p {
    color: #64748b;
    margin: 0;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.card h3 {
    margin-top: 0;
    color: #102a56;
}

.search-form {
    display: flex;
    gap: 10px;
    margin: 25px 0;
}

.search-form input {
    flex: 1;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
}

.search-form button {
    background: #102a56;
    color: white;
    border: none;
    padding: 13px 25px;
    border-radius: 7px;
    cursor: pointer;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #f8fafc;
    color: #102a56;
}

.badge {
    padding: 7px 13px;
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
    margin-top: 25px;
    text-decoration: none;
    color: #102a56;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}

@media(max-width: 650px) {
    .summary {
        flex-direction: column;
    }

    .search-form {
        flex-direction: column;
    }

    .search-form button {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="header">

    <h2>EduTrack</h2>

    <p>Class Management / Class Details</p>

</div>

<div class="container">

    <div class="summary">

        <div class="summary-card">

            <h3><?php echo $total_classes; ?></h3>

            <p>
                <?php echo ($search !== "") ? "Matching Classes" : "Total Classes"; ?>
            </p>

        </div>

    </div>

    <div class="card">

        <h3>Registered Class Details</h3>

        <p>View and search the classes registered under your school.</p>

        <form method="GET" class="search-form">

            <input
 type="text"
                name="search"
                placeholder="Search class name..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit">Search</button>

        </form>

        <?php if (count($classes) > 0): ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Class Name</th>
                        <th>Educational Level</th>
                        <th>Total Students</th>
                    </tr>

                </thead>

                <tbody>

                <?php $number = 1; ?>

                <?php foreach ($classes as $class): ?>

                    <tr>

                        <td><?php echo $number++; ?></td>

                        <td>
                            <strong>
                                <?php echo htmlspecialchars($class["class_name"]); ?>
                            </strong>
                        </td>

                        <td>

                            <span class="badge <?php echo strtolower($class["class_level"]); ?>">

                                <?php echo htmlspecialchars($class["class_level"]); ?>

                            </span>

                        </td>

                        <td>
                            <?php echo (int)$class["total_students"]; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php else: ?>

            <div class="empty">

                <h3>No Classes Found</h3>

                <p>
                    No classes have been registered yet or your search returned no results.
                </p>

            </div>

        <?php endif; ?>

    </div>

    <a href="dashboard.php" class="back">
        ← Back to Main Page
    </a>

</div>

</body>

</html>