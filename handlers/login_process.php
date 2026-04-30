<?php
require_once __DIR__ . '/../autoload.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['login_error'] = 'Invalid request method';
    header('Location: ../pages/login.php');
    exit();
}

// Get form data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Email and password are required';
    header('Location: ../pages/login.php');
    exit();
}

// Attempt login
$result = Auth::login($email, $password);

if ($result['success']) {
    // Get user info
    $user = Auth::getUser();
    
    // Redirect based on role and verification status
    if ($user['role'] === 'admin') {
        header('Location: ../pages/admin/verify_reports.php');
        exit();
    } else {
        if ($user['is_verified']) {
            header('Location: ../pages/dashboard.php');
            exit();
        } else {
            header('Location: ../pages/verification.php');
            exit();
        }
    }
} else {
    $_SESSION['login_error'] = $result['message'];
    header('Location: ../pages/login.php');
    exit();
}
?>