
<?php
session_start();
require 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token if necessary
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

// GET PATIENT RECORD
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: index.php");
    exit;
}

$error = '';

// UPDATE PATIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Invalid request. Please refresh the page.");
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birth_date = $_POST['birth_date'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $blood_type = $_POST['blood_type'] ?? '';
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $status = $_POST['status'] ?? '';

    $validGenders = ['Male', 'Female', 'Other'];
    $validBloodTypes = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $validStatuses = ['Active', 'Inactive'];

    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)
        && checkdate(
            (int)substr($birth_date, 5, 2),
            (int)substr($birth_date, 8, 2),
            (int)substr($birth_date, 0, 4)
        )
        && $birth_date <= date('Y-m-d');

    if (
        $first_name === '' ||
        $last_name === '' ||
        !in_array($gender, $validGenders, true) ||
        !$validDate ||
        $phone === '' ||
        $address === '' ||
        $diagnosis === '' ||
        !in_array($blood_type, $validBloodTypes, true) ||
        !in_array($status, $validStatuses, true)
    ) {
        $error = "Please complete all required fields correctly.";

    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } else {

        $stmt = $pdo->prepare("
            UPDATE patients SET
                first_name = ?,
                last_name = ?,
                gender = ?,
                birth_date = ?,
                phone = ?,
                email = ?,
                address = ?,
                blood_type = ?,
                diagnosis = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $first_name,
            $last_name,
            $gender,
            $birth_date,
            $phone,
            $email ?: null,
            $address,
            $blood_type ?: null,
            $diagnosis,
            $status,
            $id
        ]);

        $_SESSION['success'] = "Patient record updated successfully!";

        header("Location: index.php");
        exit;
    }

    // Preserve entered information if validation fails
    $patient = array_merge($patient, [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'gender' => $gender,
        'birth_date' => $birth_date,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'blood_type' => $blood_type,
        'diagnosis' => $diagnosis,
        'status' => $status
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Patient Record</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="container py-5">

    <div class="content-card">

        <div class="section-heading">
            <div>
                <h4>Edit Patient Information</h4>
                <p>Update the selected patient's record.</p>
            </div>

            <a href="index.php" class="btn btn-cancel">
                Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger custom-alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-section-title">
            Personal Information
        </div>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="row g-3">

                <div class="col-md-6">
                    <label>First Name *</label>
                    <input type="text" name="first_name"
                           class="form-control"
                           value="<?= e($patient['first_name']) ?>"
                           maxlength="100" required>
                </div>

                <div class="col-md-6">
                    <label>Last Name *</label>
                    <input type="text" name="last_name"
                           class="form-control"
                           value="<?= e($patient['last_name']) ?>"
                           maxlength="100" required>
                </div>

                <div class="col-md-4">
                    <label>Gender *</label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select Gender</option>
                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                            <option value="<?= $g ?>"
                                <?= $patient['gender'] === $g ? 'selected' : '' ?>>
                                <?= $g ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label>Date of Birth *</label>
                    <input type="date" name="birth_date"
                           class="form-control"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= e($patient['birth_date']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label>Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">Select Blood Type</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $b): ?>
                            <option value="<?= $b ?>"
                                <?= $patient['blood_type'] === $b ? 'selected' : '' ?>>
                                <?= $b ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="form-section-title mt-4">
                Contact Information
            </div>

            <div class="row g-3">

                <div class="col-md-6">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone"
                           class="form-control"
                           value="<?= e($patient['phone']) ?>"
                           maxlength="20" required>
                </div>

                <div class="col-md-6">
                    <label>Email Address</label>
                    <input type="email" name="email"
                           class="form-control"
                           value="<?= e($patient['email']) ?>"
                           maxlength="150">
                </div>

                <div class="col-md-12">
                    <label>Complete Address *</label>
                    <textarea name="address" class="form-control"
                              rows="3" required><?= e($patient['address']) ?></textarea>
                </div>

            </div>

            <div class="form-section-title mt-4">
                Medical Information
            </div>

            <div class="row g-3">

                <div class="col-md-8">
                    <label>Diagnosis *</label>
                    <input type="text" name="diagnosis"
                           class="form-control"
                           value="<?= e($patient['diagnosis']) ?>"
                           maxlength="255" required>
                </div>

                <div class="col-md-4">
                    <label>Patient Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="Active"
                            <?= $patient['status'] === 'Active' ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="Inactive"
                            <?= $patient['status'] === 'Inactive' ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn btn-primary-custom">
                    Update Patient
                </button>

                <a href="index.php" class="btn btn-cancel">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>