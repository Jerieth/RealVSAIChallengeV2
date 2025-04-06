<div class="container">
    <!-- Hero Section -->
    <div class="row align-items-center py-5">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <h1 class="display-4 fw-bold mb-3">Real vs AI</h1>
            <p class="lead mb-4">
                Can you tell the difference between real photos and AI-generated images? 
                Put your skills to the test in this challenging and educational game.
            </p>
            <div class="d-grid gap-2 d-md-flex">
                <a href="/start-game" class="btn btn-primary btn-lg">
                    <i class="fas fa-play me-2"></i> Play Now
                </a>
                <a href="/instructions" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-info-circle me-2"></i> How to Play
                </a>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card bg-dark">
                <div class="card-body p-0">
                    <div class="row g-0">
                        <div class="col-6">
                            <img src="/static/images/Real/sample1.jpg" class="img-fluid rounded-start" alt="Real Photo">
                            <div class="position-absolute bottom-0 start-0 bg-success text-white p-2 m-2 rounded">
                                Real Photo
                            </div>
                        </div>
                        <div class="col-6">
                            <img src="/static/images/AI/sample1.jpg" class="img-fluid rounded-end" alt="AI Image">
                            <div class="position-absolute bottom-0 end-0 bg-primary text-white p-2 m-2 rounded">
                                AI Generated
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Game Modes Section -->
    <div class="row py-5">
        <div class="col-12 text-center mb-4">
            <h2>Game Modes</h2>
            <p class="lead">Choose how you want to play</p>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-user text-primary"></i>
                    </div>
                    <h3 class="card-title">Single Player</h3>
                    <p class="card-text">
                        Test your skills with different difficulty levels. 
                        Choose from Easy, Medium, or Hard to challenge yourself.
                    </p>
                </div>
                <div class="card-footer">
                    <a href="/start-game" class="btn btn-primary d-block mb-2">
                        <i class="fas fa-play me-2"></i> Start Single Player
                    </a>
                    <?php if (is_logged_in()): ?>
                    <a href="/daily-summary" class="btn btn-info d-block">
                        <i class="fas fa-calendar-day me-2"></i> Play Daily Challenge
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-users text-success"></i>
                    </div>
                    <h3 class="card-title">Multiplayer</h3>
                    <p class="card-text">
                        Compete with friends to see who has the better eye for 
                        spotting AI-generated images. Play with up to 4 players.
                    </p>
                </div>
                <div class="card-footer">
                    <a href="/multiplayer" class="btn btn-success d-block">
                        <i class="fas fa-users me-2"></i> Play with Friends
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-infinity text-info"></i>
                    </div>
                    <h3 class="card-title">Endless Mode</h3>
                    <p class="card-text">
                        Keep playing as long as you can to achieve the highest score. 
                        Challenge yourself with unlimited turns!
                    </p>
                </div>
                <div class="card-footer">
                    <a href="/start-game?mode=endless" class="btn btn-info d-block">
                        <i class="fas fa-infinity me-2"></i> Start Endless Mode
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Dashboard (if logged in) -->
    <?php 
    // Check for user and fetch their data - properly checking for variable existence
    $user = isset($user) ? $user : (function_exists('get_current_logged_in_user') ? get_current_logged_in_user() : null);
    $total_games = isset($total_games) ? $total_games : 0;
    $highest_score = isset($highest_score) ? $highest_score : 0;
    $user_achievements = isset($user_achievements) ? $user_achievements : [];
    $recent_achievements = isset($recent_achievements) ? $recent_achievements : [];
    
    if ($user): 
    ?>
    <div class="row py-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3>Your Dashboard</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Games Played</h5>
                                    <p class="display-4"><?= $total_games ?></p>
                                    <a href="/profile" class="btn btn-sm btn-outline-primary">View History</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Highest Score</h5>
                                    <p class="display-4"><?= $highest_score ?></p>
                                    <a href="/profile" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Achievements</h5>
                                    <p class="display-4"><?= count($user_achievements) ?></p>
                                    <a href="/achievements" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($recent_achievements)): ?>
                    <div class="mt-3">
                        <h5>Recent Achievements</h5>
                        <div class="row">
                            <?php foreach ($recent_achievements as $achievement): ?>
                            <div class="col-md-4 mb-2">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas <?= $achievement['icon'] ?> text-success me-2"></i>
                                            <?= $achievement['title'] ?>
                                        </h6>
                                        <p class="card-text small"><?= $achievement['description'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leaderboard Section -->
    <div class="row py-5">
        <div class="col-12 text-center mb-4">
            <h2>Leaderboards</h2>
            <p class="lead">See who has the best eye for spotting AI images</p>
        </div>
        
        <?php
        // Initialize leaderboard variables if not set
        $single_easy_leaderboard = isset($single_easy_leaderboard) ? $single_easy_leaderboard : [];
        $endless_leaderboard = isset($endless_leaderboard) ? $endless_leaderboard : [];
        ?>
        
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4>Single Player - Easy</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($single_easy_leaderboard)): ?>
                        <p class="text-center text-muted">No entries yet. Be the first!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Player</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($single_easy_leaderboard as $index => $entry): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($entry['initials']) ?></td>
                                        <td><strong><?= $entry['score'] ?></strong></td>
                                        <td><?= date('M j, Y', strtotime($entry['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="/leaderboard?mode=single&difficulty=easy" class="btn btn-sm btn-outline-primary">View Full Leaderboard</a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4>Endless Mode</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($endless_leaderboard)): ?>
                        <p class="text-center text-muted">No entries yet. Be the first!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Player</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($endless_leaderboard as $index => $entry): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($entry['initials']) ?></td>
                                        <td><strong><?= $entry['score'] ?></strong></td>
                                        <td><?= date('M j, Y', strtotime($entry['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="/leaderboard?mode=endless" class="btn btn-sm btn-outline-primary">View Full Leaderboard</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feature Section -->
    <div class="row py-5">
        <div class="col-12 text-center mb-4">
            <h2>Why Play Real vs AI?</h2>
            <p class="lead">More than just a game - it's educational and fun</p>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-brain text-warning"></i>
                    </div>
                    <h4 class="card-title">Train Your Eye</h4>
                    <p class="card-text">
                        Develop your ability to spot AI-generated content in an 
                        increasingly AI-dominated digital landscape.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-trophy text-success"></i>
                    </div>
                    <h4 class="card-title">Unlock Achievements</h4>
                    <p class="card-text">
                        Earn badges and climb the leaderboards as you improve your 
                        skills and complete challenges.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="display-4 mb-3">
                        <i class="fas fa-users text-info"></i>
                    </div>
                    <h4 class="card-title">Play with Friends</h4>
                    <p class="card-text">
                        Challenge your friends to see who has the better eye for 
                        detecting AI-generated images.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call to Action -->
    <div class="row py-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-5">
                    <h3 class="card-title">Ready to Test Your Skills?</h3>
                    <p class="card-text mb-4">
                        Start playing now and see if you can tell the difference between real and AI-generated images!
                    </p>
                    <a href="/start-game" class="btn btn-light btn-lg">Play Now</a>
                </div>
            </div>
        </div>
    </div>
</div>