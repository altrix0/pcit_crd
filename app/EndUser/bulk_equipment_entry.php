<?php
// Start the session to maintain user login state
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../database.php';

// Check if bulk fields are set in session
if (!isset($_SESSION['bulk_fields']) || empty($_SESSION['bulk_fields'])) {
    // Redirect to bulk field selection if no fields were selected
    header('Location: dashboard.php?page=bulk_field_selection');
    exit;
}

// Set the page title
$page_title = "Bulk Entry - Equipment Data";

// Define required individual fields (that can't be bulk entered)
$individual_fields = [
    'pw_no' => 'PW Number',
    'serial_number' => 'Serial Number',
];

// Initialize variables
$show_form = true;
$message = '';
$message_type = '';

// Fetch the user's unit from the unit table
$user_unit_stmt = $pdo->prepare("SELECT unit_id, unit_name, unit_code FROM unit WHERE created_by = ?");
$user_unit_stmt->execute([$_SESSION['user_id']]);
$user_unit = $user_unit_stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has created a unit
if (!$user_unit) {
    $message = "You must create a unit before adding equipment.";
    $message_type = "warning";
    $show_form = false;
}

// Counter to show how many records have been entered
if (!isset($_SESSION['bulk_entry_count'])) {
    $_SESSION['bulk_entry_count'] = 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    try {
        // Sanitize and validate input
        $pw_no = filter_input(INPUT_POST, 'pw_no', FILTER_SANITIZE_NUMBER_INT);
        $serial_number = filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Check if serial number already exists
        $check_serial = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE serial_number = ?");
        $check_serial->execute([$serial_number]);
        if ($check_serial->fetchColumn() > 0) {
            throw new Exception("Equipment with this serial number already exists");
        }
        
        // Prepare column values from bulk fields
        $values = [
            'pw_no' => $pw_no,
            'serial_number' => $serial_number,
            'unit_id' => $user_unit['unit_id'],
            'created_by' => $_SESSION['user_id']
        ];
                
        // Add bulk fields from the session
        foreach ($_SESSION['bulk_fields'] as $field_id => $field_data) {
            // Special handling for status which is stored as a string in the database
            if ($field_id === 'status') {
                $status_id = $field_data['value'];
                $statuses = [
                    1 => 'Working',
                    2 => 'Not-Working',
                    3 => 'Theft',
                    4 => 'Damage'
                ];
                $values['equipment_status'] = $statuses[$status_id] ?? 'Unknown';
            } else {
                $values[$field_id] = $field_data['value'];
            }
        }
        
        // Build SQL query dynamically
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO equipment ($columns) VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($values));
        
        // Increment entry count
        $_SESSION['bulk_entry_count']++;
        
        // Set success message
        $_SESSION['bulk_success'] = true;
        
        // Check if user clicked "Done" or "Next"
        if (isset($_POST['done'])) {
            // Cleanup session data
            $entry_count = $_SESSION['bulk_entry_count'];
            unset($_SESSION['bulk_fields']);
            unset($_SESSION['bulk_entry_count']);
            
            // Redirect with success message
            header('Location: dashboard.php?page=bulk_equipment_entry&bulk_success=true&count=' . $entry_count);
            exit;
        }
        
        // For "Next" button, just reload the page
        header('Location: dashboard.php?page=bulk_equipment_entry');
        exit;
        
    } catch (PDOException $e) {
        // Set error message
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get success message if exists and clear it
$success = isset($_SESSION['bulk_success']) && $_SESSION['bulk_success'];
unset($_SESSION['bulk_success']);

// Lookup tables for displaying user-friendly values
$statuses = [
    1 => 'Working',
    2 => 'Not-Working',
    3 => 'Theft',
    4 => 'Damage'
];

// Get deployment name if deployment_id is set
if ($show_form && isset($_SESSION['bulk_fields']['deployment_id'])) {
    $deployment_id = $_SESSION['bulk_fields']['deployment_id']['value'];
    try {
        $deployment_stmt = $pdo->prepare("SELECT name FROM deployment WHERE deployment_id = ?");
        $deployment_stmt->execute([$deployment_id]);
        $deployment = $deployment_stmt->fetch(PDO::FETCH_ASSOC);
        if ($deployment) {
            $_SESSION['bulk_fields']['deployment_id']['display_value'] = $deployment['name'];
        }
    } catch (PDOException $e) {
        // Silently fail, we'll display the ID instead
    }
}

// Map status ID to name for display
if ($show_form && isset($_SESSION['bulk_fields']['status'])) {
    $status_id = $_SESSION['bulk_fields']['status']['value'];
    if (isset($statuses[$status_id])) {
        $_SESSION['bulk_fields']['status']['display_value'] = $statuses[$status_id];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PCIT CRD</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom Dashboard CSS -->
    <link rel="stylesheet" href="../../public/css/dashboard.css">
</head>
<body>
    <!-- Main container -->
    <div class="container my-5">
        <!-- Page header with navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?php echo $page_title; ?></h2>
            <div>
                <?php if ($show_form): ?>
                <a href="dashboard.php?page=bulk_field_selection" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Change Bulk Fields
                </a>
                <?php endif; ?>
                <a href="dashboard.php?page=equipment_choice" class="btn btn-outline-secondary">
                    <i class="bi bi-x"></i> Cancel
                </a>
            </div>
        </div>
        
        <?php if (!$show_form): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="alert alert-warning mb-4">
                        <h5>Unit Required</h5>
                        <p>You must create a unit before adding equipment.</p>
                    </div>
                    <a href="dashboard.php?page=unit" class="btn btn-custom mt-2">Create Unit</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Entry counter -->
            <div class="alert alert-success mb-4">
                <i class="bi bi-check-circle"></i> Equipment entries added in this session: <strong><?php echo $_SESSION['bulk_entry_count']; ?></strong>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Equipment entry saved successfully! You can add another one below.
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Bulk values sidebar -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Bulk Default Values</h5>
                            <small class="text-muted">These values will be applied to all entries</small>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($_SESSION['bulk_fields'])): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($_SESSION['bulk_fields'] as $field_id => $field_data): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <strong><?php echo $field_data['name']; ?>:</strong>
                                        <span>
                                            <?php 
                                            if (isset($field_data['display_value'])) {
                                                echo htmlspecialchars($field_data['display_value']);
                                            } else {
                                                echo $field_data['value'] ? htmlspecialchars($field_data['value']) : '(not set)'; 
                                            }
                                            ?>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-center text-muted">No bulk fields selected</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Unit information card -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Unit Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Unit Name:</strong> <?php echo htmlspecialchars($user_unit['unit_name']); ?></p>
                            <p><strong>Unit Code:</strong> <?php echo htmlspecialchars($user_unit['unit_code']); ?></p>
                            <small class="text-muted">Equipment will be associated with your unit automatically</small>
                        </div>
                    </div>
                </div>
                
                <!-- Individual entry form -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Individual Equipment Details</h5>
                            <small class="text-muted">Enter unique information for this equipment</small>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" autocomplete="off" novalidate>
                                <div class="row g-3">
                                    <!-- PW Number field -->
                                    <div class="col-md-6">
                                        <label for="pw_no" class="form-label">PW Number <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="pw_no" name="pw_no" required min="1">
                                        <div class="invalid-feedback">Please enter a valid PW number.</div>
                                    </div>
                                    
                                    <!-- Serial Number field -->
                                    <div class="col-md-6">
                                        <label for="serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                                        <div class="invalid-feedback">Please enter a serial number.</div>
                                    </div>
                                    
                                    
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                    <button type="submit" name="next" class="btn btn-custom me-md-2">
                                        <i class="bi bi-plus-circle"></i> Save & Add Another
                                    </button>
                                    <button type="submit" name="done" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Save & Finish
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($show_form): ?>
    <script>
        // JavaScript for form validation
        (function () {
            'use strict';

            // Fetch all forms we want to apply validation styles to
            var forms = document.querySelectorAll('.needs-validation');

            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Reset form fields after successful submission
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Clear form fields
            document.getElementById('pw_no').value = '';
            document.getElementById('serial_number').value = '';
            // Focus on first field for next entry
            document.getElementById('pw_no').focus();
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
