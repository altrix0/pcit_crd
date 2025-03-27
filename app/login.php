<?php
// Start the session at the very beginning
session_start();

// Initialize message variables
$message = '';
$message_type = '';

// Include the database connection
require_once 'database.php';

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $message = 'Please enter both username and password';
        $message_type = 'danger';
    } else {
        try {
            // Query for the user with the given sevarth_id
            // Updated to explicitly select login_user_role
            $stmt = $pdo->prepare('SELECT employee_id, sevarth_id, password_hash, login_user_role FROM employee WHERE sevarth_id = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            // Verify user exists and password is correct
            if ($user && password_verify($password, $user['password_hash'])) {
                // Authentication successful
                // Store minimal user info in session
                $_SESSION['user_id'] = $user['employee_id'];
                $_SESSION['sevarth_id'] = $user['sevarth_id'];
                $_SESSION['user_role'] = $user['login_user_role'];
                
                // Redirect based on user role
                if ($_SESSION['user_role'] == 6) {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: EndUser/dashboard.php');
                }
                exit;
            } else {
                // Authentication failed
                $message = 'Invalid username or password';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            // Database error
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
  <title>Login - CRD</title>
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
        <h4 class="text-center mb-4">Login</h4>
        
        <?php if (!empty($message)): ?>
          <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
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
            <button type="submit" class="btn btn-custom">LOGIN TO ACCOUNT</button>
          </div>
          
          <div class="link-container mt-3 d-flex justify-content-between">
            <a href="forgot-password.php">Forgot password?</a>
            <a href="signup.php">Register</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

