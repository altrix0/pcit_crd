<?php

// Start session and include database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database.php';

// Add AJAX endpoint for sevarth_id search - UPDATED to use personnel_info table
if (isset($_GET['search_sevarth']) && isset($_GET['term'])) {
    
    $term = $_GET['term'] . '%';
    $stmt = $pdo->prepare("SELECT personnel_id, sevarth_id, first_name, last_name, 
                           CONCAT(first_name, ' ', last_name) AS employee_name
                           FROM personnel_info 
                           WHERE sevarth_id LIKE ? 
                           ORDER BY sevarth_id 
                           LIMIT 10");
    $stmt->execute([$term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Add AJAX endpoint for fetching sub-posts by post type ID
if (isset($_GET['get_subposts']) && isset($_GET['post_id'])) {
    $post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT);
    
    if ($post_id) {
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

// Fetch logged-in user's personnel information
$personnel_stmt = $pdo->prepare("SELECT personnel_id, sevarth_id, CONCAT(first_name, ' ', last_name) AS employee_name 
                               FROM personnel_info 
                               WHERE personnel_id = ?");
$personnel_stmt->execute([$user_id]);
$logged_in_personnel = $personnel_stmt->fetch(PDO::FETCH_ASSOC);

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

// 3️⃣ Fetch Post Types from Database
$posts_stmt = $pdo->query("SELECT id, name FROM post_types ORDER BY name");
$posts = $posts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    try {
        // Validate required fields
        $required_fields = ['personnel_id', 'joining_unit_date', 'post', 'reporting_person'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled out.");
            }
        }

        // Sanitize and validate input
        $personnel_id = filter_input(INPUT_POST, 'personnel_id', FILTER_VALIDATE_INT);
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

        // Check if personnel already has an active posting
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM posting WHERE personnel_id = ? AND relieve_unit_date IS NULL");
        $check_stmt->execute([$personnel_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("This personnel already has an active posting. Please end the current posting before creating a new one.");
        }

        $sql = "INSERT INTO posting (
            personnel_id, unit_id, joining_unit_date, relieve_unit_date, 
            post, sub_post, created_at, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, NOW(), ?
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $personnel_id,
            $unit_id,
            $joining_unit_date,
            $relieve_unit_date,
            $post,
            $sub_post,
            $user_id
        ]);

        // Update personnel record with reporting_person
        $update_personnel_sql = "UPDATE personnel_info SET reporting_person = ? WHERE personnel_id = ?";
        $update_stmt = $pdo->prepare($update_personnel_sql);
        $update_stmt->execute([$reporting_person, $personnel_id]);

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
            <form id="postingForm" method="POST" action="" class="needs-validation" autocomplete="off" novalidate>
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
                        <input type="hidden" id="personnel_id" name="personnel_id" required>
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
    const personnelNameField = $("#personnel_name");
    const personnelIdField = $("#personnel_id");
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
                        subPostSelect.prop('required', false);
                    }
                    
                    // Add validation classes if form is already validated
                    if (form.hasClass('was-validated')) {
                        postSelect.removeClass('is-invalid').addClass('is-valid');
                    }
                },
                error: function() {
                    // Handle error gracefully
                    subPostSelect.empty();
                    subPostSelect.append('<option value="">Error loading sub-posts</option>');
                    subPostSelect.append('<option value="" disabled>Please try again</option>');
                    console.error("Failed to load sub-posts");
                }
            });
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
            
            // Validate personnel selection
            if (!personnelIdField.val()) {
                isValid = false;
                searchInput.addClass('is-invalid').removeClass('is-valid');
                personnelNameField.addClass('is-invalid').removeClass('is-valid');
                
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
            
            // Validate reporting person is selected
            if (!$('#reporting_person').val()) {
                isValid = false;
                $('#reporting_person').addClass('is-invalid').removeClass('is-valid');
            } else {
                $('#reporting_person').addClass('is-valid').removeClass('is-invalid');
            }
            
            // Validate sub-post is selected when post is selected and sub-posts are available
            if (postSelect.val() && subPostSelect.prop('required') && !subPostSelect.val()) {
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
                personnelNameField.val('');
                personnelIdField.val('');
                
                // Remove loading indicator if exists
                removeSearchLoadingIndicator();
                return;
            }
            
            // Mark fields as invalid when search term changes
            if (term !== searchInput.data('selected-value')) {
                personnelIdField.val('');
                personnelNameField.val('');
                searchInput.addClass('is-invalid').removeClass('is-valid');
                personnelNameField.addClass('is-invalid').removeClass('is-valid');
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
                            searchResults.append('<div class="search-result-item text-muted">No matching results found</div>');
                            searchResults.show();
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
                                    
                                    // Set personnel name and ID
                                    personnelNameField.val(item.employee_name);
                                    personnelIdField.val(item.personnel_id);
                                    
                                    // Update validation states
                                    searchInput.removeClass('is-invalid').addClass('is-valid');
                                    personnelNameField.removeClass('is-invalid').addClass('is-valid');
                                    
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
                        searchResults.empty();
                        searchResults.append('<div class="search-result-item text-danger">Error searching. Please try again.</div>');
                        searchResults.show();
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