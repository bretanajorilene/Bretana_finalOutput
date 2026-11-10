
<?php
session_start();
require 'db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh the page.');
    }

    $data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'gender'     => $_POST['gender'] ?? '',
        'birth_date' => $_POST['birth_date'] ?? '',
        'phone'      => trim($_POST['phone'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'address'    => trim($_POST['address'] ?? ''),
        'blood_type' => $_POST['blood_type'] ?? '',
        'diagnosis'  => trim($_POST['diagnosis'] ?? ''),
        'status'     => $_POST['status'] ?? 'Active'
    ];

    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birth_date'])
        && checkdate(
            (int)substr($data['birth_date'], 5, 2),
            (int)substr($data['birth_date'], 8, 2),
            (int)substr($data['birth_date'], 0, 4)
        )
        && $data['birth_date'] <= date('Y-m-d');

    if (
        !$data['first_name'] || !$data['last_name'] ||
        !$data['phone'] || !$data['address'] ||
        !$data['diagnosis'] || !$validDate ||
        !in_array($data['gender'], ['Male', 'Female', 'Other'], true) ||
        !in_array($data['blood_type'], ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true) ||
        !in_array($data['status'], ['Active', 'Inactive'], true)
    ) {
        $error = 'Please complete all required fields correctly.';
    } elseif ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO patients
            (first_name, last_name, gender, birth_date, phone, email,
             address, blood_type, diagnosis, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['gender'],
            $data['birth_date'], $data['phone'], $data['email'] ?: null,
            $data['address'], $data['blood_type'] ?: null,
            $data['diagnosis'], $data['status']
        ]);

        $_SESSION['success'] = 'Patient registered successfully!';
        header('Location: index.php');
        exit;
    }
}

// SEARCH PATIENT USING COMPLETE NAME
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM patients
        WHERE CONCAT(first_name, ' ', last_name) = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$search]);
} else {
    $stmt = $pdo->query("
        SELECT * FROM patients
        ORDER BY id DESC
    ");
}

$patients = $stmt->fetchAll();

// DASHBOARD STATISTICS
$totalPatients = $pdo->query("
    SELECT COUNT(*) FROM patients
")->fetchColumn();

$activePatients = $pdo->query("
    SELECT COUNT(*) FROM patients
    WHERE status = 'Active'
")->fetchColumn();

$inactivePatients = $pdo->query("
    SELECT COUNT(*) FROM patients
    WHERE status = 'Inactive'
")->fetchColumn();

$old = $_POST;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>CarePlus | Patient Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="brand">
        <h3>Care<span>Plus</span></h3>
        <small>Patient Record System</small>
    </div>

    <nav>
        <a class="menu-link active" href="index.php">▦ Dashboard</a>
        <a class="menu-link" href="#patient-form">✚ Add Patient</a>
        <a class="menu-link" href="#records">▤ Patient Records</a>
    </nav>

    <div class="admin">
        <div class="admin-avatar">AD</div>
        <div>
            <strong>Jorilene Bretana</strong>
            <small>Administrator</small>
        </div>
    </div>

</div>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- HEADER -->
    <header class="topbar">
        <div>
            <h2>Patient Dashboard</h2>
            <p>Manage and monitor your patient records.</p>
        </div>

        <span class="online">● System Online</span>
    </header>

    <!-- SUCCESS MESSAGE -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGE -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- DASHBOARD STATISTICS -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="stat-card">
                <span class="stat-icon blue">♙</span>
                <div>
                    <p>Total Patients</p>
                    <h2><?= e($totalPatients) ?></h2>
                    <small>All registered patients</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <span class="stat-icon green">✓</span>
                <div>
                    <p>Active Patients</p>
                    <h2><?= e($activePatients) ?></h2>
                    <small>Currently active</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <span class="stat-icon orange">◷</span>
                <div>
                    <p>Inactive Patients</p>
                    <h2><?= e($inactivePatients) ?></h2>
                    <small>Inactive records</small>
                </div>
            </div>
        </div>

    </div>

    <!-- PATIENT REGISTRATION -->
    <section class="content-card" id="patient-form">

        <div class="section-heading">
            <div>
                <h4>Register New Patient</h4>
                <p>Fill in the patient information below.</p>
            </div>

            <span class="section-icon">✚</span>
        </div>

        <form method="POST" action="index.php">

            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <!-- PERSONAL INFORMATION -->
            <h6 class="form-title">Personal Information</h6>

            <div class="row g-3">

                <div class="col-md-6">
                    <label>First Name *</label>
                    <input class="form-control"
                           name="first_name"
                           maxlength="100"
                           placeholder="First name"
                           value="<?= e($old['first_name'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label>Last Name *</label>
                    <input class="form-control"
                           name="last_name"
                           maxlength="100"
                           placeholder="Last name"
                           value="<?= e($old['last_name'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4">
                    <label>Gender *</label>
                    <select class="form-select" name="gender" required>
                        <option value="">Select Gender</option>

                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                            <option value="<?= $g ?>"
                                <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                <?= $g ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-4">
                    <label>Date of Birth *</label>
                    <input type="date"
                           class="form-control"
                           name="birth_date"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= e($old['birth_date'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4">
                    <label>Blood Type</label>
                    <select class="form-select" name="blood_type">
                        <option value="">Select Blood Type</option>

                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $b): ?>
                            <option value="<?= $b ?>"
                                <?= ($old['blood_type'] ?? '') === $b ? 'selected' : '' ?>>
                                <?= $b ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <!-- CONTACT INFORMATION -->
            <h6 class="form-title mt-4">Contact Information</h6>

            <div class="row g-3">

                <div class="col-md-6">
                    <label>Phone Number *</label>
                    <input type="tel"
                           class="form-control"
                           name="phone"
                           maxlength="20"
                           placeholder="Phone number"
                           value="<?= e($old['phone'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label>Email Address</label>
                    <input type="email"
                           class="form-control"
                           name="email"
                           maxlength="150"
                           placeholder="example@email.com"
                           value="<?= e($old['email'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label>Complete Address *</label>
                    <textarea class="form-control"
                              name="address"
                              rows="2"
                              placeholder="Complete address"
                              required><?= e($old['address'] ?? '') ?></textarea>
                </div>

            </div>

            <!-- MEDICAL INFORMATION -->
            <h6 class="form-title mt-4">Medical Information</h6>

            <div class="row g-3">

                <div class="col-md-8">
                    <label>Diagnosis *</label>
                    <input class="form-control"
                           name="diagnosis"
                           maxlength="255"
                           placeholder="Enter diagnosis"
                           value="<?= e($old['diagnosis'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4">
                    <label>Patient Status *</label>
                    <select class="form-select" name="status" required>
                        <option value="Active"
                            <?= ($old['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="Inactive"
                            <?= ($old['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </div>

            </div>

            <!-- FORM BUTTONS -->
            <div class="form-actions">

                <button class="btn btn-primary-custom" type="submit">
                    + Register Patient
                </button>

                <a href="index.php" class="btn btn-secondary-custom">
                    Clear Form
                </a>

            </div>

        </form>

    </section>

    <!-- PATIENT RECORDS -->
    <section class="content-card mt-4" id="records">

        <div class="section-heading">
            <div>
                <h4>Patient Records</h4>
                <p>Click the button to view and manage registered patients.</p>
            </div>

            <span class="record-count">
                <?= count($patients) ?> Records
            </span>
        </div>

        <!-- SHOW / HIDE TABLE BUTTON -->
        <button class="btn btn-primary-custom mb-3"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#patientTable"
                aria-expanded="false"
                aria-controls="patientTable">

            <span id="tableButtonText">▤ See Patient Records</span>

        </button>

        <!-- HIDDEN TABLE -->
        <div class="collapse" id="patientTable">

            <!-- SEARCH BY COMPLETE NAME -->
            <form method="GET" class="search-form mb-3">

                <input type="text"
                       class="form-control"
                       name="search"
                       placeholder="Enter complete patient name (e.g. Juan Dela Cruz)..."
                       value="<?= e($search) ?>">

                <button class="btn btn-primary-custom" type="submit">
                    Search
                </button>

                <a href="index.php#records" class="btn btn-secondary-custom">
                    Reset
                </a>

            </form>

            <!-- TABLE -->
            <div class="table-responsive">

                <table class="table custom-table align-middle">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient Name</th>
                            <th>Gender</th>
                            <th>Birth Date</th>
                            <th>Phone</th>
                            <th>Blood</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($patients): ?>

                        <?php foreach ($patients as $p): ?>

                            <tr>

                                <td>
                                    <span class="patient-id">
                                        #<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                                    </strong>

                                    <small class="d-block text-muted">
                                        <?= e($p['email'] ?: 'No email') ?>
                                    </small>
                                </td>

                                <td><?= e($p['gender']) ?></td>

                                <td><?= e($p['birth_date']) ?></td>

                                <td><?= e($p['phone']) ?></td>

                                <td>
                                    <span class="blood-badge">
                                        <?= e($p['blood_type'] ?: 'N/A') ?>
                                    </span>
                                </td>

                                <td><?= e($p['diagnosis']) ?></td>

                                <td>
                                    <span class="status-badge <?= $p['status'] === 'Active' ? 'active' : 'inactive' ?>">
                                        <?= e($p['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="action-buttons">

                                        <a class="btn btn-edit"
                                           href="edit.php?id=<?= (int)$p['id'] ?>">
                                            Edit
                                        </a>

                                        <form action="delete.php"
                                              method="POST"
                                              onsubmit="return confirm('Delete this patient record?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$p['id'] ?>">

                                            <button class="btn btn-delete" type="submit">
                                                Delete
                                            </button>

                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="9" class="text-center py-4">
                                No patient records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    <!-- FOOTER -->
    <footer class="footer">
        Patient Record Management System © <?= date('Y') ?>
        <small>By: Jorilene Bretana</small>
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const table = document.getElementById('patientTable');
const buttonText = document.getElementById('tableButtonText');

table.addEventListener('show.bs.collapse', () => {
    buttonText.textContent = '▤ Hide Patient Records';
});

table.addEventListener('hide.bs.collapse', () => {
    buttonText.textContent = '▤ See Patient Records';
});

// Automatically show the table after searching
<?php if ($search !== ''): ?>
new bootstrap.Collapse(table, {toggle: true});
<?php endif; ?>
</script>

</body>
</html>
