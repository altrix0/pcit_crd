<?php
/**
 * Authentication Helper Functions
 * 
 * Helper functions for authentication, authorization,
 * session management, and security
 */

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isUserLoggedIn() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user_id is set in session
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Alias for isUserLoggedIn with underscore naming convention
 */
function check_logged_in() {
    return isUserLoggedIn();
}

/**
 * Get current user's role
 * @return string|null User role or null if not logged in
 */
function get_user_role() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Get current user's access level
 * @return int|null User access level or null if not logged in
 */
function get_user_access_level() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['access_level']) ? (int)$_SESSION['access_level'] : null;
}

/**
 * Validate a login token
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validate_token($token) {
    // Check if token is provided and not empty
    if (empty($token)) {
        return false;
    }
    
    try {
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $user = new User($database);
        
        // Use model to validate token
        return ($user->getUserByToken($token) !== false);
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is authorized for a specific action
 * @param int|array $user User ID or user data array
 * @param string $action Action to check authorization for
 * @param array $context Additional context for the action
 * @return bool True if authorized, false otherwise
 */
function is_authorized_for_action($user, $action, $context = []) {
    try {
        // Get user ID if an array was passed
        $userId = is_array($user) ? $user['uid'] : $user;
        
        // If user is not provided, get from session
        if (empty($userId) && isUserLoggedIn()) {
            $userId = $_SESSION['user_id'];
        }
        
        // Load required models
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $userModel = new User($database);
        
        // Get user data if only ID was provided
        $userData = is_array($user) ? $user : $userModel->getUserById($userId);
        
        if (!$userData) {
            return false;
        }
        
        // Check authorization based on action
        switch ($action) {
            case 'edit_equipment':
                // Check if user has edit permission or owns the equipment
                $requiredLevel = 2; // Level 2+ can edit equipment
                
                // If specific equipment is being edited, check if user is the creator
                if (isset($context['equipment_id'])) {
                    // Check if Equipment model is available
                    if (file_exists(__DIR__ . '/../models/Equipment.php')) {
                        require_once __DIR__ . '/../models/Equipment.php';
                        
                        // Make sure the Equipment class exists
                        if (class_exists('Equipment')) {
                            try {
                                $equipmentModel = new Equipment($database);
                                $equipment = $equipmentModel->getEquipmentById($context['equipment_id']);
                                
                                // User can edit if they created the equipment and it's not locked
                                if ($equipment && $equipment['created_by'] == $userId && !$equipment['locked']) {
                                    return true;
                                }
                            } catch (Exception $e) {
                                error_log("Error checking equipment permissions: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                // Otherwise, check access level
                return (int)$userData['access_level'] >= $requiredLevel;
                
            case 'approve_report':
                // Only supervisors (level 3+) can approve reports
                $requiredLevel = 3;
                return (int)$userData['access_level'] >= $requiredLevel;
                
            case 'manage_users':
                // Only administrators (level 4+) can manage users
                $requiredLevel = 4;
                return (int)$userData['access_level'] >= $requiredLevel;
                
            case 'view_unit_data':
                // Check if user belongs to the unit or has sufficient access level
                if (isset($context['unit_id'])) {
                    // Level 3+ can view any unit data
                    if ((int)$userData['access_level'] >= 3) {
                        return true;
                    }
                    
                    // Check if user belongs to the unit
                    // We'll use a simple query instead of requiring Unit model
                    try {
                        $query = "SELECT COUNT(*) as count 
                                FROM posting p 
                                WHERE p.sevarth_id = :sevarth_id 
                                AND p.posting_unit = :unit_id 
                                AND (p.relieve_unit_date IS NULL OR p.relieve_unit_date > NOW())";
                        
                        $params = [
                            ':sevarth_id' => $userData['sevarth_id'],
                            ':unit_id' => $context['unit_id']
                        ];
                        
                        $result = Database::executeQuery($query, $params);
                        
                        if ($result && isset($result[0]['count']) && $result[0]['count'] > 0) {
                            return true;
                        }
                    } catch (Exception $e) {
                        error_log("Error checking unit membership: " . $e->getMessage());
                    }
                }
                
                return false;
                
            case 'generate_report':
                // Any authenticated user can generate reports
                return true;
                
            case 'view_logs':
                // Only administrators can view logs
                $requiredLevel = 4;
                return (int)$userData['access_level'] >= $requiredLevel;
                
            default:
                // Unknown action, deny by default
                return false;
        }
    } catch (Exception $e) {
        error_log("Authorization check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 * @return string Generated CSRF token
 */
function generate_csrf_token() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate a new token if it doesn't exist
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token($token) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if token exists and matches
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Regenerate token for one-time use
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }
    
    return false;
}

/**
 * Get current user data from session
 * @return array|null User data or null if not logged in
 */
function get_current_user() {
    if (!isUserLoggedIn()) {
        return null;
    }
    
    try {
        // Get basic info from session
        $userData = [
            'uid' => $_SESSION['user_id'],
            'sevarth_id' => $_SESSION['sevarth_id'],
            'role' => $_SESSION['user_role'],
            'access_level' => $_SESSION['access_level'],
            'full_name' => $_SESSION['full_name']
        ];
        
        // Optionally get more detailed info from database
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $userModel = new User($database);
        
        $detailedInfo = $userModel->getUserById($_SESSION['user_id']);
        
        if ($detailedInfo) {
            // Merge session data with database data (session data takes precedence)
            $userData = array_merge($detailedInfo, $userData);
        }
        
        return $userData;
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get reporting officers based on user's hierarchy
 * @param int|null $userId User ID or null for current user
 * @return array List of reporting officers
 */
function get_reporting_officers($userId = null) {
    try {
        // If no user ID provided, use current user
        if ($userId === null) {
            if (!isUserLoggedIn()) {
                return [];
            }
            $userId = $_SESSION['user_id'];
        }
        
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $userModel = new User($database);
        
        $reportingStructure = $userModel->getReportingStructure($userId);
        
        return $reportingStructure['reporting_to'];
    } catch (Exception $e) {
        error_log("Error getting reporting officers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get subordinate officers based on user's hierarchy
 * @param int|null $userId User ID or null for current user
 * @return array List of subordinate officers
 */
function get_subordinate_officers($userId = null) {
    try {
        // If no user ID provided, use current user
        if ($userId === null) {
            if (!isUserLoggedIn()) {
                return [];
            }
            $userId = $_SESSION['user_id'];
        }
        
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../config/database.php';
        
        $database = new Database();
        $userModel = new User($database);
        
        $reportingStructure = $userModel->getReportingStructure($userId);
        
        return $reportingStructure['subordinates'];
    } catch (Exception $e) {
        error_log("Error getting subordinate officers: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if password meets complexity requirements
 * @param string $password Password to check
 * @return bool True if password is complex enough, false otherwise
 */
function isPasswordComplex($password) {
    // Minimum length
    if (strlen($password) < 8) {
        return false;
    }
    
    // Check for uppercase
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Check for lowercase
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Check for numbers
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    // Check for special characters
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Generate a secure random token
 * @param int $length Length of the token
 * @return string Generated token
 */
function generate_secure_token($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get client IP address
 * @return string Client IP address
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

/**
 * Log user out and redirect
 * @param string $redirectTo URL to redirect to after logout
 */
function logout_user($redirectTo = 'login.php') {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear persistent login cookie if exists
        if (isset($_COOKIE['auth_token'])) {
            setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Get user ID before destroying session
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // If we have a user ID and a token, remove it from database
        if ($userId && isset($_COOKIE['auth_token'])) {
            require_once __DIR__ . '/../models/User.php';
            require_once __DIR__ . '/../config/database.php';
            
            $database = new Database();
            $userModel = new User($database);
            $userModel->removeLoginToken($userId, $_COOKIE['auth_token']);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Redirect
        header("Location: $redirectTo");
        exit();
    } catch (Exception $e) {
        error_log("Error during logout: " . $e->getMessage());
        
        // Still attempt to destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Redirect if user is not logged in
 * @param string $redirectTo URL to redirect to if not logged in
 */
function require_login($redirectTo = 'login.php') {
    if (!isUserLoggedIn()) {
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Redirect if user does not have required access level
 * @param int $requiredLevel Required access level
 * @param string $redirectTo URL to redirect to if access denied
 */
function require_access_level($requiredLevel, $redirectTo = 'access-denied.php') {
    require_login();
    
    $userLevel = get_user_access_level();
    
    if ($userLevel < $requiredLevel) {
        header("Location: $redirectTo");
        exit();
    }
}

?>