<?php
require_once __DIR__ . '/../autoload.php';

Auth::requireAuth('login.php');

$user = Auth::getUser();

// Check if user is already verified
if ($user['is_verified']) {
    header('Location: ../pages/dashboard.php');
    exit();
}

// Check file upload
if (!isset($_FILES['medical_report']) || $_FILES['medical_report']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Please select a valid file';
    header('Location: ../pages/verification.php');
    exit();
}

$file = $_FILES['medical_report'];
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 
                 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate file
if (!in_array($file['type'], $allowedTypes)) {
    $_SESSION['error'] = 'Invalid file type. Only PDF, JPG, PNG, DOC allowed.';
    header('Location: ../pages/verification.php');
    exit();
}

if ($file['size'] > $maxSize) {
    $_SESSION['error'] = 'File too large. Maximum size is 5MB.';
    header('Location: ../pages/verification.php');
    exit();
}

// Create upload directory if not exists
$uploadDir = '../uploads/medical_reports/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'report_' . $user['user_id'] . '_' . time() . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    $_SESSION['error'] = 'Failed to upload file. Please try again.';
    header('Location: ../pages/verification.php');
    exit();
}

// Save to database
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    
    // Insert medical report
    $sql = "INSERT INTO medical_reports (donor_id, user_id, file_path, file_name, file_type, file_size, status) 
            VALUES (:donor_id, :user_id, :file_path, :file_name, :file_type, :file_size, 'pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'donor_id' => $user['donor_id'],
        'user_id' => $user['user_id'],
        'file_path' => $filePath,
        'file_name' => $file['name'],
        'file_type' => $file['type'],
        'file_size' => $file['size']
    ]);
    
    // Update user verification status
    $sql = "UPDATE users SET verification_status = 'pending', verification_notes = NULL WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user['user_id']]);
    
    // Update session
    $_SESSION['verification_status'] = 'pending';
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Medical report uploaded successfully. Verification usually takes 24-48 hours.';
    header('Location: ../pages/verification.php');
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // Delete uploaded file if database fails
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    $_SESSION['error'] = 'Failed to save report: ' . $e->getMessage();
    header('Location: ../pages/verification.php');
    exit();
}
?>