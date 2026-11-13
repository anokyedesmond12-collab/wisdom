<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["school_id"]) || ($_SESSION["role"] ?? "") !== "school_admin") {
    header("Location: ../school/login.php");
    exit();
}

$school_id = (int) $_SESSION["school_id"];
$message = "";
$message_type = "";
$portalCredentials = $_SESSION["portal_credentials"] ?? null;
unset($_SESSION["portal_credentials"]);
$editStudentId = (int)($_GET["edit_id"] ?? 0);
$editValues = [];

if (empty($_SESSION["school_csrf_token"])) {
    $_SESSION["school_csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["school_csrf_token"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        ($_POST["action"] ?? "") === "update_student" &&
        is_scalar($_POST["student_record_id"] ?? null)
    ) {
        $editStudentId = (int)$_POST["student_record_id"];
    }

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($csrfToken, (string)$_POST["csrf_token"])
    ) {
        $message = "Your session expired. Refresh the page and try again.";
        $message_type = "error";
    } elseif (($_POST["action"] ?? "") === "update_student") {
        $studentRecordId = is_scalar($_POST["student_record_id"] ?? null)
            ? (int)$_POST["student_record_id"]
            : 0;
        $editStudentId = $studentRecordId;
        $editValues = $_POST;

        $getText = static function ($field) {
            $value = $_POST[$field] ?? "";
            return is_string($value) ? trim($value) : "";
        };
        $studentId = $getText("student_id");
        $studentEmail = strtolower($getText("email"));
        $firstName = $getText("first_name");
        $lastName = $getText("last_name");
        $otherNames = $getText("other_names");
        $gender = $getText("gender");
        $dateOfBirth = $getText("date_of_birth");
        $admissionDate = $getText("admission_date");
        $classInput = $_POST["class_id"] ?? 0;
        $classId = is_scalar($classInput) ? (int)$classInput : 0;
        $parentName = $getText("parent_name");
        $parentPhone = $getText("parent_phone");
        $parentEmail = strtolower($getText("parent_email"));
        $address = $getText("address");
        $validOptionalDate = static function ($value) {
            if ($value === "") {
                return true;
            }
            $date = DateTime::createFromFormat("!Y-m-d", $value);
            return $date && $date->format("Y-m-d") === $value;
        };

        $classCheck = $conn->prepare(
            "SELECT id FROM classes WHERE id = ? AND school_id = ?"
        );
        $classCheck->execute([$classId, $school_id]);
        $validClass = $classCheck->fetchColumn();

        if (
            $studentRecordId < 1 ||
            $studentId === "" ||
            strlen($studentId) > 50 ||
            !filter_var($studentEmail, FILTER_VALIDATE_EMAIL) ||
            strlen($studentEmail) > 190 ||
            $firstName === "" ||
            strlen($firstName) > 100 ||
            $lastName === "" ||
            strlen($lastName) > 100 ||
            strlen($otherNames) > 100 ||
            !in_array($gender, ["Male", "Female"], true) ||
            !$validOptionalDate($dateOfBirth) ||
            !$validOptionalDate($admissionDate) ||
            !$validClass ||
            strlen($parentName) > 150 ||
            strlen($parentPhone) > 20 ||
            ($parentEmail !== "" && (
                !filter_var($parentEmail, FILTER_VALIDATE_EMAIL) ||
                strlen($parentEmail) > 150
            ))
        ) {
            $message = "Check the student details, dates, email addresses, and selected class.";
            $message_type = "error";
        } else {
            $newParentPassword = null;
            $parentAccountCreated = false;

            try {
                $conn->beginTransaction();

                $currentStmt = $conn->prepare(
                    "SELECT email, email_verified_at, parent_email
                     FROM students
                     WHERE id = ? AND school_id = ?
                     FOR UPDATE"
                );
                $currentStmt->execute([$studentRecordId, $school_id]);
                $currentStudent = $currentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$currentStudent) {
                    $conn->rollBack();
                    $message = "That student was not found in your school.";
                    $message_type = "error";
                } else {
                    $classCheck = $conn->prepare(
                        "SELECT id FROM classes WHERE id = ? AND school_id = ?"
                    );
                    $classCheck->execute([$classId, $school_id]);

                    if (!$classCheck->fetchColumn()) {
                        $conn->rollBack();
                        $message = "Select a class belonging to your school.";
                        $message_type = "error";
                    } else {
                        $emailChanged = strtolower((string)$currentStudent["email"]) !== $studentEmail;
                        $emailVerifiedAt = $emailChanged
                            ? null
                            : $currentStudent["email_verified_at"];

                        $update = $conn->prepare(
                            "UPDATE students
                             SET student_id = ?, email = ?, email_verified_at = ?,
                                 first_name = ?, last_name = ?, other_names = ?, gender = ?,
                                 date_of_birth = ?, admission_date = ?, class_id = ?,
                                 parent_name = ?, parent_phone = ?, parent_email = ?, address = ?
                             WHERE id = ? AND school_id = ?"
                        );
                        $update->execute([
                            $studentId,
                            $studentEmail,
                            $emailVerifiedAt,
                            $firstName,
                            $lastName,
                            $otherNames !== "" ? $otherNames : null,
                            $gender,
                            $dateOfBirth !== "" ? $dateOfBirth : null,
                            $admissionDate !== "" ? $admissionDate : null,
                            $classId,
                            $parentName !== "" ? $parentName : null,
                            $parentPhone !== "" ? $parentPhone : null,
                            $parentEmail !== "" ? $parentEmail : null,
                            $address !== "" ? $address : null,
                            $studentRecordId,
                            $school_id
                        ]);

                        $oldParentEmail = strtolower(trim($currentStudent["parent_email"] ?? ""));
                        if ($parentEmail !== "") {
                            $parentCheck = $conn->prepare(
                                "SELECT id FROM parents WHERE email = ? LIMIT 1 FOR UPDATE"
                            );
                            $parentCheck->execute([$parentEmail]);
                            $parentId = $parentCheck->fetchColumn();

                            if ($parentId) {
                                $saveParent = $conn->prepare(
                                    "UPDATE parents SET full_name = ?, phone = ? WHERE id = ?"
                                );
                                $saveParent->execute([
                                    $parentName !== "" ? $parentName : "Parent",
                                    $parentPhone !== "" ? $parentPhone : null,
                                    $parentId
                                ]);
                            } else {
                                $oldParentId = false;
                                $otherLinkedStudents = 1;
                                if ($oldParentEmail !== "" && $oldParentEmail !== $parentEmail) {
                                    $oldParentCheck = $conn->prepare(
                                        "SELECT id FROM parents WHERE email = ? LIMIT 1 FOR UPDATE"
                                    );
                                    $oldParentCheck->execute([$oldParentEmail]);
                                    $oldParentId = $oldParentCheck->fetchColumn();

                                    $linkedStudents = $conn->prepare(
                                        "SELECT COUNT(*) FROM students
                                         WHERE parent_email = ? AND id <> ?"
                                    );
                                    $linkedStudents->execute([$oldParentEmail, $studentRecordId]);
                                    $otherLinkedStudents = (int)$linkedStudents->fetchColumn();
                                }

                                if ($oldParentId && $otherLinkedStudents === 0) {
                                    $moveParent = $conn->prepare(
                                        "UPDATE parents
                                         SET full_name = ?, email = ?, phone = ?
                                         WHERE id = ?"
                                    );
                                    $moveParent->execute([
                                        $parentName !== "" ? $parentName : "Parent",
                                        $parentEmail,
                                        $parentPhone !== "" ? $parentPhone : null,
                                        $oldParentId
                                    ]);
                                } else {
                                    $newParentPassword = bin2hex(random_bytes(6));
                                    $createParent = $conn->prepare(
                                        "INSERT INTO parents (full_name, email, phone, password)
                                         VALUES (?, ?, ?, ?)"
                                    );
                                    $createParent->execute([
                                        $parentName !== "" ? $parentName : "Parent",
                                        $parentEmail,
                                        $parentPhone !== "" ? $parentPhone : null,
                                        password_hash($newParentPassword, PASSWORD_DEFAULT)
                                    ]);
                                    $parentAccountCreated = true;
                                }
                            }
                        }

                        $conn->commit();
                        if ($parentAccountCreated) {
                            $_SESSION["portal_credentials"] = [
                                "student_id" => "",
                                "student_password" => "",
                                "parent_email" => $parentEmail,
                                "parent_password" => $newParentPassword,
                                "parent_account_created" => true
                            ];
                        }
                        header("Location: student.php?updated=1" . ($emailChanged ? "&email_changed=1" : ""));
                        exit();
                    }
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                if ($e->getCode() === "23000") {
                    $message = "The admission number or student email is already in use.";
                } else {
                    error_log("Unable to update student details: " . $e->getMessage());
                    $message = "Unable to update student details. Please try again.";
                }
                $message_type = "error";
            }
        }
    } elseif (($_POST["action"] ?? "") === "set_student_password") {
        $studentRecordId = is_scalar($_POST["student_record_id"] ?? null)
            ? (int)$_POST["student_record_id"]
            : 0;
        $newPassword = is_string($_POST["new_password"] ?? null)
            ? $_POST["new_password"]
            : "";

        if ($studentRecordId < 1 || strlen($newPassword) < 8) {
            $message = "Choose a password with at least 8 characters.";
            $message_type = "error";
        } else {
            try {
                $update = $conn->prepare(
                    "UPDATE students SET password = ? WHERE id = ? AND school_id = ?"
                );
                $update->execute([
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $studentRecordId,
                    $school_id
                ]);
                if ($update->rowCount() === 1) {
                    $message = "Student portal password reset successfully.";
                    $message_type = "success";
                } else {
                    $message = "The student record was not found in your school.";
                    $message_type = "error";
                }
            } catch (PDOException $e) {
                error_log("Unable to reset student portal password: " . $e->getMessage());
                $message = "Unable to reset the student password. Please try again.";
                $message_type = "error";
            }
        }
    } elseif (($_POST["action"] ?? "") === "set_student_email") {
        $studentRecordId = (int)($_POST["student_record_id"] ?? 0);
        $studentEmail = strtolower(trim($_POST["email"] ?? ""));

        if ($studentRecordId < 1 || !filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "Enter a valid student email address.";
            $message_type = "error";
        } else {
            try {
                $update = $conn->prepare(
                    "UPDATE students
                     SET email_verified_at = IF(email <> ?, NULL, email_verified_at),
                         email = ?
                     WHERE id = ? AND school_id = ?"
                );
                $update->execute([$studentEmail, $studentEmail, $studentRecordId, $school_id]);
                $message = "Student Google sign-in email updated. A changed address must be verified before Google sign-in.";
                $message_type = "success";
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") {
                    $message = "That email is already assigned to a student in this school.";
                    $message_type = "error";
                } else {
                    error_log("Unable to update student Google email: " . $e->getMessage());
                    $message = "Unable to update the student email. Please try again.";
                    $message_type = "error";
                }
            }
        }
    } elseif (($_POST["action"] ?? "") === "set_parent_password") {
        $parentEmailInput = $_POST["parent_email"] ?? "";
        $parentEmail = is_string($parentEmailInput)
            ? strtolower(trim($parentEmailInput))
            : "";
        $newPassword = is_string($_POST["new_password"] ?? null)
            ? $_POST["new_password"]
            : "";

        if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL) || strlen($newPassword) < 8) {
            $message = "Enter a valid parent email and a password with at least 8 characters.";
            $message_type = "error";
        } else {
            try {
                $parentInfo = $conn->prepare(
                    "SELECT parent_name, parent_phone
                     FROM students
                     WHERE school_id = ? AND parent_email = ?
                     LIMIT 1"
                );
                $parentInfo->execute([$school_id, $parentEmail]);
                $parent = $parentInfo->fetch(PDO::FETCH_ASSOC);

                if (!$parent) {
                    $message = "No student in this school is linked to that parent email.";
                    $message_type = "error";
                } else {
                    $saveParent = $conn->prepare(
                        "INSERT INTO parents (full_name, email, phone, password)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             full_name = VALUES(full_name),
                             phone = VALUES(phone),
                             password = VALUES(password)"
                    );
                    $saveParent->execute([
                        $parent["parent_name"] ?: "Parent",
                        $parentEmail,
                        $parent["parent_phone"],
                        password_hash($newPassword, PASSWORD_DEFAULT)
                    ]);
                    $message = "Parent portal password reset successfully.";
                    $message_type = "success";
                }
            } catch (PDOException $e) {
                error_log("Unable to reset parent portal password: " . $e->getMessage());
                $message = "Unable to reset the parent password. Please try again.";
                $message_type = "error";
            }
        }
    } else {
        $student_id = trim($_POST["student_id"] ?? "");
        $student_email = strtolower(trim($_POST["email"] ?? ""));
        $first_name = trim($_POST["first_name"] ?? "");
        $last_name = trim($_POST["last_name"] ?? "");
        $other_names = trim($_POST["other_names"] ?? "");
        $gender = $_POST["gender"] ?? "";
        $dob = !empty($_POST["date_of_birth"])
            ? $_POST["date_of_birth"] : null;

        $class_id = (int)($_POST["class_id"] ?? 0);

        $parent_name = trim($_POST["parent_name"] ?? "");
        $parent_phone = trim($_POST["parent_phone"] ?? "");
        $parent_email = strtolower(trim($_POST["parent_email"] ?? ""));

        $classCheck = $conn->prepare(
            "SELECT id FROM classes WHERE id = ? AND school_id = ?"
        );

        $classCheck->execute([$class_id, $school_id]);

        $validClass = $classCheck->fetch(PDO::FETCH_ASSOC);

        if (
            $student_id === "" ||
            !filter_var($student_email, FILTER_VALIDATE_EMAIL) ||
            $first_name === "" ||
            $last_name === "" ||
            !in_array($gender, ["Male", "Female"], true) ||
            !$validClass ||
            ($parent_email !== "" && !filter_var($parent_email, FILTER_VALIDATE_EMAIL))
        ) {
            $message = "Please complete all required fields, use a valid parent email, and select a valid class.";
            $message_type = "error";
        } else {
            $studentPassword = bin2hex(random_bytes(6));
            $parentPassword = null;
            $parentAccountCreated = false;

            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare(
                    "INSERT INTO students
                    (school_id, student_id, password, email, first_name, last_name, other_names,
                     gender, date_of_birth, class_id, parent_name, parent_phone, parent_email)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $school_id,
                    $student_id,
                    password_hash($studentPassword, PASSWORD_DEFAULT),
                    $student_email,
                    $first_name,
                    $last_name,
                    $other_names,
                    $gender,
                    $dob,
                    $class_id,
                    $parent_name,
                    $parent_phone,
                    $parent_email !== "" ? $parent_email : null
                ]);

                if ($parent_email !== "") {
                    $parentCheck = $conn->prepare("SELECT id FROM parents WHERE email = ? LIMIT 1");
                    $parentCheck->execute([$parent_email]);

                    if (!$parentCheck->fetchColumn()) {
                        $parentPassword = bin2hex(random_bytes(6));
                        $createParent = $conn->prepare(
                            "INSERT INTO parents (full_name, email, phone, password)
                             VALUES (?, ?, ?, ?)"
                        );
                        $createParent->execute([
                            $parent_name !== "" ? $parent_name : "Parent",
                            $parent_email,
                            $parent_phone !== "" ? $parent_phone : null,
                            password_hash($parentPassword, PASSWORD_DEFAULT)
                        ]);
                        $parentAccountCreated = true;
                    }
                }

                $conn->commit();
                $_SESSION["portal_credentials"] = [
                    "student_id" => $student_id,
                    "student_password" => $studentPassword,
                    "parent_email" => $parent_email,
                    "parent_password" => $parentPassword,
                    "parent_account_created" => $parentAccountCreated
                ];
                header("Location: student.php?success=1");
                exit();
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Student registration error: " . $e->getMessage());
                $message = ($e->getCode() == "23000")
                    ? "Registration failed. The admission number or student email may already be in use."
                    : "Unable to register student. Please try again.";
                $message_type = "error";
            }
        }
    }
}

// SUCCESS MESSAGE
if (isset($_GET["success"])) {
    $message = "Student registered successfully!";
    $message_type = "success";
} elseif (isset($_GET["updated"])) {
    $message = isset($_GET["email_changed"])
        ? "Student details updated. The new student email must be verified before Google sign-in."
        : "Student details updated.";
    $message_type = "success";
}

// GET SCHOOL CLASSES
$classStmt = $conn->prepare(
    "SELECT id, class_name
     FROM classes
     WHERE school_id = ?
     ORDER BY class_name"
);

$classStmt->execute([$school_id]);
$classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

// GET REGISTERED STUDENTS
$studentStmt = $conn->prepare(
    "SELECT s.*, c.class_name
     FROM students s
     INNER JOIN classes c ON c.id = s.class_id
     WHERE s.school_id = ?
     AND c.school_id = ?
     ORDER BY s.id DESC"
);

$studentStmt->execute([$school_id, $school_id]);
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

$editStudent = null;
if ($editStudentId > 0) {
    $editStmt = $conn->prepare(
        "SELECT * FROM students WHERE id = ? AND school_id = ? LIMIT 1"
    );
    $editStmt->execute([$editStudentId, $school_id]);
    $editStudent = $editStmt->fetch(PDO::FETCH_ASSOC);

    if (!$editStudent && $message === "") {
        $message = "That student was not found in your school.";
        $message_type = "error";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Management | EduTrack</title>

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

/* HEADER */

.header {
    background: linear-gradient(120deg, #10264b, #1d4ed8);
    color: white;
    padding: 30px 24px;
}

.header-inner {
    max-width: 1280px;
    margin: 0 auto;
}

.header-kicker,
.section-kicker {
    color: #f3cf70;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.header h1 {
    margin: 8px 0 6px;
    font-size: clamp(26px, 4vw, 34px);
}

.header p {
    margin: 0;
    color: #dbeafe;
    line-height: 1.6;
}

.container {
    max-width: 1280px;
    margin: 28px auto;
    padding: 0 24px;
}

.card {
    margin-bottom: 22px;
    padding: 26px;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: white;
    box-shadow: 0 8px 24px rgba(16, 38, 75, 0.05);
}

.card h2 {
    margin: 0;
    color: #10264b;
    font-size: 20px;
}

.description {
    margin: 8px 0 20px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.notice {
    margin-bottom: 18px;
    padding: 14px 16px;
    border: 1px solid transparent;
    border-radius: 12px;
    font-weight: 600;
    line-height: 1.5;
}

.success {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #166534;
}

.error {
    border-color: #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.credentials-notice {
    display: grid;
    gap: 18px;
    margin-bottom: 22px;
    padding: 22px;
    border: 1px solid #a7d8bd;
    border-radius: 16px;
    background: linear-gradient(135deg, #f0fbf4, #ffffff);
    color: #14532d;
    box-shadow: 0 8px 24px rgba(22, 101, 52, 0.06);
}

.credentials-heading {
    display: flex;
    align-items: flex-start;
    gap: 13px;
}

.credentials-icon {
    display: grid;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 12px;
    background: #dcfce7;
    color: #15803d;
    font-size: 20px;
    font-weight: 800;
}

.credentials-heading h2 {
    margin: 0 0 4px;
    color: #14532d;
    font-size: 19px;
}

.credentials-heading p {
    margin: 0;
    color: #3f6b51;
    font-size: 13px;
    line-height: 1.5;
}

.credentials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
}

.credential-item {
    display: grid;
    gap: 8px;
    min-width: 0;
    padding: 13px;
    border: 1px solid #d5efdf;
    border-radius: 11px;
    background: white;
}

.credential-item span {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.credential-item code {
    overflow-wrap: anywhere;
    color: #10264b;
    font-size: 14px;
    font-weight: 700;
}

.credential-note {
    grid-column: 1 / -1;
    margin: 0;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff;
    color: #52647e;
    font-size: 13px;
    line-height: 1.5;
}

.summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    background: white;
    box-shadow: 0 6px 18px rgba(16, 38, 75, 0.04);
}

.summary-icon {
    display: grid;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    place-items: center;
    border-radius: 14px;
    background: #eef4ff;
    color: #1d4ed8;
    font-size: 22px;
}

.summary-card h3 {
    margin: 0 0 3px;
    color: #10264b;
    font-size: 26px;
}

.summary-card p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.register-card {
    padding: 0;
    overflow: hidden;
}

.register-card > summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 22px 26px;
    cursor: pointer;
    list-style: none;
}

.register-card > summary::-webkit-details-marker {
    display: none;
}

.register-card > summary::after {
    display: grid;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 12px;
    background: #eef4ff;
    color: #183d70;
    content: "+";
    font-size: 24px;
    font-weight: 500;
}

.register-card[open] > summary::after {
    content: "−";
}

.register-summary-copy h2 {
    margin: 5px 0;
}

.register-summary-copy .section-kicker {
    color: #b58114;
}

.register-summary-copy p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.register-form-content {
    padding: 0 26px 26px;
}

.register-form-content > .description {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 20px;
}

.form-section-label {
    grid-column: 1 / -1;
    margin-top: 4px;
    padding-bottom: 9px;
    border-bottom: 1px solid #e8edf4;
    color: #183d70;
    font-size: 13px;
    font-weight: 800;
}

.form-group {
    min-width: 0;
}

label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

input,
select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    color: #17243b;
    font-size: 14px;
}

input:focus,
select:focus {
    outline: 3px solid rgba(37, 99, 171, 0.14);
    border-color: #2563ab;
}

.submit-btn {
    margin-top: 20px;
    padding: 12px 18px;
    border: 0;
    border-radius: 9px;
    background: #10264b;
    color: white;
    cursor: pointer;
    font-weight: 700;
    transition: background 0.2s ease, transform 0.2s ease;
}

.submit-btn:hover {
    transform: translateY(-1px);
    background: #183d70;
}

.edit-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.portal-password-form {
    display: grid;
    align-content: start;
    gap: 8px;
    min-width: 0;
    padding: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
}

.portal-password-form input {
    min-height: 40px;
}

.portal-password-form button {
    min-height: 38px;
    padding: 9px 11px;
    border: 0;
    border-radius: 8px;
    background: #10264b;
    color: white;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.portal-password-form button:hover {
    background: #183d70;
}

.student-list-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.student-list-heading .description {
    margin-bottom: 0;
}

.student-count-badge {
    flex: 0 0 auto;
    padding: 7px 11px;
    border-radius: 999px;
    background: #eef4ff;
    color: #183d70;
    font-size: 12px;
    font-weight: 800;
}

.student-card-grid {
    display: grid;
    gap: 14px;
}

.student-card {
    padding: 18px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.student-card:hover {
    border-color: #cbd8ea;
    box-shadow: 0 8px 20px rgba(16, 38, 75, 0.05);
}

.student-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.student-index {
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.student-card h3 {
    margin: 5px 0 0;
    color: #10264b;
    font-size: 17px;
}

.student-facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin: 16px 0;
}

.student-fact {
    display: grid;
    gap: 5px;
    min-width: 0;
    padding: 11px 12px;
    border-radius: 10px;
    background: #f8fafc;
}

.student-fact span {
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.student-fact strong {
    overflow-wrap: anywhere;
    color: #334155;
    font-size: 13px;
}

.access-details {
    border-top: 1px solid #edf1f6;
}

.access-details summary {
    padding: 13px 0 2px;
    color: #183d70;
    cursor: pointer;
    font-size: 13px;
    font-weight: 800;
}

.access-panel {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    padding-top: 14px;
}

.edit-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 12px;
    border: 1px solid #d5e2f5;
    border-radius: 9px;
    background: #eef4ff;
    color: #183d70;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.edit-link:hover {
    background: #dce9ff;
}

.empty {
    padding: 32px 18px;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: #f8fafc;
    color: #64748b;
    text-align: center;
}

.empty h3 {
    margin: 0 0 6px;
    color: #183d70;
}

.empty p {
    margin: 0;
    line-height: 1.5;
}

.back {
    display: inline-flex;
    margin: 4px 0 26px;
    color: #183d70;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
}

.back:hover {
    text-decoration: underline;
}

@media(max-width: 700px) {
    .header {
        padding: 24px 18px;
    }

    .container {
        margin: 20px auto;
        padding: 0 14px;
    }

    .card {
        padding: 19px;
        border-radius: 13px;
    }

    .register-card {
        padding: 0;
    }

    .register-card > summary {
        padding: 19px;
    }

    .register-form-content {
        padding: 0 19px 19px;
    }

    .form-grid,
    .summary {
        grid-template-columns: 1fr;
    }

    .student-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .student-list-heading {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media(max-width: 420px) {
    .student-card-top {
        flex-direction: column;
    }

    .student-facts {
        grid-template-columns: 1fr;
    }

    .access-panel {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<!-- HEADER -->

<header class="header">
    <div class="header-inner">
        <span class="header-kicker">School Admin · Student Records</span>
        <h1>Student Management</h1>
        <p>Register students, keep records organized, and manage portal access.</p>
    </div>
</header>


<div class="container">

    <!-- NOTIFICATIONS -->

    <?php if ($message !== "" && !$portalCredentials): ?>

        <div class="notice <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($portalCredentials): ?>
        <div class="credentials-notice">
            <div class="credentials-heading">
                <span class="credentials-icon" aria-hidden="true">✓</span>
                <div>
                    <h2><?php echo !empty($portalCredentials["student_id"]) ? "Student registered successfully" : "Parent portal account created"; ?></h2>
                    <p>These temporary credentials are shown only once. Share them securely.</p>
                </div>
            </div>
            <div class="credentials-grid">
            <?php if (!empty($portalCredentials["student_id"])): ?>
                <div class="credential-item">
                    <span>Student admission number</span>
                    <code><?php echo htmlspecialchars($portalCredentials["student_id"]); ?></code>
                </div>
                <div class="credential-item">
                    <span>Temporary student password</span>
                    <code><?php echo htmlspecialchars($portalCredentials["student_password"]); ?></code>
                </div>
            <?php endif; ?>
            <?php if ($portalCredentials["parent_account_created"]): ?>
                <div class="credential-item">
                    <span>Parent portal email</span>
                    <code><?php echo htmlspecialchars($portalCredentials["parent_email"]); ?></code>
                </div>
                <div class="credential-item">
                    <span>Temporary parent password</span>
                    <code><?php echo htmlspecialchars($portalCredentials["parent_password"]); ?></code>
                </div>
            <?php elseif ($portalCredentials["parent_email"] !== ""): ?>
                <p class="credential-note">A parent account already exists for <?php echo htmlspecialchars($portalCredentials["parent_email"]); ?>. Its password was not changed.</p>
            <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($editStudent): ?>
        <?php
        $editValue = static function ($field) use ($editValues, $editStudent) {
            $value = $editValues[$field] ?? $editStudent[$field] ?? "";
            return htmlspecialchars(is_scalar($value) ? (string)$value : "");
        };
        $editGender = $editValues["gender"] ?? $editStudent["gender"];
        $editGender = is_string($editGender) ? $editGender : "";
        $editClassId = $editValues["class_id"] ?? $editStudent["class_id"];
        $editClassId = is_scalar($editClassId) ? (string)$editClassId : "";
        ?>
        <div class="card" id="edit-student">
            <h2>Update Student Details</h2>
            <p class="description">
                Update the student's school record. Portal passwords are not changed. Changing the student email clears its verification status.
            </p>

            <form method="POST" action="student.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="student_record_id" value="<?php echo (int)$editStudent["id"]; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-student-id">Admission Number *</label>
                        <input id="edit-student-id" type="text" name="student_id" maxlength="50" value="<?php echo $editValue("student_id"); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-student-email">Student Email for Google Sign-in *</label>
                        <input id="edit-student-email" type="email" name="email" maxlength="190" value="<?php echo $editValue("email"); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-first-name">First Name *</label>
                        <input id="edit-first-name" type="text" name="first_name" maxlength="100" value="<?php echo $editValue("first_name"); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-last-name">Last Name *</label>
                        <input id="edit-last-name" type="text" name="last_name" maxlength="100" value="<?php echo $editValue("last_name"); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-other-names">Other Names</label>
                        <input id="edit-other-names" type="text" name="other_names" maxlength="100" value="<?php echo $editValue("other_names"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-gender">Gender *</label>
                        <select id="edit-gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $editGender === "Male" ? "selected" : ""; ?>>Male</option>
                            <option value="Female" <?php echo $editGender === "Female" ? "selected" : ""; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-date-of-birth">Date of Birth</label>
                        <input id="edit-date-of-birth" type="date" name="date_of_birth" value="<?php echo $editValue("date_of_birth"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-admission-date">Admission Date</label>
                        <input id="edit-admission-date" type="date" name="admission_date" value="<?php echo $editValue("admission_date"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-class">Class *</label>
                        <select id="edit-class" name="class_id" required>
                            <option value="">Choose Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo (int)$class["id"]; ?>" <?php echo $editClassId === (string)$class["id"] ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($class["class_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-parent-name">Parent/Guardian Name</label>
                        <input id="edit-parent-name" type="text" name="parent_name" maxlength="150" value="<?php echo $editValue("parent_name"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-parent-phone">Parent/Guardian Phone</label>
                        <input id="edit-parent-phone" type="text" name="parent_phone" maxlength="20" value="<?php echo $editValue("parent_phone"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-parent-email">Parent/Guardian Email</label>
                        <input id="edit-parent-email" type="email" name="parent_email" maxlength="150" value="<?php echo $editValue("parent_email"); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit-address">Address</label>
                        <input id="edit-address" type="text" name="address" value="<?php echo $editValue("address"); ?>">
                    </div>
                </div>

                <div class="edit-actions">
                    <button type="submit" class="submit-btn">Save Student Details</button>
                    <a class="back" href="student.php">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-card">
            <span class="summary-icon" aria-hidden="true">🎓</span>
            <div>
                <h3><?php echo count($students); ?></h3>
                <p>Total students</p>
            </div>
        </div>

        <div class="summary-card">
            <span class="summary-icon" aria-hidden="true">🏫</span>
            <div>
                <h3><?php echo count($classes); ?></h3>
                <p>Available classes</p>
            </div>
        </div>
    </div>

    <details class="card register-card" id="register-student" <?php echo !$portalCredentials ? "open" : ""; ?>>
        <summary>
            <div class="register-summary-copy">
                <span class="section-kicker">Add a student</span>
                <h2>Register new student</h2>
                <p>Enter student details and optional parent or guardian information.</p>
            </div>
        </summary>
        <div class="register-form-content">
            <p class="description">
                Students and linked parents can set their own portal passwords after verifying their email.
            </p>

        <?php if (count($classes) > 0): ?>

        <form method="POST" action="student.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-grid">

                <div class="form-section-label">Student information</div>

                <div class="form-group">

                    <label>Admission Number *</label>

                    <input
                        type="text"
                        name="student_id"
                        placeholder="Enter admission number"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Student Email for Google Sign-in *</label>

                    <input
                        type="email"
                        name="email"
                        placeholder="student@example.com"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>First Name *</label>

                    <input
                        type="text"
                        name="first_name"
                        placeholder="Enter first name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Last Name *</label>

                    <input
                        type="text"
                        name="last_name"
                        placeholder="Enter last name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Other Names</label>

                    <input
                        type="text"
                        name="other_names"
                        placeholder="Enter other names"
                    >

                </div>

                <div class="form-group">

                    <label>Gender *</label>

                    <select name="gender" required>

                        <option value="">Select Gender</option>

                        <option value="Male">Male</option>

                        <option value="Female">Female</option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Date of Birth</label>

                    <input
                        type="date"
                        name="date_of_birth"
                    >

                </div>

                <div class="form-group">

                    <label>Select Class *</label>

                    <select name="class_id" required>

                        <option value="">Choose Class</option>

                        <?php foreach ($classes as $class): ?>

                            <option value="<?php echo (int)$class["id"]; ?>">

                                <?php echo htmlspecialchars($class["class_name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-section-label">Parent / guardian information</div>

                <div class="form-group">

                    <label>Parent/Guardian Name</label>

                    <input
                        type="text"
                        name="parent_name"
                        placeholder="Enter parent or guardian name"
                    >

                </div>

                <div class="form-group">

                    <label>Parent/Guardian Phone</label>

                    <input
                        type="text"
                        name="parent_phone"
                        placeholder="Enter phone number"
                    >

                </div>

                <div class="form-group">

                    <label>Parent/Guardian Email</label>

                    <input
                        type="email"
                        name="parent_email"
                        placeholder="Enter email address"
                    >

                </div>

            </div>

            <button type="submit" class="submit-btn">
                + Register Student
            </button>

        </form>

        <?php else: ?>

            <div class="empty">
                <h3>No Classes Available</h3>
                <p>Create a class before registering students.</p>
            </div>

        <?php endif; ?>
        </div>
    </details>


    <!-- REGISTERED STUDENTS -->

    <div class="card">
        <div class="student-list-heading">
            <div>
                <h2>Student directory</h2>
                <p class="description">Recently added students appear first. Manage records and portal access from each student card.</p>
            </div>
            <span class="student-count-badge"><?php echo count($students); ?> students</span>
        </div>

        <?php if (count($students) > 0): ?>

        <div class="student-card-grid">
                <?php $number = 1; ?>
                <?php foreach ($students as $student): ?>
                    <article class="student-card">
                        <div class="student-card-top">
                            <div>
                                <span class="student-index">Student #<?php echo $number++; ?></span>
                                <h3><?php echo htmlspecialchars(trim($student["first_name"] . " " . ($student["other_names"] ?? "") . " " . $student["last_name"])); ?></h3>
                            </div>
                            <a class="edit-link" href="student.php?edit_id=<?php echo (int)$student["id"]; ?>#edit-student">Edit details</a>
                        </div>
                        <div class="student-facts">
                            <div class="student-fact">
                                <span>Admission number</span>
                                <strong><?php echo htmlspecialchars($student["student_id"]); ?></strong>
                            </div>
                            <div class="student-fact">
                                <span>Class</span>
                                <strong><?php echo htmlspecialchars($student["class_name"]); ?></strong>
                            </div>
                            <div class="student-fact">
                                <span>Gender</span>
                                <strong><?php echo htmlspecialchars($student["gender"]); ?></strong>
                            </div>
                            <div class="student-fact">
                                <span>Student email</span>
                                <strong><?php echo htmlspecialchars($student["email"] ?? ""); ?></strong>
                            </div>
                        </div>
                        <details class="access-details">
                            <summary>Manage portal access and passwords</summary>
                            <div class="access-panel">
                            <form method="POST" action="student.php" class="portal-password-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="set_student_email">
                                <input type="hidden" name="student_record_id" value="<?php echo (int)$student["id"]; ?>">
                                <label for="student-email-<?php echo (int)$student["id"]; ?>">Student email</label>
                                <input id="student-email-<?php echo (int)$student["id"]; ?>" type="email" name="email" value="<?php echo htmlspecialchars($student["email"] ?? ""); ?>" placeholder="student@example.com" required>
                                <button type="submit">Save Email</button>
                            </form>
                            <form method="POST" action="student.php" class="portal-password-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="set_student_password">
                                <input type="hidden" name="student_record_id" value="<?php echo (int)$student["id"]; ?>">
                                <label for="student-password-<?php echo (int)$student["id"]; ?>">New student password</label>
                                <input id="student-password-<?php echo (int)$student["id"]; ?>" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
                                <button type="submit">Reset Student Password</button>
                            </form>
                            <?php if (!empty($student["parent_email"])): ?>
                                <form method="POST" action="student.php" class="portal-password-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="set_parent_password">
                                    <input type="hidden" name="parent_email" value="<?php echo htmlspecialchars($student["parent_email"]); ?>">
                                    <label for="parent-password-<?php echo (int)$student["id"]; ?>">New parent password (<?php echo htmlspecialchars($student["parent_email"]); ?>)</label>
                                    <input id="parent-password-<?php echo (int)$student["id"]; ?>" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
                                    <button type="submit">Reset Parent Password</button>
                                </form>
                            <?php else: ?>
                                <p class="credential-note">No parent email is on file for this student.</p>
                            <?php endif; ?>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
        </div>

        <?php else: ?>

            <div class="empty">
                <h3>No Students Registered</h3>
                <p>Register a student above. New records will appear here.</p>
            </div>

        <?php endif; ?>

    </div>


    <a href="../school/dashboard.php" class="back">
        ← Back to Dashboard
    </a>

    </div>

    </body>
</html>