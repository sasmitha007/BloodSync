<?php
// request_blood_process.php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

// Check if hospital is verified
$pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->prepare("
    SELECT h.*, u.is_verified 
    FROM hospitals h 
    JOIN users u ON h.user_id = u.id 
    WHERE h.user_id = :user_id
");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hospital || !$hospital['is_verified']) {
    $_SESSION['error_message'] = "Hospital not verified. Please wait for admin approval.";
    header('Location: ../pages/hospital/hospital_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $patientName = trim($_POST['patient_name'] ?? '');
    $patientAge = intval($_POST['patient_age'] ?? 0);
    $patientSex = trim($_POST['patient_sex'] ?? '');
    $patientWard = trim($_POST['patient_ward'] ?? '');
    $bloodType = trim($_POST['blood_type'] ?? '');
    $unitsRequired = intval($_POST['units_required'] ?? 0);
    $requiredDate = trim($_POST['required_date'] ?? '');
    $urgencyLevel = trim($_POST['urgency_level'] ?? 'normal');
    $reason = trim($_POST['reason'] ?? '');
    $hospitalId = intval($_POST['hospital_id'] ?? 0);

    // Validate inputs
    if (empty($patientName) || empty($bloodType) || $unitsRequired < 1 || empty($requiredDate)) {
        $_SESSION['error_message'] = "Please fill in all required fields.";
        header('Location: ../pages/hospital/hospital_dashboard.php');
        exit();
    }

    // Check valid blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (!in_array($bloodType, $validBloodTypes)) {
        $_SESSION['error_message'] = "Invalid blood type selected.";
        header('Location: ../pages/hospital/hospital_dashboard.php');
        exit();
    }

    // Check future date
    if (strtotime($requiredDate) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = "Required date must be today or in the future.";
        header('Location: ../pages/hospital/hospital_dashboard.php');
        exit();
    }

    try {
        // Generate request number
        $requestNumber = 'BR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert blood request
        $stmt = $pdo->prepare("
            INSERT INTO blood_requests (
                request_number, hospital_id, patient_name, patient_age, 
                patient_sex, patient_ward, blood_type, units_required, 
                required_date, urgency_level, reason, status, created_at
            ) VALUES (
                :request_number, :hospital_id, :patient_name, :patient_age,
                :patient_sex, :patient_ward, :blood_type, :units_required,
                :required_date, :urgency_level, :reason, 'pending', CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            'request_number' => $requestNumber,
            'hospital_id' => $hospitalId,
            'patient_name' => $patientName,
            'patient_age' => $patientAge,
            'patient_sex' => $patientSex,
            'patient_ward' => $patientWard,
            'blood_type' => $bloodType,
            'units_required' => $unitsRequired,
            'required_date' => $requiredDate,
            'urgency_level' => $urgencyLevel,
            'reason' => $reason
        ]);
        
        $requestId = $pdo->lastInsertId();
        
        // Create notification for admin
        $stmt = $pdo->prepare("
            INSERT INTO admin_notifications (
                notification_type, title, message, related_id, related_type, priority
            ) VALUES (
                'new_blood_request', 'New Blood Request',
                :message,
                :request_id, 'blood_request', :priority
            )
        ");
        
        $message = "New blood request #" . $requestNumber . " from " . $hospital['hospital_name'] . 
                   " for " . $unitsRequired . " units of " . $bloodType;
        
        $priority = $urgencyLevel === 'critical' ? 'high' : ($urgencyLevel === 'urgent' ? 'medium' : 'low');
        
        $stmt->execute([
            'message' => $message,
            'request_id' => $requestId,
            'priority' => $priority
        ]);
        
        $_SESSION['success_message'] = "Blood request submitted successfully! Request #: " . $requestNumber;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Failed to submit request: " . $e->getMessage();
    }
    
    header('Location: ../pages/hospital/hospital_dashboard.php');
    exit();
}

header('Location: ../pages/hospital/hospital_dashboard.php');
exit();
?>