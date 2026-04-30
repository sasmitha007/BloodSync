<?php
require_once __DIR__ . '/../autoload.php';
require_once  'MedicalReport.php';

Auth::requireAuth('login.php');

$user = Auth::getUser();
$profile = Auth::getDonorProfile();
$medicalReport = new MedicalReport();

$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$report = $medicalReport->getById($report_id, $profile['id']);

if (!$report) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$file_path = __DIR__ . '/../uploads/medical_reports/' . $report['file_path'];

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// Get file extension
$extension = pathinfo($file_path, PATHINFO_EXTENSION);

// Set appropriate headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($file_path);
exit;