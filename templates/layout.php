<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : DEFAULT_PAGE_TITLE ?></title>
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/static/css/custom.css">
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/index.php">
                <i class="fas fa-robot me-2"></i>
                <?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/leaderboard.php">Leaderboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/instructions.php">How to Play</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contribute.php">Contribute</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/profile.php">
                                <i class="fas fa-user-circle me-1"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>
                        <?php if (is_current_user_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin.php">
                                <i class="fas fa-cog me-1"></i> Admin
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php 
    // Use the RealAI\Template namespace for flash messages
    use RealAI\Template;
    echo '<div class="container mt-3">' . Template\render_flash_messages() . '</div>'; 
    ?>

    <!-- Main Content -->
    <main class="my-4">
        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><?= APP_NAME ?></h5>
                    <p class="small">Can you tell which images are real and which are AI-generated?</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/terms.php" class="text-light">Terms of Service</a></li>
                        <li><a href="/privacy.php" class="text-light">Privacy Policy</a></li>
                        <li><a href="<?= MORE_GAMES_LINK ?>" class="text-light" target="_blank">More Games</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Support Us</h5>
                    <p class="small">If you enjoy this game, please consider making a donation to support further development.</p>
                    <a href="<?= DONATION_LINK ?>" class="btn btn-outline-light btn-sm" target="_blank">
                        <i class="fab fa-paypal me-1"></i> Donate
                    </a>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="small mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/static/js/validation.js"></script>
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
</body>
</html>