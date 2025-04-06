<?php
/**
 * Admin Header Template
 * 
 * Consistent header for all admin pages with navigation and authentication check
 */

// Security check - ensure the user is an admin
require_once 'includes/auth.php';
ensure_admin_authenticated();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real VS AI - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin Styles -->
    <style>
        body {
            background-color: #111;
            color: #eee;
        }
        
        .admin-section {
            background-color: #222;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .admin-title {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #444;
        }
        
        .nav-link.active {
            background-color: #444 !important;
        }
        
        .stats-card {
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .table-dark {
            background-color: #222;
        }
        
        .form-control, .form-select {
            background-color: #333;
            border-color: #444;
            color: #eee;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #444;
            border-color: #666;
            color: #fff;
        }
    </style>
</head>
<body>

<!-- Admin Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="admin.php">
            <i class="fas fa-shield-alt me-2"></i> Real VS AI Admin
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'active' : '' ?>" href="admin.php">
                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin_images.php' ? 'active' : '' ?>" href="admin_images.php">
                        <i class="fas fa-images me-1"></i> Images
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin.php#users-section">
                        <i class="fas fa-users me-1"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin.php#leaderboard-section">
                        <i class="fas fa-trophy me-1"></i> Leaderboard
                    </a>
                </li>
            </ul>
            
            <div class="d-flex">
                <a href="index.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-home me-1"></i> Game Home
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>
<!-- End Admin Navbar -->