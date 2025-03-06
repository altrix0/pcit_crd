<?php
/**
 * Authentication Controller
 * Handles user authentication, login, logout, and session management
 */
class AuthController {
    private $db;
    private $user;
    private $otpService;
    private $loggingService;
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../services/OTPService.php';
        require_once __DIR__ . '/../services/LoggingService.php';
        require_once __DIR__ . '/../helpers/auth_helper.php';
        
        $this->db = new Database();
        $this->user = new User($this->db);
        $this->otpService = new OTPService($this->db);
        $this->loggingService = new LoggingService($this->db);
    }
    
    /**
     * Process login request with Sevarth ID
     * @return array Response with status and message
     */
    public function login() {
        // Check if the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        // Get login details
        $sevarthId = isset($_POST['sevarth_id']) ? trim($_POST['sevarth_id']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $rememberMe = isset($_POST['remember_me']) ? (bool)$_POST['remember_me'] : false;
        
        // Validate input
        if (empty($sevarthId) || empty($password)) {
            return ['status' => 'error', 'message' => 'Sevarth ID and password are required'];
        }
        
        // Attempt to authenticate user
        $userData = $this->user->authenticate($sevarthId, $password);
        
        if (!$userData) {
            // Log failed login attempt
            $this->loggingService->logAction('Failed login attempt', [
                'sevarth_id' => $sevarthId,
                'ip' => get_client_ip()
            ]);
            return ['status' => 'error', 'message' => 'Invalid Sevarth ID or password'];
        }
        
        // Check if 2FA is required for this account
        if ($userData['requires_2fa']) {
            // Generate and send OTP
            $this->otpService->generateAndSendOTP($userData['uid'], $userData['email_id'], $userData['mobile_number']);
            
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Store user data in session for OTP verification
            $_SESSION['pending_auth_user'] = $userData['uid'];
            $_SESSION['requires_otp'] = true;
            
            return [
                'status' => 'otp_required', 
                'message' => 'OTP has been sent to your registered mobile and email'
            ];
        }
        
        // If no 2FA required, complete login
        $this->completeLogin($userData, $rememberMe);
        
        return ['status' => 'success', 'message' => 'Login successful'];
    }
    
    /**
     * Complete the login process after successful authentication
     * @param array $userData User data
     * @param bool $rememberMe Whether to create persistent login
     */
    private function completeLogin($userData, $rememberMe = false) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $userData['uid'];
        $_SESSION['sevarth_id'] = $userData['sevarth_id'];
        $_SESSION['user_role'] = $userData['role_name'];
        $_SESSION['access_level'] = $userData['access_level'];
        $_SESSION['full_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
        $_SESSION['last_login'] = time();
        
        // Set persistent login if remember me is checked (90 days)
        if ($rememberMe) {
            $token = generate_secure_token();
            $expiry = time() + (90 * 24 * 60 * 60); // 90 days
            
            // Store token in database
            $this->user->storeLoginToken($userData['uid'], $token, $expiry);
            
            // Set secure cookie
            setcookie('auth_token', $token, $expiry, '/', '', true, true);
        }
        
        // Log successful login
        $this->loggingService->logAction('Login', [
            'user_id' => $userData['uid'],
            'ip' => get_client_ip()
        ]);
    }
    
    /**
     * Verify OTP for two-factor authentication
     * @return array Response with status and message
     */
    public function verifyOTP() {
        // Check if the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if there's a pending authentication
        if (!isset($_SESSION['pending_auth_user'])) {
            return ['status' => 'error', 'message' => 'No pending authentication'];
        }
        
        $userId = $_SESSION['pending_auth_user'];
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $rememberMe = isset($_POST['remember_me']) ? (bool)$_POST['remember_me'] : false;
        
        // Validate OTP
        if (empty($otp)) {
            return ['status' => 'error', 'message' => 'OTP is required'];
        }
        
        // Verify OTP
        $verified = $this->otpService->verifyOTP($userId, $otp);
        
        if (!$verified) {
            // Check if user is locked out
            if ($this->otpService->isUserLockedOut($userId, 'login')) {
                $remainingTime = $this->otpService->getRemainingLockoutTime($userId, 'login');
                $minutes = ceil($remainingTime / 60);
                
                return [
                    'status' => 'error', 
                    'message' => "Too many failed attempts. Please try again after {$minutes} minutes.",
                    'lockout' => true,
                    'remaining_time' => $remainingTime
                ];
            }
            
            return ['status' => 'error', 'message' => 'Invalid OTP. Please try again.'];
        }
        
        // Get user data
        $userData = $this->user->getUserById($userId);
        
        // Complete login
        $this->completeLogin($userData, $rememberMe);
        
        // Clear pending authentication
        unset($_SESSION['pending_auth_user']);
        unset($_SESSION['requires_otp']);
        
        return ['status' => 'success', 'message' => 'Login successful'];
    }
    
    /**
     * Log out the current user
     * @return array Response with status and message
     */
    public function logout() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get current user ID before destroying session
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Delete persistent login token if exists
        if (isset($_COOKIE['auth_token']) && $userId) {
            $this->user->removeLoginToken($userId, $_COOKIE['auth_token']);
            setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Log logout action
        if ($userId) {
            $this->loggingService->logAction('Logout', [
                'user_id' => $userId
            ]);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        return ['status' => 'success', 'message' => 'Logout successful'];
    }
    
    /**
     * Change user password
     * @return array Response with status and message
     */
    public function changePassword() {
        // Check if the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        // Check if user is logged in
        if (!isUserLoggedIn()) {
            return ['status' => 'error', 'message' => 'User not authenticated'];
        }
        
        $userId = $_SESSION['user_id'];
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return ['status' => 'error', 'message' => 'All password fields are required'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['status' => 'error', 'message' => 'New passwords do not match'];
        }
        
        // Check password complexity
        if (!isPasswordComplex($newPassword)) {
            return [
                'status' => 'error', 
                'message' => 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character'
            ];
        }
        
        // Verify current password
        if (!$this->user->verifyPassword($userId, $currentPassword)) {
            return ['status' => 'error', 'message' => 'Current password is incorrect'];
        }
        
        // Update password
        $updated = $this->user->updatePassword($userId, $newPassword);
        
        if (!$updated) {
            return ['status' => 'error', 'message' => 'Failed to update password'];
        }
        
        // Log password change
        $this->loggingService->logAction('Password change', [
            'user_id' => $userId
        ]);
        
        return ['status' => 'success', 'message' => 'Password updated successfully'];
    }
    
    /**
     * Initiate forgot password process
     * @return array Response with status and message
     */
    public function forgotPassword() {
        // Check if the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        $sevarthId = isset($_POST['sevarth_id']) ? trim($_POST['sevarth_id']) : '';
        
        if (empty($sevarthId)) {
            return ['status' => 'error', 'message' => 'Sevarth ID is required'];
        }
        
        // Get user by Sevarth ID
        $userData = $this->user->getUserBySevarthId($sevarthId);
        
        if (!$userData) {
            // Don't reveal if user exists for security reasons
            return ['status' => 'success', 'message' => 'If your Sevarth ID is registered, you will receive an OTP'];
        }
        
        // Check if user is locked out from password resets
        if ($this->otpService->isUserLockedOut($userData['uid'], 'password_reset')) {
            // Don't reveal lockout for security, just show generic message
            return ['status' => 'success', 'message' => 'If your Sevarth ID is registered, you will receive an OTP'];
        }
        
        // Generate and send OTP for password reset
        $this->otpService->generateAndSendOTP(
            $userData['uid'], 
            $userData['email_id'], 
            $userData['mobile_number'], 
            'password_reset'
        );
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store user data in session for OTP verification
        $_SESSION['password_reset_user'] = $userData['uid'];
        
        return [
            'status' => 'success', 
            'message' => 'Password reset OTP has been sent to your registered mobile and email'
        ];
    }
    
    /**
     * Reset password after OTP verification
     * @return array Response with status and message
     */
    public function resetPassword() {
        // Check if the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if there's a pending password reset
        if (!isset($_SESSION['password_reset_user'])) {
            return ['status' => 'error', 'message' => 'No pending password reset'];
        }
        
        $userId = $_SESSION['password_reset_user'];
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validate input
        if (empty($otp) || empty($newPassword) || empty($confirmPassword)) {
            return ['status' => 'error', 'message' => 'All fields are required'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['status' => 'error', 'message' => 'Passwords do not match'];
        }
        
        // Check password complexity
        if (!isPasswordComplex($newPassword)) {
            return [
                'status' => 'error', 
                'message' => 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character'
            ];
        }
        
        // Verify OTP
        $verified = $this->otpService->verifyOTP($userId, $otp, 'password_reset');
        
        if (!$verified) {
            // Check if user is locked out
            if ($this->otpService->isUserLockedOut($userId, 'password_reset')) {
                $remainingTime = $this->otpService->getRemainingLockoutTime($userId, 'password_reset');
                $minutes = ceil($remainingTime / 60);
                
                return [
                    'status' => 'error', 
                    'message' => "Too many failed attempts. Please try again after {$minutes} minutes.",
                    'lockout' => true,
                    'remaining_time' => $remainingTime
                ];
            }
            
            return ['status' => 'error', 'message' => 'Invalid OTP. Please try again.'];
        }
        
        // Update password
        $updated = $this->user->updatePassword($userId, $newPassword);
        
        if (!$updated) {
            return ['status' => 'error', 'message' => 'Failed to update password'];
        }
        
        // Clear password reset data
        unset($_SESSION['password_reset_user']);
        
        // Log password reset
        $this->loggingService->logAction('Password reset', [
            'user_id' => $userId
        ]);
        
        return ['status' => 'success', 'message' => 'Password has been reset successfully'];
    }
    
    /**
     * Check if user session is valid
     * @return bool True if session is valid, false otherwise
     */
    public function validateSession() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isUserLoggedIn()) {
            // Try to restore from persistent login
            return $this->checkPersistentLogin();
        }
        
        // Check if session has expired (idle timeout - 30 minutes)
        if (isset($_SESSION['last_login']) && (time() - $_SESSION['last_login'] > 1800)) {
            $this->logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_login'] = time();
        return true;
    }
    
    /**
     * Check for persistent login token and authenticate if valid
     * @return bool True if authentication successful, false otherwise
     */
    private function checkPersistentLogin() {
        if (!isset($_COOKIE['auth_token'])) {
            return false;
        }
        
        $token = $_COOKIE['auth_token'];
        $userData = $this->user->getUserByToken($token);
        
        if (!$userData) {
            // Invalid or expired token
            setcookie('auth_token', '', time() - 3600, '/', '', true, true);
            return false;
        }
        
        // Valid token, complete login
        $this->completeLogin($userData, true);
        return true;
    }
}

class LoggingService {

    private $db;



    public function __construct($db) {

        $this->db = $db;

    }



    public function logAction($action, $details) {

        // Implement logging logic here

    }

}
?>