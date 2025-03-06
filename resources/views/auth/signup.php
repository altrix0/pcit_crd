<?php
require_once '../src/config/database.php';
$message = '';
$message_type = '';
$retirement_date = '';
$otp = '';
$is_otp_sent = false;
$is_otp_verified = false;

// Function to format Aadhar number with spaces
function formatAadhar($aadhar) {
    $aadhar = preg_replace('/\D/', '', $aadhar); // Remove non-digits
    return trim(chunk_split($aadhar, 4, ' ')); // Add space after every 4 digits
}

// Generate OTP
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Calculate retirement date based on DOB
function calculateRetirementDate($dob) {
    return date('Y-m-d', strtotime($dob . ' + 58 years'));
}

// Handle OTP sending
if (isset($_POST['send_otp'])) {
    // Password validation
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $message = "Passwords do not match!";
        $message_type = "danger";
    } else {
        // In a real application, you would send OTP via SMS/Email
        $_SESSION['otp'] = generateOTP();
        $otp = $_SESSION['otp']; // For demonstration purposes only
        $is_otp_sent = true;
        $message = "OTP sent successfully!";
        $message_type = "success";
        // Store password in session for form submission
        $_SESSION['password'] = $_POST['password'];
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    if (isset($_SESSION['otp']) && $_SESSION['otp'] == $_POST['otp']) {
        $is_otp_verified = true;
        $message = "OTP verified successfully!";
        $message_type = "success";
    } else {
        $message = "Invalid OTP!";
        $message_type = "danger";
    }
}

// Calculate retirement date on DOB change
if (isset($_POST['calculate_retirement'])) {
    if (!empty($_POST['dob'])) {
        $retirement_date = calculateRetirementDate($_POST['dob']);
        echo $retirement_date;
        exit;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Validate OTP verification
    if (!isset($_SESSION['otp']) || !$_SESSION['otp'] || $_SESSION['otp'] != $_POST['otp']) {
        $message = "Please verify OTP before submitting!";
        $message_type = "danger";
    } else {
        // Capture and sanitize form data
        $sevarth_id = htmlspecialchars(trim($_POST['sevarth_id']));
        $first_name = htmlspecialchars(trim($_POST['first_name']));
        $last_name = htmlspecialchars(trim($_POST['last_name']));
        $father_name = htmlspecialchars(trim($_POST['father_name']));
        $mother_name = htmlspecialchars(trim($_POST['mother_name']));
        $spouse_name = htmlspecialchars(trim($_POST['spouse_name']));
        $dob = $_POST['dob'];
        $mobile = preg_replace('/\D/', '', $_POST['mobile']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $aadhar_number = preg_replace('/\D/', '', $_POST['aadhar_number']);
        $current_posting = htmlspecialchars(trim($_POST['current_posting']));
        $reporting_person = htmlspecialchars(trim($_POST['reporting_person']));
        $password = password_hash($_SESSION['password'], PASSWORD_DEFAULT); // Hash the password
        $loginuser_role = 'employee'; // Default role

        // Calculate Retirement Date
        $retirement_date = calculateRetirementDate($dob);

        // Insert data into the database (uid is auto-increment)
        $stmt = $conn->prepare("INSERT INTO employees (sevarth_id, first_name, last_name, father_name, mother_name, spouse_name, dob, mobile, email, aadhar_number, retirement_date, current_posting, loginuser_role, reporting_person, password) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssssssss", $sevarth_id, $first_name, $last_name, $father_name, $mother_name, $spouse_name, $dob, $mobile, $email, $aadhar_number, $retirement_date, $current_posting, $loginuser_role, $reporting_person, $password);

        if ($stmt->execute()) {
            $message = "Employee signed in successfully!";
            $message_type = "success";
            
            // Reset form after successful submission
            unset($_SESSION['otp']);
            unset($_SESSION['password']);
            $is_otp_sent = false;
            $is_otp_verified = false;
            
            // Redirect to login page after successful registration
            header("Location: login.php?registered=1");
            exit;
        } else {
            $message = "Error: Could not sign in employee. " . $stmt->error;
            $message_type = "danger";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Sign-in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/signup.css">
</head>

<body>
    <div class="container">
        <div class="card mx-auto mt-5" style="max-width: 900px;">
            <div class="card-body p-5">
                <h3 class="text-center mb-4 text-uppercase" style="color: #180153;">Sign-in</h3>

                <?php if ($message) : ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Sevarth ID</label>
                            <input type="text" class="form-control" name="sevarth_id" placeholder="Sevarth ID" required value="<?php echo isset($_POST['sevarth_id']) ? htmlspecialchars($_POST['sevarth_id']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" placeholder="First Name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" placeholder="Last Name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="dob" required value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ''; ?>" onchange="updateRetirementDate(this.value)">
                        </div>
                        <div class="col-md-6">
                            <label for="retirement_date" class="form-label">Retirement Date (Auto-generated)</label>
                            <input type="text" class="form-control" name="retirement_date" id="retirement_date" value="<?php echo $retirement_date; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father Name</label>
                            <input type="text" class="form-control" name="father_name" placeholder="Father Name" required value="<?php echo isset($_POST['father_name']) ? htmlspecialchars($_POST['father_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother Name</label>
                            <input type="text" class="form-control" name="mother_name" placeholder="Mother Name" required value="<?php echo isset($_POST['mother_name']) ? htmlspecialchars($_POST['mother_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Spouse Name</label>
                            <input type="text" class="form-control" name="spouse_name" placeholder="Spouse Name" value="<?php echo isset($_POST['spouse_name']) ? htmlspecialchars($_POST['spouse_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aadhar Number</label>
                            <input type="text" class="form-control" name="aadhar_number" placeholder="XXXX XXXX XXXX" maxlength="14" required value="<?php echo isset($_POST['aadhar_number']) ? formatAadhar($_POST['aadhar_number']) : ''; ?>" oninput="formatAadharInput(this)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email ID</label>
                            <input type="email" class="form-control" name="email" placeholder="abc@domain.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" name="mobile" placeholder="Mobile Number" required value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Posting</label>
                            <input type="text" class="form-control" name="current_posting" placeholder="Current Posting" required value="<?php echo isset($_POST['current_posting']) ? htmlspecialchars($_POST['current_posting']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporting Person</label>
                            <input type="text" class="form-control" name="reporting_person" placeholder="Reporting Person" required value="<?php echo isset($_POST['reporting_person']) ? htmlspecialchars($_POST['reporting_person']) : ''; ?>">
                        </div>
                        <!-- New Password Fields -->
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Enter Password" required value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter Password" required value="<?php echo isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; ?>">
                            <div id="password-match-feedback" class="invalid-feedback">Passwords do not match!</div>
                        </div>
                    </div>
                    <br>
                    <div class="otp-section mt-4">
                        <label class="form-label">OTP Verification</label>
                        <input type="text" class="form-control mb-2" name="otp" placeholder="Enter OTP" required value="<?php echo $otp; ?>" <?php echo $is_otp_verified ? 'readonly' : ''; ?>>
                        <div class="d-flex gap-2">
                            <button type="submit" name="send_otp" class="btn btn-custom w-50" <?php echo $is_otp_verified ? 'disabled' : ''; ?>>Send OTP</button>
                            <button type="submit" name="verify_otp" class="btn btn-custom w-50" <?php echo $is_otp_verified ? 'disabled' : ''; ?>>Verify OTP</button>
                        </div>
                    </div>
                    <div class="signup-btn d-grid mt-4">
                        <button type="submit" name="submit" class="btn btn-custom w-100">SIGN IN</button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none" style="color: #180153;">Already a user? Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Single function to handle the retirement date calculation via AJAX
    function updateRetirementDate(dob) {
        if (dob) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('retirement_date').value = xhr.responseText;
                }
            };
            xhr.send('calculate_retirement=1&dob=' + dob);
        }
    }
    
    // Script to format Aadhar number with spaces after every 4 digits
    function formatAadharInput(input) {
        // Remove all non-digits
        let value = input.value.replace(/\D/g, '');
        
        // Limit to 12 digits
        if (value.length > 12) {
            value = value.slice(0, 12);
        }
        
        // Add spaces after every 4 digits
        let formattedValue = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        
        // Update the input value
        input.value = formattedValue;
    }
    
    // Script to validate passwords match in real-time
    document.addEventListener('DOMContentLoaded', function() {
        const passwordField = document.querySelector('input[name="password"]');
        const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
        const feedback = document.getElementById('password-match-feedback');
        
        function validatePasswords() {
            if (confirmPasswordField.value && passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.classList.add('is-invalid');
                feedback.style.display = 'block';
            } else {
                confirmPasswordField.classList.remove('is-invalid');
                feedback.style.display = 'none';
            }
        }
        
        passwordField.addEventListener('input', validatePasswords);
        confirmPasswordField.addEventListener('input', validatePasswords);
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>