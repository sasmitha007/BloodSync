<?php
require_once '../../config/database.php';
require_once '../includes/header.php';
require_once '../includes/hospital_nav.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user info from database
$pdo = new PDO("pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user exists and is hospital
if (!$user || $user['role'] !== 'hospital') {
    header('Location: ../dashboard.php');
    exit();
}

// Get hospital info
$stmt = $pdo->prepare("
    SELECT h.*, u.verification_status, u.email as account_email
    FROM hospitals h 
    JOIN users u ON h.user_id = u.id 
    WHERE h.user_id = :user_id
");
$stmt->execute(['user_id' => $user['id']]);
$hospital = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if hospital exists
if (!$hospital) {
    // If hospital profile doesn't exist, redirect to complete registration
    header('Location: complete_hospital_profile.php');
    exit();
}

// Check verification status
$isVerified = $hospital['is_verified'] && $hospital['verification_status'] === 'approved';

// Get hospital statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
        COUNT(CASE WHEN status = 'fulfilled' THEN 1 END) as fulfilled_requests,
        SUM(CASE WHEN status = 'approved' THEN units_required ELSE 0 END) as total_units_approved
    FROM blood_requests 
    WHERE hospital_id = :hospital_id
");
$stmt->execute(['hospital_id' => $hospital['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent blood requests
$stmt = $pdo->prepare("
    SELECT * FROM blood_requests 
    WHERE hospital_id = :hospital_id 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute(['hospital_id' => $hospital['id']]);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Hospital Profile</h1>
                <p class="text-gray-600">View your hospital information and statistics</p>
            </div>
            <div>
                <a href="edit_hospital_profile.php" 
                   class="px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition inline-flex items-center">
                    <i class="ri-edit-line mr-2"></i>
                    Edit Profile
                </a>
            </div>
        </div>
        <div class="mt-4">
            <a href="hospital_dashboard.php" class="text-red-600 hover:underline">
                ← Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Verification Status Banner -->
    <?php if (!$isVerified): ?>
        <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <i class="ri-alert-line mr-2"></i>
                <span>
                    <?php if ($hospital['verification_status'] === 'pending'): ?>
                        Your hospital profile is pending verification. You cannot request blood supplies until approved.
                    <?php elseif ($hospital['verification_status'] === 'rejected'): ?>
                        Your hospital profile was rejected. 
                        <?php if (!empty($hospital['verification_note'])): ?>
                            Reason: <?php echo htmlspecialchars($hospital['verification_note']); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        Your hospital profile is under review.
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Profile Cards -->
    <div class="grid lg:grid-cols-3 gap-8 mb-8">
        <!-- Hospital Info Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Hospital Details</h2>
                    <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                        <i class="ri-hospital-line text-2xl"></i>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Hospital Name</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Registration Number</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo htmlspecialchars($hospital['registration_number']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium">License Number</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo !empty($hospital['license_number']) ? htmlspecialchars($hospital['license_number']) : '<span class="text-gray-400">Not provided</span>'; ?>
                                <?php if ($hospital['license_expiry']): ?>
                                    <span class="text-sm text-gray-500 block mt-1">
                                        Expires: <?php echo date('M d, Y', strtotime($hospital['license_expiry'])); ?>
                                        <?php if (strtotime($hospital['license_expiry']) < time()): ?>
                                            <span class="text-red-600 ml-2">(Expired)</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Contact Person</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo htmlspecialchars($hospital['contact_person']); ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Contact Email</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo !empty($hospital['contact_email']) ? htmlspecialchars($hospital['contact_email']) : '<span class="text-gray-400">Not provided</span>'; ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Contact Phone</p>
                            <p class="text-lg font-semibold text-gray-900 mt-1">
                                <?php echo htmlspecialchars($hospital['contact_phone']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Location -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-gray-500 text-sm font-medium mb-2">Location / Address</p>
                    <p class="text-gray-800">
                        <?php echo nl2br(htmlspecialchars($hospital['location'])); ?>
                    </p>
                </div>
                
                <!-- Account Information -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-gray-500 text-sm font-medium mb-2">Account Information</p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-500 text-xs">Account Email</p>
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($hospital['account_email']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Profile Created</p>
                            <p class="text-sm font-medium"><?php echo date('M d, Y', strtotime($hospital['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Last Updated</p>
                            <p class="text-sm font-medium"><?php echo date('M d, Y h:i A', strtotime($hospital['updated_at'])); ?></p>
                        </div>
                        <?php if ($isVerified && $hospital['verified_at']): ?>
                            <div>
                                <p class="text-gray-500 text-xs">Verified On</p>
                                <p class="text-sm font-medium"><?php echo date('M d, Y', strtotime($hospital['verified_at'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status & Verification Card -->
        <div class="space-y-6">
            <!-- Verification Status -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Verification Status</h3>
                
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-sm">Status</p>
                        <p class="text-xl font-bold <?php echo $isVerified ? 'text-green-600' : ($hospital['verification_status'] === 'rejected' ? 'text-red-600' : 'text-yellow-600'); ?>">
                            <?php 
                            if ($isVerified) {
                                echo 'Verified';
                            } elseif ($hospital['verification_status'] === 'rejected') {
                                echo 'Rejected';
                            } elseif ($hospital['verification_status'] === 'pending') {
                                echo 'Pending Review';
                            } else {
                                echo ucfirst($hospital['verification_status']);
                            }
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 <?php echo $isVerified ? 'bg-green-100 text-green-600' : ($hospital['verification_status'] === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600'); ?> rounded-full flex items-center justify-center">
                        <i class="ri-<?php echo $isVerified ? 'shield-check' : ($hospital['verification_status'] === 'rejected' ? 'close-circle' : 'time'); ?>-line text-2xl"></i>
                    </div>
                </div>
                
                <?php if ($isVerified): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                        <div class="flex items-center">
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            <span>Your hospital is verified and can request blood supplies</span>
                        </div>
                    </div>
                <?php elseif ($hospital['verification_status'] === 'rejected' && !empty($hospital['verification_note'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                        <p class="font-medium">Verification Note:</p>
                        <p class="mt-1"><?php echo htmlspecialchars($hospital['verification_note']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Card -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Hospital Statistics</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_requests'] ?? 0; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                            <i class="ri-file-list-line"></i>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-yellow-50 p-3 rounded-lg">
                            <p class="text-yellow-700 text-sm">Pending</p>
                            <p class="text-xl font-bold text-yellow-800"><?php echo $stats['pending_requests'] ?? 0; ?></p>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <p class="text-green-700 text-sm">Approved</p>
                            <p class="text-xl font-bold text-green-800"><?php echo $stats['approved_requests'] ?? 0; ?></p>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-blue-700 text-sm">Fulfilled</p>
                            <p class="text-xl font-bold text-blue-800"><?php echo $stats['fulfilled_requests'] ?? 0; ?></p>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg">
                            <p class="text-purple-700 text-sm">Units</p>
                            <p class="text-xl font-bold text-purple-800"><?php echo $stats['total_units_approved'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                
                <div class="space-y-3">
                    <a href="edit_hospital_profile.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-edit-line text-red-600 mr-3"></i>
                        <span>Edit Hospital Profile</span>
                    </a>
                    <?php if ($isVerified): ?>
                        <a href="hospital_dashboard.php#request-form" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                            <i class="ri-drop-line text-red-600 mr-3"></i>
                            <span>Request Blood Supply</span>
                        </a>
                    <?php endif; ?>
                    <a href="hospital_requests.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-list-check text-red-600 mr-3"></i>
                        <span>View All Requests</span>
                    </a>
                    <a href="blood_stocks.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-heart-pulse-line text-red-600 mr-3"></i>
                        <span>Check Blood Stock</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Requests Section -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Recent Blood Requests</h2>
            <a href="hospital_requests.php" class="text-red-600 hover:underline font-medium">
                View All →
            </a>
        </div>
        
        <?php if (empty($recentRequests)): ?>
            <div class="text-center py-8">
                <i class="ri-inbox-line text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No blood requests yet</p>
                <?php if ($isVerified): ?>
                    <p class="text-gray-500 text-sm mt-2">
                        <a href="hospital_dashboard.php#request-form" class="text-red-600 hover:underline">
                            Make your first blood request
                        </a>
                    </p>
                <?php else: ?>
                    <p class="text-gray-500 text-sm mt-2">You need to be verified to make requests</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blood Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Units</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Urgency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentRequests as $request): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                #<?php echo substr($request['request_number'] ?? 'REQ-' . $request['id'], -6); ?>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($request['patient_name']); ?></p>
                                    <p class="text-xs text-gray-500">Age: <?php echo $request['patient_age']; ?>, <?php echo ucfirst($request['patient_sex']); ?></p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium"><?php echo $request['blood_type']; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium"><?php echo $request['units_required']; ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $urgencyColor = match($request['urgency_level']) {
                                    'critical' => 'bg-red-100 text-red-800',
                                    'urgent' => 'bg-orange-100 text-orange-800',
                                    default => 'bg-blue-100 text-blue-800'
                                };
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $urgencyColor; ?>">
                                    <?php echo ucfirst($request['urgency_level']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $statusColor = match($request['status']) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'fulfilled' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($request['required_date'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Additional Information -->
    <div class="mt-6 grid md:grid-cols-2 gap-6">
        <!-- Profile Completion -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Profile Completion</h3>
            
            <?php
            $completion = 0;
            $totalFields = 8;
            $completedFields = 0;
            
            $fieldsToCheck = [
                'hospital_name' => $hospital['hospital_name'],
                'registration_number' => $hospital['registration_number'],
                'location' => $hospital['location'],
                'contact_person' => $hospital['contact_person'],
                'contact_phone' => $hospital['contact_phone'],
                'contact_email' => $hospital['contact_email'],
                'license_number' => $hospital['license_number'],
                'license_expiry' => $hospital['license_expiry']
            ];
            
            foreach ($fieldsToCheck as $field => $value) {
                if (!empty($value)) {
                    $completedFields++;
                }
            }
            
            $completion = round(($completedFields / $totalFields) * 100);
            ?>
            
            <div class="mb-4">
                <div class="flex justify-between mb-1">
                    <span class="text-gray-700 text-sm font-medium"><?php echo $completion; ?>% Complete</span>
                    <span class="text-gray-500 text-sm"><?php echo $completedFields; ?>/<?php echo $totalFields; ?> fields</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-600 h-2 rounded-full" style="width: <?php echo $completion; ?>%"></div>
                </div>
            </div>
            
            <div class="text-sm text-gray-600">
                <p>Complete your profile to:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Improve verification chances</li>
                    <li>Get faster response from admin</li>
                    <li>Ensure accurate communication</li>
                </ul>
                <?php if ($completion < 100): ?>
                    <a href="edit_hospital_profile.php" class="inline-block mt-3 text-red-600 hover:underline font-medium">
                        Complete your profile →
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Verification Requirements -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Verification Requirements</h3>
            
            <div class="space-y-3">
                <div class="flex items-start">
                    <i class="ri-<?php echo !empty($hospital['registration_number']) ? 'check-circle' : 'checkbox-blank-circle'; ?>-line mt-0.5 mr-3 <?php echo !empty($hospital['registration_number']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    <div>
                        <p class="font-medium">Valid Registration Number</p>
                        <p class="text-sm text-gray-500">Government-issued hospital registration</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <i class="ri-<?php echo !empty($hospital['license_number']) ? 'check-circle' : 'checkbox-blank-circle'; ?>-line mt-0.5 mr-3 <?php echo !empty($hospital['license_number']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    <div>
                        <p class="font-medium">Medical License (Optional)</p>
                        <p class="text-sm text-gray-500">Hospital operating license</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <i class="ri-<?php echo !empty($hospital['contact_person']) && !empty($hospital['contact_phone']) ? 'check-circle' : 'checkbox-blank-circle'; ?>-line mt-0.5 mr-3 <?php echo !empty($hospital['contact_person']) && !empty($hospital['contact_phone']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    <div>
                        <p class="font-medium">Contact Information</p>
                        <p class="text-sm text-gray-500">Designated contact person with phone</p>
                    </div>
                </div>
                
                <div class="flex items-start">
                    <i class="ri-<?php echo !empty($hospital['location']) ? 'check-circle' : 'checkbox-blank-circle'; ?>-line mt-0.5 mr-3 <?php echo !empty($hospital['location']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    <div>
                        <p class="font-medium">Physical Address</p>
                        <p class="text-sm text-gray-500">Complete hospital location details</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>