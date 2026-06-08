<?php
/**
 * Authentication Helper
 * 
 * This file provides authentication utilities for the DupliChecker application
 */

/**
 * Check if user is logged in
 * Redirects to login page if not authenticated
 * 
 * @param bool $die If true, will exit the script after redirect
 * @return bool True if user is logged in, false otherwise
 */
function requireLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        // For AJAX requests, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        // For regular requests, redirect to login
        header("Location: login.php");
        exit;
    }
    return true;
}

/**
 * Check if user has a specific role
 * 
 * @param string $role The role to check (e.g., 'admin', 'user')
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Get current logged-in user's information
 * 
 * @param string $field Optional specific field to get (id, username, email, first_name, last_name, role)
 * @return mixed User data array or specific field value
 */
function getCurrentUser($field = null) {
    $user = [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? null,
        'last_name' => $_SESSION['last_name'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
    
    if ($field !== null) {
        return $user[$field] ?? null;
    }
    
    return $user;
}

/**
 * Get user's full name
 * 
 * @return string User's full name or username if names are not available
 */
function getUserFullName() {
    $firstName = $_SESSION['first_name'] ?? '';
    $lastName = $_SESSION['last_name'] ?? '';
    
    $fullName = trim($firstName . ' ' . $lastName);
    
    if (empty($fullName)) {
        return $_SESSION['username'] ?? 'User';
    }
    
    return $fullName;
}

/**
 * Check if user is authenticated (convenience function)
 * 
 * @return bool True if user is logged in
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

/**
 * Require admin role
 * Redirects to dashboard or login if user is not admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        // Redirect to dashboard with error
        header("Location: index.php?error=admin_only");
        exit;
    }
}
