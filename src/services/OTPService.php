<?php
/**
 * OTP Service Class
 * 
 * Handles generation, sending, storage and validation of One Time Passwords (OTPs)
 * for two-factor authentication and secure operations
 */
class OTPService {
    private $conn;
    private $otpLength = 6;
    private $otpExpiry = 600; // 10 minutes in seconds
    private $maxRetries = 3;
    private $lockoutTime = 1800; // 30 minutes in seconds
    
    /**
     * Constructor
     * @param Database $db Database connection object
     */
    public function __construct($db) {
        $this->conn = $db->getConnection();
        require_once __DIR__ . '/../helpers/auth_helper.php';
    }
    
    /**
     * Generate a random OTP
     * @param int $length Length of OTP (default is 6)
     * @return string Generated OTP
     */
    public function generateOTP($length = null) {
        $otpLength = $length ?? $this->otpLength;
        
        // Use random_int for secure random generation
        $min = pow(10, $otpLength - 1);
        $max = pow(10, $otpLength) - 1;
        $otp = (string)random_int($min, $max);
        
        return $otp;
    }
    
    /**
     * Store OTP in database
     * @param int $userId User ID
     * @param string $otp Generated OTP
     * @param string $purpose Purpose of OTP (login, password_reset, etc.)
     * @return bool Success status
     */
    public function storeOTP($userId, $otp, $purpose = 'login') {
        try {
            // First, invalidate any existing OTPs for this user and purpose
            $query = "UPDATE otp_records 
                    SET is_active = 0 
                    WHERE user_id = :user_id AND purpose = :purpose";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->execute();
            
            // Hash the OTP for secure storage
            $hashedOTP = password_hash($otp, PASSWORD_DEFAULT);
            
            // Insert new OTP record
            $query = "INSERT INTO otp_records 
                    (user_id, otp_hash, created_at, expires_at, purpose, is_active, attempt_count) 
                    VALUES 
                    (:user_id, :otp_hash, NOW(), DATE_ADD(NOW(), INTERVAL :expiry SECOND), :purpose, 1, 0)";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':otp_hash', $hashedOTP);
            $stmt->bindParam(':expiry', $this->otpExpiry);
            $stmt->bindParam(':purpose', $purpose);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error storing OTP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate OTP and send it to user
     * @param int $userId User ID
     * @param string $email User email
     * @param string $mobile User mobile number
     * @param string $purpose Purpose of OTP
     * @return bool Success status
     */
    public function generateAndSendOTP($userId, $email, $mobile, $purpose = 'login') {
        // Check if user is locked out
        if ($this->isUserLockedOut($userId, $purpose)) {
            return false;
        }
        
        // Generate OTP
        $otp = $this->generateOTP();
        
        // Store OTP in database
        $stored = $this->storeOTP($userId, $otp, $purpose);
        
        if (!$stored) {
            return false;
        }
        
        // Send OTP via both SMS and email for redundancy
        $smsResult = $this->sendOTPViaSMS($mobile, $otp, $purpose);
        $emailResult = $this->sendOTPViaEmail($email, $otp, $purpose);
        
        // Log the OTP sending attempt
        $this->logOTPSendAttempt($userId, $smsResult, $emailResult, $purpose);
        
        // Return true if at least one method was successful
        return ($smsResult || $emailResult);
    }
    
    /**
     * Send OTP via SMS
     * @param string $mobile Mobile number
     * @param string $otp OTP to send
     * @param string $purpose Purpose of OTP
     * @return bool Success status
     */
    private function sendOTPViaSMS($mobile, $otp, $purpose) {
        // Replace with your SMS gateway integration
        // This is a placeholder implementation
        try {
            $message = "Your OTP for " . ucfirst($purpose) . " is: " . $otp . ". Valid for " 
                      . ($this->otpExpiry / 60) . " minutes. Do not share with anyone.";
            
            // Log the SMS attempt without the actual OTP for security
            error_log("SMS would be sent to {$mobile} for purpose: {$purpose}");
            
            // Implement actual SMS sending logic here
            // For development/testing, return true to simulate successful sending
            return true;
            
        } catch (Exception $e) {
            error_log("Error sending SMS OTP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send OTP via Email
     * @param string $email Email address
     * @param string $otp OTP to send
     * @param string $purpose Purpose of OTP
     * @return bool Success status
     */
    private function sendOTPViaEmail($email, $otp, $purpose) {
        // This is a placeholder implementation
        try {
            $subject = "Your OTP for Central Resource Dashboard";
            
            // Create email body
            $body = "Dear User,\n\n";
            $body .= "Your One-Time Password (OTP) for " . ucfirst($purpose) . " is: " . $otp . "\n\n";
            $body .= "This OTP is valid for " . ($this->otpExpiry / 60) . " minutes.\n";
            $body .= "If you did not request this OTP, please contact the administrator immediately.\n\n";
            $body .= "Regards,\nCentral Resource Dashboard Team\nMaharashtra State Police";
            
            // Set headers
            $headers = "From: no-reply@crd.maharashtrapolice.gov.in\r\n";
            $headers .= "Reply-To: no-reply@crd.maharashtrapolice.gov.in\r\n";
            
            // Log the email attempt without the actual OTP for security
            error_log("Email would be sent to {$email} for purpose: {$purpose}");
            
            // Implement actual email sending logic here
            // For development/testing, return true to simulate successful sending
            return true;
            
            // Uncomment for actual implementation:
            // return mail($email, $subject, $body, $headers);
            
        } catch (Exception $e) {
            error_log("Error sending Email OTP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify OTP entered by user
     * @param int $userId User ID
     * @param string $inputOTP OTP entered by user
     * @param string $purpose Purpose of OTP
     * @return bool True if OTP is valid, false otherwise
     */
    public function verifyOTP($userId, $inputOTP, $purpose = 'login') {
        try {
            // Check if user is locked out
            if ($this->isUserLockedOut($userId, $purpose)) {
                return false;
            }
            
            // Get the active OTP record for this user
            $query = "SELECT id, otp_hash, created_at, expires_at, attempt_count 
                    FROM otp_records 
                    WHERE user_id = :user_id 
                    AND purpose = :purpose 
                    AND is_active = 1 
                    AND expires_at > NOW()";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // No active OTP found or expired
                return false;
            }
            
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Increment attempt counter
            $this->incrementAttemptCounter($otpRecord['id']);
            
            // Check if max attempts exceeded
            if ($otpRecord['attempt_count'] >= $this->maxRetries) {
                $this->lockoutUser($userId, $purpose);
                return false;
            }
            
            // Verify OTP
            if (password_verify($inputOTP, $otpRecord['otp_hash'])) {
                // Mark as used
                $this->markOTPAsUsed($otpRecord['id']);
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error verifying OTP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if OTP has expired
     * @param int $otpId OTP record ID
     * @return bool True if expired, false otherwise
     */
    public function checkOTPExpiry($otpId) {
        $query = "SELECT expires_at FROM otp_records WHERE id = :otp_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':otp_id', $otpId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return true; // Consider non-existent OTPs as expired
        }
        
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        $expiryTime = strtotime($record['expires_at']);
        
        return (time() > $expiryTime);
    }
    
    /**
     * Increment the attempt counter for an OTP
     * @param int $otpId OTP record ID
     * @return bool Success status
     */
    private function incrementAttemptCounter($otpId) {
        try {
            $query = "UPDATE otp_records 
                    SET attempt_count = attempt_count + 1 
                    WHERE id = :otp_id";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':otp_id', $otpId);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error incrementing attempt counter: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark an OTP as used (inactive)
     * @param int $otpId OTP record ID
     * @return bool Success status
     */
    private function markOTPAsUsed($otpId) {
        try {
            $query = "UPDATE otp_records 
                    SET is_active = 0, used_at = NOW() 
                    WHERE id = :otp_id";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':otp_id', $otpId);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error marking OTP as used: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lock out a user after too many failed attempts
     * @param int $userId User ID
     * @param string $purpose Purpose of OTP
     * @return bool Success status
     */
    private function lockoutUser($userId, $purpose) {
        try {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->lockoutTime);
            
            $query = "INSERT INTO otp_lockouts 
                    (user_id, locked_until, purpose) 
                    VALUES 
                    (:user_id, :locked_until, :purpose)
                    ON DUPLICATE KEY UPDATE 
                    locked_until = :locked_until";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':locked_until', $lockUntil);
            $stmt->bindParam(':purpose', $purpose);
            
            // Log the lockout
            error_log("User ID {$userId} locked out for OTP {$purpose} until {$lockUntil}");
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error locking out user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a user is currently locked out
     * @param int $userId User ID
     * @param string $purpose Purpose of OTP
     * @return bool True if locked out, false otherwise
     */
    public function isUserLockedOut($userId, $purpose) {
        try {
            $query = "SELECT locked_until 
                    FROM otp_lockouts 
                    WHERE user_id = :user_id 
                    AND purpose = :purpose 
                    AND locked_until > NOW()";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->execute();
            
            return ($stmt->rowCount() > 0);
            
        } catch (PDOException $e) {
            error_log("Error checking lockout status: " . $e->getMessage());
            return false; // Default to not locked out on error
        }
    }
    
    /**
     * Get remaining lockout time in seconds
     * @param int $userId User ID
     * @param string $purpose Purpose of OTP
     * @return int Seconds remaining in lockout or 0 if not locked
     */
    public function getRemainingLockoutTime($userId, $purpose) {
        try {
            $query = "SELECT locked_until 
                    FROM otp_lockouts 
                    WHERE user_id = :user_id 
                    AND purpose = :purpose 
                    AND locked_until > NOW()";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $lockedUntil = strtotime($row['locked_until']);
                $remainingSeconds = $lockedUntil - time();
                return ($remainingSeconds > 0) ? $remainingSeconds : 0;
            }
            
            return 0;
            
        } catch (PDOException $e) {
            error_log("Error getting lockout time: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Log OTP sending attempts
     * @param int $userId User ID
     * @param bool $smsStatus SMS delivery status
     * @param bool $emailStatus Email delivery status
     * @param string $purpose Purpose of OTP
     * @return bool Success status
     */
    private function logOTPSendAttempt($userId, $smsStatus, $emailStatus, $purpose) {
        try {
            $query = "INSERT INTO otp_send_logs 
                    (user_id, sms_status, email_status, purpose, ip_address, user_agent) 
                    VALUES 
                    (:user_id, :sms_status, :email_status, :purpose, :ip_address, :user_agent)";
                    
            $stmt = $this->conn->prepare($query);
            
            $smsStatusCode = $smsStatus ? 'SUCCESS' : 'FAILED';
            $emailStatusCode = $emailStatus ? 'SUCCESS' : 'FAILED';
            $ipAddress = get_client_ip();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':sms_status', $smsStatusCode);
            $stmt->bindParam(':email_status', $emailStatusCode);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error logging OTP send attempt: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set OTP configuration parameters
     * @param array $config Configuration array with keys: otpLength, otpExpiry, maxRetries, lockoutTime
     */
    public function setConfig($config) {
        if (isset($config['otpLength'])) {
            $this->otpLength = (int)$config['otpLength'];
        }
        
        if (isset($config['otpExpiry'])) {
            $this->otpExpiry = (int)$config['otpExpiry'];
        }
        
        if (isset($config['maxRetries'])) {
            $this->maxRetries = (int)$config['maxRetries'];
        }
        
        if (isset($config['lockoutTime'])) {
            $this->lockoutTime = (int)$config['lockoutTime'];
        }
    }
}
?>