<?php
// hospital_requests.php
require_once __DIR__ . '/../../autoload.php';
Auth::requireHospital('../../login.php');

require_once '../includes/header.php';
require_once '../includes/hospital_nav.php';

// Get hospital info
$user = Auth::getUser();
$hospital = Database::fetch(
    "SELECT h.* FROM hospitals h WHERE h.user_id = :user_id",
    ['user_id' => $user['id']]
);

if (!$hospital) {
    header('Location: complete_hospital_profile.php');
    exit();
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$blood_type = $_GET['blood_type'] ?? 'all';

// Build query
$query = "SELECT br.* FROM blood_requests br WHERE br.hospital_id = :hospital_id";
$params = ['hospital_id' => $hospital['id']];

if (!empty($search)) {
    $query .= " AND (br.patient_name ILIKE :search OR br.request_number ILIKE :search)";
    $params['search'] = "%{$search}%";
}

if (!empty($status) && $status !== 'all') {
    $query .= " AND br.status = :status";
    $params['status'] = $status;
}

if (!empty($blood_type) && $blood_type !== 'all') {
    $query .= " AND br.blood_type = :blood_type";
    $params['blood_type'] = $blood_type;
}

$query .= " ORDER BY br.created_at DESC";

// Fetch blood requests
$bloodRequests = Database::fetchAll($query, $params);
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Blood Requests</h1>
    <p class="text-gray-600 mb-6">Track and manage your blood supply requests.</p>
    
    <!-- Stats Cards -->
    <div class="grid md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Total Requests</p>
            <h3 class="text-2xl font-bold mt-2"><?php echo count($bloodRequests); ?></h3>
        </div>
        <div class="bg-yellow-50 rounded-xl shadow p-6">
            <p class="text-yellow-600 text-sm">Pending</p>
            <h3 class="text-2xl font-bold mt-2 text-yellow-700">
                <?php echo count(array_filter($bloodRequests, fn($r) => $r['status'] === 'pending')); ?>
            </h3>
        </div>
        <div class="bg-green-50 rounded-xl shadow p-6">
            <p class="text-green-600 text-sm">Approved</p>
            <h3 class="text-2xl font-bold mt-2 text-green-700">
                <?php echo count(array_filter($bloodRequests, fn($r) => $r['status'] === 'approved')); ?>
            </h3>
        </div>
        <div class="bg-blue-50 rounded-xl shadow p-6">
            <p class="text-blue-600 text-sm">Fulfilled</p>
            <h3 class="text-2xl font-bold mt-2 text-blue-700">
                <?php echo count(array_filter($bloodRequests, fn($r) => $r['status'] === 'fulfilled')); ?>
            </h3>
        </div>
    </div>
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <form method="GET" class="grid md:grid-cols-4 gap-4">
            <div>
                <label class="block text-gray-700 text-sm mb-2">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg" 
                       placeholder="Patient or Request #">
            </div>
            <div>
                <label class="block text-gray-700 text-sm mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="fulfilled" <?php echo $status === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm mb-2">Blood Type</label>
                <select name="blood_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $blood_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="A+" <?php echo $blood_type === 'A+' ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo $blood_type === 'A-' ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo $blood_type === 'B+' ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo $blood_type === 'B-' ? 'selected' : ''; ?>>B-</option>
                    <option value="AB+" <?php echo $blood_type === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo $blood_type === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    <option value="O+" <?php echo $blood_type === 'O+' ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo $blood_type === 'O-' ? 'selected' : ''; ?>>O-</option>
                </select>
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg">
                    Apply Filters
                </button>
                <a href="hospital_requests.php" class="w-full px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                    Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Blood Requests Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">All Blood Requests</h2>
        </div>
        
        <?php if (empty($bloodRequests)): ?>
            <div class="text-center py-12">
                <i class="ri-inbox-line text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No blood requests found.</p>
                <?php if (!empty($search) || !empty($status) || !empty($blood_type)): ?>
                    <p class="text-gray-400 text-sm mt-2">Try changing your filter criteria.</p>
                <?php else: ?>
                    <p class="text-gray-400 text-sm mt-2">Submit your first blood request from the dashboard.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blood & Units</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Urgency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timeline</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($bloodRequests as $request): 
                            $urgencyColor = match($request['urgency_level']) {
                                'critical' => 'bg-red-100 text-red-800',
                                'urgent' => 'bg-orange-100 text-orange-800',
                                default => 'bg-blue-100 text-blue-800'
                            };
                            
                            $statusColor = match($request['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'fulfilled' => 'bg-blue-100 text-blue-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            };
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <p class="font-mono text-sm font-medium"><?php echo $request['request_number']; ?></p>
                                <p class="font-medium mt-1"><?php echo htmlspecialchars($request['patient_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $request['patient_age']; ?>y, <?php echo $request['patient_sex']; ?></p>
                                <p class="text-xs text-gray-400">Ward: <?php echo htmlspecialchars($request['patient_ward']); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm font-medium">
                                        <?php echo $request['blood_type']; ?>
                                    </span>
                                    <span class="font-bold text-lg"><?php echo $request['units_required']; ?></span>
                                    <span class="text-sm text-gray-500">units</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $urgencyColor; ?>">
                                    <?php echo ucfirst($request['urgency_level']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                                <?php if ($request['status'] === 'approved' && $request['approved_at']): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    Approved: <?php echo date('M d', strtotime($request['approved_at'])); ?>
                                </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium"><?php echo date('M d, Y', strtotime($request['required_date'])); ?></p>
                                <?php 
                                $daysLeft = floor((strtotime($request['required_date']) - time()) / (60 * 60 * 24));
                                if ($daysLeft < 0) {
                                    echo '<p class="text-xs text-red-500">Overdue!</p>';
                                } elseif ($daysLeft === 0) {
                                    echo '<p class="text-xs text-orange-500">Due today</p>';
                                } else {
                                    echo '<p class="text-xs text-gray-500">In ' . $daysLeft . ' days</p>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-500">
                        Showing <?php echo count($bloodRequests); ?> request<?php echo count($bloodRequests) !== 1 ? 's' : ''; ?>
                    </p>
                    <a href="hospital_dashboard.php" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">
                        <i class="ri-add-line mr-1"></i> New Request
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Stats -->
    <div class="mt-8 grid md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="font-bold text-gray-900 mb-4">Request Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Units Requested</span>
                    <span class="font-medium">
                        <?php echo array_sum(array_column($bloodRequests, 'units_required')); ?> units
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Average Units/Request</span>
                    <span class="font-medium">
                        <?php echo count($bloodRequests) > 0 ? round(array_sum(array_column($bloodRequests, 'units_required')) / count($bloodRequests), 1) : 0; ?> units
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Approval Rate</span>
                    <span class="font-medium">
                        <?php 
                        $approvedFulfilled = count(array_filter($bloodRequests, fn($r) => $r['status'] === 'approved' || $r['status'] === 'fulfilled'));
                        echo count($bloodRequests) > 0 ? round(($approvedFulfilled / count($bloodRequests)) * 100) : 0; ?>%
                    </span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="hospital_dashboard.php" class="flex items-center text-red-600 hover:text-red-800">
                    <i class="ri-add-line mr-2"></i>
                    <span>Submit New Request</span>
                </a>
                <a href="hospital_profile.php" class="flex items-center text-green-600 hover:text-green-800">
                    <i class="ri-building-2-line mr-2"></i>
                    <span>Update Hospital Profile</span>
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="font-bold text-gray-900 mb-4">Need Help?</h3>
            <div class="space-y-2">
                <p class="text-sm text-gray-600">
                    For urgent blood requests, call the blood bank directly:
                </p>
                <p class="font-medium text-red-600">Emergency: 011-1234567</p>
                <p class="text-sm text-gray-500">Available 24/7 for emergencies</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>