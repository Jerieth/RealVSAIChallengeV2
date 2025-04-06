<div class="container">
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5">
                    <?php if (!empty($user['avatar'])): ?>
                        <span class="me-2" id="currentAvatar"><?= htmlspecialchars($user['avatar']) ?></span>
                    <?php else: ?>
                        <i class="fas fa-user-circle me-2" id="defaultAvatar"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($user['username']) ?>
                    <?php if (isset($user['vip']) && $user['vip'] == 1): ?>
                    <span class="badge bg-warning text-dark ms-2" title="Ad-free experience">
                        <i class="fas fa-crown me-1"></i>VIP
                    </span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted">
                    Member since <?= \RealAI\Template\format_datetime($user_stats['joined'], 'F j, Y') ?>
                    <?php if (isset($user['vip']) && $user['vip'] == 1): ?>
                    <span class="ms-2 text-warning"><i class="fas fa-star me-1"></i>Ad-free experience</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if (is_current_user_admin()): ?>
                    <a href="admin.php" class="btn btn-outline-primary">
                        <i class="fas fa-cog me-1"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-stats">
            <div class="profile-stat-item">
                <div class="profile-stat-label">Games Played</div>
                <div class="profile-stat-value"><?= $user_stats['total_games'] ?></div>
            </div>
            <div class="profile-stat-item">
                <div class="profile-stat-label">Highest Score</div>
                <div class="profile-stat-value"><?= $user_stats['highest_score'] ?></div>
            </div>
            <div class="profile-stat-item">
                <div class="profile-stat-label">Achievements</div>
                <div class="profile-stat-value"><?= $earned_achievements_count ?></div>
            </div>
            <?php if ($daily_challenge_stats): ?>
            <div class="profile-stat-item">
                <div class="profile-stat-label">Daily Challenges</div>
                <div class="profile-stat-value"><?= $daily_challenge_stats['games_completed'] ?></div>
            </div>
            <div class="profile-stat-item">
                <div class="profile-stat-label">Current Streak</div>
                <div class="profile-stat-value"><?= $daily_challenge_stats['streak'] ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Top Scores</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($best_scores)): ?>
                        <p class="text-center">No scores yet. Start playing to see your best performances!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Score</th>
                                        <th>Game Mode</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($best_scores as $score): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($score['score']) ?></strong></td>
                                            <td>
                                                <?= htmlspecialchars(ucfirst($score['game_mode'])) ?>
                                                <?php if (!empty($score['difficulty'])): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($score['difficulty'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= \RealAI\Template\format_datetime($score['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Recent Games</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_games)): ?>
                        <p class="text-center">No games played yet. Start playing to see your game history!</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mode</th>
                                        <th>Score</th>
                                        <th>Result</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_games as $game): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars(ucfirst($game['game_mode'])) ?>
                                                <?php if (!empty($game['difficulty'])): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($game['difficulty'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($game['score']) ?></td>
                                            <td>
                                                <?php if ($game['completed']): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Game Over</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= \RealAI\Template\format_datetime($game['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($daily_challenge_stats): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-light">
                    <h4 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Daily Challenge Stats</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Completed</h5>
                                    <p class="display-4"><?= $daily_challenge_stats['games_completed'] ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Current Streak</h5>
                                    <p class="display-4"><?= $daily_challenge_stats['streak'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <?php
                        // Calculate when the next challenge is available
                        $next_challenge = null;
                        $can_play = false;
                        
                        if (isset($daily_challenge_stats['next_challenge_date'])) {
                            $next_challenge = new DateTime($daily_challenge_stats['next_challenge_date']);
                            $now = new DateTime();
                            
                            if ($next_challenge <= $now) {
                                $can_play = true;
                            }
                        } else {
                            $can_play = true; // First time player can play immediately
                        }
                        ?>
                        
                        <?php if ($can_play): ?>
                            <a href="/daily-summary.php" class="btn btn-warning">
                                <i class="fas fa-play-circle me-2"></i>Today's Challenge is Ready!
                            </a>
                        <?php else: ?>
                            <p class="mb-3">Next challenge available:</p>
                            <h5><?= $next_challenge->format('F j, Y g:i A') ?></h5>
                            
                            <?php
                            // Calculate time remaining
                            $interval = $now->diff($next_challenge);
                            $hours = $interval->h + ($interval->days * 24);
                            $minutes = $interval->i;
                            ?>
                            
                            <p class="text-muted">(in <?= sprintf('%d hours, %d minutes', $hours, $minutes) ?>)</p>
                            
                            <a href="/daily-summary.php" class="btn btn-outline-warning mt-2">
                                <i class="fas fa-calendar-day me-2"></i>View Daily Challenge Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-light">
                    <h4 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Daily Challenge</h4>
                </div>
                <div class="card-body text-center">
                    <p class="mb-4">You haven't played any Daily Challenges yet!</p>
                    <p>Play a new challenge each day and earn special achievements for consistent play.</p>
                    <a href="/daily-summary.php" class="btn btn-warning mt-3">
                        <i class="fas fa-play-circle me-2"></i>Start Your First Challenge
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Achievements</h4>
                    <a href="all_achievements.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($achievements)): ?>
                        <p class="text-center">No achievements available.</p>
                    <?php else: ?>
                        <div class="achievement-list">
                            <?php 
                            $count = 0;
                            foreach ($achievements as $achievement_type => $achievement): 
                                $hiddenClass = $count >= 14 ? 'achievement-hidden' : '';
                                $count++;
                            ?>
                                <div class="achievement-item <?= $achievement['earned'] ? 'earned' : 'locked' ?> <?= $hiddenClass ?>">
                                    <div class="achievement-icon">
                                        <i class="<?= htmlspecialchars($achievement['icon']) ?>"></i>
                                    </div>
                                    <div class="achievement-details">
                                        <h4><?= htmlspecialchars($achievement['title']) ?></h4>
                                        <p><?= htmlspecialchars($achievement['description']) ?></p>
                                        <?php if ($achievement['earned']): ?>
                                            <div class="achievement-date">
                                                Earned on <?= \RealAI\Template\format_datetime($achievement['date'], 'M j, Y') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($achievements) > 14): ?>
                            <div class="text-center mt-3">
                                <button id="expandAchievements" class="btn btn-outline-primary">Show More Achievements</button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Account Settings</h4>
                </div>
                <div class="card-body">
                    <!-- Avatar Selection -->
                    <div class="mb-4">
                        <h5>Avatar for Multiplayer</h5>
                        <p class="text-muted small mb-3">Choose an avatar that will appear next to your name in multiplayer games</p>

                        <form action="/profile.php" method="post" id="avatarForm">
                            <input type="hidden" name="action" value="update_avatar">
                            <input type="hidden" name="avatar" id="selectedAvatar" value="<?= htmlspecialchars($user['avatar'] ?? '') ?>">

                            <div class="avatar-selection mb-3">
                                <div class="row g-2">
                                    <!-- Humans -->
                                    <div class="col-12 mb-2">
                                        <div class="avatar-category">Humans</div>
                                    </div>
                                    <?php foreach (['👨', '👩', '👴', '👵', '👲', '👳‍♂️', '👳‍♀️', '👮‍♂️', '👮‍♀️', '👷‍♂️', '👷‍♀️', '💂‍♂️', '👨‍⚕️', '👩‍⚕️'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" data-avatar="<?= $avatar ?>">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Robots -->
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">Robots & Technology</div>
                                    </div>
                                    <?php foreach (['🤖', '👾', '💻', '🖥️', '📽️','📱', '📟', '🎮', '🕹️', '🔌', '💡', '🔋', '🔍', '📡'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" data-avatar="<?= $avatar ?>">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- VIP Exclusive Emojis -->
                                    <?php if (isset($user['vip']) && $user['vip'] == 1): ?>
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">
                                            <i class="fas fa-crown text-warning me-2"></i>VIP Basic Tier
                                        </div>
                                        <small class="text-muted">Special avatars available to all donors</small>
                                    </div>
                                    <?php foreach ($tier_avatars['vip'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" 
                                                 data-avatar="<?= $avatar ?>"
                                                 data-bs-toggle="tooltip"
                                                 title="VIP exclusive avatar">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- $2+ Donation Tier -->
                                    <?php if (isset($total_donations) && $total_donations >= 2): ?>
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">
                                            <i class="fas fa-star text-warning me-2"></i>Bronze Tier ($2+)
                                        </div>
                                        <small class="text-muted">Special avatars for $2+ donors</small>
                                    </div>
                                    <?php foreach ($tier_avatars['tier1'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" 
                                                 data-avatar="<?= $avatar ?>"
                                                 data-bs-toggle="tooltip"
                                                 title="Bronze tier avatar ($2+ donors)">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- $5+ Donation Tier -->
                                    <?php if (isset($total_donations) && $total_donations >= 5): ?>
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">
                                            <i class="fas fa-medal text-secondary me-2"></i>Silver Tier ($5+)
                                        </div>
                                        <small class="text-muted">Special avatars for $5+ donors</small>
                                    </div>
                                    <?php foreach ($tier_avatars['tier2'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" 
                                                 data-avatar="<?= $avatar ?>"
                                                 data-bs-toggle="tooltip"
                                                 title="Silver tier avatar ($5+ donors)">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- $25+ Donation Tier -->
                                    <?php if (isset($total_donations) && $total_donations >= 25): ?>
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">
                                            <i class="fas fa-award text-warning me-2"></i>Gold Tier ($25+)
                                        </div>
                                        <small class="text-muted">Special avatars for $25+ donors</small>
                                    </div>
                                    <?php foreach ($tier_avatars['tier3'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" 
                                                 data-avatar="<?= $avatar ?>"
                                                 data-bs-toggle="tooltip"
                                                 title="Gold tier avatar ($25+ donors)">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- $50+ Donation Tier -->
                                    <?php if (isset($total_donations) && $total_donations >= 50): ?>
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">
                                            <i class="fas fa-gem text-info me-2"></i>Platinum Tier ($50+)
                                        </div>
                                        <small class="text-muted">Special avatars for $50+ donors</small>
                                    </div>
                                    <?php foreach ($tier_avatars['tier4'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" 
                                                 data-avatar="<?= $avatar ?>"
                                                 data-bs-toggle="tooltip"
                                                 title="Platinum tier avatar ($50+ donors)">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Other Emojis -->
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">Animals & Nature</div>
                                    </div>
                                    <?php foreach (['🐱', '🐶', '🦊', '🐼', '🐨', '🦁', '🐯', '🐮', '🐷', '🐸', '🐙', '🦄', '🦉', '🦋'] as $avatar): ?>
                                        <div class="col-2 col-md-1">
                                            <div class="avatar-option <?= ($user['avatar'] === $avatar) ? 'selected' : '' ?>" data-avatar="<?= $avatar ?>">
                                                <?= $avatar ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Rewards - Earned Avatars -->
                                    <div class="col-12 mb-2 mt-3">
                                        <div class="avatar-category">Rewards</div>
                                        <small class="text-muted">Special avatars earned from achievements and challenges</small>
                                    </div>
                                    
                                    <!-- Default reward for registering -->
                                    <div class="col-2 col-md-1">
                                        <div class="avatar-option <?= ($user['avatar'] === '🎉') ? 'selected' : '' ?>" 
                                             data-avatar="🎉" 
                                             data-bs-toggle="tooltip" 
                                             title="Earned for registering an account">
                                            🎉
                                        </div>
                                    </div>
                                    
                                    <?php
                                    // Get daily challenge reward avatars
                                    require_once __DIR__ . '/../includes/daily_challenge_functions.php';
                                    $bonus_avatars = d_get_user_avatar_rewards($user['username']);
                                    
                                    // Display daily challenge bonus avatars if earned
                                    if (!empty($bonus_avatars)) {
                                        foreach ($bonus_avatars as $avatar) {
                                            echo '<div class="col-2 col-md-1">';
                                            echo '<div class="avatar-option ' . ($user['avatar'] === $avatar ? 'selected' : '') . '" ';
                                            echo 'data-avatar="' . htmlspecialchars($avatar) . '" ';
                                            echo 'data-bs-toggle="tooltip" ';
                                            echo 'title="Daily Challenge Bonus Reward">';
                                            echo $avatar;
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // Display earned rewards from the main system if any
                                    if (!empty($user_rewards)) {
                                        foreach ($user_rewards as $reward) {
                                            // Check if the reward image path exists
                                            $image_path = $reward['image_path'];
                                            $image_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . $image_path);
                                            
                                            echo '<div class="col-2 col-md-1">';
                                            echo '<div class="avatar-option ' . ($user['avatar'] === $image_path ? 'selected' : '') . '" ';
                                            echo 'data-avatar="' . htmlspecialchars($image_path) . '" ';
                                            echo 'data-bs-toggle="tooltip" ';
                                            echo 'title="' . htmlspecialchars($reward['name'] . ': ' . $reward['description']) . '">';
                                            
                                            if ($image_exists) {
                                                echo '<img src="' . htmlspecialchars($image_path) . '" alt="' . 
                                                      htmlspecialchars($reward['name']) . '" class="img-fluid" style="width: 100%; height: 100%;">';
                                            } else {
                                                echo '<i class="fas fa-user-circle fa-2x"></i>';
                                            }
                                            
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // Display Achievement-Specific Emoji Rewards
                                    if (isset($achievement_avatars) && !empty($achievement_avatars)) {
                                        echo '<div class="col-12 mb-2 mt-3">';
                                        echo '<div class="avatar-category"><i class="fas fa-trophy text-success me-1"></i>Achievement Rewards</div>';
                                        echo '<small class="text-muted">Special emojis unlocked by earning achievements</small>';
                                        echo '</div>';
                                        
                                        foreach ($achievement_avatars as $achievement_type => $avatars) {
                                            $achievement_title = str_replace('_', ' ', $achievement_type);
                                            $achievement_title = ucwords($achievement_title);
                                            
                                            foreach ($avatars as $avatar) {
                                                echo '<div class="col-2 col-md-1">';
                                                echo '<div class="avatar-option ' . ($user['avatar'] === $avatar ? 'selected' : '') . '" ';
                                                echo 'data-avatar="' . $avatar . '" ';
                                                echo 'data-bs-toggle="tooltip" ';
                                                echo 'title="' . htmlspecialchars($achievement_title) . ' Achievement Reward">';
                                                echo $avatar;
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    
                                    // Display High-Value Donor Avatars ($100+)
                                    if (isset($high_value_donor_avatars) && !empty($high_value_donor_avatars) && isset($total_donations) && $total_donations >= 100) {
                                        echo '<div class="col-12 mb-2 mt-3">';
                                        echo '<div class="avatar-category"><i class="fas fa-gem text-primary me-1"></i>Elite Donor Avatars';
                                        echo '<span class="badge bg-primary ms-2">$100+</span></div>';
                                        echo '</div>';
                                        
                                        foreach ($high_value_donor_avatars['high_value_donor'] as $avatar) {
                                            echo '<div class="col-2 col-md-1">';
                                            echo '<div class="avatar-option ' . ($user['avatar'] === $avatar ? 'selected' : '') . '" ';
                                            echo 'data-avatar="' . $avatar . '" ';
                                            echo 'data-bs-toggle="tooltip" ';
                                            echo 'title="Elite Donor Avatar">';
                                            echo $avatar;
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // Show a message if no rewards are available
                                    if (empty($bonus_avatars) && empty($user_rewards) && empty($achievement_avatars) && (empty($high_value_donor_avatars) || $total_donations < 100)) {
                                        echo '<div class="col-12">';
                                        echo '<div class="alert alert-info">';
                                        echo '<i class="fas fa-info-circle me-2"></i> Complete daily challenges and play the bonus game to earn special avatars!';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if (!empty($user['avatar'])): ?>
                                        <p class="mb-0">Current Avatar: <span class="selected-avatar-display"><?= htmlspecialchars($user['avatar']) ?></span></p>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">No avatar selected</p>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-primary" id="saveAvatarBtn">Save Avatar</button>
                            </div>
                        </form>
                    </div>

                    <hr class="my-4">
                    
                    <!-- VIP Status Section -->
                    <div class="mb-4">
                        <h5>VIP Status</h5>
                        <p class="text-muted small mb-3">VIP users enjoy an ad-free experience across the site and exclusive avatars</p>
                        
                        <?php if (isset($user['vip']) && $user['vip'] == 1): ?>
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-crown fa-2x"></i>
                                    </div>
                                    <div>
                                        <strong>VIP Status Active</strong>
                                        <p class="mb-0">Thank you for your donation! You're enjoying an ad-free experience on Real vs AI and have access to exclusive VIP avatars.</p>
                                        
                                        <?php if (isset($total_donations) && $total_donations > 0): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-light text-dark">Total Donated: $<?= number_format($total_donations, 2) ?></span>
                                            
                                            <?php if ($total_donations >= 50): ?>
                                                <span class="badge bg-info text-white"><i class="fas fa-gem me-1"></i>Platinum Tier</span>
                                            <?php elseif ($total_donations >= 25): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-award me-1"></i>Gold Tier</span>
                                            <?php elseif ($total_donations >= 5): ?>
                                                <span class="badge bg-secondary text-white"><i class="fas fa-medal me-1"></i>Silver Tier</span>
                                            <?php elseif ($total_donations >= 2): ?>
                                                <span class="badge bg-danger text-white"><i class="fas fa-star me-1"></i>Bronze Tier</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <a href="/contribute.php" class="btn btn-sm btn-outline-warning">Donate More</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert bg-dark border border-secondary" style="border-radius: 8px;">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-crown fa-2x text-secondary"></i>
                                    </div>
                                    <div>
                                        <strong class="text-light">Standard Account</strong>
                                        <p class="mb-0 text-light">Make a donation to earn VIP status and enjoy an ad-free experience and exclusive avatars. <a href="/contribute.php" class="text-warning">Support Real vs AI</a></p>
                                        
                                        <div class="mt-3">
                                            <h6 class="mb-2 text-light">VIP Benefits:</h6>
                                            <ul class="list-unstyled small text-light">
                                                <li><i class="fas fa-check-circle text-success me-1"></i> Ad-free experience</li>
                                                <li><i class="fas fa-check-circle text-success me-1"></i> Exclusive VIP avatars</li>
                                                <li><i class="fas fa-check-circle text-success me-1"></i> Special "Donator" achievement</li>
                                                <li><i class="fas fa-check-circle text-success me-1"></i> More avatars with higher donations</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="/contribute.php" class="btn btn-warning btn-sm">
                                                <i class="fas fa-crown me-1"></i> Become a VIP
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">

                    <!-- Email Update -->
                    <div class="mb-4">
                        <h5>Update Email</h5>
                        <form action="/profile.php" method="post" id="emailForm">
                            <input type="hidden" name="action" value="update_email">

                            <div class="mb-3">
                                <label for="current_email" class="form-label">Current Email</label>
                                <input type="email" class="form-control bg-dark text-light" id="current_email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="new_email" class="form-label">New Email</label>
                                <input type="email" class="form-control bg-dark text-light" id="new_email" name="new_email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password_for_email" class="form-label">Current Password</label>
                                <input type="password" class="form-control bg-dark text-light" id="password_for_email" name="password" required>
                                <div class="form-text">Enter your current password to confirm this change</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Email</button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <!-- Country Selection -->
                    <div class="mb-4">
                        <h5>Your Country</h5>
                        <p class="text-muted small mb-3">Select your country to display a flag next to your name on the leaderboard</p>

                        <?php
                        // Include countries helper functions
                        require_once __DIR__ . '/../includes/countries.php';

                        // Get user's country or default to US
                        $user_country = $user['country'] ?? 'US';
                        $country_name = get_country_name($user_country);
                        ?>

                        <div class="mb-3">
                            <div class="country-display mb-3">
                                <div class="country-flag"><?= get_country_flag_html($user_country, 'md') ?></div>
                                <div><?= htmlspecialchars($country_name) ?></div>
                            </div>
                            <a href="/update_user_country.php" class="btn btn-outline-primary">
                                <i class="fas fa-globe me-1"></i> Change Country
                            </a>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Game Preferences -->
                    <div class="mb-4">
                        <h5>Game Preferences</h5>
                        <p class="text-muted small mb-3">Customize your gameplay experience with these settings</p>

                        <form action="/profile.php" method="post" id="timerPreferencesForm">
                            <input type="hidden" name="action" value="update_timer_preference">

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="hideTimer" name="hide_timer" value="1" <?= ($user['hide_timer'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hideTimer">Hide Timer</label>
                                <div class="form-text">The timer will still work in the background, but won't be visible during gameplay.</div>
                                <div class="form-text text-warning mt-1">Note: This only affects Single Player (Hard), Multiplayer, and Endless modes.</div>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="hideUsername" name="hide_username" value="1" <?= ($user['hide_username'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hideUsername">Hide Username on Leaderboard</label>
                                <div class="form-text">Your username will be partially hidden on the leaderboard (e.g., "j***n" instead of "jason").</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Preferences</button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <!-- Password Update -->
                    <div>
                        <h5>Change Password</h5>
                        <form action="/profile.php" method="post" id="passwordForm">
                            <input type="hidden" name="action" value="update_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control bg-dark text-light" id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control bg-dark text-light" id="new_password" name="new_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control bg-dark text-light" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>

            <script src="/static/js/profile.js"></script>
        </div>
    </div>
</div>