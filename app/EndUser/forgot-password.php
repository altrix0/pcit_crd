<?php
// Start the session
session_start();

// Initialize message variables
$message = '';
$message_type = '';
$reset_method = isset($_POST['reset_method']) ? $_POST['reset_method'] : 'sevarth_id';

// Include the database connection
require_once 'database.php';

// Process OTP verification for Mobile Number method
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

// Process the form submission for Sevarth ID method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset']) && $_POST['reset_method'] === 'sevarth_id') {
    // Get the form data
    $sevarth_id = $_POST['sevarth_id'] ?? '';
    
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
  <title>Forgot Password - CRD</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/login.css">
</head>
<body>
  <div class="container">
    <div class="card mx-auto mt-5" style="max-width: 500px;">
      <div class="card-body p-4">
        <div class="logo-container text-center mb-3">
          <img src="logo.png" alt="Logo" class="logo" style="max-width: 100px;">
        </div>
        <h4 class="text-center mb-4">Forgot Password</h4>
        
        <?php if (!empty($message)): ?>
          <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <?php echo $message; // Not using htmlspecialchars to allow HTML in the message ?>
          </div>
        <?php endif; ?>
        
        <div class="mb-4">
          <ul class="nav nav-pills nav-justified" id="resetMethodTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $reset_method === 'sevarth_id' ? 'active' : ''; ?>" 
                      id="sevarth-tab" data-bs-toggle="tab" data-bs-target="#sevarth-pane" 
                      type="button" role="tab" aria-selected="<?php echo $reset_method === 'sevarth_id' ? 'true' : 'false'; ?>">
                Sevarth ID
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $reset_method === 'mobile' ? 'active' : ''; ?>" 
                      id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobile-pane" 
                      type="button" role="tab" aria-selected="<?php echo $reset_method === 'mobile' ? 'true' : 'false'; ?>">
                Mobile Number
              </button>
            </li>
          </ul>
          
          <div class="tab-content mt-3" id="resetMethodTabsContent">
            <!-- Sevarth ID Method -->
            <div class="tab-pane fade <?php echo $reset_method === 'sevarth_id' ? 'show active' : ''; ?>" id="sevarth-pane" role="tabpanel" aria-labelledby="sevarth-tab">
              <form method="POST" action="forgot-password.php" autocomplete="off">
                <input type="hidden" name="reset_method" value="sevarth_id">
                <div class="mb-3">
                  <label for="sevarth_id" class="form-label">Enter your SEVARTH ID</label>
                  <input type="text" class="form-control" id="sevarth_id" name="sevarth_id" placeholder="SEVARTH ID" required>
                  <small class="form-text text-muted">A password reset link will be sent to your registered email.</small>
                </div>
                
                <div class="d-grid">
                  <button type="submit" name="request_reset" class="btn btn-custom">Reset Password</button>
                </div>
              </form>
            </div>
            
            <!-- Mobile Number Method -->
            <div class="tab-pane fade <?php echo $reset_method === 'mobile' ? 'show active' : ''; ?>" id="mobile-pane" role="tabpanel" aria-labelledby="mobile-tab">
              <form method="POST" action="forgot-password.php" autocomplete="off">
                <input type="hidden" name="reset_method" value="mobile">
                <div class="mb-3">
                  <label for="mobile" class="form-label">Enter your Registered Mobile Number</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="mobile" name="mobile" placeholder="10-digit Mobile Number" required maxlength="10" pattern="[0-9]{10}">
                    <button type="submit" name="send_otp" class="btn btn-outline-primary">Send OTP</button>
                  </div>
                </div>
                
                <?php if (isset($_SESSION['otp'])): ?>
                <div class="mb-3">
                  <label for="otp" class="form-label">Enter OTP</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="otp" name="otp" placeholder="Enter OTP" required>
                    <button type="submit" name="verify_otp" class="btn btn-outline-success">Verify OTP</button>
                  </div>
                  <small class="form-text text-muted">Enter the OTP sent to your mobile number</small>
                </div>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>
        
        <div class="text-center mt-3">
          <a href="login.php">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Handle tab switching to update the hidden reset_method field
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function(tab) {
      tab.addEventListener('shown.bs.tab', function(event) {
        const activeTabId = event.target.id;
        const resetMethod = activeTabId === 'sevarth-tab' ? 'sevarth_id' : 'mobile';
        document.querySelectorAll('input[name="reset_method"]').forEach(function(input) {
          input.value = resetMethod;
        });
      });
    });
    
    // Mobile number validation - only allow digits and limit to 10
    document.getElementById('mobile').addEventListener('input', function() {
      let value = this.value.replace(/\D/g, ''); // Remove non-digits
      if (value.length > 10) {
        value = value.slice(0, 10); // Limit to 10 digits
      }
      this.value = value;
    });
  });
  </script>
</body>
</html>