<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: login.php");
    exit();
}

$school_id = (int)$_SESSION["school_id"];
$message = "";
$message_type = "";

// Fetch current school data
$stmt = $conn->prepare("SELECT school_name, school_logo FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    die("School not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["logo"])) {
    $file = $_FILES["logo"];

    if ($file["error"] === UPLOAD_ERR_NO_FILE) {
        $message = "Please select an image file to upload.";
        $message_type = "error";
    } elseif ($file["error"] !== UPLOAD_ERR_OK) {
        $message = "File upload failed with error code: " . $file["error"];
        $message_type = "error";
    } else {
        $allowedExts = ["jpg", "jpeg", "png", "webp"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowedExts, true)) {
            $message = "Invalid file type. Allowed formats: JPG, JPEG, PNG, WEBP.";
            $message_type = "error";
        } elseif ($file["size"] > $maxSize) {
            $message = "File is too large. Maximum allowed size is 2MB.";
            $message_type = "error";
        } else {
            // Verify real image
            $imageInfo = @getimagesize($file["tmp_name"]);
            if ($imageInfo === false) {
                $message = "Uploaded file is not a valid image.";
                $message_type = "error";
            } else {
                $uploadDir = __DIR__ . "/../uploads/school_logos/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newFileName = "school_" . md5(uniqid((string)rand(), true)) . "." . $ext;
                $targetPath = $uploadDir . $newFileName;
                $dbPath = "uploads/school_logos/" . $newFileName;

                if (move_uploaded_file($file["tmp_name"], $targetPath)) {
                    // Update database
                    $updateStmt = $conn->prepare("UPDATE schools SET school_logo = ? WHERE id = ?");
                    $updateStmt->execute([$dbPath, $school_id]);

                    $school["school_logo"] = $dbPath;
                    $message = "School logo updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Could not save the uploaded image. Check directory permissions.";
                    $message_type = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload School Logo | EDUTRACK</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f4f6fa;
            color: #172554;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        h2 {
            margin-top: 0;
            margin-bottom: 8px;
            color: #102a56;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .current-logo-card {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px dashed #cbd5e1;
            margin-bottom: 25px;
        }
        .current-logo-img {
            max-width: 140px;
            max-height: 140px;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            background: white;
            padding: 6px;
        }
        .no-logo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #102a56;
            color: #e9b949;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 14px;
        }
        input[type="file"] {
            width: 100%;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 7px;
            background: #102a56;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #e9b949;
            color: #102a56;
        }
        .notice {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .notice.success {
            background: #dcfce7;
            color: #166534;
        }
        .notice.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .back {
            display: inline-block;
            margin-top: 20px;
            color: #102a56;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }
        .back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back">← Back to Dashboard</a>
    <h2 style="margin-top: 15px;">Upload School Logo</h2>
    <p class="subtitle"><?php echo htmlspecialchars($school["school_name"]); ?> — Update your official emblem or badge.</p>

    <?php if ($message !== ""): ?>
        <div class="notice <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="current-logo-card">
        <p style="margin-top:0; color:#64748b; font-size:13px; font-weight:bold;">CURRENT LOGO</p>
        <?php if (!empty($school["school_logo"]) && file_exists(__DIR__ . "/../" . $school["school_logo"])): ?>
            <img src="../<?php echo htmlspecialchars($school["school_logo"]); ?>" alt="School Logo" class="current-logo-img">
        <?php else: ?>
            <div class="no-logo-placeholder">
                <?php echo strtoupper(substr($school["school_name"], 0, 1)); ?>
            </div>
            <p style="color:#8898aa; font-size:12px; margin-top:8px;">No logo uploaded yet</p>
        <?php endif; ?>
    </div>

    <form method="POST" action="upload_logo.php" enctype="multipart/form-data">
        <div class="form-group">
            <label for="logo">Select New Logo Image (JPG, PNG, WEBP — Max 2MB)</label>
            <input type="file" name="logo" id="logo" accept=".jpg,.jpeg,.png,.webp" required>
        </div>
        <button type="submit">Upload & Save Logo</button>
    </form>
</div>
</body>
</html>

