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

// Include database connection (in case needed for additional functionality)
require_once '../database.php';

// Set the page title
$page_title = "Equipment Entry Options";

// Check if the user has a unit
$user_has_unit = false;
$user_unit_stmt = $pdo->prepare("SELECT unit_id FROM unit WHERE created_by = ?");
$user_unit_stmt->execute([$_SESSION['user_id']]);
if ($user_unit_stmt->fetch()) {
    $user_has_unit = true;
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
        </div>
        
        <?php if (!$user_has_unit): ?>
        <!-- Unit required message -->
        <div class="alert alert-warning mb-4">
            <h5><i class="bi bi-exclamation-triangle"></i> Unit Required</h5>
            <p>You must create a unit before you can add equipment. Please create a unit first.</p>
            <a href="dashboard.php?page=unit" class="btn btn-custom mt-2">Create Unit</a>
        </div>
        <?php else: ?>
        <!-- Info message explaining the purpose of this page -->
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i> Select how you want to enter equipment data into the system.
        </div>
        
        <!-- Entry options cards container -->
        <div class="row justify-content-center g-4">
            <!-- Single Entry Option Card -->
            <div class="col-md-5">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="icon-container mb-3">
                            <!-- Icon for single entry - document symbol -->
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: #28a745;"></i>
                        </div>
                        <h4 class="card-title">Single Entry</h4>
                        <p class="card-text text-muted">
                            Enter one equipment record at a time. 
                            Ideal for adding individual equipment items.
                        </p>
                        <ul class="text-start mb-4">
                            <li>Add one equipment item per submission</li>
                            <li>Complete detailed information for each item</li>
                            <li>Get immediate feedback on each submission</li>
                        </ul>
                        <a href="dashboard.php?page=single_equipment_entry" class="btn btn-custom w-100">
                            <i class="bi bi-plus-circle"></i> Single Entry
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Entry Option Card -->
            <div class="col-md-5">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="icon-container mb-3">
                            <!-- Icon for bulk entry - multiple documents symbol -->
                            <i class="bi bi-files" style="font-size: 3rem; color: #007bff;"></i>
                        </div>
                        <h4 class="card-title">Bulk Entry</h4>
                        <p class="card-text text-muted">
                            Enter multiple equipment records at once. 
                            Efficient for large data imports.
                        </p>
                        <ul class="text-start mb-4">
                            <li>Apply common values across multiple items</li>
                            <li>Enter unique identifiers for each submission</li>
                            <li>Add multiple equipment items in a single session</li>
                        </ul>
                        <a href="dashboard.php?page=bulk_field_selection" class="btn btn-custom w-100">
                            <i class="bi bi-cloud-upload"></i> Bulk Entry
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional help text -->
        <div class="text-center mt-5">
            <p class="text-muted">
                <i class="bi bi-question-circle"></i> Need help deciding? 
                Use <strong>Single Entry</strong> for a few items, or <strong>Bulk Entry</strong> for many items at once.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript for any additional functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Any initialization code can go here
            console.log('Equipment choice page loaded');
        });
    </script>
</body>
</html>
