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

// Fetch make options from the equipment_options table
$make_stmt = $pdo->query("SELECT DISTINCT make FROM equipment_options ORDER BY make");
$makes = $make_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch deployment options from the database
$deployment_load_error = false;
try {
    $deployment_stmt = $pdo->prepare("SELECT deployment_id, name as deployment_name FROM deployment ORDER BY deployment_id ASC");
    $deployment_stmt->execute();
    $deployments = $deployment_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($deployments)) {
        $deployment_load_error = true;
        $message = "Could not load deployment options. Please reload the page or contact support.";
        $message_type = "warning";
    }
} catch (PDOException $e) {
    $deployment_load_error = true;
    $message = "Error loading deployment options: " . $e->getMessage();
    $message_type = "danger";
}

// Fetch status options (using placeholder until proper table exists)
$statuses = [
    ['status_id' => 1, 'status_name' => 'Working'],
    ['status_id' => 2, 'status_name' => 'Not-Working'],
    ['status_id' => 3, 'status_name' => 'Theft'],
    ['status_id' => 4, 'status_name' => 'Damage']
];

// Process form submission if using this page directly for submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_equipment']) && $show_form && !$deployment_load_error) {
    try {
        // Sanitize and validate input
        $pw_no = filter_input(INPUT_POST, 'pw_no', FILTER_SANITIZE_NUMBER_INT);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_SPECIAL_CHARS);
        $serial_number = filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_SPECIAL_CHARS);
        $make = filter_input(INPUT_POST, 'make', FILTER_SANITIZE_SPECIAL_CHARS);
        $model = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_SPECIAL_CHARS);
        $modulation_type = filter_input(INPUT_POST, 'modulation_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $freq_band = filter_input(INPUT_POST, 'freq_band', FILTER_SANITIZE_SPECIAL_CHARS);
        $equipment_type = filter_input(INPUT_POST, 'equipment_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $status_id = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
        $deployment_id = filter_input(INPUT_POST, 'deployment', FILTER_VALIDATE_INT);

        // Verify user still has a unit
        $user_unit_check = $pdo->prepare("SELECT unit_id FROM unit WHERE created_by = ?");
        $user_unit_check->execute([$_SESSION['user_id']]);
        $unit_check_result = $user_unit_check->fetch(PDO::FETCH_ASSOC);

        if (!$unit_check_result) {
            throw new Exception("You must have a unit associated with your account to add equipment.");
        }

        $unit_id = $unit_check_result['unit_id'];

        // Check if serial number already exists
        $check_serial = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE serial_number = ?");
        $check_serial->execute([$serial_number]);
        if ($check_serial->fetchColumn() > 0) {
            throw new Exception("Equipment with this serial number already exists");
        }

        // Validate year is a valid year format (YYYY)
        if (!preg_match('/^(19|20)\d{2}$/', $year)) {
            throw new Exception("Year must be in the format YYYY and be a valid year");
        }

        // Convert status_id to equipment_status string (based on the schema)
        $equipment_status = '';
        foreach ($statuses as $status) {
            if ($status['status_id'] == $status_id) {
                $equipment_status = $status['status_name'];
                break;
            }
        }

        // Insert equipment into database using user's unit_id automatically
        // According to the schema, we need to use equipment_status as a string
        $sql = "INSERT INTO equipment (
                pw_no, year, serial_number, make, model, 
                modulation_type, freq_band, equipment_type, equipment_status, 
                deployment_id, unit_id, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $pw_no,
            $year,
            $serial_number,
            $make,
            $model,
            $modulation_type,
            $freq_band,
            $equipment_type,
            $equipment_status,
            $deployment_id,
            $unit_id,
            $_SESSION['user_id']
        ]);

        $message = "Equipment added successfully! You can add another equipment or click 'Back' to return.";
        $message_type = "success";
        
        // Instead of redirecting, we'll show the form again for another entry
        // This is achieved by not changing $show_form and letting the page render the form again
        
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}
?>
<link rel="stylesheet" href="../public/css/dashboard.css">

<!-- Main container with margins matching bulk pages -->
<div class="container my-5">
    <!-- Page header with back button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Single Entry - Equipment Data</h2>
    </div>

    <!-- Display messages if any -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> mb-4">
            <i class="bi bi-info-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_form && !$deployment_load_error): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Individual Equipment Details</h5>
                <small class="text-muted">Enter equipment details below</small>
            </div>
            <div class="card-body p-4">
                <!-- Changed form action to current page to reload the same form -->
                <form action="dashboard.php?page=single_equipment_entry" method="POST" class="needs-validation" autocomplete="off" novalidate>
                    <div class="row g-3">
                        <!-- Equipment Info - Split PW No. and Year into separate fields -->
                        <div class="col-md-3 mb-3">
                            <label for="pw_no" class="form-label">PW No.</label>
                            <input type="number" class="form-control" id="pw_no" name="pw_no" required min="1">
                            <div class="invalid-feedback">Please enter a valid PW number.</div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="year" class="form-label">Year</label>
                            <input type="text" class="form-control" id="year" name="year" placeholder="YYYY" pattern="^(19|20)\d{2}$"
                                required maxlength="4">
                            <div class="invalid-feedback">Please enter a valid year (YYYY).</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="serial_number" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="make" class="form-label">Make</label>
                            <select class="form-select" id="make" name="make" required>
                                <option value="">Select Make</option>
                                <?php foreach ($makes as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['make']); ?>"><?php echo htmlspecialchars($m['make']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="model" class="form-label">Model</label>
                            <select class="form-select" id="model" name="model" required>
                                <option value="">Select Make First</option>
                            </select>
                        </div>

                        <!-- Dropdowns -->
                        <div class="col-md-6 mb-3">
                            <label for="modulation_type" class="form-label">Modulation Type</label>
                            <select class="form-select" id="modulation_type" name="modulation_type" required>
                                <option value="Digital">Digital</option>
                                <option value="Analog">Analog</option>
                                <option value="Trunking">Trunking</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="freq_band" class="form-label">Frequency Band</label>
                            <select class="form-select" id="freq_band" name="freq_band" required>
                                <option value="UHF">UHF</option>
                                <option value="VHF">VHF</option>
                                <option value="400">400</option>
                                <option value="800">800</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="equipment_type" class="form-label">Equipment Type</label>
                            <select class="form-select" id="equipment_type" name="equipment_type" required>
                                <option value="Radio Set">Radio Set</option>
                                <option value="Handheld">Handheld</option>
                                <option value="Repeater">Repeater</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="deployment" class="form-label">Deployment</label>
                            <select class="form-select" id="deployment" name="deployment" required>
                                <?php foreach ($deployments as $deployment): ?>
                                    <option value="<?php echo $deployment['deployment_id']; ?>">
                                        <?php echo htmlspecialchars($deployment['deployment_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status['status_id']; ?>">
                                        <?php echo htmlspecialchars($status['status_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Replaced dropdown with readonly field showing user's unit -->
                        <div class="col-md-6 mb-3">
                            <label for="unit_display" class="form-label">Unit Name</label>
                            <input type="text" class="form-control" id="unit_display"
                                value="<?php echo htmlspecialchars($user_unit['unit_name'] . ' (' . $user_unit['unit_code'] . ')'); ?>"
                                readonly>
                            <input type="hidden" name="unit_id" value="<?php echo $user_unit['unit_id']; ?>">
                            <small class="form-text text-muted">Equipment will be associated with your unit automatically</small>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">  
                        <a href="dashboard.php?page=equipment_choice" class="btn btn-outline-secondary me-md-2">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <button type="submit" name="save_equipment" class="btn btn-custom">
                            <i class="bi bi-save"></i> Save Equipment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($deployment_load_error): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="alert alert-danger">
                    <h5>Required Data Missing</h5>
                    <p>The system could not load necessary deployment data. Please try reloading the page or contact technical support.</p>
                    <button class="btn btn-primary mt-2" onclick="window.location.reload();">
                        <i class="bi bi-arrow-clockwise"></i> Reload Page
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (!$show_form): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="alert alert-warning">
                    <h5>Unit Required</h5>
                    <p>You must create a unit before adding equipment.</p>
                </div>
                <a href="dashboard.php?page=unit" class="btn btn-custom mt-2">Create Unit</a>
            </div>
        </div>
    <?php endif; ?>
</div>

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

    // Add event listener for make dropdown to fetch models
    document.getElementById("make").addEventListener("change", function () {
        let make = this.value;
        let modelDropdown = document.getElementById("model");

        // Clear the model dropdown
        modelDropdown.innerHTML = '<option value="">Loading models...</option>';

        if (make) {
            // Fetch models for the selected make from the updated endpoint
            fetch("fetch_equipment_details.php?action=models&make=" + encodeURIComponent(make))
                .then(response => response.json())
                .then(data => {
                    modelDropdown.innerHTML = '<option value="">Select Model</option>';
                    data.forEach(model => {
                        modelDropdown.innerHTML += `<option value="${model}">${model}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error fetching models:', error);
                    modelDropdown.innerHTML = '<option value="">Error loading models</option>';
                });
        } else {
            modelDropdown.innerHTML = '<option value="">Select Make First</option>';
        }
    });

    // Add validation for year field
    document.getElementById('year').addEventListener('input', function () {
        const year = this.value;
        const currentYear = new Date().getFullYear();
        const yearPattern = /^(19|20)\d{2}$/;

        if (!yearPattern.test(year)) {
            this.setCustomValidity('Year must be in format YYYY');
        } else if (parseInt(year) < 1900 || parseInt(year) > currentYear) {
            this.setCustomValidity('Year must be between 1900 and ' + currentYear);
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Reset form fields after successful submission
    <?php if ($message_type === 'success'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Clear form fields but don't reset dropdowns that should retain their values
        document.getElementById('pw_no').value = '';
        document.getElementById('year').value = '';
        document.getElementById('serial_number').value = '';
        // Focus on first field for next entry
        document.getElementById('pw_no').focus();
    });
    <?php endif; ?>
</script>