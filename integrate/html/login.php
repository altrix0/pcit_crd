

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/login.css">
    
</head>
<body>
    <div class="container">
        <div class="card mx-auto">
            <div class="card-body p-4">
                <div class="logo-container">
                    <img src="logo.png" alt="Logo" class="logo">
                </div>
                
                <h4 class="text-center mb-4">Login</h4>
                
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="username" placeholder="SEVARTH ID" required>
                    </div>
                    
                    <div class="mb-4">
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">LOGIN TO ACCOUNT</button>
                    </div>
                    
                    <div class="link-container">
                        <a href="forgot-password.php">Forgot password?</a>
                        <a href="signup.php">Signup</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>