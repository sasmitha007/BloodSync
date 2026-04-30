<?php
require_once __DIR__ . '/../autoload.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Check authentication
Auth::requireAuth('login.php');

$user = Auth::getUser();
$profile = Auth::getDonorProfile();

// Calculate eligibility (simplified)
$lastDonation = $profile['last_donation_date'] ?? null;
$isEligible = true;
$nextEligibleDate = null;

if ($lastDonation) {
    $last = new DateTime($lastDonation);
    $now = new DateTime();
    $interval = $now->diff($last);
    $months = ($interval->y * 12) + $interval->m;
    
    if ($months < 3) {
        $isEligible = false;
        $nextEligible = clone $last;
        $nextEligible->add(new DateInterval('P3M'));
        $nextEligibleDate = $nextEligible->format('Y-m-d');
    }
}

// Get donor's upcoming appointments
try {
    $upcomingAppointments = Database::fetchAll(
        "SELECT * FROM appointments 
         WHERE donor_id = :donor_id 
         AND status = 'scheduled'
         AND appointment_date >= CURRENT_DATE
         ORDER BY appointment_date ASC, appointment_time ASC
         LIMIT 3",
        ['donor_id' => $profile['id']]
    );
    
    // Count total appointments
    $appointmentStats = Database::fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'scheduled' AND appointment_date >= CURRENT_DATE THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
         FROM appointments 
         WHERE donor_id = :donor_id",
        ['donor_id' => $profile['id']]
    );
} catch (Exception $e) {
    $upcomingAppointments = [];
    $appointmentStats = ['total' => 0, 'upcoming' => 0, 'completed' => 0];
}

// Get today's appointments
$todayAppointments = array_filter($upcomingAppointments, function($apt) {
    return $apt['appointment_date'] == date('Y-m-d');
});

// Get recent donations
try {
    $recentDonations = Database::fetchAll(
        "SELECT * FROM donor_donations 
         WHERE donor_id = :donor_id 
         AND status = 'verified'
         ORDER BY donation_date DESC 
         LIMIT 3",
        ['donor_id' => $profile['id']]
    );
} catch (Exception $e) {
    $recentDonations = [];
}

// Get pending medical reports
try {
    $pendingReports = Database::fetchAll(
        "SELECT * FROM medical_reports 
         WHERE donor_id = :donor_id 
         AND status = 'pending'
         ORDER BY report_date DESC",
        ['donor_id' => $profile['id']]
    );
} catch (Exception $e) {
    $pendingReports = [];
}

// Get notifications
try {
    $notifications = Database::fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = :user_id 
         AND is_read = FALSE
         ORDER BY created_at DESC
         LIMIT 5",
        ['user_id' => $user['id']]
    );
} catch (Exception $e) {
    $notifications = [];
}

$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unreadCount++;
    }
}

?>



<div class="container mx-auto px-4 py-8">
    <!-- Welcome Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Welcome, <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>! 👋</h1>
        <p class="text-gray-600">Here's your donation dashboard</p>
        
        <a href="notifications.php" class="text-red-600 font-medium hover:text-red-700 flex items-center mt-2">
            <i class="ri-notification-3-line mr-1"></i>Notifications
                <?php if ($unreadCount > 0): ?>
                <span class="ml-1 bg-red-500 text-white text-xs rounded-full px-2 py-1">
                <?php echo $unreadCount; ?>
                </span>
            <?php endif; ?>
        </a>
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
    
    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
    <div class="mb-8 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center mb-3">
            <i class="ri-notification-3-line text-blue-600 text-xl mr-2"></i>
            <h3 class="font-bold text-blue-800">Notifications</h3>
            <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                <?php echo count($notifications); ?> new
            </span>
        </div>
        <div class="space-y-2">
            <?php foreach ($notifications as $notification): ?>
            <div class="bg-white rounded-lg p-3 border border-blue-100">
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                <p class="text-xs text-gray-500 mt-1">
                    <i class="ri-time-line mr-1"></i>
                    <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3 text-right">
            <a href="notifications.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View all notifications →
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Blood Type Card -->
        <div class="bg-gradient-to-br from-red-600 to-red-800 text-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-red-200 text-sm">Blood Type</p>
                    <h3 class="text-3xl font-bold mt-2"><?php echo htmlspecialchars($profile['blood_type'] ?? 'Unknown'); ?></h3>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="ri-drop-fill text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Eligibility Card -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm">Eligibility Status</p>
                    <h3 class="text-2xl font-bold mt-2 <?php echo $isEligible ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <?php echo $isEligible ? 'Eligible' : 'Not Eligible'; ?>
                    </h3>
                    <?php if (!$isEligible): ?>
                        <p class="text-gray-500 text-sm mt-1">Next: <?php echo date('M d, Y', strtotime($nextEligibleDate)); ?></p>
                    <?php else: ?>
                        <p class="text-green-500 text-sm mt-1">Ready to donate</p>
                    <?php endif; ?>
                </div>
                <div class="w-12 h-12 <?php echo $isEligible ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'; ?> rounded-full flex items-center justify-center">
                    <i class="ri-<?php echo $isEligible ? 'check' : 'time'; ?>-line text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Appointments Card -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm">Appointments</p>
                    <h3 class="text-2xl font-bold mt-2 text-blue-600"><?php echo $appointmentStats['upcoming']; ?></h3>
                    <p class="text-gray-500 text-sm mt-1">Upcoming</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                    <i class="ri-calendar-event-line text-2xl"></i>
                </div>
            </div>
            <?php if ($appointmentStats['total'] > 0): ?>
            <a href="appointments.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium mt-3 inline-block">
                View all →
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Donation Stats Card -->
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-500 text-sm">Total Donations</p>
                    <h3 class="text-2xl font-bold mt-2"><?php echo $profile['total_donations'] ?? 0; ?></h3>
                    <p class="text-gray-500 text-sm mt-1">
                        <?php echo $profile['total_units_donated'] ?? 0; ?> units
                    </p>
                </div>
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                    <i class="ri-heart-pulse-line text-2xl"></i>
                </div>
            </div>
            <a href="history.php" class="text-red-600 hover:text-red-800 text-sm font-medium mt-3 inline-block">
                View history →
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Personal Info -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Personal Information</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-500 text-sm">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Phone</p>
                        <p class="font-medium"><?php echo htmlspecialchars($profile['contact_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">NIC</p>
                        <p class="font-medium"><?php echo htmlspecialchars($profile['nic']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Date of Birth</p>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($profile['date_of_birth'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Weight</p>
                        <p class="font-medium"><?php echo htmlspecialchars($profile['weight'] ?? 'N/A'); ?> kg</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-gray-500 text-sm">Address</p>
                        <p class="font-medium"><?php echo htmlspecialchars($profile['address'] . ', ' . $profile['city']); ?></p>
                    </div>
                </div>
                <div class="mt-6 flex space-x-4">
                    <a href="profile.php" class="text-red-600 font-medium hover:underline flex items-center">
                        <i class="ri-edit-line mr-2"></i> Edit Profile
                    </a>
                    <a href="medical_reports.php" class="text-blue-600 font-medium hover:underline flex items-center">
                        <i class="ri-file-medical-line mr-2"></i> Medical Reports
                    </a>
                </div>
            </div>
            
            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Upcoming Appointments</h2>
                    <a href="appointments.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        View All →
                    </a>
                </div>
                
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="text-center py-8">
                        <i class="ri-calendar-line text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">No upcoming appointments</p>
                        <p class="text-sm text-gray-500 mt-2">Contact admin to schedule a medical report appointment</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($upcomingAppointments as $appointment): 
                            $isToday = $appointment['appointment_date'] == date('Y-m-d');
                            $appointmentTime = date('h:i A', strtotime($appointment['appointment_time']));
                            $appointmentDate = date('M d, Y', strtotime($appointment['appointment_date']));
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4 <?php echo $isToday ? 'border-yellow-300 bg-yellow-50' : 'hover:bg-gray-50'; ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center mb-2">
                                        <h3 class="font-bold text-gray-900">Medical Report Collection</h3>
                                        <?php if ($isToday): ?>
                                            <span class="ml-3 px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">Today</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-gray-500 text-sm">Date & Time</p>
                                            <p class="font-medium">
                                                <?php echo $appointmentDate; ?> at <?php echo $appointmentTime; ?>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-gray-500 text-sm">Hospital</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($appointment['hospital_name']); ?></p>
                                        </div>
                                        
                                        <?php if ($appointment['doctor_name']): ?>
                                        <div>
                                            <p class="text-gray-500 text-sm">Doctor</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-gray-500 text-sm">Contact</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($appointment['doctor_contact'] ?? 'N/A'); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($appointment['notes']): ?>
                                    <div class="mt-4">
                                        <p class="text-gray-500 text-sm">Notes</p>
                                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-right">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                        Scheduled
                                    </span>
                                    <p class="text-sm text-gray-500 mt-2">
                                        Created: <?php echo date('M d, Y', strtotime($appointment['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-600">
                                    <i class="ri-information-line mr-1"></i>
                                    Please bring your NIC and any previous medical reports to the appointment.
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($todayAppointments) > 0): ?>
                    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="ri-alarm-warning-line text-yellow-600 mr-2"></i>
                            <h3 class="font-medium text-yellow-800">Today's Appointments Reminder</h3>
                        </div>
                        <p class="text-yellow-700 text-sm mt-1">
                            You have <?php echo count($todayAppointments); ?> appointment(s) scheduled for today. 
                            Please be on time and bring all required documents.
                        </p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Recent Donations -->
            <?php if (!empty($recentDonations)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Donations</h2>
                <div class="space-y-4">
                    <?php foreach ($recentDonations as $donation): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-900">Blood Donation</p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($donation['hospital_location']); ?></p>
                                <div class="flex items-center mt-2">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs mr-3">
                                        <?php echo $donation['units_donated']; ?> units
                                    </span>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                        <?php echo htmlspecialchars($donation['blood_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></p>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                    Verified
                                </span>
                            </div>
                        </div>
                        <?php if ($donation['notes']): ?>
                        <p class="text-sm text-gray-500 mt-2"><?php echo htmlspecialchars($donation['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-6">
                    <a href="history.php" class="text-red-600 font-medium hover:underline">
                        View full donation history →
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-4">
                    
                    <a href="history.php" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 text-center transition">
                        <i class="ri-history-line text-2xl text-blue-600 mb-2"></i>
                        <p class="font-medium text-gray-900">Donation History</p>
                    </a>
                    
                    <a href="medical_reports.php" class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 text-center transition">
                        <i class="ri-file-medical-line text-2xl text-green-600 mb-2"></i>
                        <p class="font-medium text-gray-900">Medical Reports</p>
                    </a>
                    
                    <a href="appointments.php" class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 text-center transition">
                        <i class="ri-calendar-line text-2xl text-purple-600 mb-2"></i>
                        <p class="font-medium text-gray-900">My Appointments</p>
                    </a>
                </div>
            </div>
            
            <!-- Pending Medical Reports -->
            <?php if (!empty($pendingReports)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                <div class="flex items-center mb-4">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                    <h2 class="text-lg font-bold text-yellow-800">Pending Medical Reports</h2>
                </div>
                <div class="space-y-3">
                    <?php foreach ($pendingReports as $report): ?>
                    <div class="bg-white rounded-lg p-3">
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($report['title']); ?></p>
                        <p class="text-sm text-gray-600">Uploaded: <?php echo date('M d, Y', strtotime($report['uploaded_at'])); ?></p>
                        <p class="text-xs text-yellow-600 mt-1">
                            <i class="ri-time-line mr-1"></i>Awaiting admin review
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-center">
                    <a href="medical_reports.php" class="text-yellow-700 hover:text-yellow-900 text-sm font-medium">
                        View all reports →
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Urgent Needs -->
            <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                <div class="flex items-center mb-4">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                    <h2 class="text-lg font-bold text-red-800">Urgent Blood Needs</h2>
                </div>
                <div class="space-y-3">
                    <div class="bg-white rounded-lg p-3">
                        <p class="text-sm text-gray-600">General Hospital, Colombo</p>
                        <p class="font-medium text-red-700">
                            <i class="ri-drop-line mr-1"></i>
                            <?php echo $profile['blood_type']; ?> needed urgently
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Emergency surgeries</p>
                    </div>
                    <div class="bg-white rounded-lg p-3">
                        <p class="text-sm text-gray-600">Kandy Teaching Hospital</p>
                        <p class="font-medium text-red-700">
                            <i class="ri-drop-line mr-1"></i>
                            All blood types needed
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Blood drive this weekend</p>
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <a href="urgent_needs.php" class="text-red-700 hover:text-red-900 text-sm font-medium">
                        View all urgent needs →
                    </a>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                <div class="flex items-center mb-4">
                    <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                    <h2 class="text-lg font-bold text-blue-800">Upcoming Events</h2>
                </div>
                <div class="space-y-3">
                    <div class="bg-white rounded-lg p-3">
                        <p class="font-medium text-gray-900">Blood Donation Camp</p>
                        <p class="text-sm text-gray-600">Dec 15, 2024 • Community Center</p>
                        <p class="text-xs text-blue-600 mt-1">
                            <i class="ri-calendar-event-line mr-1"></i>Register now
                        </p>
                    </div>
                    <div class="bg-white rounded-lg p-3">
                        <p class="font-medium text-gray-900">Awareness Seminar</p>
                        <p class="text-sm text-gray-600">Dec 20, 2024 • City Hall</p>
                        <p class="text-xs text-blue-600 mt-1">
                            <i class="ri-information-line mr-1"></i>Free health checkups
                        </p>
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <a href="events.php" class="text-blue-700 hover:text-blue-900 text-sm font-medium">
                        View all events →
                    </a>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Your Stats</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Donor Since</span>
                        <span class="font-medium"><?php echo date('M Y', strtotime($profile['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Donations</span>
                        <span class="font-medium"><?php echo $profile['total_donations'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Units</span>
                        <span class="font-medium"><?php echo $profile['total_units_donated'] ?? 0; ?> units</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Last Donation</span>
                        <span class="font-medium">
                            <?php echo $lastDonation ? date('M d, Y', strtotime($lastDonation)) : 'Never'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Appointments</span>
                        <span class="font-medium"><?php echo $appointmentStats['total']; ?> total</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Recent Activity</h2>
        
        <?php
        try {
            $recentActivity = Database::fetchAll(
                "SELECT 
                    'appointment' as type, 
                    'Appointment Scheduled' as title, 
                    a.created_at as created_at,
                    CONCAT('Medical report at ', a.hospital_name) as details,
                    a.appointment_date as activity_date
                 FROM appointments a
                 WHERE a.donor_id = :donor_id
                 UNION ALL
                 SELECT 
                    'report' as type, 
                    'Report Uploaded' as title, 
                    mr.uploaded_at as created_at,
                    mr.title as details,
                    mr.report_date as activity_date
                 FROM medical_reports mr
                 WHERE mr.donor_id = :donor_id
                 UNION ALL
                 SELECT 
                    'donation' as type,
                    'Blood Donation' as title,
                    dd.verified_at as created_at,
                    CONCAT(dd.units_donated, ' units donated') as details,
                    dd.donation_date as activity_date
                 FROM donor_donations dd
                 WHERE dd.donor_id = :donor_id
                 AND dd.status = 'verified'
                 ORDER BY created_at DESC
                 LIMIT 8",
                ['donor_id' => $profile['id']]
            );
        } catch (Exception $e) {
            $recentActivity = [];
        }
        ?>
        
        <?php if (empty($recentActivity)): ?>
            <div class="text-center py-8">
                <i class="ri-history-line text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-600">No recent activity</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($recentActivity as $activity): 
                    $icon = match($activity['type']) {
                        'appointment' => 'calendar-event-line',
                        'report' => 'file-medical-line',
                        'donation' => 'heart-pulse-line',
                        default => 'history-line'
                    };
                    $color = match($activity['type']) {
                        'appointment' => 'blue',
                        'report' => 'green',
                        'donation' => 'red',
                        default => 'gray'
                    };
                ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-full bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-600 flex items-center justify-center mr-3">
                            <i class="ri-<?php echo $icon; ?>"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900"><?php echo $activity['title']; ?></h4>
                            <p class="text-xs text-gray-500">
                                <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($activity['details']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="ri-time-line mr-1"></i>
                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>