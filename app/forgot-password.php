<?php
// Start the session
session_start();

// Initialize message variables
$message = '';
$message_type = '';
$reset_method = isset($_POST['reset_method']) ? $_POST['reset_method'] : 'sevarth_id';

// Include the database connection
require_once 'database.php';

// Process OTP verification
if (isset($_POST['send_otp'])) {
    // Get the phone number
    $mobile = $_POST['mobile'] ?? '';
    
    if (empty($mobile)) {
        $message = 'Please enter your mobile number';
        $message_type = 'danger';
    } else {
        try {
            // Check if the user exists with the given mobile number
            $stmt = $pdo->prepare('SELECT * FROM employee WHERE mobile_number = :mobile LIMIT 1');
            $stmt->execute(['mobile' => $mobile]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate a 6-digit random OTP
                $otp = sprintf("%06d", mt_rand(0, 999999));
                
                // Store OTP in session
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_time'] = time();
                $_SESSION['reset_mobile'] = $mobile;
                
                // In a production environment, you would integrate with an SMS API here
                // For this example, we'll just store the OTP and display it (for testing purposes)
                $message = "OTP generated: $otp (In production, this would be sent via SMS)";
                $message_type = "info";
            } else {
                // User not found, but don't reveal this for security reasons
                $message = 'If your mobile number is registered, an OTP will be sent to you.';
                $message_type = 'info';
            }
        } catch (PDOException $e) {
            // Database error
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Verify OTP
if (isset($_POST['verify_otp']) && isset($_POST['otp'])) {
    $entered_otp = $_POST['otp'];
    
    if (!isset($_SESSION['otp'])) {
        $message = "Please generate an OTP first";
        $message_type = "warning";
    } elseif ($_SESSION['otp'] == $entered_otp) {
        try {
            // OTP verified, find user by mobile
            $stmt = $pdo->prepare('SELECT * FROM employee WHERE mobile_number = :mobile LIMIT 1');
            $stmt->execute(['mobile' => $_SESSION['reset_mobile']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate a reset token for the user
                $token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store the reset token in the database
                $update = $pdo->prepare('UPDATE employee SET reset_token = :token, reset_token_expiry = :expiry WHERE employee_id = :id');
                $update->execute([
                    'token' => $token,
                    'expiry' => $token_expiry,
                    'id' => $user['employee_id']
                ]);
                
                // Create reset link
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/pcit_crd/app/reset-password.php?token=$token";
                
                $_SESSION['otp_verified'] = true;
                $message = "Mobile verified successfully! You can now reset your password.";
                $message_type = "success";
                $message .= "<br><br>Reset link: <a href='$reset_link'>$reset_link</a>";
            } else {
                $message = "Something went wrong. Please try again.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Invalid OTP. Please try again";
        $message_type = "danger";
    }
}

// Process the form submission for Sevarth ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset']) && $_POST['reset_method'] === 'sevarth_id') {
    // Get the form data
    $sevarth_id = $_POST['sevarth_id'] ?? '';
}
    // Validate input
    if (empty($sevarth_id)) {
        $message = 'Please enter your SEVARTH ID';
        $message_type = 'danger';
    } else {
        try {
            // Check if the user exists
            $stmt = $pdo->prepare('SELECT * FROM employee WHERE sevarth_id = :sevarth_id LIMIT 1');
            $stmt->execute(['sevarth_id' => $sevarth_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // User exists, generate a reset token
                $token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store the reset token in the database
                $update = $pdo->prepare('UPDATE employee SET reset_token = :token, reset_token_expiry = :expiry WHERE sevarth_id = :sevarth_id');
                $update->execute([
                    'token' => $token,
                    'expiry' => $token_expiry,
                    'sevarth_id' => $sevarth_id
                ]);
                
                // Create reset link
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/pcit_crd/app/reset-password.php?token=$token";
                
                // Here you would normally send an email with the reset link
                // For this example, we'll just show the link on the page
                $message = "A password reset link has been generated. <br>In a real application, an email would be sent to the user with the link.";
                $message_type = 'success';
                
                // For testing purposes, also display the link
                $message .= "<br><br>Reset link: <a href='$reset_link'>$reset_link</a>";
                
                // Uncomment and customize this code to send a real email
                /*
                $to = $user['email_id'];
                $subject = "Password Reset Request";
                $email_message = "Hello,\n\nYou have requested to reset your password. Please click on the link below to reset your password:\n\n$reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";
                $headers = "From: noreply@pcitcrd.com";
                
                mail($to, $subject, $email_message, $headers);
                */
                
            } else {
                // User not found, but don't reveal this for security reasons
                $message = 'If your SEVARTH ID is registered, a password reset link will be sent to your email.';
                $message_type = 'info';
            }
        }}