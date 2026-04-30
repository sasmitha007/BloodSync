<?php
require_once '../../config/database.php';
require_once '../includes/header.php';
require_once '../includes/hospital_nav.php';  // Use the main nav, not hospital_nav

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
    SELECT h.*, u.verification_status 
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

// Check if hospital is verified
$isVerified = $hospital['is_verified'] && $hospital['verification_status'] === 'approved';

// Get pending requests count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count 
    FROM blood_requests 
    WHERE hospital_id = :hospital_id AND status = 'pending'
");
$stmt->execute(['hospital_id' => $hospital['id']]);
$pendingRequests = $stmt->fetchColumn();

// Get approved requests count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as approved_count 
    FROM blood_requests 
    WHERE hospital_id = :hospital_id AND status = 'approved'
");
$stmt->execute(['hospital_id' => $hospital['id']]);
$approvedRequests = $stmt->fetchColumn();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Hospital Dashboard</h1>
        <p class="text-gray-600">Welcome, <?php echo htmlspecialchars($hospital['hospital_name']); ?></p>
        <p class="text-gray-500 text-sm">Logged in as: <?php echo htmlspecialchars($user['email']); ?></p>
        
        <?php if (!$isVerified): ?>
            <div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="ri-alert-line mr-2"></i>
                    <span>Your account is pending verification. You cannot request blood supplies until approved by admin.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?php echo $_SESSION['success_message']; ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <?php echo $_SESSION['error_message']; ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <!-- Hospital Info Card -->
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 text-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-blue-200 text-sm">Hospital</p>
                    <h3 class="text-2xl font-bold mt-2"><?php echo htmlspecialchars($hospital['hospital_name']); ?></h3>
                    <p class="text-blue-200 text-sm mt-2"><?php echo htmlspecialchars($hospital['location']); ?></p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="ri-hospital-line text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Verification Status Card -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm">Verification Status</p>
                    <h3 class="text-2xl font-bold mt-2 <?php echo $isVerified ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <?php echo $isVerified ? 'Verified' : 'Pending'; ?>
                    </h3>
                    <p class="text-gray-500 text-sm mt-1">Reg: <?php echo htmlspecialchars($hospital['registration_number']); ?></p>
                </div>
                <div class="w-12 h-12 <?php echo $isVerified ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'; ?> rounded-full flex items-center justify-center">
                    <i class="ri-<?php echo $isVerified ? 'shield-check' : 'time'; ?>-line text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm">Blood Requests</p>
                    <div class="flex items-center mt-2 space-x-4">
                        <div>
                            <h3 class="text-2xl font-bold text-blue-600"><?php echo $pendingRequests; ?></h3>
                            <p class="text-gray-500 text-xs">Pending</p>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-green-600"><?php echo $approvedRequests; ?></h3>
                            <p class="text-gray-500 text-xs">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                    <i class="ri-heart-pulse-line text-2xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Request Blood Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Request Blood Supply</h2>
                
                <?php if ($isVerified): ?>
                    <form action="../../handlers/request_blood_process.php" method="POST">
                        <div class="grid md:grid-cols-2 gap-4">
                            <!-- Patient Name -->
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 font-medium mb-2">Patient Name *</label>
                                <input type="text" name="patient_name" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                       placeholder="John Doe">
                            </div>
                            
                            <!-- Age -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Age *</label>
                                <input type="number" name="patient_age" min="0" max="120" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                       placeholder="30">
                            </div>
                            
                            <!-- Sex -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Sex *</label>
                                <select name="patient_sex" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <!-- Ward -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Ward/Department *</label>
                                <input type="text" name="patient_ward" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                       placeholder="ICU/Ward 5">
                            </div>
                            
                            <!-- Blood Type -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Blood Type Required *</label>
                                <select name="blood_type" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                                    <option value="">Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            
                            <!-- Units Required -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Units Required *</label>
                                <input type="number" name="units_required" min="1" max="10" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                       placeholder="2">
                            </div>
                            
                            <!-- Required Date -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Required Date *</label>
                                <input type="date" name="required_date" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <!-- Urgency Level -->
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Urgency Level</label>
                                <select name="urgency_level" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                                    <option value="normal">Normal</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            
                            <!-- Reason -->
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 font-medium mb-2">Reason for Request</label>
                                <textarea name="reason" rows="3" 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                          placeholder="Brief description of why blood is needed..."></textarea>
                            </div>
                            
                            <!-- Hidden hospital_id -->
                            <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition duration-300">
                                Submit Blood Request
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="ri-user-search-line text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">Your hospital account needs to be verified before you can request blood supplies.</p>
                        <p class="text-gray-500 text-sm mt-2">Please wait for admin approval.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Requests -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Blood Requests</h2>
                
                <?php
                $stmt = $pdo->prepare("
                    SELECT br.*, bs.units_available 
                    FROM blood_requests br
                    LEFT JOIN blood_stocks bs ON br.blood_type = bs.blood_type
                    WHERE br.hospital_id = :hospital_id 
                    ORDER BY br.created_at DESC 
                    LIMIT 5
                ");
                $stmt->execute(['hospital_id' => $hospital['id']]);
                $recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($recentRequests)): ?>
                    <p class="text-gray-500 text-center py-8">No blood requests yet</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blood Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Units</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($recentRequests as $request): ?>
                                <tr>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="font-medium"><?php echo $request['blood_type']; ?></span>
                                        <?php if ($request['units_available'] < $request['units_required']): ?>
                                            <span class="text-xs text-yellow-600 ml-1">(Low stock)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3"><?php echo $request['units_required']; ?></td>
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
                    <div class="mt-4 text-center">
                        <a href="hospital_requests.php" class="text-red-600 font-medium hover:underline">View all requests →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Hospital Information -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Hospital Information</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-gray-500 text-sm">Registration Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hospital['registration_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Contact Person</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hospital['contact_person']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Contact Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hospital['contact_email']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Contact Phone</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hospital['contact_phone']); ?></p>
                    </div>
                    <?php if ($hospital['license_number']): ?>
                    <div>
                        <p class="text-gray-500 text-sm">License Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($hospital['license_number']); ?></p>
                        <?php if ($hospital['license_expiry']): ?>
                        <p class="text-gray-500 text-xs">
                            Expires: <?php echo date('M d, Y', strtotime($hospital['license_expiry'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-6">
                    <a href="hospital_profile.php" class="text-red-600 font-medium hover:underline">Edit Hospital Info →</a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Links</h2>
                <div class="space-y-3">
                    <a href="hospital_requests.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-list-check text-red-600 mr-3"></i>
                        <span>View All Requests</span>
                    </a>
                    <a href="hospital_profile.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-building-2-line text-red-600 mr-3"></i>
                        <span>Hospital Profile</span>
                    </a>
                    <a href="blood_stocks.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                        <i class="ri-drop-line text-red-600 mr-3"></i>
                        <span>Check Blood Stock</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>