
<?php
session_start();
require 'db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Verify CSRF token
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die("Invalid request. Please refresh the page.");
}

// Get patient ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['success'] = "Invalid patient record.";
    header("Location: index.php");
    exit;
}

// Check if patient exists
$stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$stmt->execute([$id]);

if (!$stmt->fetch()) {
    $_SESSION['success'] = "Patient record not found.";
    header("Location: index.php");
    exit;
}

// DELETE PATIENT
$stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Patient record deleted successfully!";

// Return to dashboard
header("Location: index.php");
exit;
?>