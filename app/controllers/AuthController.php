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
        $this->otpService = new OTPService();
        $this->loggingService = new LoggingService($this->db);
    }
    
    /**
     * Handle user login
     * 
     * @param string $sevarthId User's Sevarth ID
     * @param string $password User's password
     * @param bool $rememberMe Whether to create a persistent login
     * @return array Response with status and message
     */
    public function login($sevarthId, $password, $rememberMe = false) {
        try {
            // Validate inputs
            if (empty($sevarthId) || empty($password)) {
                return [
                    'status' => 'error',
                    'message' => 'Sevarth ID and password are required'
                ];
            }
            
            // Get user by Sevarth ID
            $user = $this->user->getUserBySevarthId($sevarthId);
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid Sevarth ID or password'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid Sevarth ID or password'
                ];
            }
            
            // Check if user is active
            if (!$user['is_active']) {
                return [
                    'status' => 'error',
                    'message' => 'Account is inactive. Please contact administrator.'
                ];
            }
            
            // Check if OTP is required
            $isTrustedDevice = $this->checkTrustedDevice($user['uid']);
            
            if (!$isTrustedDevice) {
                // Generate and send OTP
                $otpSecret = $this->otpService->generateSecret();
                $this->user->updateOtpSecret($user['uid'], $otpSecret);
                $otp = $this->otpService->generateOTP($otpSecret);
                
                // In a real application, send OTP via SMS/email
                // For now, just store in session for demo
                $_SESSION['temp_otp'] = $otp;
                $_SESSION['temp_user_id'] = $user['uid'];
                
                return [
                    'status' => 'otp_required',
                    'message' => 'OTP has been sent to your registered mobile/email',
                    'user_id' => $user['uid']
                ];
            } else {
                // Complete login without OTP
                $this->completeLogin($user, $rememberMe);
                
                return [
                    'status' => 'success',
                    'message' => 'Login successful',
                    'user' => [
                        'uid' => $user['uid'],
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'role' => $user['role_name'],
                        'access_level' => $user['access_level']
                    ]
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Login failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify OTP and complete login process
     * 
     * @param int $userId User ID
     * @param string $otp One-time password entered by user
     * @param bool $rememberMe Whether to create a persistent login
     * @param bool $trustDevice Whether to trust this device for future logins
     * @return array Response with status and message
     */
    public function verifyOTP($userId, $otp, $rememberMe = false, $trustDevice = false) {
        try {
            // Get user data
            $user = $this->user->getUserById($userId);
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid user'
                ];
            }
            
            // Verify OTP
            $isValid = $this->otpService->verifyOTP($user['otp_secret'], $otp);
            
            if (!$isValid) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid OTP'
                ];
            }
            
            // Complete login
            $this->completeLogin($user, $rememberMe);
            
            // Mark device as trusted if requested
            if ($trustDevice) {
                $this->setTrustedDevice($user['uid']);
            }
            
            return [
                'status' => 'success',
                'message' => 'Login successful',
                'user' => [
                    'uid' => $user['uid'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => $user['role_name'],
                    'access_level' => $user['access_level']
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'OTP verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Complete the login process
     * 
     * @param array $user User data
     * @param bool $rememberMe Whether to create a persistent login
     */
    private function completeLogin($user, $rememberMe = false) {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['uid'];
        $_SESSION['sevarth_id'] = $user['sevarth_id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['access_level'] = $user['access_level'];
        $_SESSION['last_activity'] = time();
        
        // Update last login timestamp
        $this->user->updateLastLogin($user['uid']);
        
        // Create persistent login if requested
        if ($rememberMe) {
            $this->createPersistentLogin($user['uid']);
        }
        
        // Log login event
        $this->loggingService->logAction($user['uid'], 'Login');
    }
    
    /**
     * Create a persistent login token
     * 
     * @param int $userId User ID
     */
    private function createPersistentLogin($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Store token in database
        $this->user->createSession($userId, $token, $ip, $userAgent, $expires);
        
        // Set cookie with token
        setcookie('crd_auth', $token, strtotime('+90 days'), '/', '', true, true);
    }
    
    /**
     * Check if the current device is trusted for this user
     * 
     * @param int $userId User ID
     * @return bool True if device is trusted
     */
    private function checkTrustedDevice($userId) {
        // Check if persistent login cookie exists
        if (isset($_COOKIE['crd_auth'])) {
            $token = $_COOKIE['crd_auth'];
            
            // Validate token in database
            $session = $this->user->getSessionByToken($token);
            
            if ($session && $session['user_id'] == $userId && $session['is_active']) {
                // Check if session has expired
                if (strtotime($session['expires_at']) > time()) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Mark current device as trusted
     * 
     * @param int $userId User ID
     */
    private function setTrustedDevice($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Store token in database
        $this->user->createSession($userId, $token, $ip, $userAgent, $expires);
        
        // Set cookie with token
        setcookie('crd_auth', $token, strtotime('+90 days'), '/', '', true, true);
    }
    
    /**
     * Resume session from persistent login cookie
     * 
     * @return bool|array False if no valid session, user data if successful
     */
    public function resumeSession() {
        // Check if user is already logged in
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        
        // Check if persistent login cookie exists
        if (isset($_COOKIE['crd_auth'])) {
            $token = $_COOKIE['crd_auth'];
            
            // Validate token in database
            $session = $this->user->getSessionByToken($token);
            
            if ($session && $session['is_active']) {
                // Check if session has expired
                if (strtotime($session['expires_at']) > time()) {
                    // Get user data
                    $user = $this->user->getUserById($session['user_id']);
                    
                    if ($user && $user['is_active']) {
                        // Complete login
                        $this->completeLogin($user);
                        
                        return [
                            'status' => 'success',
                            'message' => 'Session resumed',
                            'user' => [
                                'uid' => $user['uid'],
                                'name' => $user['first_name'] . ' ' . $user['last_name'],
                                'role' => $user['role_name'],
                                'access_level' => $user['access_level']
                            ]
                        ];
                    }
                }
                
                // Session expired or user inactive, remove it
                $this->user->deactivateSession($token);
                setcookie('crd_auth', '', time() - 3600, '/', '', true, true);
            }
        }
        
        return false;
    }
    
    /**
     * Log out the current user
     * 
     * @return array Response with status and message
     */
    public function logout() {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        
        // Remove persistent login if exists
        if (isset($_COOKIE['crd_auth'])) {
            $token = $_COOKIE['crd_auth'];
            $this->user->deactivateSession($token);
            setcookie('crd_auth', '', time() - 3600, '/', '', true, true);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Log logout if user was logged in
        if ($userId) {
            $this->loggingService->logAction($userId, 'Logout');
        }
        
        return [
            'status' => 'success',
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Response with status and message
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get user data
            $user = $this->user->getUserById($userId);
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return [
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return [
                    'status' => 'error',
                    'message' => 'New password must be at least 8 characters long'
                ];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->user->updatePassword($userId, $hashedPassword);
            
            // Log password change
            $this->loggingService->logAction($userId, 'Password Change');
            
            return [
                'status' => 'success',
                'message' => 'Password changed successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Password change failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Request password reset
     * 
     * @param string $sevarthId User's Sevarth ID
     * @return array Response with status and message
     */
    public function requestPasswordReset($sevarthId) {
        try {
            // Get user by Sevarth ID
            $user = $this->user->getUserBySevarthId($sevarthId);
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }
            
            // Generate and send OTP
            $otpSecret = $this->otpService->generateSecret();
            $this->user->updateOtpSecret($user['uid'], $otpSecret);
            $otp = $this->otpService->generateOTP($otpSecret);
            
            // In a real application, send OTP via SMS/email
            // For now, just store in session for demo
            $_SESSION['temp_otp'] = $otp;
            $_SESSION['temp_user_id'] = $user['uid'];
            $_SESSION['reset_password'] = true;
            
            return [
                'status' => 'success',
                'message' => 'OTP has been sent to your registered mobile/email',
                'user_id' => $user['uid']
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Password reset request failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset password with OTP verification
     * 
     * @param int $userId User ID
     * @param string $otp One-time password
     * @param string $newPassword New password
     * @return array Response with status and message
     */
    public function resetPassword($userId, $otp, $newPassword) {
        try {
            // Get user data
            $user = $this->user->getUserById($userId);
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }
            
            // Verify OTP
            $isValid = $this->otpService->verifyOTP($user['otp_secret'], $otp);
            
            if (!$isValid) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid OTP'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return [
                    'status' => 'error',
                    'message' => 'New password must be at least 8 characters long'
                ];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->user->updatePassword($userId, $hashedPassword);
            
            // Clear OTP secret and reset flag
            $this->user->updateOtpSecret($userId, null);
            unset($_SESSION['reset_password']);
            unset($_SESSION['temp_otp']);
            unset($_SESSION['temp_user_id']);
            
            // Log password reset
            $this->loggingService->logAction($userId, 'Password Reset');
            
            return [
                'status' => 'success',
                'message' => 'Password reset successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Password reset failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if user has required access level
     * 
     * @param int $requiredLevel Required access level
     * @return bool True if user has required access level
     */
    public function hasAccessLevel($requiredLevel) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['access_level'] >= $requiredLevel;
    }
}