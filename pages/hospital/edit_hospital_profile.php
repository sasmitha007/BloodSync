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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $hospital_name = $_POST['hospital_name'] ?? '';
        $registration_number = $_POST['registration_number'] ?? '';
        $location = $_POST['location'] ?? '';
        $contact_person = $_POST['contact_person'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        $contact_phone = $_POST['contact_phone'] ?? '';
        $license_number = $_POST['license_number'] ?? '';
        $license_expiry = $_POST['license_expiry'] ?? '';
        
        // Basic validation
        $errors = [];
        
        if (empty($hospital_name)) {
            $errors[] = "Hospital name is required";
        }
        
        if (empty($registration_number)) {
            $errors[] = "Registration number is required";
        }
        
        if (empty($location)) {
            $errors[] = "Location is required";
        }
        
        if (empty($contact_person)) {
            $errors[] = "Contact person is required";
        }
        
        if (empty($contact_phone)) {
            $errors[] = "Contact phone is required";
        }
        
        if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if registration number is unique (excluding current hospital)
        if (!empty($registration_number)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM hospitals 
                WHERE registration_number = :reg_num AND id != :hospital_id
            ");
            $stmt->execute([
                'reg_num' => $registration_number,
                'hospital_id' => $hospital['id']
            ]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Registration number already exists";
            }
        }
        
        // If no errors, update database
        if (empty($errors)) {
            $pdo->beginTransaction();
            
            try {
                // Update hospital table ONLY - do NOT update user email
                $stmt = $pdo->prepare("
                    UPDATE hospitals SET
                        hospital_name = :hospital_name,
                        registration_number = :registration_number,
                        location = :location,
                        contact_person = :contact_person,
                        contact_email = :contact_email,
                        contact_phone = :contact_phone,
                        license_number = :license_number,
                        license_expiry = :license_expiry,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :hospital_id AND user_id = :user_id
                ");
                
                $stmt->execute([
                    'hospital_name' => $hospital_name,
                    'registration_number' => $registration_number,
                    'location' => $location,
                    'contact_person' => $contact_person,
                    'contact_email' => $contact_email,
                    'contact_phone' => $contact_phone,
                    'license_number' => $license_number,
                    'license_expiry' => $license_expiry ?: null,
                    'hospital_id' => $hospital['id'],
                    'user_id' => $user['id']
                ]);
                
                // REMOVED: The code that was updating user email
                // This ensures only the contact_email in hospitals table is updated
                // The account email in users table remains unchanged
                
                $pdo->commit();
                
                // Update session variables and reload hospital data
                $success_message = "Hospital profile updated successfully!";
                
                // Reload hospital data
                $stmt = $pdo->prepare("
                    SELECT h.*, u.verification_status 
                    FROM hospitals h 
                    JOIN users u ON h.user_id = u.id 
                    WHERE h.user_id = :user_id
                ");
                $stmt->execute(['user_id' => $user['id']]);
                $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
        
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Edit Hospital Profile</h1>
        <p class="text-gray-600">Update your hospital information</p>
        <div class="mt-2">
            <a href="hospital_dashboard.php" class="text-red-600 hover:underline">
                ← Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="ri-checkbox-circle-line mr-2"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="ri-error-warning-line mr-2"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Hospital Information</h2>
                
                <form method="POST" action="">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Hospital Name -->
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">
                                Hospital Name *
                            </label>
                            <input type="text" name="hospital_name" required 
                                   value="<?php echo htmlspecialchars($hospital['hospital_name']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="General Hospital">
                        </div>
                        
                        <!-- Registration Number -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                Registration Number *
                            </label>
                            <input type="text" name="registration_number" required 
                                   value="<?php echo htmlspecialchars($hospital['registration_number']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="HOS-001">
                        </div>
                        
                        <!-- License Number -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                License Number (Optional)
                            </label>
                            <input type="text" name="license_number" 
                                   value="<?php echo htmlspecialchars($hospital['license_number'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="LIC-12345">
                        </div>
                        
                        <!-- License Expiry -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                License Expiry Date (Optional)
                            </label>
                            <input type="date" name="license_expiry" 
                                   value="<?php echo htmlspecialchars($hospital['license_expiry'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <!-- Location -->
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 font-medium mb-2">
                                Location / Address *
                            </label>
                            <textarea name="location" rows="3" required 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                      placeholder="123 Hospital Road, City, Country"><?php echo htmlspecialchars($hospital['location']); ?></textarea>
                        </div>
                        
                        <!-- Contact Person -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                Contact Person *
                            </label>
                            <input type="text" name="contact_person" required 
                                   value="<?php echo htmlspecialchars($hospital['contact_person']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="Dr. John Smith">
                        </div>
                        
                        <!-- Contact Email -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                Contact Email (Optional)
                                <span class="text-gray-500 text-sm">- This is separate from your account login email</span>
                            </label>
                            <input type="email" name="contact_email" 
                                   value="<?php echo htmlspecialchars($hospital['contact_email'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="contact@hospital.com">
                            <p class="text-gray-500 text-xs mt-1">
                                This email is for contact purposes only. Your login email remains: <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        </div>
                        
                        <!-- Contact Phone -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                Contact Phone *
                            </label>
                            <input type="tel" name="contact_phone" required 
                                   value="<?php echo htmlspecialchars($hospital['contact_phone']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                                   placeholder="+94 11 123 4567">
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="mt-8 pt-6 border-t border-gray-200 flex justify-between">
                        <a href="hospital_dashboard.php" 
                           class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Verification Status -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Verification Status</h3>
                
                <?php 
                $isVerified = $hospital['is_verified'] && $hospital['verification_status'] === 'approved';
                ?>
                
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-sm">Current Status</p>
                        <p class="text-xl font-bold <?php echo $isVerified ? 'text-green-600' : 'text-yellow-600'; ?>">
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
                    <div class="w-12 h-12 <?php echo $isVerified ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'; ?> rounded-full flex items-center justify-center">
                        <i class="ri-<?php echo $isVerified ? 'shield-check' : 'time'; ?>-line text-2xl"></i>
                    </div>
                </div>
                
                <?php if ($hospital['verification_status'] === 'rejected' && !empty($hospital['verification_note'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                        <p class="font-medium">Verification Note:</p>
                        <p class="mt-1"><?php echo htmlspecialchars($hospital['verification_note']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!$isVerified): ?>
                    <div class="text-sm text-gray-600 mt-4">
                        <p>Your profile will be reviewed by admin. Once verified, you'll be able to:</p>
                        <ul class="list-disc pl-5 mt-2 space-y-1">
                            <li>Submit blood requests</li>
                            <li>View detailed blood stock</li>
                            <li>Access all hospital features</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Profile Information -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Profile Information</h3>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-gray-500 text-sm">Account Email (Login)</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-gray-500 text-xs mt-1">This cannot be changed from this page</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Contact Email</p>
                        <p class="font-medium"><?php echo !empty($hospital['contact_email']) ? htmlspecialchars($hospital['contact_email']) : '<span class="text-gray-400">Not set</span>'; ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Profile Created</p>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($hospital['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Last Updated</p>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($hospital['updated_at'])); ?></p>
                    </div>
                </div>
                
                <?php if ($isVerified && $hospital['verified_at']): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-gray-500 text-sm">Verified On</p>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($hospital['verified_at'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Important Notes -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                <h3 class="text-lg font-bold text-yellow-800 mb-3">
                    <i class="ri-information-line mr-2"></i> Important Notes
                </h3>
                
                <ul class="space-y-2 text-sm text-yellow-700">
                    <li class="flex items-start">
                        <i class="ri-check-line mr-2 mt-0.5"></i>
                        <span>Fields marked with * are required</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line mr-2 mt-0.5"></i>
                        <span>Registration number must be unique</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line mr-2 mt-0.5"></i>
                        <span>Contact email is separate from your login email</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line mr-2 mt-0.5"></i>
                        <span>To change your account email, please contact support</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line mr-2 mt-0.5"></i>
                        <span>Keep your contact information up to date</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>