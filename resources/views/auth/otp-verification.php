<?php
// Start session at the top
session_start();

// Include required files (adjust paths as needed)
require_once '../src/config/database.php';
require_once '../app/models/User.php';
require_once '../app/services/OTPService.php';
require_once '../app/services/LoggingService.php';
require_once '../app/helpers/auth_helper.php';
require_once '../app/controllers/AuthController.php';

// Initialize the authentication controller
$authController = new AuthController();

// Initialize message variables for user feedback
$message = '';
$message_type = '';

// Generate a CSRF token for the OTP form
$csrf_token = generate_csrf_token();

// If the user is already logged in, redirect to dashboard
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = "Invalid CSRF token. Please refresh and try again.";
        $message_type = "danger";
    } else {
        // Capture OTP input (AuthController::verifyOTP() expects the OTP in $_POST['otp'])
        $otp = trim($_POST['otp']);
        // Also capture remember me option if provided
        $rememberMe = isset($_POST['remember_me']) ? true : false;
        
        // Call the verifyOTP method from AuthController
        $response = $authController->verifyOTP();

        // Handle responses
        if ($response['status'] === 'success') {
            header("Location: dashboard.php");
            exit();
        } elseif ($response['status'] === 'otp_required') {
            // If additional OTP attempts are needed, remain on the page
            $message = $response['message'];
            $message_type = 'warning';
        } else {
            $message = $response['message'];
            $message_type = ($response['status'] === 'error') ? 'danger' : 'warning';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - CRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="container">
        <div class="card mx-auto mt-5" style="max-width: 400px;">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <img src="logo.png" alt="CRD Logo" class="logo" style="max-width: 100px;">
                </div>
                <h4 class="text-center mb-4">OTP Verification</h4>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="otp-verification.php">
                    <!-- CSRF token field -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="mb-3">
                        <input type="text" name="otp" class="form-control" placeholder="Enter OTP" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember_me" class="form-check-input" id="remember_me">
                        <label class="form-check-label" for="remember_me">Remember Me</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">Verify OTP</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <a href="resend-otp.php">Resend OTP</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>