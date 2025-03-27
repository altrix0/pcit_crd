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

// Set the page title
$page_title = "Bulk Entry - Field Selection";

// Initialize variables for unit validation
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

// Define available equipment fields for bulk entry
$available_fields = [
    'year' => 'Year',
    'make' => 'Make',
    'model' => 'Model', 
    'modulation_type' => 'Modulation Type',
    'freq_band' => 'Frequency Band',
    'equipment_type' => 'Equipment Type',
    'deployment_id' => 'Deployment',
    'status' => 'Status'
];

// Only fetch options if user has a unit
if ($show_form) {
    // Fetch make options from the equipment_options table
    $make_stmt = $pdo->query("SELECT DISTINCT make FROM equipment_options ORDER BY make");
    $makes = $make_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch deployment options from the database
    try {
        $deployment_stmt = $pdo->prepare("SELECT deployment_id, name as deployment_name FROM deployment ORDER BY deployment_id ASC");
        $deployment_stmt->execute();
        $deployments = $deployment_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $deployments = [];
    }

    // Fetch status options
    $statuses = [
        ['status_id' => 1, 'status_name' => 'Working'],
        ['status_id' => 2, 'status_name' => 'Not-Working'],
        ['status_id' => 3, 'status_name' => 'Theft'],
        ['status_id' => 4, 'status_name' => 'Damage']
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    // Store selected fields and their values in session
    $_SESSION['bulk_fields'] = [];
    foreach ($available_fields as $field_id => $field_name) {
        // Check if field was selected
        if (isset($_POST['selected_fields']) && in_array($field_id, $_POST['selected_fields'])) {
            $_SESSION['bulk_fields'][$field_id] = [
                'name' => $field_name,
                'value' => $_POST[$field_id] ?? ''
            ];
        }
    }
    
    // Redirect to bulk equipment entry page
    header('Location: dashboard.php?page=bulk_equipment_entry');
    exit;
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
        <!-- Page header with back button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?php echo $page_title; ?></h2>
            <a href="dashboard.php?page=equipment_choice" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <i class="bi bi-info-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_form): ?>
            <!-- Info message explaining the purpose of this page -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i> Select fields you want to set as default for bulk entry. These values will be applied to all equipment entries in this session.
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="" id="bulk-selection-form" class="needs-validation" autocomplete="off" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="mb-3">Step 1: Select Fields for Bulk Entry</h5>
                                <div class="form-group mb-3">
                                    <label class="form-label">Select fields to include in bulk entry:</label>
                                    <div class="row g-3">
                                        <?php foreach ($available_fields as $field_id => $field_name): ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input field-selector" type="checkbox" 
                                                       name="selected_fields[]" value="<?php echo $field_id; ?>" 
                                                       id="field_<?php echo $field_id; ?>">
                                                <label class="form-check-label" for="field_<?php echo $field_id; ?>">
                                                    <?php echo $field_name; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4" id="default-values-container">
                            <div class="col-md-12">
                                <h5 class="mb-3">Step 2: Set Default Values</h5>
                                <div class="alert alert-secondary" id="no-fields-message">
                                    <i class="bi bi-info-circle"></i> Please select fields above to set default values.
                                </div>
                                
                                <!-- Year field -->
                                <div class="form-group mb-3 default-value-group" id="group_year" style="display: none;">
                                    <label for="year" class="form-label">Year Default Value:</label>
                                    <input type="text" class="form-control" id="year" name="year" 
                                           placeholder="YYYY" pattern="^(19|20)\d{2}$" maxlength="4">
                                    <div class="invalid-feedback">Please enter a valid year (YYYY).</div>
                                </div>
                                
                                <!-- Make field -->
                                <div class="form-group mb-3 default-value-group" id="group_make" style="display: none;">
                                    <label for="make" class="form-label">Make Default Value:</label>
                                    <select class="form-select" id="make" name="make">
                                        <option value="">Select Make</option>
                                        <?php foreach ($makes as $m): ?>
                                            <option value="<?php echo htmlspecialchars($m['make']); ?>"><?php echo htmlspecialchars($m['make']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Model field -->
                                <div class="form-group mb-3 default-value-group" id="group_model" style="display: none;">
                                    <label for="model" class="form-label">Model Default Value:</label>
                                    <select class="form-select" id="model" name="model">
                                        <option value="">Select Make First</option>
                                    </select>
                                </div>
                                
                                <!-- Modulation Type field -->
                                <div class="form-group mb-3 default-value-group" id="group_modulation_type" style="display: none;">
                                    <label for="modulation_type" class="form-label">Modulation Type Default Value:</label>
                                    <select class="form-select" id="modulation_type" name="modulation_type">
                                        <option value="">Select Modulation Type</option>
                                        <option value="Digital">Digital</option>
                                        <option value="Analog">Analog</option>
                                        <option value="Trunking">Trunking</option>
                                    </select>
                                </div>
                                
                                <!-- Frequency Band field -->
                                <div class="form-group mb-3 default-value-group" id="group_freq_band" style="display: none;">
                                    <label for="freq_band" class="form-label">Frequency Band Default Value:</label>
                                    <select class="form-select" id="freq_band" name="freq_band">
                                        <option value="">Select Frequency Band</option>
                                        <option value="UHF">UHF</option>
                                        <option value="VHF">VHF</option>
                                        <option value="400">400</option>
                                        <option value="800">800</option>
                                    </select>
                                </div>
                                
                                <!-- Equipment Type field -->
                                <div class="form-group mb-3 default-value-group" id="group_equipment_type" style="display: none;">
                                    <label for="equipment_type" class="form-label">Equipment Type Default Value:</label>
                                    <select class="form-select" id="equipment_type" name="equipment_type">
                                        <option value="">Select Equipment Type</option>
                                        <option value="Radio Set">Radio Set</option>
                                        <option value="Handheld">Handheld</option>
                                        <option value="Repeater">Repeater</option>
                                    </select>
                                </div>
                                
                                <!-- Deployment field -->
                                <div class="form-group mb-3 default-value-group" id="group_deployment_id" style="display: none;">
                                    <label for="deployment_id" class="form-label">Deployment Default Value:</label>
                                    <select class="form-select" id="deployment_id" name="deployment_id">
                                        <option value="">Select Deployment</option>
                                        <?php foreach ($deployments as $deployment): ?>
                                            <option value="<?php echo $deployment['deployment_id']; ?>">
                                                <?php echo htmlspecialchars($deployment['deployment_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Status field -->
                                <div class="form-group mb-3 default-value-group" id="group_status" style="display: none;">
                                    <label for="status" class="form-label">Status Default Value:</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Select Status</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status['status_id']; ?>">
                                                <?php echo htmlspecialchars($status['status_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5 class="mb-3">Step 3: Preview</h5>
                                <div class="alert alert-secondary" id="preview-container">
                                    <p class="mb-2"><strong>Selected Bulk Fields:</strong></p>
                                    <div id="preview-content">
                                        <p>No fields selected yet.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-custom">
                                <i class="bi bi-arrow-right"></i> Continue to Data Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="alert alert-warning mb-4">
                        <h5>Unit Required</h5>
                        <p>You must create a unit before adding equipment.</p>
                    </div>
                    <a href="dashboard.php?page=unit" class="btn btn-custom mt-2">Create Unit</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($show_form): ?>
    <!-- Custom JavaScript for field selection interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fieldSelectors = document.querySelectorAll('.field-selector');
            const noFieldsMessage = document.getElementById('no-fields-message');
            const previewContent = document.getElementById('preview-content');
            const form = document.getElementById('bulk-selection-form');
            
            // Handle field selection changes
            fieldSelectors.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const fieldId = this.value;
                    const fieldGroup = document.getElementById('group_' + fieldId);
                    
                    if (this.checked) {
                        fieldGroup.style.display = 'block';
                    } else {
                        fieldGroup.style.display = 'none';
                    }
                    
                    updatePreview();
                    updateNoFieldsMessage();
                });
            });
            
            // Update default values container visibility
            function updateNoFieldsMessage() {
                const anySelected = Array.from(fieldSelectors).some(cb => cb.checked);
                noFieldsMessage.style.display = anySelected ? 'none' : 'block';
            }
            
            // Update preview section
            function updatePreview() {
                let previewHTML = '';
                let anySelected = false;
                
                fieldSelectors.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        const fieldId = checkbox.value;
                        const fieldName = checkbox.nextElementSibling.textContent.trim();
                        const fieldElement = document.getElementById(fieldId);
                        let fieldValue = '';
                        
                        // Get value based on element type
                        if (fieldElement.tagName === 'SELECT') {
                            const selectedOption = fieldElement.options[fieldElement.selectedIndex];
                            fieldValue = selectedOption ? selectedOption.text : '';
                        } else {
                            fieldValue = fieldElement.value;
                        }
                        
                        previewHTML += `<div class="mb-1"><strong>${fieldName}:</strong> ${fieldValue || '(not set)'}</div>`;
                        anySelected = true;
                    }
                });
                
                previewContent.innerHTML = anySelected ? previewHTML : '<p>No fields selected yet.</p>';
            }
            
            // Add input/change event listeners to update preview when values change
            document.querySelectorAll('.default-value-group input, .default-value-group select').forEach(function(input) {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });

            // Add event listener for make dropdown to fetch models
            document.getElementById("make").addEventListener("change", function() {
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
                            updatePreview(); // Update preview after models are loaded
                        })
                        .catch(error => {
                            console.error('Error fetching models:', error);
                            modelDropdown.innerHTML = '<option value="">Error loading models</option>';
                        });
                } else {
                    modelDropdown.innerHTML = '<option value="">Select Make First</option>';
                    updatePreview();
                }
            });

            // Add validation for year field
            document.getElementById('year').addEventListener('input', function() {
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
            
            // Form validation
            form.addEventListener('submit', function(event) {
                const anySelected = Array.from(fieldSelectors).some(cb => cb.checked);
                
                if (!anySelected) {
                    event.preventDefault();
                    alert('Please select at least one field for bulk entry.');
                }
                
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
