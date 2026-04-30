<?php
require_once '../config/database.php';
require_once __DIR__ . '/../autoload.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

Auth::requireAuth('login.php');

$user = Auth::getUser();

// Get verification status from user data
$verificationStatus = [
    'verification_status' => $user['verification_status'] ?? 'pending',
    'verification_notes' => $user['verification_notes'] ?? null, // Changed to verification_notes
    'rejection_reason' => $user['rejection_reason'] ?? null
];

// Check if user is a donor and get donor ID
$donorInfo = Database::fetch(
    "SELECT id FROM donors WHERE user_id = :user_id",
    ['user_id' => $user['id']] // Changed from user['user_id'] to user['id']
);

if ($donorInfo) {
    $donorId = $donorInfo['id'];
    
    // Check if donor has submitted a report - FIXED JOIN
    $existingReport = Database::fetch(
        "SELECT mr.*, u.verification_status, u.is_verified, u.verification_notes, u.verified_at
         FROM medical_reports mr
         JOIN donors d ON mr.donor_id = d.id  -- FIXED: Join with donors table
         JOIN users u ON d.user_id = u.id      -- FIXED: Then join with users table
         WHERE mr.donor_id = :donor_id 
         ORDER BY mr.uploaded_at DESC LIMIT 1",
        ['donor_id' => $donorId]  // Changed from user_id to donor_id
    );
} else {
    // User is not a donor (should not happen for donors)
    $existingReport = false;
}

if ($existingReport) {
    $hasReport = true;
    $reportStatus = $existingReport['status'];
    $fileName = basename($existingReport['file_path']); // Get filename from path
    $createdAt = $existingReport['uploaded_at'];
    $verificationNote = $existingReport['verification_notes'];
    $verifiedAt = $existingReport['verified_at'];
} else {
    $hasReport = false;
    $reportStatus = 'none';
}

// Check if user is already verified and redirect to dashboard
if ($verificationStatus['verification_status'] == 'approved') {
    header('Location: dashboard.php');
    exit();
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <i class="ri-error-warning-line text-xl mr-3 mt-0.5"></i>
                    <div>
                        <p class="font-bold">Error</p>
                        <p><?php echo $_SESSION['error']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <i class="ri-checkbox-circle-line text-xl mr-3 mt-0.5"></i>
                    <div>
                        <p class="font-bold">Success!</p>
                        <p><?php echo $_SESSION['success_message']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Verification Status Card -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <div class="text-center mb-8">
                <?php if ($verificationStatus['verification_status'] == 'approved'): ?>
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center bg-green-100 text-green-600">
                        <i class="ri-check-line text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Verified Donor</h2>
                    <p class="text-gray-600">Your account has been verified. Thank you!</p>
                    
                <?php elseif ($verificationStatus['verification_status'] == 'rejected'): ?>
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center bg-red-100 text-red-600">
                        <i class="ri-close-line text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Verification Required</h2>
                    <p class="text-gray-600">
                        <?php echo $verificationStatus['rejection_reason'] ?: ($verificationStatus['verification_notes'] ?: 'Please upload a valid medical report'); ?>
                    </p>
                    
                <?php else: ?>
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center bg-yellow-100 text-yellow-600">
                        <i class="ri-time-line text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Pending Verification</h2>
                    <p class="text-gray-600">Please upload your medical report to complete verification</p>
                <?php endif; ?>
            </div>
            
            <?php if ($verificationStatus['verification_status'] != 'approved'): ?>
                <!-- Upload Form -->
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-red-400 transition">
                    <form id="uploadForm" action="../handlers/upload_medical_report.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="donor_id" value="<?php echo $donorId ?? ''; ?>">
                        <input type="file" name="medical_report" id="medical_report" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="hidden" required>
                        
                        <div class="mb-6">
                            <i class="ri-file-upload-line text-5xl text-gray-400 mb-4"></i>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Upload Medical Report</h3>
                            <p class="text-gray-600 mb-2">Supported formats: PDF, JPG, PNG, DOC, DOCX</p>
                            <p class="text-sm text-gray-500">Maximum file size: 5MB</p>
                        </div>
                        
                        <label for="medical_report" class="inline-block cursor-pointer mb-4">
                            <div class="bg-red-600 hover:bg-red-700 text-white font-medium px-8 py-3 rounded-lg transition inline-flex items-center">
                                <i class="ri-folder-open-line mr-2"></i> Choose File
                            </div>
                        </label>
                        
                        <div id="fileName" class="mt-4 text-gray-700 font-medium"></div>
                        <div id="fileSize" class="text-sm text-gray-500"></div>
                        
                        <button type="submit" id="submitBtn" class="mt-6 w-full bg-red-600 hover:bg-red-700 text-white font-medium py-3 rounded-lg transition hidden">
                            <span id="submitText">Submit for Verification</span>
                            <span id="submitLoading" class="hidden">
                                <i class="ri-loader-4-line animate-spin mr-2"></i>Uploading...
                            </span>
                        </button>
                    </form>
                </div>
                
                <!-- Report Requirements -->
                <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
                    <h4 class="font-bold text-blue-800 mb-3 flex items-center">
                        <i class="ri-information-line mr-2"></i> Report Requirements:
                    </h4>
                    <ul class="space-y-2 text-blue-700">
                        <li class="flex items-start">
                            <i class="ri-check-line mt-1 mr-2"></i>
                            <span>Must include blood test results</span>
                        </li>
                        <li class="flex items-start">
                            <i class="ri-check-line mt=1 mr-2"></i>
                            <span>Clear indication of blood type</span>
                        </li>
                        <li class="flex items-start">
                            <i class="ri-check-line mt-1 mr-2"></i>
                            <span>Recent report (within 6 months)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="ri-check-line mt-1 mr-2"></i>
                            <span>Issued by a recognized medical center</span>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Existing Report Status -->
            <?php if ($hasReport): ?>
                <div class="mt-8 bg-gray-50 rounded-xl p-6">
                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="ri-file-history-line mr-2"></i> Current Report Status
                    </h4>
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <p class="font-medium text-gray-900">
                                <i class="ri-file-line mr-2"></i><?php echo htmlspecialchars($fileName); ?>
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="ri-calendar-line mr-1"></i>
                                Uploaded: <?php echo date('F j, Y g:i A', strtotime($createdAt)); ?>
                            </p>
                        </div>
                        <div>
                            <?php
                            $statusColor = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800'
                            ];
                            ?>
                            <span class="px-4 py-2 rounded-full text-sm font-medium <?php echo $statusColor[$reportStatus] ?? 'bg-gray-100'; ?>">
                                <?php echo ucfirst($reportStatus); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($reportStatus == 'rejected' && ($verificationNote || $verificationStatus['rejection_reason'])): ?>
                        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <h5 class="font-bold text-red-800 mb-2 flex items-center">
                                <i class="ri-alert-line mr-2"></i> Admin Feedback
                            </h5>
                            <p class="text-red-700">
                                <?php echo htmlspecialchars($verificationStatus['rejection_reason'] ?? $verificationNote); ?>
                            </p>
                        </div>
                    <?php elseif ($reportStatus == 'approved' && $verifiedAt): ?>
                        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <h5 class="font-bold text-green-800 mb-2 flex items-center">
                                <i class="ri-check-double-line mr-2"></i> Verified Successfully
                            </h5>
                            <p class="text-green-700">Your medical report has been approved by our admin team on <?php echo date('F j, Y', strtotime($verifiedAt)); ?>.</p>
                        </div>
                    <?php elseif ($reportStatus == 'pending'): ?>
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h5 class="font-bold text-yellow-800 mb-2 flex items-center">
                                <i class="ri-time-line mr-2"></i> Under Review
                            </h5>
                            <p class="text-yellow-700">Your report is currently being reviewed by our admin team.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($verificationStatus['verification_status'] == 'approved'): ?>
                <div class="mt-8 text-center">
                    <a href="dashboard.php" 
                       class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-lg transition">
                        <i class="ri-dashboard-line mr-2"></i> Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- FAQ Section -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="ri-question-line mr-3 text-red-600"></i> Frequently Asked Questions
            </h3>
            <div class="space-y-6">
                <div class="border-b pb-6">
                    <h4 class="font-bold text-gray-900 text-lg mb-2">Why do I need to upload a medical report?</h4>
                    <p class="text-gray-600">To ensure the safety of both donors and recipients, we verify blood types through official medical documents.</p>
                </div>
                
                <div class="border-b pb-6">
                    <h4 class="font-bold text-gray-900 text-lg mb-2">How long does verification take?</h4>
                    <p class="text-gray-600">Usually 24-48 hours during business days. You'll receive a notification once verified.</p>
                </div>
                
                <div class="border-b pb-6">
                    <h4 class="font-bold text-gray-900 text-lg mb-2">What if my report gets rejected?</h4>
                    <p class="text-gray-600">You'll receive specific feedback and can upload a corrected report immediately.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// File upload handling
document.getElementById('medical_report').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fileNameDiv = document.getElementById('fileName');
    const fileSizeDiv = document.getElementById('fileSize');
    const submitBtn = document.getElementById('submitBtn');
    
    if (file) {
        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            alert('File is too large. Maximum size is 5MB.');
            this.value = ''; // Clear the file input
            fileNameDiv.textContent = '';
            fileSizeDiv.textContent = '';
            submitBtn.classList.add('hidden');
            return;
        }
        
        // Show file info
        fileNameDiv.textContent = file.name;
        fileSizeDiv.textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`;
        submitBtn.classList.remove('hidden');
    } else {
        fileNameDiv.textContent = '';
        fileSizeDiv.textContent = '';
        submitBtn.classList.add('hidden');
    }
});

// Form submission loading
document.getElementById('uploadForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitLoading = document.getElementById('submitLoading');
    
    submitText.classList.add('hidden');
    submitLoading.classList.remove('hidden');
    submitBtn.disabled = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>