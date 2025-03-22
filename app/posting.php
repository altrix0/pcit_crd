<?php

// Start session and include database connection
session_start();
require_once 'database.php';

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// 2️⃣ Auto-Fetch Reporting Person
$reporting_person_stmt = $pdo->prepare("SELECT reporting_person FROM employee WHERE employee_id = ?");
$reporting_person_stmt->execute([$user_id]);
$reporting_person_data = $reporting_person_stmt->fetch(PDO::FETCH_ASSOC);
$reporting_person = $reporting_person_data ? $reporting_person_data['reporting_person'] : '';

// 3️⃣ Fetch Post & Sub-Post from Database with proper filtering
$posts_stmt = $pdo->query("SELECT post_id, name FROM post_types WHERE post_type = 'post' ORDER BY name");
$posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

$sub_posts_stmt = $pdo->query("SELECT post_id AS sub_post_id, name FROM post_types WHERE post_type = 'subpost' ORDER BY name");
$sub_posts = $sub_posts_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4️⃣ Fetch Employees for dropdown
$employees_stmt = $pdo->query("SELECT employee_id, sevarth_id, CONCAT(first_name, ' ', last_name) AS employee_name FROM employee ORDER BY first_name");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'joining_unit_date', 'post','sub_post', 'relieve_unit_date'];
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
<link rel="stylesheet" href="../public/css/dashboard.css">
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

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h2 class="mb-4">Posting Management</h2>
  
<?php if ($show_form): ?>
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
            <label for="employee_name" class="form-label">Employee Name</label>
            <input type="text" class="form-control" id="employee_name" readonly>
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
                <option value="<?php echo $post['post_id']; ?>"><?php echo htmlspecialchars($post['name']); ?></option>
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

    <div class="mt-4">
        <button type="submit" class="btn btn-custom" id="submitBtn">Save Posting</button>
    </div>
</form>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <p class="card-text"><?php echo $message; ?></p>
        <a href="dashboard.php?page=unit" class="btn btn-custom">Create a Unit</a>
    </div>
</div>
<?php endif; ?>

<!-- Include jQuery before Bootstrap for better DOM manipulation -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Form elements
    const form = $("#postingForm");
    const searchInput = $("#search_sevarth");
    const searchResults = $("#search_results");
    const employeeNameField = $("#employee_name");
    const employeeIdField = $("#employee_id");
    const postSelect = $("#post");
    const subPostSelect = $("#sub_post");
    const joiningDateInput = $("#joining_unit_date");
    const relieveDateInput = $("#relieve_unit_date");
    
    // Initialize validation handlers
    initializeValidation();
    
    // Function to initialize all validation
    function initializeValidation() {
        // Date validation
        joiningDateInput.on('input change', validateDates);
        relieveDateInput.on('input change', validateDates);
        
        // Post/Sub-post relationship
        postSelect.on('change', function() {
            if (this.value) {
                // Enable the sub-post field
                subPostSelect.prop('disabled', false);
                
                // Reset and load sub-posts
                subPostSelect.html('<option value="">Select Sub-Post</option>');
                <?php foreach ($sub_posts as $sub_post): ?>
                subPostSelect.append('<option value="<?php echo $sub_post['sub_post_id']; ?>"><?php echo addslashes(htmlspecialchars($sub_post['name'])); ?></option>');
                <?php endforeach; ?>
                
                // Add validation classes if form is already validated
                if (form.hasClass('was-validated')) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
                
                // Highlight the sub-post field to draw attention
                subPostSelect.addClass('field-highlight');
                setTimeout(() => subPostSelect.removeClass('field-highlight'), 1000);
                
            } else {
                // Disable and reset sub-post
                subPostSelect.prop('disabled', true);
                subPostSelect.html('<option value="">Select Post First</option>');
                subPostSelect.removeClass('is-valid is-invalid');
            }
        });
        
        // Sub-post validation
        subPostSelect.on('change', function() {
            if (form.hasClass('was-validated')) {
                validateSelect($(this));
            }
        });
        
        // Prevent form submission on Enter in search field
        searchInput.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                return false;
            }
        });
        
        // Setup Sevarth ID search functionality
        setupSearchFunctionality();
        
        // Form submission validation
        form.on('submit', function(e) {
            let isValid = true;
            
            // Validate employee selection
            if (!employeeIdField.val()) {
                isValid = false;
                searchInput.addClass('is-invalid').removeClass('is-valid');
                employeeNameField.addClass('is-invalid').removeClass('is-valid');
                
                // Update error message based on what's wrong
                const feedback = searchInput.siblings('.invalid-feedback');
                if (searchInput.val().trim() === '') {
                    feedback.text("Please enter a Sevarth ID.");
                } else {
                    feedback.text("Please select a valid Sevarth ID from the search results.");
                }
            }
            
            // Validate dates
            if (!validateDates()) {
                isValid = false;
            }
            
            // Validate sub-post is selected when post is selected
            if (postSelect.val() && !subPostSelect.val()) {
                isValid = false;
                subPostSelect.addClass('is-invalid').removeClass('is-valid');
            }
            
            // Check all required fields
            form.find('[required]:not(:disabled)').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid').removeClass('is-valid');
                } else if (!$(this).hasClass('is-invalid')) {
                    $(this).addClass('is-valid').removeClass('is-invalid');
                }
            });
            
            // Prevent submission if invalid
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                form.addClass('was-validated');
                
                // Scroll to first invalid element
                const firstInvalid = $('.is-invalid:first');
                if (firstInvalid.length) {
                    $('html, body').animate({
                        scrollTop: firstInvalid.offset().top - 100
                    }, 200);
                }
            }
        });
    }
    
    // Function to validate dates
    function validateDates() {
        // Only validate if both dates have values
        if (joiningDateInput.val() && relieveDateInput.val()) {
            const joiningDate = new Date(joiningDateInput.val());
            const relieveDate = new Date(relieveDateInput.val());
            
            if (relieveDate <= joiningDate) {
                relieveDateInput.addClass('is-invalid').removeClass('is-valid');
                return false;
            } else {
                relieveDateInput.removeClass('is-invalid').addClass('is-valid');
                joiningDateInput.removeClass('is-invalid').addClass('is-valid');
                return true;
            }
        } else {
            // If form is already validated, check required fields
            if (form.hasClass('was-validated')) {
                if (!joiningDateInput.val()) {
                    joiningDateInput.addClass('is-invalid').removeClass('is-valid');
                }
                if (!relieveDateInput.val()) {
                    relieveDateInput.addClass('is-invalid').removeClass('is-valid');
                }
            }
            return false;
        }
    }
    
    // Function to validate select fields
    function validateSelect(selectField) {
        if (selectField.val()) {
            selectField.addClass('is-valid').removeClass('is-invalid');
            return true;
        } else {
            selectField.addClass('is-invalid').removeClass('is-valid');
            return false;
        }
    }
    
    // Function to setup search functionality
    function setupSearchFunctionality() {
        let debounceTimer;
        let searchLoading = null;
        
        searchInput.on('input', function() {
            clearTimeout(debounceTimer);
            const term = $(this).val().trim();
            
            // Clear fields if search is empty
            if (term.length < 1) {
                searchResults.hide();
                employeeNameField.val('');
                employeeIdField.val('');
                
                // Remove loading indicator if exists
                removeSearchLoadingIndicator();
                return;
            }
            
            // Mark fields as invalid when search term changes
            if (term !== searchInput.data('selected-value')) {
                employeeIdField.val('');
                employeeNameField.val('');
                searchInput.addClass('is-invalid').removeClass('is-valid');
                employeeNameField.addClass('is-invalid').removeClass('is-valid');
            }
            
            // Show loading indicator
            showSearchLoadingIndicator();
            
            debounceTimer = setTimeout(() => {
                $.ajax({
                    url: 'posting.php',
                    data: { 
                        search_sevarth: true, 
                        term: term 
                    },
                    dataType: 'json',
                    success: function(data) {
                        // Remove loading indicator
                        removeSearchLoadingIndicator();
                        searchResults.empty();
                        
                        if (data.length === 0) {
                            searchResults.hide();
                            return;
                        }
                        
                        // Build search results
                        $.each(data, function(i, item) {
                            const resultItem = $('<div class="search-result-item"></div>')
                                .text(`${item.sevarth_id} - ${item.employee_name}`)
                                .on('click', function() {
                                    // Set Sevarth ID and data
                                    searchInput.val(item.sevarth_id);
                                    searchInput.data('selected-value', item.sevarth_id);
                                    
                                    // Set employee name and ID
                                    employeeNameField.val(item.employee_name);
                                    employeeIdField.val(item.employee_id);
                                    
                                    // Update validation states
                                    searchInput.removeClass('is-invalid').addClass('is-valid');
                                    employeeNameField.removeClass('is-invalid').addClass('is-valid');
                                    
                                    // Hide results
                                    searchResults.hide();
                                    
                                    // Make sure no loading indicator is shown
                                    removeSearchLoadingIndicator();
                                });
                            
                            searchResults.append(resultItem);
                        });
                        
                        searchResults.show();
                    },
                    error: function(xhr, status, error) {
                        removeSearchLoadingIndicator();
                        console.error("Error in search:", error);
                    }
                });
            }, 300);
        });
        
        // Helper function to show loading indicator
        function showSearchLoadingIndicator() {
            // Remove any existing loading indicator first
            removeSearchLoadingIndicator();
            
            // Create and add new loading indicator
            searchLoading = $('<div id="search-loading" class="position-absolute" style="right: 40px; top: 50%; transform: translateY(-50%);"><span class="spinner-border spinner-border-sm text-primary" role="status"></span></div>');
            searchInput.after(searchLoading);
        }
        
        // Helper function to remove loading indicator
        function removeSearchLoadingIndicator() {
            if (searchLoading) {
                searchLoading.remove();
                searchLoading = null;
            }
            $('#search-loading').remove(); // Backup removal by ID
        }
        
        // Hide results when clicking outside
        $(document).on('click', function(e) {
            if (!searchInput.is(e.target) && !searchResults.is(e.target) && searchResults.has(e.target).length === 0) {
                searchResults.hide();
                // Also remove loading indicator if it's still showing
                removeSearchLoadingIndicator();
            }
        });
    }
});
</script>