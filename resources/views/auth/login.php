<?php
// Start session at the top
session_start();

// Include required files
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/../../../src/models/User.php';
require_once __DIR__ . '/../../../src/services/OTPService.php';
require_once __DIR__ . '/../../../src/services/LoggingService.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/controllers/AuthController.php';

// Initialize the authentication controller
$authController = new AuthController();

// Initialize message variables
$message = '';
$message_type = '';

// Generate CSRF token for the login form
$csrf_token = generate_csrf_token();

// Check if the user is already logged in (session or remember me)
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $message = "Invalid CSRF token. Please refresh and try again.";
        $message_type = "danger";
    } else {
        // Capture form input
        $_POST['sevarth_id'] = $_POST['username'];
        $rememberMe = isset($_POST['remember_me']) ? true : false;

        // Attempt login
        $response = $authController->login();

        // Handle login responses
        if ($response['status'] === 'success') {
            header("Location: dashboard.php");
            exit();
        } elseif ($response['status'] === 'otp_required') {
            header("Location: otp-verification.php");
            exit();
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
  <title>Login - CRD</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/login.css">
</head>
<body>
  <div class="container">
    <div class="card mx-auto mt-5" style="max-width: 400px;">
      <div class="card-body p-4">
        <div class="logo-container text-center mb-3">
          <img src="logo.png" alt="Logo" class="logo" style="max-width: 100px;">
        </div>
        <h4 class="text-center mb-4">Login</h4>
        
        <?php if (!empty($message)): ?>
          <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
          <!-- CSRF token field -->
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          
          <div class="mb-3">
            <input type="text" class="form-control" name="username" placeholder="SEVARTH ID" required>
          </div>
          
          <div class="mb-4">
            <input type="password" class="form-control" name="password" placeholder="Password" required>
          </div>
          
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="remember_me" id="remember_me">
            <label class="form-check-label" for="remember_me">Remember Me</label>
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-success">LOGIN TO ACCOUNT</button>
          </div>
          
          <div class="link-container mt-3 d-flex justify-content-between">
            <a href="forgot-password.php">Forgot password?</a>
            <a href="signup.php">Signup</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

