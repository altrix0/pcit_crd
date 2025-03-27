<?php
// Start session and include database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize variables
$message = '';
$message_type = '';
$show_form = true;
$user_id = $_SESSION['user_id'];

// 1️⃣ Auto-Fetch User's Assigned Unit
$unit_stmt = $pdo->prepare("SELECT u.unit_id, u.unit_name, u.unit_code FROM unit u WHERE u.created_by = ?");
$unit_stmt->execute([$user_id]);
$user_unit = $unit_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_unit) {
    $message = "You must create a unit before adding personnel. Please create a unit first.";
    $message_type = "warning";
    $show_form = false;
}

// 2️⃣ Get reporting person info for current user
$reporting_stmt = $pdo->prepare("
    SELECT e.reporting_person, r.designation, r.department_location 
    FROM employee e 
    JOIN reporting_employees r ON e.reporting_person = r.id
    WHERE e.employee_id = ?
");
$reporting_stmt->execute([$user_id]);
$reporting_data = $reporting_stmt->fetch(PDO::FETCH_ASSOC);
$reporting_person_id = $reporting_data ? $reporting_data['reporting_person'] : null;
$reporting_person_text = $reporting_data ? $reporting_data['designation'] . ' (' . $reporting_data['department_location'] . ')' : '';

// 3️⃣ Fetch Post Types
$posts_stmt = $pdo->query("SELECT id, name FROM post_types ORDER BY name");
$posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    try {
        // Validate required fields
        $required_fields = ['sevarth_id', 'first_name', 'last_name', 'father_name', 'mother_name', 'dob', 'mobile_number', 'email_id', 'aadhar_number'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled out.");
            }
        }

        // Sanitize and validate input
        $sevarth_id = filter_input(INPUT_POST, 'sevarth_id', FILTER_SANITIZE_SPECIAL_CHARS);
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $father_name = filter_input(INPUT_POST, 'father_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $mother_name = filter_input(INPUT_POST, 'mother_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $spouse_name = filter_input(INPUT_POST, 'spouse_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_SPECIAL_CHARS);
        $retirement_date = filter_input(INPUT_POST, 'retirement_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $mobile_number = filter_input(INPUT_POST, 'mobile_number', FILTER_SANITIZE_SPECIAL_CHARS);
        $email_id = filter_input(INPUT_POST, 'email_id', FILTER_SANITIZE_EMAIL);
        $aadhar_number = filter_input(INPUT_POST, 'aadhar_number', FILTER_SANITIZE_SPECIAL_CHARS);
        // Clean up aadhar number (remove spaces)
        $aadhar_number = preg_replace('/\s+/', '', $aadhar_number);
        
        // Additional validation
        if (!filter_var($email_id, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        if (strlen($aadhar_number) !== 12 || !is_numeric($aadhar_number)) {
            throw new Exception("Please enter a valid 12-digit Aadhar number.");
        }

        // Check if sevarth_id already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel_info WHERE sevarth_id = ?");
        $check_stmt->execute([$sevarth_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Personnel with this Sevarth ID already exists.");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert into personnel_info table
        $sql = "INSERT INTO personnel_info (
                    sevarth_id, first_name, last_name, father_name, mother_name, 
                    spouse_name, dob, mobile_number, email_id, aadhar_number,
                    retirement_date, current_posting, reporting_person
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $sevarth_id, 
            $first_name, 
            $last_name, 
            $father_name, 
            $mother_name,
            $spouse_name, 
            $dob, 
            $mobile_number, 
            $email_id, 
            $aadhar_number,
            $retirement_date,
            $user_unit['unit_name'],
            $reporting_person_id
        ]);

        $pdo->commit();
        
        $message = "Personnel information saved successfully!";
        $message_type = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}
?>
<link rel="stylesheet" href="../public/css/dashboard.css">

<!-- Main Content -->
<div class="container my-5">
    <!-- Page header with back button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Personnel Management</h2>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> mb-4">
            <i class="bi bi-info-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($show_form): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="dashboard.php?page=personnel" method="POST" class="needs-validation" id="personnelForm" autocomplete="off" novalidate>
                <div class="row">
                    <!-- Personnel Info -->

                    <div class="col-md-6 mb-3">
                        <label for="sevarth_id" class="form-label">Sevarth ID</label>
                        <input type="text" class="form-control" id="sevarth_id" name="sevarth_id" required />
                        <div class="invalid-feedback">Please enter a valid Sevarth ID.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required />
                        <div class="invalid-feedback">Please enter a first name.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required />
                        <div class="invalid-feedback">Please enter a last name.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="father_name" class="form-label">Father Name</label>
                        <input type="text" class="form-control" id="father_name" name="father_name" required />
                        <div class="invalid-feedback">Please enter father's name.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="mother_name" class="form-label">Mother Name</label>
                        <input type="text" class="form-control" id="mother_name" name="mother_name" required />
                        <div class="invalid-feedback">Please enter mother's name.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="spouse_name" class="form-label">Spouse Name (Optional)</label>
                        <input type="text" class="form-control" id="spouse_name" name="spouse_name" />
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" required />
                        <div class="invalid-feedback">Please select a valid date of birth.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="retirement_date" class="form-label">Retirement Date</label>
                        <input type="date" class="form-control" id="retirement_date" name="retirement_date" readonly />
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="mobile_number" class="form-label">Mobile Number</label>
                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" required maxlength="10" pattern="[0-9]{10}" />
                        <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email_id" class="form-label">Email ID</label>
                        <input type="email" class="form-control" id="email_id" name="email_id" required />
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="aadhar_number" class="form-label">Aadhar Number</label>
                        <input type="text" class="form-control" id="aadhar_number" name="aadhar_number" maxlength="14" oninput="formatAadharInput(this)" required />
                        <div class="invalid-feedback">Please enter a valid 12-digit Aadhar number.</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="current_posting" class="form-label">Current Posting (Your Unit)</label>
                        <input type="text" class="form-control" id="current_posting" name="current_posting" value="<?php echo htmlspecialchars($user_unit['unit_name'] . ' (' . $user_unit['unit_code'] . ')'); ?>" readonly />
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="reporting_person_display" class="form-label">Reporting Person</label>
                        <input type="text" class="form-control" id="reporting_person_display" name="reporting_person_display" value="<?php echo htmlspecialchars($reporting_person_text); ?>" readonly />
                        <input type="hidden" name="reporting_person" value="<?php echo htmlspecialchars($reporting_person_id); ?>" />
                    </div>
                </div>
                <!-- Submit Button -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-custom" id="submitBtn">Save Personnel</button>
                </div>
            </form>
        </div>
    </div>
    <?php elseif (!$show_form): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="alert alert-warning">
                    <h5>Unit Required</h5>
                    <p>You must create a unit before adding personnel. Please create a unit first.</p>
                </div>
                <a href="dashboard.php?page=unit" class="btn btn-custom mt-2">Create Unit</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Auto-calculate retirement date based on date of birth (58 years of service)
        $('#dob').change(function() {
            const dob = new Date($(this).val());
            if (!isNaN(dob.getTime())) {
                const retirementDate = new Date(dob);
                retirementDate.setFullYear(dob.getFullYear() + 58);
                $('#retirement_date').val(retirementDate.toISOString().split('T')[0]);
            }
        });

        // Validate mobile number - only allow digits
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
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        });
        
        // Email validation
        $('#email_id').on('blur', function() {
            const email = $(this).val();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                $(this).removeClass('is-valid').addClass('is-invalid');
            } else if (email) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            }
        });
        
        // Required field validation
        $('form input[required], form select[required]').on('blur', function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
                if ($(this).attr('type') !== 'email' && !$(this).hasClass('is-valid')) {
                    $(this).addClass('is-valid');
                }
            }
        });

        // Aadhar validation based on 12 digits
        $('#aadhar_number').on('input', function() {
            const value = $(this).val().replace(/\D/g, ''); // Remove non-digits
            if (value.length === 12) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid');
                if (value.length > 0) {
                    $(this).addClass('is-invalid');
                }
            }
        });

        // Form validation on submit
        $('#personnelForm').on('submit', function(e) {
            let isValid = true;
            
            // Check all required fields
            $(this).find('input[required], select[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    isValid = false;
                }
            });
            
            // Check email format
            const email = $('#email_id').val();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailPattern.test(email)) {
                $('#email_id').addClass('is-invalid');
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
            
            // Check date relationships
            const joiningDate = new Date($('#joining_unit_date').val());
            const relieveDate = new Date($('#relieve_unit_date').val());
            if ($('#relieve_unit_date').val() && !isNaN(joiningDate.getTime()) && !isNaN(relieveDate.getTime())) {
                if (relieveDate < joiningDate) {
                    $('#relieve_unit_date').addClass('is-invalid');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.is-invalid:first').offset().top - 100
                }, 200);
            }
        });
    });

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
        
        // Validate the input
        if (value.length === 12) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else if (value.length > 0) {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
        }
    }
</script>

