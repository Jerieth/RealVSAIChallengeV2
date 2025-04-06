<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card bg-dark mt-4">
                <div class="card-header bg-dark">
                    <h3 class="text-center mb-0">Login</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/login.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <?php if (!empty($redirect_to)): ?>
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirect_to) ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3 d-flex justify-content-center">
                            <div style="width: 50%;">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4 d-flex justify-content-center">
                            <div style="width: 50%;">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-center mb-3">
                            <button type="submit" class="btn btn-primary" style="width: 50%;">Login</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-dark">
                    <div class="text-center">
                        <p>Don't have an account? <a href="/register.php" class="text-info">Register Here</a></p>
                    </div>
                </div>
            </div>

            <div class="card bg-dark mt-4">
                <div class="card-body">
                    <h5 class="text-center">Play Anonymously</h5>
                    <p class="text-center">You can still play the game without an account, but your progress won't be saved.</p>
                    <div class="d-flex justify-content-center">
                        <a href="/index.php" class="btn btn-outline-secondary" style="width: 50%;">Continue as Guest</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>