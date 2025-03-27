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

// Fetch the unit details for this user 
$stmt = $pdo->prepare("
    SELECT u.*, r.designation, r.department_location, e.first_name, e.last_name 
    FROM unit u 
    LEFT JOIN reporting_employees r ON u.unit_incharge = r.id
    LEFT JOIN employee e ON u.created_by = e.employee_id
    WHERE u.created_by = :user_id
");
$stmt->execute([$user_id]);
$unit = $stmt->fetch(PDO::FETCH_ASSOC);

// If no unit found, redirect to unit creation page with a message
if (!$unit) {
    $_SESSION['message'] = "You haven't created a unit yet. Please create one first.";
    $_SESSION['message_type'] = "info";
    header('Location: dashboard.php?page=unit');
    exit;
}

// Define upload path for photos to ensure consistency
$upload_path = '../uploads/unit_photos/';
$photo_url = !empty($unit['unit_photo']) ? $upload_path . $unit['unit_photo'] : '';
$photo_exists = !empty($photo_url) && file_exists($photo_url);
?>

<div class="container-fluid px-4 py-3">
    <!-- Header Section with Back Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?php echo htmlspecialchars($unit['unit_name']); ?></h2>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="row">
        <!-- Unit Information Card - Now takes full width -->
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light py-3">
                    <h4 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Unit Information</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-5">
                            <?php if ($photo_exists): ?>
                                <img src="<?php echo $photo_url; ?>" alt="Unit Photo" class="img-fluid rounded shadow-sm mb-3">
                            <?php else: ?>
                                <div class="text-center p-4 bg-light rounded mb-3">
                                    <i class="bi bi-building" style="font-size: 4rem; color: #6c757d;"></i>
                                    <p class="text-muted mt-2">No photo available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <h5 class="border-bottom pb-2 mb-3">Basic Details</h5>
                            <p><strong>Entry Created By:</strong><br>
                                <?php echo htmlspecialchars($unit['first_name'] . ' ' . $unit['last_name']); ?>
                            </p>
                            <p><strong>Unit Incharge:</strong><br> 
                                <?php echo htmlspecialchars($unit['designation'] . ' - ' . $unit['department_location']); ?>
                            </p>
                            <p><strong>Created On:</strong><br>
                            <?php echo isset($unit['created_at']) ? date('F j, Y, g:i a', strtotime($unit['created_at'])) : 'Not available'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3">Description</h5>
                    <div class="p-3 bg-light rounded mb-3">
                        <?php echo nl2br(htmlspecialchars($unit['unit_description'])); ?>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3">Coordinates</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Latitude:</strong> <?php echo htmlspecialchars($unit['latitude']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Longitude:</strong> <?php echo htmlspecialchars($unit['longitude']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../css/dashboard.css" />
