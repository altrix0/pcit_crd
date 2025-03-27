<?php

// Start session and include database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// Add AJAX endpoint for sevarth_id search
if (isset($_GET['search_sevarth']) && isset($_GET['term'])) {
    
    $term = $_GET['term'] . '%';
    $stmt = $pdo->prepare("SELECT employee_id, sevarth_id, first_name, last_name, 
                           CONCAT(first_name, ' ', last_name) AS employee_name
                           FROM employee 
                           WHERE sevarth_id LIKE ? 
                           ORDER BY sevarth_id 
                           LIMIT 10");
    $stmt->execute([$term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Update AJAX endpoint for fetching sub-posts by parent post ID
if (isset($_GET['get_subposts']) && isset($_GET['post_id'])) {
    $post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT);
    
    if ($post_id) {
        // Updated query to fetch from subpost_types table using post_type_id
        $stmt = $pdo->prepare("SELECT id, name FROM subpost_types 
                              WHERE post_type_id = ? 
                              ORDER BY name");
        $stmt->execute([$post_id]);
        $sub_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($sub_posts);
    } else {
        header('Content-Type: application/json');
        echo json_encode([]);
    }
    exit;
}

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

// Fetch logged-in user's employee information
$employee_stmt = $pdo->prepare("SELECT employee_id, sevarth_id, CONCAT(first_name, ' ', last_name) AS employee_name 
                               FROM employee 
                               WHERE employee_id = ?");
$employee_stmt->execute([$user_id]);
$logged_in_employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

// 1️⃣ Auto-Fetch User's Assigned Unit
$unit_stmt = $pdo->prepare("SELECT u.unit_id, u.unit_name, u.unit_code FROM unit u WHERE u.created_by = ?");
$unit_stmt->execute([$user_id]);
$user_unit = $unit_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_unit) {
    $message = "You must create a unit before adding personnel. Please create a unit first.";
    $message_type = "warning";
    $show_form = false;
}

// 2️⃣ Fetch reporting employees grouped by level for dropdown
$reporting_stmt = $pdo->query("SELECT id, designation, department_location, level 
                              FROM reporting_employees 
                              ORDER BY level DESC, designation ASC");
$reporting_employees = $reporting_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize reporting employees by level for grouped display
$reporting_levels = [];
foreach ($reporting_employees as $employee) {
    $level = $employee['level'];
    if (!isset($reporting_levels[$level])) {
        $reporting_levels[$level] = [];
    }
    $reporting_levels[$level][] = $employee;
}

// 3️⃣ Fetch Post Types from Database (updated to match new schema)
$posts_stmt = $pdo->query("SELECT id, name FROM post_types ORDER BY name");
$posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'joining_unit_date', 'post', 'reporting_person'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled out.");
            }
        }

        // Sanitize and validate input
        $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
        $unit_id = $user_unit['unit_id'];
        $joining_unit_date = filter_input(INPUT_POST, 'joining_unit_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $relieve_unit_date = !empty($_POST['relieve_unit_date']) ? filter_input(INPUT_POST, 'relieve_unit_date', FILTER_SANITIZE_SPECIAL_CHARS) : null;
        $post = filter_input(INPUT_POST, 'post', FILTER_VALIDATE_INT);
        $sub_post = !empty($_POST['sub_post']) ? filter_input(INPUT_POST, 'sub_post', FILTER_VALIDATE_INT) : null;
        $reporting_person = filter_input(INPUT_POST, 'reporting_person', FILTER_VALIDATE_INT);
        
        // Validate dates if relieve date is provided
        if ($relieve_unit_date && strtotime($relieve_unit_date) <= strtotime($joining_unit_date)) {
            throw new Exception("Relieve date must be after joining date.");
        }
        
        $pdo->beginTransaction();
        
        // Check if employee already has an active posting
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM posting WHERE employee_id = ? AND relieve_unit_date IS NULL");
        $check_stmt->execute([$employee_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("This employee already has an active posting. Please end the current posting before creating a new one.");
        }
        
        $sql = "INSERT INTO posting (
                    employee_id, unit_id, joining_unit_date, relieve_unit_date, 
                    post, sub_post, created_at, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW(), ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $employee_id, $unit_id, $joining_unit_date, $relieve_unit_date,
            $post, $sub_post, $user_id
        ]);

        // Update employee record with reporting_person
        $update_employee_sql = "UPDATE employee SET reporting_person = ? WHERE employee_id = ?";
        $update_stmt = $pdo->prepare($update_employee_sql);
        $update_stmt->execute([$reporting_person, $employee_id]);

        $pdo->commit();
        $message = "Posting information saved successfully!";
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

<!-- Use the official Bootstrap CSS and Icons libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../../public/css/dashboard.css">
<style>
    /* Core search functionality styles - keep these */
    .search-results {
        position: absolute;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0 0 4px 4px;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .search-result-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }
    .search-result-item:hover {
        background-color: #f8f9fa;
    }
    .search-result-item:last-child {
        border-bottom: none;
    }
    .search-container {
        position: relative;
    }
    
    /* Only a few custom styles that aren't in Bootstrap */
    .form-control[readonly] {
        background-color: #f8f9fa;
    }
    
    /* Field highlight effect */
    .field-highlight {
        animation: field-highlight-animation 1s ease-in-out;
    }
    
    @keyframes field-highlight-animation {
        0% { background-color: #fff; }
        50% { background-color: #e8f4ff; }
        100% { background-color: #fff; }
    }
</style>

<div class="container my-5">
    <!-- Page header with back button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Posting Management</h2>
    </div>
  
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> mb-4">
            <i class="bi bi-info-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($show_form): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form id="postingForm" method="POST" action="" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="search_sevarth" class="form-label">Sevarth ID</label>
                        <div class="search-container">
                            <input type="text" class="form-control" id="search_sevarth" placeholder="Enter Sevarth ID" required>
                            <div id="search_results" class="search-results"></div>
                            <div class="invalid-feedback">Please enter and select a valid Sevarth ID.</div>
                        </div>
                    </div>
                
                    <div class="col-md-6 mb-3">
                        <label for="personnel_name" class="form-label">Personnel Name</label>
                        <input type="text" class="form-control" id="personnel_name" readonly>
                        <input type="hidden" id="employee_id" name="employee_id" required>
                        <div class="invalid-feedback">Please select a valid Sevarth ID first.</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="posting_unit" class="form-label">Posting Unit Name</label>
                        <input type="text" class="form-control" id="posting_unit" name="posting_unit" value="<?php echo htmlspecialchars($user_unit['unit_name']); ?>" readonly />
                        <input type="hidden" name="unit_id" value="<?php echo $user_unit['unit_id']; ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="reporting_person" class="form-label">Reporting Person</label>
                        <select class="form-select" id="reporting_person" name="reporting_person" required>
                            <option value="">Select Reporting Person</option>
                            <?php foreach ($reporting_levels as $level => $employees): ?>
                            <optgroup label="Level <?php echo $level; ?>">
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['designation'] . ' (' . $employee['department_location'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a reporting person.</div>
                    </div>
                </div>
            
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="joining_unit_date" class="form-label">Joining Unit Date</label>
                        <input type="date" class="form-control" id="joining_unit_date" name="joining_unit_date" required>
                        <div class="invalid-feedback">Please select a joining date.</div>
                    </div>
            
                    <div class="col-md-6 mb-3">
                        <label for="relieve_unit_date" class="form-label">Relieve Unit Date</label>
                        <input type="date" class="form-control" id="relieve_unit_date" name="relieve_unit_date" required>
                        <div class="invalid-feedback">Relieve date must be after joining date.</div>
                    </div>
                </div>
            
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="post" class="form-label">Post</label>
                        <select class="form-select" id="post" name="post" required>
                            <option value="">Select Post</option>
                            <?php foreach ($posts as $post): ?>
                            <option value="<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a post.</div>
                    </div>
            
                    <div class="col-md-6 mb-3">
                        <label for="sub_post" class="form-label">Sub-Post</label>
                        <select class="form-select" id="sub_post" name="sub_post" required disabled>
                            <option value="">Select Post First</option>
                        </select>
                        <div class="invalid-feedback">Please select a sub-post.</div>
                    </div>
                </div>
            
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">  
                    <button type="submit" class="btn btn-custom" id="submitBtn">Save Posting</button>
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

<!-- Include jQuery before Bootstrap for better DOM manipulation -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Form elements
    const form = $("#postingForm");
    const searchInput = $("#search_sevarth");
    const searchResults = $("#search_results");
    const employeeNameField = $("#personnel_name");
    const employeeIdField = $("#employee_id");
    const postSelect = $("#post");
    const subPostSelect = $("#sub_post");
    const joiningDateInput = $("#joining_unit_date");
    const relieveDateInput = $("#relieve_unit_date");
    
    // Initialize validation handlers
    initializeValidation();
    
    // Setup Sevarth ID search functionality
    setupSevarthSearch();
    
    // Function to initialize all validation
    function initializeValidation() {
        // Date validation
        joiningDateInput.on('input change', validateDates);
        relieveDateInput.on('input change', validateDates);
        
        // Post/Sub-post relationship - dynamically load sub-posts via AJAX
        postSelect.on('change', function() {
            const selectedPostId = $(this).val();
            
            // Reset the sub-post dropdown
            subPostSelect.empty().append('<option value="">Select Sub-Post</option>');
            
            if (!selectedPostId) {
                // If no post selected, disable sub-post dropdown
                subPostSelect.prop('disabled', true);
                return;
            }
            
            // Enable sub-post dropdown
            subPostSelect.prop('disabled', false);
            
            // Add loading indicator
            subPostSelect.append('<option value="" disabled selected>Loading sub-posts...</option>');
            
            // Fetch sub-posts via AJAX
            $.ajax({
                url: 'posting.php',
                type: 'GET',
                data: { 
                    get_subposts: true, 
                    post_id: selectedPostId 
                },
                dataType: 'json',
                success: function(data) {
                    // Remove loading indicator
                    subPostSelect.find('option[disabled]').remove();
                    
                    // Populate sub-posts dropdown
                    if (data.length > 0) {
                        $.each(data, function(i, item) {
                            subPostSelect.append('<option value="' + item.id + '">' + 
                                item.name + '</option>');
                        });
                        
                        // Highlight the sub-post field to draw attention
                        subPostSelect.addClass('field-highlight');
                        setTimeout(() => subPostSelect.removeClass('field-highlight'), 1000);
                    } else {
                        subPostSelect.append('<option value="" disabled>No sub-posts available</option>');
                    }
                    
                    // Add validation classes if form is already validated
                    if (form.hasClass('was-validated')) {
                        postSelect.removeClass('is-invalid').addClass('is-valid');
                    }
                },
                error: function(xhr, status, error) {
                    // Handle error
                    subPostSelect.empty().append('<option value="" disabled selected>Error loading sub-posts</option>');
                    console.error("AJAX Error:", error);
                    // Show error alert
                    showAlert("danger", "Failed to load sub-posts. Please try again.");
                }
            });
        });
        
        // Form submission validation
        form.on('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Add validation class
            $(this).addClass('was-validated');
        });
    }
    
    // Function to setup Sevarth ID search
    function setupSevarthSearch() {
        // Timer for debounce
        let searchTimer;
        
        // Keyup event for search input
        searchInput.on('keyup', function() {
            const searchTerm = $(this).val().trim();
            
            // Clear previous timer
            clearTimeout(searchTimer);
            
            // Clear results if search term is too short
            if (searchTerm.length < 2) {
                searchResults.empty().hide();
                return;
            }
            
            // Set a timer to prevent too many requests
            searchTimer = setTimeout(function() {
                // Show loading indicator
                searchResults.html('<div class="p-2 text-center"><i class="bi bi-hourglass-split me-2"></i>Searching...</div>').show();
                
                // Perform AJAX request
                $.ajax({
                    url: 'posting.php',
                    type: 'GET',
                    data: { 
                        search_sevarth: true, 
                        term: searchTerm 
                    },
                    dataType: 'json',
                    success: function(data) {
                        searchResults.empty();
                        
                        if (data.length > 0) {
                            // Display search results
                            $.each(data, function(i, item) {
                                const resultItem = $('<div>')
                                    .addClass('search-result-item')
                                    .attr('data-id', item.employee_id)
                                    .attr('data-name', item.employee_name)
                                    .text(item.sevarth_id + ' - ' + item.employee_name);
                                
                                searchResults.append(resultItem);
                            });
                            
                            // Show results
                            searchResults.show();
                            
                            // Click event for result items
                            $('.search-result-item').on('click', function() {
                                const employeeId = $(this).data('id');
                                const employeeName = $(this).data('name');
                                const sevarthId = $(this).text().split(' - ')[0];
                                
                                // Set values
                                searchInput.val(sevarthId);
                                employeeNameField.val(employeeName);
                                employeeIdField.val(employeeId);
                                
                                // Hide results
                                searchResults.hide();
                                
                                // Add validation classes if form is validated
                                if (form.hasClass('was-validated')) {
                                    searchInput.removeClass('is-invalid').addClass('is-valid');
                                    employeeNameField.removeClass('is-invalid').addClass('is-valid');
                                }
                                
                                // Highlight the personnel_name field
                                employeeNameField.addClass('field-highlight');
                                setTimeout(() => employeeNameField.removeClass('field-highlight'), 1000);
                            });
                        } else {
                            // No results found
                            searchResults.html('<div class="p-2 text-center text-muted">No results found</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle error
                        searchResults.html('<div class="p-2 text-center text-danger">Error: ' + error + '</div>').show();
                        console.error("AJAX Error:", error);
                    }
                });
            }, 500); // 500ms debounce
        });
        
        // Hide results when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.search-container').length) {
                searchResults.hide();
            }
        });
        
        // Clear button functionality
        searchInput.on('search', function() {
            if (!this.value) {
                employeeNameField.val('');
                employeeIdField.val('');
                searchResults.empty().hide();
            }
        });
    }
    
    // Function to validate dates
    function validateDates() {
        const joiningDate = new Date(joiningDateInput.val());
        const relieveDate = new Date(relieveDateInput.val());
        
        // Check if relieve date is after joining date
        if (relieveDateInput.val() && joiningDateInput.val()) {
            if (relieveDate <= joiningDate) {
                relieveDateInput.removeClass('is-valid').addClass('is-invalid');
                return false;
            } else {
                relieveDateInput.removeClass('is-invalid').addClass('is-valid');
                joiningDateInput.removeClass('is-invalid').addClass('is-valid');
                return true;
            }
        }
        
        return true;
    }
    
    // Function to show alerts
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Add alert before the form
        $(alertHtml).insertBefore(form);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            $('.alert').alert('close');
        }, 5000);
    }
});
</script>