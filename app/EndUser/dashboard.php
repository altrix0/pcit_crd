<?php
// Start the session (if needed for authentication or session data)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the requested page from the URL (default to 'home')
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Whitelist of allowed pages to prevent unauthorized file access
$allowed_pages = ['home', 'reports', 'settings', 'equipment_choice', 'unit', 'personnel','posting', 'single_equipment_entry', 'bulk_field_selection', 'bulk_equipment_entry', 'view_my_unit'];

// Fallback to home if the requested page is not allowed
if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../public/css/dashboard.css">
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="?page=home">Dashboard</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'home') ? 'active' : ''; ?>" href="?page=home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'reports') ? 'active' : ''; ?>" href="?page=reports">Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'unit') ? 'active' : ''; ?>" href="?page=unit">Unit</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'equipment_choice') ? 'active' : ''; ?>" href="?page=equipment_choice">Equipment</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'personnel') ? 'active' : ''; ?>" href="?page=personnel">Personnel</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'posting') ? 'active' : ''; ?>" href="?page=posting">Posting</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link <?php echo ($page == 'single_equipment_entry') ? 'active' : ''; ?>" href="?page=single_equipment_entry">SE</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link <?php echo ($page == 'bulk_field_selection') ? 'active' : ''; ?>"
                        href="?page=bulk_field_selection">SE</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link <?php echo ($page == 'bulk_equipment_entry') ? 'active' : ''; ?>"
                        href="?page=bulk_equipment_entry">SE</a>
                </li>
            </ul>
        </div>

        <!-- Right-Aligned User Options -->
        <div class="d-flex align-items-center">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="#">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'settings') ? 'active' : ''; ?>" href="?page=settings">Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="content p-4">
    <?php 
        // Dynamically load content from the corresponding file
        if (file_exists($page . '.php')) {
            include($page . '.php');
        } else {
            echo "<p>Page not found</p>";
        }
    ?>
</div>


<footer class="footer mt-5 py-3 bg-light">
    <div class="container text-center">
            <span class="text-muted">PCIT CRD Equipment Management System &copy; <?php echo date('Y'); ?></span>
    </div>
</footer>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/app.js"></script>

</body>
</html>


