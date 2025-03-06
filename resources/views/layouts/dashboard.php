<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Get user information from session
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$username = isset($user['name']) ? $user['name'] : 'User';
$role = isset($user['role']) ? $user['role'] : 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css"> <!-- Linking external CSS -->
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">CRD Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content Layout -->
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                
                <?php if (isset($_SESSION['user_permissions']) && in_array('view_equipment', $_SESSION['user_permissions'])): ?>
                <li class="nav-item"><a href="equipment.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'equipment.php' ? 'active' : ''; ?>">Equipment</a></li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_permissions']) && in_array('view_personnel', $_SESSION['user_permissions'])): ?>
                <li class="nav-item"><a href="personnel.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'personnel.php' ? 'active' : ''; ?>">Personnel</a></li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_permissions']) && in_array('view_reports', $_SESSION['user_permissions'])): ?>
                <li class="nav-item"><a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">Reports</a></li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_permissions']) && in_array('view_verification', $_SESSION['user_permissions'])): ?>
                <li class="nav-item"><a href="verification.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verification.php' ? 'active' : ''; ?>">Verification</a></li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <li class="nav-item"><a href="admin.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : ''; ?>">Admin Panel</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="content p-4">
            <?php
            // Check if there are any flash messages to display
            if (isset($_SESSION['flash_message'])) {
                $type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';
                echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
                echo $_SESSION['flash_message'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                
                // Clear the flash message
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            }
            ?>
            
            <h2>Welcome to the Dashboard</h2>
            <p>This is your main dashboard where you can access resources, reports, and other settings.</p>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Equipment Status</h5>
                            <p class="card-text">View and manage equipment inventory and status.</p>
                            <a href="equipment.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Personnel Records</h5>
                            <p class="card-text">Access personnel records and deployment information.</p>
                            <a href="personnel.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Generate Reports</h5>
                            <p class="card-text">Create, review, and submit official reports.</p>
                            <a href="reports.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <div class="row mt-2">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">System Statistics</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['users']) ? $stats['users'] : '0'; ?></h3>
                                        <p>Active Users</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['equipment']) ? $stats['equipment'] : '0'; ?></h3>
                                        <p>Equipment Items</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['personnel']) ? $stats['personnel'] : '0'; ?></h3>
                                        <p>Personnel Records</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['reports']) ? $stats['reports'] : '0'; ?></h3>
                                        <p>Generated Reports</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define project root for consistency
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['user_id'])) {
    header("Location: /pcit_crd/resources/views/auth/login.php");
    exit();
}

// Get user information from session
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$username = isset($user['name']) ? $user['name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
$role = isset($user['role']) ? $user['role'] : (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'User');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fix CSS path to be absolute from project root -->
    <link rel="stylesheet" href="/pcit_crd/public/css/dashboard.css">
    <style>
        /* Fallback styling in case the CSS file doesn't load */
        .navbar {
            background-color: #180153;
        }
        .sidebar {
            background-color: #f8f9fa;
            min-width: 250px;
            min-height: calc(100vh - 56px);
            padding-top: 20px;
        }
        .content {
            flex: 1;
        }
        .nav-link.active {
            background-color: #180153;
            color: white;
        }
        .stat-box {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/pcit_crd/resources/views/layouts/dashboard.php">CRD Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/pcit_crd/resources/views/user/profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="/pcit_crd/resources/views/user/settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/pcit_crd/resources/views/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content Layout -->
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/layouts/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                </li>
                
                <?php if (!isset($_SESSION['user_permissions']) || in_array('view_equipment', $_SESSION['user_permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/equipment/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/pcit_crd/resources/views/equipment' ? 'active' : ''; ?>">Equipment</a>
                </li>
                <?php endif; ?>
                
                <?php if (!isset($_SESSION['user_permissions']) || in_array('view_personnel', $_SESSION['user_permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/personnel/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/pcit_crd/resources/views/personnel' ? 'active' : ''; ?>">Personnel</a>
                </li>
                <?php endif; ?>
                
                <?php if (!isset($_SESSION['user_permissions']) || in_array('view_reports', $_SESSION['user_permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/reports/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/pcit_crd/resources/views/reports' ? 'active' : ''; ?>">Reports</a>
                </li>
                <?php endif; ?>
                
                <?php if (!isset($_SESSION['user_permissions']) || in_array('view_verification', $_SESSION['user_permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/verification/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/pcit_crd/resources/views/verification' ? 'active' : ''; ?>">Verification</a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="/pcit_crd/resources/views/admin/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/pcit_crd/resources/views/admin' ? 'active' : ''; ?>">Admin Panel</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="content p-4">
            <?php
            // Check if there are any flash messages to display
            if (isset($_SESSION['flash_message'])) {
                $type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';
                echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">';
                echo htmlspecialchars($_SESSION['flash_message']);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                
                // Clear the flash message
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            }
            ?>
            
            <h2>Welcome to the Dashboard</h2>
            <p>This is your main dashboard where you can access resources, reports, and other settings.</p>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Equipment Status</h5>
                            <p class="card-text">View and manage equipment inventory and status.</p>
                            <a href="/pcit_crd/resources/views/equipment/index.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Personnel Records</h5>
                            <p class="card-text">Access personnel records and deployment information.</p>
                            <a href="/pcit_crd/resources/views/personnel/index.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Generate Reports</h5>
                            <p class="card-text">Create, review, and submit official reports.</p>
                            <a href="/pcit_crd/resources/views/reports/index.php" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <div class="row mt-2">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">System Statistics</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['users']) ? htmlspecialchars($stats['users']) : '0'; ?></h3>
                                        <p>Active Users</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['equipment']) ? htmlspecialchars($stats['equipment']) : '0'; ?></h3>
                                        <p>Equipment Items</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['personnel']) ? htmlspecialchars($stats['personnel']) : '0'; ?></h3>
                                        <p>Personnel Records</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo isset($stats['reports']) ? htmlspecialchars($stats['reports']) : '0'; ?></h3>
                                        <p>Generated Reports</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips if needed
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Any additional JavaScript can go here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips if needed
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Add any additional JavaScript functionality here
        });
    </script>
</body>
</html>