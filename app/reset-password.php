<?php
// Start the session
session_start();

// Initialize message variables
$message = '';
$message_type = '';
$token_valid = false;
$token = '';

// Include the database connection
require_once 'database.php';

// Check if token exists in the URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Check if token is valid and not expired
        $stmt = $pdo->prepare('SELECT * FROM employee WHERE reset_token = :token AND reset_token_expiry > :now LIMIT 1');
        $stmt->execute([
            'token' => $token,
            'now' => date('Y-m-d H:i:s')
        ]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token_valid = true;
        } else {
            $message = 'Invalid or expired password reset token.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'danger';
    }
} else {
    $message = 'No reset token provided.';
    $message_type = 'danger';
}

// Process password reset form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $message = 'Please enter both password fields.';
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'danger';
    } else {
        try {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update the user's password and clear reset token
            $update = $pdo->prepare('UPDATE employee SET password_hash = :password_hash, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = :token');
            $update->execute([
                'password_hash' => $password_hash,
                'token' => $token
            ]);
            
            $message = 'Your password has been successfully updated. You can now login with your new password.';
            $message_type = 'success';
            
            // Reset token valid flag to hide the form
            $token_valid = false;
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - CRD</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/css/login.css">
</head>
<body>
  <div class="container">
    <div class="card mx-auto mt-5" style="max-width: 400px;">
      <div class="card-body p-4">
        <div class="logo-container text-center mb-3">
          <img src="logo.png" alt="Logo" class="logo" style="max-width: 100px;">
        </div>
        <h4 class="text-center mb-4">Reset Password</h4>
        
        <?php if (!empty($message)): ?>
          <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>
        
        <?php if ($token_valid): ?>
        <form method="POST">
          <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
          </div>
          
          <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-custom">Update Password</button>
          </div>
        </form>
        <?php elseif ($message_type === 'success'): ?>
          <div class="text-center mt-3">
            <a href="login.php" class="btn btn-primary">Go to Login</a>
          </div>
        <?php else: ?>
          <div class="text-center mt-3">
            <a href="forgot-password.php" class="btn btn-primary">Back to Forgot Password</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
