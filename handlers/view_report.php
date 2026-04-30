<?php
require_once __DIR__ . '/../autoload.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die("Access denied. Please login.");
}

$user = $_SESSION;
$reportId = $_GET['id'] ?? 0;

// Get report details
$report = Database::fetch(
    "SELECT mr.*, u.id as user_id, u.role 
     FROM medical_reports mr
     JOIN users u ON mr.user_id = u.id
     WHERE mr.id = :id",
    ['id' => $reportId]
);

if (!$report) {
    die("Report not found");
}

// Check permissions - admin can view all, users can only view their own
if ($user['role'] !== 'admin' && $user['user_id'] != $report['user_id']) {
    die("Access denied. You can only view your own reports.");
}

$filePath = $report['file_path'];

if (!file_exists($filePath)) {
    die("File not found on server.");
}

// Set appropriate headers based on file type
$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

switch ($fileExtension) {
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        break;
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    default:
        // For doc files, offer download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
}

header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit();
?>