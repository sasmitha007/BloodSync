<?php
require_once __DIR__ . '/../autoload.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ../pages/register.php');
    exit();
}

// Get form data
$data = [
    'fullname' => trim($_POST['fullname'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'nic' => trim($_POST['nic'] ?? ''),
    'blood_type' => $_POST['blood_type'] ?? '',
    'dob' => $_POST['dob'] ?? '',
    'password' => $_POST['password'] ?? '',
    'address' => $_POST['address'] ?? '',
    'city' => $_POST['city'] ?? 'Colombo',
    'weight' => $_POST['weight'] ?? 50.0
];

// Validation
$errors = [];

// Required fields
$required = ['fullname', 'email', 'phone', 'nic', 'blood_type', 'dob', 'password'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
    }
}

// Email validation
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Password validation
if (strlen($data['password']) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}

// NIC validation (Sri Lankan)
if (!preg_match('/^[0-9]{9}[vVxX]?$/', $data['nic']) && !preg_match('/^[0-9]{12}$/', $data['nic'])) {
    $errors[] = 'Invalid NIC format';
}

// Age validation
$dob = new DateTime($data['dob']);
$today = new DateTime();
$age = $today->diff($dob)->y;
if ($age < 18) {
    $errors[] = 'You must be at least 18 years old';
}

// Weight validation
if ($data['weight'] < 40) {
    $errors[] = 'Minimum weight is 40kg';
}

// If there are errors
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    $_SESSION['form_data'] = $data;
    header('Location: ../pages/register.php');
    exit();
}

// Add confirm password to data
$data['confirm_password'] = $_POST['confirm_password'] ?? '';

// Check password confirmation
if ($data['password'] !== $data['confirm_password']) {
    $_SESSION['error'] = 'Passwords do not match';
    $_SESSION['form_data'] = $data;
    header('Location: ../pages/register.php');
    exit();
}

// Attempt registration
$result = Auth::register($data);

if ($result['success']) {
    // Registration successful - redirect to verification page
    header('Location: ../pages/verification.php');
    exit();
} else {
    $_SESSION['error'] = $result['message'];
    $_SESSION['form_data'] = $data;
    header('Location: ../pages/register.php');
    exit();
}
?>