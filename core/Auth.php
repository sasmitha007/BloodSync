<?php
// Auth.php - Authentication class for BloodSync

class Auth {
    
    // Get PDO connection
    private static function getPDO() {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    
    // Helper method to fetch single row
    private static function fetch($query, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Helper method to fetch all rows
    private static function fetchAll($query, $params = []) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Register a new user (donor)
    public static function register($data) {
        try {
            $pdo = self::getPDO();
            $pdo->beginTransaction();
            
            // Check if email exists
            $existing = self::fetch(
                "SELECT id FROM users WHERE email = :email",
                ['email' => $data['email']]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert into users table
            $sql = "INSERT INTO users (email, password_hash, role, is_verified, verification_status) 
                    VALUES (:email, :password, 'donor', FALSE, 'pending') 
                    RETURNING id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'email' => $data['email'],
                'password' => $hashedPassword
            ]);
            
            $userId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
            
            // Split name
            $nameParts = explode(' ', $data['fullname'], 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            
            // Insert into donors table
            $sql = "INSERT INTO donors (
                user_id, first_name, last_name, nic, date_of_birth, 
                blood_type, contact_number, address, city, weight
            ) VALUES (
                :user_id, :first_name, :last_name, :nic, :dob, 
                :blood_type, :phone, :address, :city, :weight
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nic' => $data['nic'],
                'dob' => $data['dob'],
                'blood_type' => $data['blood_type'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? 'Not specified',
                'city' => $data['city'] ?? 'Colombo',
                'weight' => $data['weight'] ?? 50.0
            ]);
            
            $pdo->commit();
            
            // Set session for immediate login
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $data['email'];
            $_SESSION['role'] = 'donor';
            $_SESSION['logged_in'] = true;
            $_SESSION['name'] = $firstName . ' ' . $lastName;
            $_SESSION['blood_type'] = $data['blood_type'];
            $_SESSION['is_verified'] = false;
            $_SESSION['verification_status'] = 'pending';
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    // Login user
    public static function login($email, $password) {
        try {
            // Get user by email
            $user = self::fetch(
                "SELECT id, email, password_hash, role, is_verified, verification_status 
                 FROM users WHERE email = :email",
                ['email' => $email]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Get additional info based on role
            $additionalInfo = [];
            if ($user['role'] === 'donor') {
                $donor = self::fetch(
                    "SELECT id, first_name, last_name, blood_type FROM donors WHERE user_id = :user_id",
                    ['user_id' => $user['id']]
                );
                if ($donor) {
                    $additionalInfo = [
                        'donor_id' => $donor['id'],
                        'name' => $donor['first_name'] . ' ' . $donor['last_name'],
                        'blood_type' => $donor['blood_type']
                    ];
                }
            } elseif ($user['role'] === 'hospital') {
                $hospital = self::fetch(
                    "SELECT id, hospital_name FROM hospitals WHERE user_id = :user_id",
                    ['user_id' => $user['id']]
                );
                if ($hospital) {
                    $additionalInfo = [
                        'hospital_id' => $hospital['id'],
                        'name' => $hospital['hospital_name']
                    ];
                }
            } elseif ($user['role'] === 'admin') {
                $additionalInfo = [
                    'name' => 'Administrator'
                ];
            }
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['is_verified'] = $user['is_verified'];
            $_SESSION['verification_status'] = $user['verification_status'];
            
            // Add additional info to session
            foreach ($additionalInfo as $key => $value) {
                $_SESSION[$key] = $value;
            }
            
            return ['success' => true, 'message' => 'Login successful'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    // Logout user
    public static function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Check if user is verified
    public static function isVerified() {
        return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] === true;
    }
    
    // Get current user info
    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        try {
            // Get fresh user data from database
            $user = self::fetch(
                "SELECT id, email, role, is_verified, verification_status, verification_notes, rejection_reason 
                 FROM users WHERE id = :id",
                ['id' => $_SESSION['user_id']]
            );
            
            if (!$user) {
                return null;
            }
            
            // Update session with latest data
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_verified'] = $user['is_verified'];
            $_SESSION['verification_status'] = $user['verification_status'];
            
            // Build result array
            $result = [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_verified' => $user['is_verified'],
                'verification_status' => $user['verification_status'],
                'verification_notes' => $user['verification_notes'],
                'rejection_reason' => $user['rejection_reason']
            ];
            
            // Add role-specific info
            if ($user['role'] === 'donor') {
                $donor = self::fetch(
                    "SELECT id, first_name, last_name, blood_type FROM donors WHERE user_id = :user_id",
                    ['user_id' => $user['id']]
                );
                if ($donor) {
                    $result['donor_id'] = $donor['id'];
                    $result['name'] = $donor['first_name'] . ' ' . $donor['last_name'];
                    $result['blood_type'] = $donor['blood_type'];
                }
            } elseif ($user['role'] === 'hospital') {
                $hospital = self::fetch(
                    "SELECT id, hospital_name FROM hospitals WHERE user_id = :user_id",
                    ['user_id' => $user['id']]
                );
                if ($hospital) {
                    $result['hospital_id'] = $hospital['id'];
                    $result['name'] = $hospital['hospital_name'];
                }
            } elseif ($user['role'] === 'admin') {
                $result['name'] = 'Administrator';
            }
            
            return $result;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // Require authentication
    public static function requireAuth($redirect = 'login.php') {
        if (!self::isLoggedIn()) {
            header("Location: $redirect");
            exit();
        }
    }
    
    // Require admin role
    public static function requireAdmin($redirect = '../login.php') {
        self::requireAuth($redirect);
        
        $user = self::getUser();
        if ($user['role'] !== 'admin') {
            header("Location: ../dashboard.php");
            exit();
        }
    }
    
    // Require hospital role
    public static function requireHospital($redirect = 'login.php') {
        self::requireAuth($redirect);
        
        $user = self::getUser();
        if ($user['role'] !== 'hospital') {
            header("Location: dashboard.php");
            exit();
        }
    }
    
    // Get donor profile
    public static function getDonorProfile($donorId = null) {
        if (!$donorId) {
            $user = self::getUser();
            $donorId = $user['donor_id'] ?? null;
        }
        
        if (!$donorId) {
            return null;
        }
        
        return self::fetch(
            "SELECT d.*, u.email, u.is_verified, u.verification_status, u.verification_notes, u.rejection_reason, u.verified_at
             FROM donors d 
             JOIN users u ON d.user_id = u.id 
             WHERE d.id = :id",
            ['id' => $donorId]
        );
    }
    
    // Get hospital profile
    public static function getHospitalProfile($hospitalId = null) {
        if (!$hospitalId) {
            $user = self::getUser();
            $hospitalId = $user['hospital_id'] ?? null;
        }
        
        if (!$hospitalId) {
            return null;
        }
        
        return self::fetch(
            "SELECT h.*, u.email, u.is_verified, u.verification_status, u.verification_notes, u.rejection_reason, u.verified_at
             FROM hospitals h 
             JOIN users u ON h.user_id = u.id 
             WHERE h.id = :id",
            ['id' => $hospitalId]
        );
    }
}
?>