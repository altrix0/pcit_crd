<?php
// Start the session (if needed for authentication or session data)
session_start();

// Get the requested page from the URL (default to 'home')
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Whitelist of allowed pages to prevent unauthorized file access
$allowed_pages = ['home', 'reports', 'settings', 'equipment', 'unit', 'personnel','posting'];

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
    <link rel="stylesheet" href="../public/css/dashboard.css">
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
                    <a class="nav-link <?php echo ($page == 'equipment') ? 'active' : ''; ?>" href="?page=equipment">Equipment</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'personnel') ? 'active' : ''; ?>" href="?page=personnel">Personnel</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'posting') ? 'active' : ''; ?>" href="?page=posting">Posting</a>
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
                    <a class="nav-link text-danger" href="logout.php">Logout</a>
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

<!-- Footer -->
<footer class="footer mt-4 text-center">
   
</footer>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/app.js"></script>

</body>
</html>


