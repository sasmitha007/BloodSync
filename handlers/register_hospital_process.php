<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register_hospital.php');
    exit();
}

// Store form data in session in case of error
$_SESSION['form_data'] = $_POST;

try {
    $pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Validate passwords
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $_SESSION['error'] = "Passwords do not match!";
        header('Location: ../pages/register_hospital.php');
        exit();
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $_POST['email']]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Email already registered!";
        header('Location: ../pages/register_hospital.php');
        exit();
    }
    
    // Check if registration number exists
    $stmt = $pdo->prepare("SELECT id FROM hospitals WHERE registration_number = :reg_number");
    $stmt->execute(['reg_number' => $_POST['registration_number']]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Registration number already exists!";
        header('Location: ../pages/register_hospital.php');
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Create user
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, role, is_verified, verification_status) 
        VALUES (:email, :password, 'hospital', FALSE, 'pending') 
        RETURNING id
    ");
    $stmt->execute([
        'email' => $_POST['email'],
        'password' => $hashedPassword
    ]);
    $userId = $stmt->fetchColumn();
    
    // Create hospital record
    $stmt = $pdo->prepare("
        INSERT INTO hospitals (
            user_id, hospital_name, registration_number, location, 
            contact_person, contact_email, contact_phone, 
            license_number, license_expiry, is_verified, verification_status
        ) VALUES (
            :user_id, :hospital_name, :registration_number, :location,
            :contact_person, :contact_email, :contact_phone,
            :license_number, :license_expiry, FALSE, 'pending'
        )
    ");
    
    $stmt->execute([
        'user_id' => $userId,
        'hospital_name' => $_POST['hospital_name'],
        'registration_number' => $_POST['registration_number'],
        'location' => $_POST['location'],
        'contact_person' => $_POST['contact_person'],
        'contact_email' => $_POST['contact_email'],
        'contact_phone' => $_POST['contact_phone'],
        'license_number' => $_POST['license_number'] ?: null,
        'license_expiry' => $_POST['license_expiry'] ?: null
    ]);
    
    // Create admin notification
    $stmt = $pdo->prepare("
        INSERT INTO admin_notifications (
            notification_type, title, message, related_id, related_type, priority
        ) VALUES (
            'hospital_registration', 'New Hospital Registration', 
            :message, :hospital_id, 'hospital', 'high'
        )
    ");
    
    $message = "New hospital registration: " . $_POST['hospital_name'] . 
               " (" . $_POST['registration_number'] . ")";
    $stmt->execute([
        'message' => $message,
        'hospital_id' => $pdo->lastInsertId()
    ]);
    
    $pdo->commit();
    
    // Clear form data
    unset($_SESSION['form_data']);
    
    $_SESSION['success_message'] = "Hospital registration submitted successfully! Please wait for admin verification.";
    header('Location: ../pages/login.php');
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Registration failed: " . $e->getMessage();
    header('Location: ../pages/register_hospital.php');
} catch (Exception $e) {
    $_SESSION['error'] = "Registration failed: " . $e->getMessage();
    header('Location: ../pages/register_hospital.php');
}
?>