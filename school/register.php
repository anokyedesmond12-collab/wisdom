 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Your School - EDUTRACK</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
    .registration-heading {
        background: #102a56;
        color: white;
        padding: 28px 7%;
    }

    .registration-heading h1 {
        margin: 0;
        font-size: 27px;
        color: #a3b65e;
    }

    .registration-heading p {
        color: #d0d5df;
        margin-bottom: 0;
    }

    .logo-section {
        margin: 20px 0;
        padding: 18px;
        background: #f5f7fb;
        border: 1px solid #e0e5ed;
        border-radius: 9px;
    }

    .logo-section label {
        font-weight: bold;
        display: block;
        margin-bottom: 10px;
    }

    .logo-section input {
        width: 100%;
    }

    .logo-preview {
        width: 120px;
        height: 120px;
        margin-top: 15px;
        border: 2px dashed #cbd3df;
        border-radius: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        background: white;
    }

    .logo-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .logo-placeholder {
        color: #78869b;
        font-size: 12px;
        text-align: center;
        padding: 10px;
    }

    .logo-note {
        font-size: 12px;
        color: #78869b;
        margin-top: 8px;
    }
</style>
</head>
<body class="registration-page">
<div class="registration-container">

    <div class="registration-heading">
        <h1>Register Your School</h1>
        <p>Join EDUTRACK to manage your school's academic activities.</p>
    </div>

    <form action="register_process.php"
          method="POST"
          enctype="multipart/form-data"
          class="registration-form">

        <label>School Name:</label><br>
        <input type="text" name="school_name" required>
        <br><br>

        <label>School Type:</label><br>

        <select name="school_type" required>
            <option value="">Select School Type</option>
            <option value="Primary">Primary</option>
            <option value="JHS">Junior High School</option>
            <option value="SHS">Senior High School</option>
            <option value="Combined">Combined (Primary, JHS and SHS)</option>
        </select>

        <br><br>

        <label>School Location:</label><br>
        <input type="text" name="location" required>
        <br><br>

        <label>Administrator's Name:</label><br>
        <input type="text" name="admin_name" required>
        <br><br>

        <label>Email Address:</label><br>
        <input type="email" name="email" required>
        <br><br>

        <label>Phone Number:</label><br>
        <input type="tel" name="phone" required>
        <br><br>

        <label>Password:</label><br>
        <input type="password" name="password" minlength="8" required>
        <br><br>

        <!-- SCHOOL LOGO UPLOAD -->

        <div class="logo-section">

            <label for="school_logo">Upload School Logo</label>

            <input
                type="file"
                name="school_logo"
                id="school_logo"
                accept=".jpg,.jpeg,.png,.webp"
                required
            >

            <p class="logo-note">
                Upload your official school logo.
                Accepted formats: JPG, PNG and WEBP.
                Maximum size: 2MB.
            </p>

            <div class="logo-preview" id="logoPreview">
                <div class="logo-placeholder">
                    School logo preview
                </div>
            </div>

        </div>

        <br>

        <?php
        $subscriptionPlanConfig = require __DIR__ . "/../config/subscription_plans.php";
        $availableSubscriptionPlans = $subscriptionPlanConfig["available"];
        $selectedSubscriptionPlan = trim($_GET["plan"] ?? "Basic Monthly");
        if (!array_key_exists($selectedSubscriptionPlan, $availableSubscriptionPlans)) {
            $selectedSubscriptionPlan = "Basic Monthly";
        }
        ?>
        <label for="subscription_plan">Choose Subscription Plan:</label><br>
        <select id="subscription_plan" name="subscription_plan" required>
            <?php foreach ($availableSubscriptionPlans as $planKey => $plan): ?>
                <option value="<?php echo htmlspecialchars($planKey, ENT_QUOTES, "UTF-8"); ?>" <?php echo $selectedSubscriptionPlan === $planKey ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($plan["name"], ENT_QUOTES, "UTF-8"); ?> — GH₵<?php echo number_format((float)$plan["price"], 2); ?> / <?php echo htmlspecialchars($plan["duration_label"], ENT_QUOTES, "UTF-8"); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <br><br>

        <button type="submit">Register School</button>

    </form>

</div>

<script>
    const logoInput = document.getElementById("school_logo");
    const logoPreview = document.getElementById("logoPreview");

    logoInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) return;

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert("Please select a JPG, PNG or WEBP image.");
            this.value = "";
            logoPreview.innerHTML =
                '<div class="logo-placeholder">School logo preview</div>';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert("The logo must not exceed 2MB.");
            this.value = "";
            logoPreview.innerHTML =
                '<div class="logo-placeholder">School logo preview</div>';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            logoPreview.innerHTML =
                '<img src="' + event.target.result + '" alt="School Logo">';
        };

        reader.readAsDataURL(file);

    });
</script>
</body>
</html>