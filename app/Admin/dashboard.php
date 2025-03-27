<?php
// Start the session (if needed for authentication or session data)
session_start();

// Get the requested page from the URL (default to 'home1')
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Whitelist of allowed pages to prevent unauthorized file access
$allowed_pages = ['home', 'user_management'];

// Fallback to home1 if the requested page is not allowed
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
        <a class="navbar-brand" href="?page=home1">Dashboard</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'home1') ? 'active' : ''; ?>" href="?page=home1">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page == 'user_management') ? 'active' : ''; ?>" href="?page=user_management">User Management</a>
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
<div class="content">
    <?php 
        // Dynamically load content from the corresponding file
        if (file_exists('../' . $page . '.php')) {
            include('../' . $page . '.php');
        } else {
            echo "<p>Page not found: " . htmlspecialchars($page) . ".php</p>";
        }
    ?>
</div>

<!-- Footer -->
<footer class="footer mt-4 text-center">
   
</footer>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../js/app.js"></script>

</body>
</html>


