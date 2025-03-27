<?php
// 1. Start session and enable error reporting
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Include database connection
require_once 'database.php';

// Initialize message variables
$message = '';
$message_type = '';

// 4. Handle AJAX request for retirement date calculation
if (isset($_POST['calculate_retirement']) && isset($_POST['dob'])) {
    $dob = new DateTime($_POST['dob']);
    $retirement_date = clone $dob;
    $retirement_date->modify('+58 years');
    echo $retirement_date->format('Y-m-d');
    exit;
}

// Fetch roles from user_roles table
$roles_stmt = $pdo->query("SELECT role_id, role_name FROM user_roles WHERE role_name != 'Administrator' ORDER BY role_id");
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reporting employees for dropdown, grouped by level
$reporting_stmt = $pdo->prepare("
    SELECT id, designation, department_location, level
    FROM reporting_employees
    ORDER BY level DESC, designation, department_location
");
$reporting_stmt->execute();
$reporting_employees = $reporting_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group reporting employees by level for better organization in dropdown
$grouped_employees = [];
foreach ($reporting_employees as $employee) {
    $grouped_employees[$employee['level']][] = $employee;
}

// 3 & 7. Process OTP verification
if (isset($_POST['send_otp'])) {
    // Generate a 6-digit random OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    
    // Store OTP in session
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    
    // In a production environment, you would integrate with an SMS API here
    // For this example, we'll just store the OTP and display it (for testing purposes)
    $message = "OTP generated: $otp (In production, this would be sent via SMS)";
    $message_type = "info";
    
    // No need for further processing
    // The page will reload with the message displayed
}

// Verify OTP
if (isset($_POST['verify_otp']) && isset($_POST['otp'])) {
    $entered_otp = $_POST['otp'];
    
    if (!isset($_SESSION['otp'])) {
        $message = "Please generate an OTP first";
        $message_type = "warning";
    } elseif ($_SESSION['otp'] == $entered_otp) {
        $_SESSION['otp_verified'] = true;
        $message = "OTP verified successfully";
        $message_type = "success";
    } else {
        $message = "Invalid OTP. Please try again";
        $message_type = "danger";
    }
}

// 3. Process form submission for signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    // Check if OTP is verified
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        $message = "Please verify your mobile number with OTP before submitting";
        $message_type = "warning";
    } else {
        // Retrieve and sanitize all inputs
        $sevarth_id = isset($_POST['sevarth_id']) ? htmlspecialchars(trim($_POST['sevarth_id']), ENT_QUOTES, 'UTF-8') : '';
        $first_name = isset($_POST['first_name']) ? htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8') : '';
        $last_name = isset($_POST['last_name']) ? htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8') : '';
        $dob = isset($_POST['dob']) ? htmlspecialchars(trim($_POST['dob']), ENT_QUOTES, 'UTF-8') : '';
        $father_name = isset($_POST['father_name']) ? htmlspecialchars(trim($_POST['father_name']), ENT_QUOTES, 'UTF-8') : '';
        $mother_name = isset($_POST['mother_name']) ? htmlspecialchars(trim($_POST['mother_name']), ENT_QUOTES, 'UTF-8') : '';
        $spouse_name = isset($_POST['spouse_name']) ? htmlspecialchars(trim($_POST['spouse_name']), ENT_QUOTES, 'UTF-8') : '';
        $aadhar_number = preg_replace('/\D/', '', isset($_POST['aadhar_number']) ? trim($_POST['aadhar_number']) : ''); // Remove all non-digits
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $mobile = preg_replace('/\D/', '', isset($_POST['mobile']) ? trim($_POST['mobile']) : ''); // Remove all non-digits
        $current_posting = isset($_POST['current_posting']) ? htmlspecialchars(trim($_POST['current_posting']), ENT_QUOTES, 'UTF-8') : '';
        $reporting_person_id = filter_input(INPUT_POST, 'reporting_person', FILTER_VALIDATE_INT);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $retirement_date = isset($_POST['retirement_date']) ? htmlspecialchars(trim($_POST['retirement_date']), ENT_QUOTES, 'UTF-8') : '';
        $role_id = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);

        // Remove code that converts reporting_person_id to designation string
        // No need to get reporting person's name from ID since we'll store the ID directly

        // 8. Validate inputs
        $errors = [];

        // Check required fields
        $required_fields = [
            'sevarth_id', 'first_name', 'last_name', 'dob', 'father_name',
            'mother_name', 'aadhar_number', 'email', 'mobile', 'current_posting',
            'role', 'password', 'confirm_password', 'retirement_date'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($$field) && $field != 'role') {  // Skip 'role' as it's handled differently
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }

        // Validate role
        if (!$role_id) {
            $errors[] = "Role selection is required";
        }
        
        // Validate reporting person
        if (!$reporting_person_id) {
            $errors[] = "Reporting person selection is required";
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Validate password match
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        // Password length validation
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Validate mobile number (must be 10 digits)
        if (strlen($mobile) !== 10) {
            $errors[] = "Mobile number must be 10 digits";
        }
        
        // Validate Aadhar number (12 digits)
        if (strlen($aadhar_number) !== 12) {
            $errors[] = "Aadhar number must be 12 digits";
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            try {
                // Check if sevarth_id already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE sevarth_id = ?");
                $stmt->execute([$sevarth_id]);
                if ($stmt->fetchColumn() > 0) {
                    $message = "Sevarth ID already registered";
                    $message_type = "danger";
                } else {
                    // 9. Insert new user into database
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Use the selected role_id instead of hardcoded value
                    // Store reporting_person_id directly as an integer
                    $stmt = $pdo->prepare("
                        INSERT INTO employee (
                            sevarth_id, password_hash, first_name, last_name, 
                            father_name, mother_name, spouse_name, dob,
                            retirement_date, mobile_number, email_id, aadhar_number, 
                            login_user_role, reporting_person
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $sevarth_id, $hash, $first_name, $last_name, 
                        $father_name, $mother_name, $spouse_name, $dob,
                        $retirement_date, $mobile, $email, $aadhar_number, 
                        $role_id, $reporting_person_id // Pass reporting_person_id as integer directly
                    ]);
                    
                    // 10. Clear the session OTP data
                    unset($_SESSION['otp']);
                    unset($_SESSION['otp_time']);
                    unset($_SESSION['otp_verified']);
                    
                    // Set success message and redirect
                    $_SESSION['signup_success'] = "Registration successful! Please login with your credentials.";
                    header("Location: login.php");
                    exit;
                }
            } catch (PDOException $e) {
                $message = "Registration error: " . $e->getMessage();
                $message_type = "danger";
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/signup.css">
</head>

<body>
    <div class="container">
        <div class="card mx-auto mt-5" style="max-width: 900px;">
            <div class="card-body p-5">
                <h3 class="text-center mb-4 text-uppercase" style="color: #180153;">Registration</h3>

                <?php if ($message) : ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="signupForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Sevarth ID</label>
                            <input type="text" class="form-control" name="sevarth_id" id="sevarth_id" placeholder="Sevarth ID" required value="<?php echo isset($_POST['sevarth_id']) ? htmlspecialchars($_POST['sevarth_id']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid Sevarth ID (numbers only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="first_name" placeholder="First Name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid first name (alphabetic only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="last_name" placeholder="Last Name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid last name (alphabetic only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="dob" id="dob" required value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Retirement Date</label>
                            <input type="date" class="form-control" name="retirement_date" id="retirement_date" readonly value="<?php echo isset($_POST['retirement_date']) ? htmlspecialchars($_POST['retirement_date']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" class="form-control" name="father_name" id="father_name" placeholder="Father's Name" required value="<?php echo isset($_POST['father_name']) ? htmlspecialchars($_POST['father_name']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid father's name (alphabetic characters and spaces only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" class="form-control" name="mother_name" id="mother_name" placeholder="Mother's Name" required value="<?php echo isset($_POST['mother_name']) ? htmlspecialchars($_POST['mother_name']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid mother's name (alphabetic characters and spaces only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Spouse's Name (if applicable)</label>
                            <input type="text" class="form-control" name="spouse_name" id="spouse_name" placeholder="Spouse's Name" value="<?php echo isset($_POST['spouse_name']) ? htmlspecialchars($_POST['spouse_name']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid spouse's name (alphabetic characters and spaces only)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aadhar Number</label>
                            <input type="text" class="form-control" id="aadhar_number" name="aadhar_number" placeholder="XXXX XXXX XXXX" maxlength="14" required value="<?php echo isset($_POST['aadhar_number']) ? htmlspecialchars($_POST['aadhar_number']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid 12-digit Aadhar number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email" placeholder="Email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Posting</label>
                            <input type="text" class="form-control" name="current_posting" placeholder="Current Posting" required value="<?php echo isset($_POST['current_posting']) ? htmlspecialchars($_POST['current_posting']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="mobile" id="mobile_number" placeholder="Mobile Number" required maxlength="10" pattern="[0-9]{10}" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                                <button type="submit" class="btn btn-outline-primary" name="send_otp" formnovalidate>Send OTP</button>
                            </div>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Enter OTP</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="otp" placeholder="Enter OTP" value="<?php echo isset($_POST['otp']) ? htmlspecialchars($_POST['otp']) : ''; ?>">
                                <button type="submit" class="btn btn-outline-success" name="verify_otp" formnovalidate>Verify OTP</button>
                            </div>
                            <div id="otp-status" class="form-text">
                                <?php if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true): ?>
                                <span class="text-success">OTP Verified</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id']; ?>"><?= htmlspecialchars($role['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a role</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporting Person</label>
                            <select class="form-select" id="reporting_person" name="reporting_person" required>
                                <option value="">Select Reporting Person</option>
                                <?php
                                // Display options grouped by moderator level
                                $level_labels = [
                                    4 => 'Headquarters Level Moderators',
                                    3 => 'Regional Level Moderators',
                                    2 => 'District Level Moderators',
                                ];

                                foreach ([4, 3, 2] as $level) {
                                    if (isset($grouped_employees[$level]) && !empty($grouped_employees[$level])) {
                                        echo '<optgroup label="' . $level_labels[$level] . '">';
                                        foreach ($grouped_employees[$level] as $employee) {
                                            echo '<option value="' . $employee['id'] . '">' .
                                                htmlspecialchars($employee['designation'] . ' - ' . $employee['department_location']) .
                                                '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a reporting person</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Enter Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" data-required="true">
                            <div id="password-feedback" class="invalid-feedback">
                                Password must be at least 8 characters long
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" data-required="true">
                            <div id="confirm-password-feedback" class="invalid-feedback">
                                Passwords do not match
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" name="signup" class="btn btn-primary px-4 py-2" style="background-color: #180153;">Sign In</button>
                        </div>
                        <div class="col-12 text-center mt-3">
                            <p>Already have an account? <a href="login.php" style="color: #180153;">Login</a></p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 4. Calculate retirement date based on DOB
        $('#dob').change(function() {
            const dob = $(this).val();
            if (dob) {
                $.ajax({
                    type: 'POST',
                    url: 'signup.php',
                    data: { calculate_retirement: 1, dob: dob },
                    success: function(response) {
                        $('#retirement_date').val(response);
                    }
                });
            }
        });
        
        // 5. Format Aadhar number as user types
        $('#aadhar_number').on('input', function() {
            let value = $(this).val().replace(/\D/g, ''); // Remove non-digits
            
            // Limit to 12 digits
            if (value.length > 12) {
                value = value.slice(0, 12); 
            }
            
            // Format with spaces after every 4 digits
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            $(this).val(formattedValue);
            
            // Validate
            const digitsOnly = value.replace(/\s/g, '');
            if (digitsOnly.length === 12) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid');
                if (digitsOnly.length > 0) {
                    $(this).addClass('is-invalid');
                }
            }
        });
        
        // Mobile number validation - only allow digits and limit to 10
        $('#mobile_number').on('input', function() {
            let value = $(this).val().replace(/\D/g, ''); // Remove non-digits
            
            if (value.length > 10) {
                value = value.slice(0, 10); // Limit to 10 digits
            }
            $(this).val(value);
            
            // Show validation message
            if (value.length === 10) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid');
                if(value.length > 0) {
                    $(this).addClass('is-invalid');
                }
            }
        });
        
        // Email validation
        $('#email').on('blur', function() {
            const email = $(this).val();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                $(this).removeClass('is-valid').addClass('is-invalid');
            } else if (email) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            }
        });
        
        // 6. Real-time password matching validation
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            if (confirmPassword !== password) {
                $(this).addClass('is-invalid');
                $('#confirm-password-feedback').show();
            } else {
                $(this).removeClass('is-invalid').addClass('is-valid');
                $('#confirm-password-feedback').hide();
            }
        });
        
        // Password strength validation
        $('#password').on('input', function() {
            const password = $(this).val();
            
            if (password.length < 8) {
                $(this).addClass('is-invalid');
                $('#password-feedback').show();
            } else {
                $(this).removeClass('is-invalid').addClass('is-valid');
                $('#password-feedback').hide();
            }
            
            // If confirm password is not empty, check matching
            const confirmPassword = $('#confirm_password').val();
            if (confirmPassword) {
                if (confirmPassword !== password) {
                    $('#confirm_password').addClass('is-invalid');
                    $('#confirm-password-feedback').show();
                } else {
                    $('#confirm_password').removeClass('is-invalid').addClass('is-valid');
                    $('#confirm-password-feedback').hide();
                }
            }
        });
        
        // Required field validation
        $('form input[required]').on('blur', function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
            } else {
                if (!$(this).hasClass('is-valid') && !$(this).hasClass('is-invalid')) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            }
        });

        // Add validation for role and reporting person fields
        $('#role, #reporting_person').on('change', function() {
            if ($(this).val()) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });

        // Add form submission handler to validate password fields only when signing in
        $('#signupForm').on('submit', function(e) {
            // Only enforce password validation when the main submit button is clicked
            if (e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === "signup") {
                const isOtpVerified = <?php echo isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true ? 'true' : 'false'; ?>;
                
                if (!isOtpVerified) {
                    e.preventDefault();
                    alert("Please verify your mobile number with OTP before submitting");
                    return false;
                }
                
                let isValid = true;
                
                // Check all required fields
                $(this).find('input[required], select[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    }
                });
                
                // Check password fields
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (!password) {
                    e.preventDefault();
                    $('#password').addClass('is-invalid');
                    $('#password-feedback').text('Password is required').show();
                    isValid = false;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    $('#password').addClass('is-invalid');
                    $('#password-feedback').text('Password must be at least 8 characters long').show();
                    isValid = false;
                }
                
                if (!confirmPassword) {
                    e.preventDefault();
                    $('#confirm_password').addClass('is-invalid');
                    $('#confirm-password-feedback').text('Please confirm your password').show();
                    isValid = false;
                }
                
                if (confirmPassword !== password) {
                    e.preventDefault();
                    $('#confirm_password').addClass('is-invalid');
                    $('#confirm-password-feedback').text('Passwords do not match').show();
                    isValid = false;
                }
                
                // Check mobile number format
                const mobile = $('#mobile_number').val();
                if (!mobile || mobile.length !== 10) {
                    $('#mobile_number').addClass('is-invalid');
                    isValid = false;
                }
                
                // Check Aadhar number
                const aadhar = $('#aadhar_number').val().replace(/\D/g, '');
                if (aadhar.length !== 12) {
                    $('#aadhar_number').addClass('is-invalid');
                    isValid = false;
                }
                
                // Check email format
                const email = $('#email').val();
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email && !emailPattern.test(email)) {
                    $('#email').addClass('is-invalid');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: $('.is-invalid:first').offset().top - 100
                    }, 200);
                }
            }
        });

        // Live validation for Sevarth ID (numbers only)
        $('#sevarth_id').on('input', function() {
            // Get the input value
            const value = $(this).val();
            // Check if the value matches the pattern (numbers only)
            const isValid = /^[0-9]+$/.test(value);
            
            // Apply appropriate validation class based on input
            if (value === '') {
                // Empty input - remove validation classes
                $(this).removeClass('is-valid is-invalid');
            } else if (isValid) {
                // Valid input - add valid class, remove invalid class
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                // Invalid input - add invalid class, remove valid class
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });
        
        // Live validation for First Name and Last Name (alphabetic only)
        $('#first_name, #last_name').on('input', function() {
            // Get the input value
            const value = $(this).val();
            // Check if the value matches the pattern (alphabetic only)
            const isValid = /^[A-Za-z]+$/.test(value);
            
            // Apply appropriate validation class based on input
            if (value === '') {
                // Empty input - remove validation classes
                $(this).removeClass('is-valid is-invalid');
            } else if (isValid) {
                // Valid input - add valid class, remove invalid class
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                // Invalid input - add invalid class, remove valid class
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });
        
        // Live validation for Father's Name, Mother's Name, and Spouse's Name (alphabetic with spaces)
        $('#father_name, #mother_name, #spouse_name').on('input', function() {
            // Get the input value
            const value = $(this).val();
            // Check if the value matches the pattern (alphabetic with spaces)
            const isValid = value === '' || /^[A-Za-z ]+$/.test(value);
            
            // Apply appropriate validation class based on input
            if (value === '') {
                // Empty input - remove validation classes if optional (spouse_name)
                // or keep invalid if required (father_name, mother_name)
                if ($(this).attr('id') === 'spouse_name') {
                    $(this).removeClass('is-valid is-invalid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            } else if (isValid) {
                // Valid input - add valid class, remove invalid class
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                // Invalid input - add invalid class, remove valid class
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });
    });
    </script>
</body>
</html>