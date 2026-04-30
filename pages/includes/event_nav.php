<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="bg-white shadow-lg">
    <div class="container mx-auto px-4 py-3">
        <div class="flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center space-x-2">
                <img src="../../assets/img/logod.png" class="w-10" alt="BloodSync Logo">
                <span class="text-2xl font-bold text-red-600">BloodSync</span>
            </div>

            <!-- Navigation Links -->
            <div class="flex items-center space-x-6">
                <a href="../index.php" 
                   class="<?php echo $current_page == '../index.php' ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                    Home
                </a>

                <a href="events.php" 
                   class="<?php echo $current_page == 'events.php' ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                    Events
                </a>
                
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <a href="../dashboard.php"
                       class="<?php echo $current_page == '../dashboard.php' ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                        Dashboard
                    </a>
                    <a href="../profile.php"
                       class="<?php echo $current_page == '../profile.php' ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                        Profile
                    </a>

                    <a href="../medical_reports.php" class="flex items-center space-x-2 text-gray-700 hover:text-red-600">
                        <i class="ri-file-text-line"></i>
                        <span>Medical Reports</span>
                    </a>
                    
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="../admin/verify_reports.php"
                           class="<?php echo strpos($current_page, '../admin/') !== false ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                            Admin
                        </a>
                    <?php endif; ?>
                    
                    <a href="../logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="../login.php"
                       class="<?php echo $current_page == '../login.php' ? 'text-red-600 font-bold' : 'text-gray-700 hover:text-red-600'; ?>">
                        Login
                    </a>
                    <a href="../register.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>