<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // This will be handled by login-process.php via form submission
}

// Check if there was a login error message passed
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DupliChecker</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .login-header {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            color: #2D3250;
            padding: 60px 20px;
            padding-bottom: 40px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 2.3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .logo-text-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-form {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #2D3250;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .checkbox-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-weight: 400;
        }
        
        .checkbox-group a {
            color: #667eea;
            text-decoration: none;
        }
        
        .checkbox-group a:hover {
            text-decoration: underline;
        }
        
        .login-footer {
            text-align: center;
            padding: 15px 20px;
            /* background: #f8f9fa; */
            /* border-top: 1px solid #e0e0e0; */
            font-size: 0.9rem;
            color: #666;
        }
        
        .demo-credentials {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .demo-credentials strong {
            color: #1976D2;
            display: block;
            margin-bottom: 8px;
        }
        
        .demo-credentials code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Log in to your account</p>
        </div>
        
        <!-- Login Form -->
        <div class="login-form" style="padding-top: 0;">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login-process.php">
                <div class="form-group">
                    <label for="username">
                        Username
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username" 
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        Password
                    </label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                    >
                </div>
                
                <div class="checkbox-group">
                    <!-- <label>
                        <input type="checkbox" name="remember" id="remember">
                        Remember me
                    </label> -->
                </div>

                <!-- <div class="demo-credentials">
                    <strong><i class="bi bi-info-circle"></i> Demo Credentials:</strong>
                    Username: <code>admin</code><br>
                    Password: <code>admin</code>
                </div> -->
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p class="mb-0">Developed by ee._.laine</p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
