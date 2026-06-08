<?php
session_start();
require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: login.php");
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$error = '';

// Validate inputs
if (empty($username) || empty($password)) {
    $error = "Username and password are required";
} else {
    try {
        $pdo = getConnection();
        
        // Get user from database
        $stmt = $pdo->prepare("SELECT id, username, email, password, first_name, last_name, role, status FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "Invalid username or password";
        } elseif ($user['status'] === 'inactive') {
            $error = "Your account has been deactivated. Please contact administrator.";
        } else {
            // Verify password using password_verify for hashed passwords
            // The default password is hashed as: password_hash('admin123', PASSWORD_BCRYPT)
            if (password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Update last login time (optional - requires adding a column to users table)
                // $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                // $stmt->execute([$user['id']]);
                
                // Redirect to dashboard
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid username or password";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        // Log the error for debugging
        error_log("Login error: " . $e->getMessage());
    }
}

// If we get here, login failed - redirect back to login page with error
header("Location: login.php?error=" . urlencode($error));
exit;
