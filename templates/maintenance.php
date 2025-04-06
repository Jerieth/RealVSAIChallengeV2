<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
        }
        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            color: var(--bs-warning);
        }
        .maintenance-card {
            max-width: 600px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="maintenance-card card">
        <div class="card-body">
            <i class="fas fa-tools maintenance-icon"></i>
            <h1 class="display-4">Under Maintenance</h1>
            <p class="lead">We're currently performing some updates to make your experience better.</p>
            <hr>
            <p>Please check back later. We apologize for any inconvenience.</p>
            
            <?php if (is_current_user_admin()): ?>
                <div class="alert alert-info mt-4">
                    <p><strong>Admin Notice:</strong> You're seeing this page because the site is in maintenance mode, but you're logged in as an administrator.</p>
                    <a href="admin.php" class="btn btn-primary">Go to Admin Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>