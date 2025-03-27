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

// Get current user ID from session
$user_id = $_SESSION['user_id'];

// Initialize variables
$message = '';
$message_type = '';

// Check if the user has already created a unit
$check_existing_unit = $pdo->prepare("SELECT COUNT(*) FROM unit WHERE created_by = ?");
$check_existing_unit->execute([$user_id]);
$has_existing_unit = $check_existing_unit->fetchColumn() > 0;

// If user already has a unit, prevent further creation but don't show warning message
if ($has_existing_unit) {
    $show_form = false;
} else {
    $show_form = true;
}

// Fetch reporting employees for the unit_incharge dropdown, grouped by level
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

// Process form submission if using this page directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_unit']) && $show_form) {
    try {
        // Check again if the user has already created a unit (to prevent race conditions)
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM unit WHERE created_by = ?");
        $check_stmt->execute([$user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("You have already created a unit. You can only create one unit per user.");
        }
        
        // Sanitize and validate input
        $unit_code = filter_input(INPUT_POST, 'unit_id', FILTER_SANITIZE_SPECIAL_CHARS);
        $unit_name = filter_input(INPUT_POST, 'unit_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_SANITIZE_SPECIAL_CHARS);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_SANITIZE_SPECIAL_CHARS);
        $unit_description = filter_input(INPUT_POST, 'unit_description', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Validate unit_incharge as integer and ensure it exists in reporting_employees table
        $unit_incharge = filter_input(INPUT_POST, 'unit_incharge', FILTER_VALIDATE_INT);
        
        // Validate required fields
        if (empty($unit_code) || empty($unit_name) || empty($unit_incharge)) {
            throw new Exception("Please fill all required fields");
        }
        
        // Verify that the unit_incharge ID exists in the reporting_employees table
        $check_incharge_stmt = $pdo->prepare("SELECT COUNT(*) FROM reporting_employees WHERE id = ?");
        $check_incharge_stmt->execute([$unit_incharge]);
        if ($check_incharge_stmt->fetchColumn() == 0) {
            throw new Exception("Invalid unit incharge selected");
        }
        
        // Check if unit code already exists
        $check_unit_code_stmt = $pdo->prepare("SELECT COUNT(*) FROM unit WHERE unit_code = ?");
        $check_unit_code_stmt->execute([$unit_code]);
        if ($check_unit_code_stmt->fetchColumn() > 0) {
            throw new Exception("Unit code already exists");
        }
        
        // File upload handling
        $unit_photo = '';
        if (isset($_FILES['unit_photo']) && $_FILES['unit_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/unit_photos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_name = uniqid('unit_') . '_' . basename($_FILES['unit_photo']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $file_type = $_FILES['unit_photo']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['unit_photo']['tmp_name'], $target_file)) {
                $unit_photo = $file_name;
            } else {
                throw new Exception("Failed to upload image");
            }
        }
        
        // Insert unit into database with unit_incharge and created_by
        $sql = "INSERT INTO unit (unit_code, unit_name, latitude, longitude, 
                unit_photo, unit_description, unit_incharge, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $unit_code, $unit_name, $latitude, $longitude, 
            $unit_photo, $unit_description, $unit_incharge, $user_id
        ]);
        
        // Set success message for modal
        $_SESSION['show_success_modal'] = true;
        $_SESSION['success_message'] = "Unit added successfully!";
        
        // Redirect to prevent form resubmission
        header("Location: dashboard.php?page=unit");
        exit;
    } 
    catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    }
    catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Check for success modal flag from session
$show_success_modal = false;
$success_message = "";
if (isset($_SESSION['show_success_modal']) && $_SESSION['show_success_modal']) {
    $show_success_modal = true;
    $success_message = $_SESSION['success_message'] ?? "Operation completed successfully!";
    // Clear the session variables
    unset($_SESSION['show_success_modal']);
    unset($_SESSION['success_message']);
}
?>
<link rel="stylesheet" href="../public/css/dashboard.css">

<!-- Main container with consistent margins -->
<div class="container my-5">
    <!-- Page header with navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Unit Management</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> mb-4">
        <i class="bi bi-info-circle"></i> <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Unit Information</h5>
            <small class="text-muted">Enter your unit details below</small>
        </div>
        <div class="card-body p-4">
            <form action="dashboard.php?page=unit" method="POST" class="needs-validation" enctype="multipart/form-data" autocomplete="off" novalidate>
                <div class="row g-3">
                    <!-- Unit Info -->
                    <div class="col-md-6 mb-3">
                        <label for="unit_id" class="form-label">Unit ID</label>
                        <input type="text" class="form-control" id="unit_id" name="unit_id" required>
                        <div class="invalid-feedback">Please enter a valid Unit ID (numbers only)</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="unit_name" class="form-label">Unit Name</label>
                        <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="latitude" class="form-label">Unit Latitude</label>
                        <input type="text" class="form-control" id="latitude" name="latitude" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="longitude" class="form-label">Unit Longitude</label>
                        <input type="text" class="form-control" id="longitude" name="longitude" required>
                    </div>

                    <!-- File Upload -->
                    <div class="col-md-6 mb-3">
                        <label for="unit_photo" class="form-label">Unit Photo</label>
                        <input type="file" class="form-control" id="unit_photo" name="unit_photo" accept="image/*" required>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="unit_description" class="form-label">Unit Description</label>
                        <textarea class="form-control" id="unit_description" name="unit_description" rows="3" required></textarea>
                    </div>

                    <!-- Updated Dropdown for Unit Incharge using reporting_employees -->
                    <div class="col-md-6 mb-3">
                        <label for="unit_incharge" class="form-label">Unit Incharge</label>
                        <select class="form-select" id="unit_incharge" name="unit_incharge" required>
                            <option value="">Select Incharge</option>
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
                        <div class="invalid-feedback">Please select a Unit Incharge</div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-custom" name="save_unit">
                        <i class="bi bi-save"></i> Save Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i> Your unit details have been recorded. Each user can only create one unit in the system.
            </div>
            <div class="text-center">
                <a href="dashboard.php?page=view_my_unit" class="btn btn-custom mt-2">View My Unit</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h4 class="mt-3"><?php echo $success_message; ?></h4>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../css/dashboard.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">

<script>
// JavaScript for form validation
(function() {
    'use strict';
    
    // Fetch all forms we want to apply validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Add latitude and longitude validation
    document.getElementById('latitude').addEventListener('input', function() {
        const lat = parseFloat(this.value);
        if (isNaN(lat) || lat < -90 || lat > 90) {
            this.setCustomValidity('Latitude must be between -90 and 90 degrees');
        } else {
            this.setCustomValidity('');
        }
    });
    
    document.getElementById('longitude').addEventListener('input', function() {
        const lng = parseFloat(this.value);
        if (isNaN(lng) || lng < -180 || lng > 180) {
            this.setCustomValidity('Longitude must be between -180 and 180 degrees');
        } else {
            this.setCustomValidity('');
        }
    });
})();

// Option to get current location
document.addEventListener('DOMContentLoaded', function() {
    // Add a button next to the latitude/longitude fields if needed
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    // Example of adding a "Get Current Location" button programmatically
    const locationBtn = document.createElement('button');
    locationBtn.textContent = 'Get Current Location';
    locationBtn.className = 'btn btn-sm btn-secondary mt-2';
    locationBtn.type = 'button';
    locationBtn.onclick = function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                latInput.value = position.coords.latitude.toFixed(6);
                lngInput.value = position.coords.longitude.toFixed(6);
            });
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    };
    
    // Insert after longitude input
    lngInput.parentNode.appendChild(locationBtn);
});

// Show success modal if needed
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($show_success_modal): ?>
    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
    <?php endif; ?>
});

// Add validation for Unit ID (numbers only)
document.addEventListener('DOMContentLoaded', function() {
    // Get the Unit ID input element
    const unitIdInput = document.getElementById('unit_id');
    
    // Add input event listener for live validation
    unitIdInput.addEventListener('input', function() {
        // Get the input value
        const value = this.value;
        
        // Check if the value matches the pattern (numbers only)
        const isValid = /^[0-9]+$/.test(value);
        
        // Apply appropriate validation class based on input
        if (value === '') {
            // Empty input - remove validation classes
            this.classList.remove('is-valid', 'is-invalid');
        } else if (isValid) {
            // Valid input - add valid class, remove invalid class
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            // Invalid input - add invalid class, remove valid class
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
        }
    });
});
</script>

