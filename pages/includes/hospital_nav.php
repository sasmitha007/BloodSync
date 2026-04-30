<?php
// hospital_nav.php
?>
<nav class="bg-white shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <a href="hospital_dashboard.php" class="flex items-center">
                    <i class="ri-heart-pulse-line text-red-600 text-2xl mr-2"></i>
                    <span class="text-xl font-bold text-gray-900">BloodSync</span>
                    <span class="ml-2 text-sm text-gray-500">Hospital Portal</span>
                </a>
            </div>
            
            <div class="hidden md:flex items-center space-x-4">
                <a href="hospital_dashboard.php" class="px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-dashboard-line mr-1"></i> Dashboard
                </a>
                <a href="hospital_requests.php" class="px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-list-check mr-1"></i> My Requests
                </a>
                <a href="hospital_profile.php" class="px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-building-2-line mr-1"></i> Profile
                </a>
                <a href="../logout.php" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    <i class="ri-logout-box-line mr-1"></i> Logout
                </a>
            </div>
            
            <!-- Mobile menu button -->
            <button id="mobile-menu-button" class="md:hidden text-gray-700">
                <i class="ri-menu-line text-2xl"></i>
            </button>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="md:hidden hidden">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="hospital_dashboard.php" class="block px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-dashboard-line mr-1"></i> Dashboard
                </a>
                <a href="hospital_requests.php" class="block px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-list-check mr-1"></i> My Requests
                </a>
                <a href="hospital_profile.php" class="block px-3 py-2 text-gray-700 hover:text-red-600 font-medium">
                    <i class="ri-building-2-line mr-1"></i> Profile
                </a>
                <a href="../logout.php" class="block px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    <i class="ri-logout-box-line mr-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
document.getElementById('mobile-menu-button').addEventListener('click', function() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
});
</script>