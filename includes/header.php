<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/template.php';

// Set default page title
$page_title = $page_title ?? SITE_TITLE;

// Initialize body data attributes if not set
$body_data_attributes = $body_data_attributes ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <!-- Google Analytics code should be placed here, right at the beginning of the head section -->
    <!-- The async attribute ensures it won't block page rendering -->
    <!-- Example: 
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-XXXXXXXXXX');
    </script>
    -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Flag Icons -->
    <link rel="stylesheet" href="/static/css/flag-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/static/css/custom.css">
    
    <!-- Achievement Notification CSS -->
    <link rel="stylesheet" href="/static/css/achievements.css">
    
    <!-- Google AdSense code - only shown to non-VIP users -->
    <?php
    // Check if user is not logged in or not a VIP (hasn't donated)
    $show_ads = true;
    
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_anonymous'])) {
        // User is logged in, check if they're a VIP
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT vip FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If user is a VIP (has made a donation), don't show ads
        if ($user && isset($user['vip']) && $user['vip'] == 1) {
            $show_ads = false;
        }
    }
    
    // Only show AdSense code if user is not a VIP
    if ($show_ads):
    ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>
    <?php endif; ?>
</head>
<body<?php 
// Add data attributes for game pages
if (isset($body_data_attributes) && is_array($body_data_attributes) && !empty($body_data_attributes)) {
    foreach ($body_data_attributes as $attr => $value) {
        echo ' data-' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
    }
}
?>>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/index.php"><i class="fas fa-robot me-2"></i>Real vs AI</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/instructions.php">Instructions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/leaderboard.php">Leaderboard</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="/contribute.php">Contribute</a>
                    </li>
                    <?php if (isset($_SESSION['user_id']) && !isset($_SESSION['is_anonymous'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/daily-summary.php">Daily Challenge</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id']) && !isset($_SESSION['is_anonymous'])): ?>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/admin.php">Admin</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-warning" href="/admin.php#upload-section" onclick="switchToBulkUpload()">
                                    <i class="fas fa-cloud-upload-alt"></i> Bulk Upload
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <span class="nav-link text-light">
                                Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                                <?php 
                                // Show VIP badge for users who have donated
                                if (!$show_ads): // Using the already calculated $show_ads variable
                                ?>
                                <span class="badge bg-warning text-dark ms-1" title="Ad-free experience">
                                    <i class="fas fa-crown me-1"></i>VIP
                                </span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/profile.php"><i class="fas fa-user me-1"></i>My Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">Logout</a>
                        </li>
                    <?php elseif (isset($_SESSION['is_anonymous'])): ?>
                        <li class="nav-item">
                            <span class="nav-link text-light">Playing as: <?= htmlspecialchars($_SESSION['username']) ?></span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">Anonymous (Logout)</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link auth-link" href="/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link auth-link" href="/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="container main-content">
        <!-- Flash Messages -->
        <?php 
        // Use correct namespace for template functions
        use RealAI\Template;
        echo Template\render_flash_messages();
        ?>