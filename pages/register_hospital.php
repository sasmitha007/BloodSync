<?php
require_once '../config/database.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit();
}
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-2xl mx-auto">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?php echo $_SESSION['error']; ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Hospital Registration</h1>
            <p class="text-gray-600 mb-8">Register your hospital to request blood supplies</p>
            
            <form action="../handlers/register_hospital_process.php" method="POST">
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <!-- Hospital Name -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-medium mb-2">Hospital Name *</label>
                        <input type="text" name="hospital_name" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="General Hospital Colombo"
                               value="<?php echo $_SESSION['form_data']['hospital_name'] ?? ''; ?>">
                    </div>
                    
                    <!-- Registration Number -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Registration Number *</label>
                        <input type="text" name="registration_number" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="HOS-2024-001"
                               value="<?php echo $_SESSION['form_data']['registration_number'] ?? ''; ?>">
                    </div>
                    
                    <!-- License Number -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">License Number</label>
                        <input type="text" name="license_number" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="LIC-12345"
                               value="<?php echo $_SESSION['form_data']['license_number'] ?? ''; ?>">
                    </div>
                    
                    <!-- License Expiry -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">License Expiry Date</label>
                        <input type="date" name="license_expiry" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               value="<?php echo $_SESSION['form_data']['license_expiry'] ?? ''; ?>">
                    </div>
                    
                    <!-- Location -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-medium mb-2">Hospital Location *</label>
                        <input type="text" name="location" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="123 Hospital Road, Colombo"
                               value="<?php echo $_SESSION['form_data']['location'] ?? ''; ?>">
                    </div>
                    
                    <!-- Contact Person -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Contact Person *</label>
                        <input type="text" name="contact_person" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="Dr. John Smith"
                               value="<?php echo $_SESSION['form_data']['contact_person'] ?? ''; ?>">
                    </div>
                    
                    <!-- Contact Email -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Contact Email *</label>
                        <input type="email" name="contact_email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="contact@hospital.com"
                               value="<?php echo $_SESSION['form_data']['contact_email'] ?? ''; ?>">
                    </div>
                    
                    <!-- Contact Phone -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Contact Phone *</label>
                        <input type="tel" name="contact_phone" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="0112345678"
                               value="<?php echo $_SESSION['form_data']['contact_phone'] ?? ''; ?>">
                    </div>
                    
                    <!-- User Email -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Login Email *</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                               placeholder="admin@hospital.com"
                               value="<?php echo $_SESSION['form_data']['email'] ?? ''; ?>">
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Password *</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none pr-12"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('password')" 
                                    class="absolute right-3 top-3 text-gray-500 hover:text-gray-700 password-toggle">
                                <i id="password_icon" class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Confirm Password *</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none pr-12"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('confirm_password')" 
                                    class="absolute right-3 top-3 text-gray-500 hover:text-gray-700 password-toggle">
                                <i id="confirm_password_icon" class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="terms" required 
                               class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <span class="ml-2 text-gray-700">
                            I agree to the <a href="#" class="text-red-600 hover:underline">Terms of Service</a> 
                            and <a href="#" class="text-red-600 hover:underline">Privacy Policy</a>
                        </span>
                    </label>
                </div>
                
                <button type="submit" 
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 rounded-lg transition duration-300">
                    Register Hospital
                </button>
                
                <p class="text-center text-gray-600 mt-6">
                    Already have an account? 
                    <a href="login.php" class="text-red-600 font-bold hover:underline">Login here</a>
                </p>
                
                <p class="text-center text-gray-600 mt-4">
                    Register as individual donor? 
                    <a href="register.php" class="text-red-600 font-bold hover:underline">Click here</a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'ri-eye-off-line';
    } else {
        field.type = 'password';
        icon.className = 'ri-eye-line';
    }
}
</script>

<?php 
// Clear form data from session
unset($_SESSION['form_data']);
require_once 'includes/footer.php'; 
?>