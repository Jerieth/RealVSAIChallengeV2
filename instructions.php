<?php
/**
 * Instructions page for Real vs AI application
 */

require_once 'includes/header.php';
?>

<div class="container mt-4 mb-5 pb-5">
    <div class="row">
        <div class="col-lg-12 mx-auto">
            <div class="card" style="background-color: #1e2940; color: #ffffff;">
                <div class="card-header" style="background-color: #0d1117; border-bottom: 1px solid rgba(116, 103, 253, 0.2);">
                    <h1 class="card-title text-center">How to Play Real vs AI</h1>
                </div>
                <div class="card-body" style="font-size: 1.05rem; line-height: 1.6;">
                    <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <h2 class="mb-3" style="color: #50b2ea;">Game Overview</h2>
                        <p>
                            Real vs AI is an image identification game that challenges your ability to distinguish between 
                            real photographs and AI-generated images. In each turn, you will be presented with two images and must 
                            determine which one is real and which one is AI-generated.
                        </p>
                        
                        <h3 class="mt-4 mb-3" style="color: #50b2ea;">Understanding AI-Generated Images</h3>
                        <p>
                            In this game, AI-generated images refer to images that are either completely AI-generated or are 
                            AI-assisted composite images. These images are created using AI models that analyze and manipulate 
                            elements from real photographs, combining them into a new visual design.
                        </p>
                        <p>
                            Some characteristics of AI-generated images include:
                        </p>
                        <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Image stitching - AI blends parts of various photos together seamlessly</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Style transfer - AI overlays artistic elements onto a base image</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Image inpainting - AI fills in missing parts of photos creatively</li>
                        </ul>
                        <p>
                            These creations retain elements of realism due to their use of real photos but are often modified 
                            or augmented to suit specific creative purposes.
                        </p>
                    </div>
                    
                    <h2 class="mb-4 text-center" style="color: #50b2ea;">Game Modes</h2>
                    
                    <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <h3 class="mb-3" style="color: #50b2ea;">Single Player</h3>
                        <p>Challenge yourself to identify AI-generated images across three difficulty levels:</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100" style="background-color: #324055; border-color: rgba(116, 103, 253, 0.3);">
                                    <div class="card-header" style="background-color: #0d1117; color: #ffffff; border-bottom: 1px solid rgba(116, 103, 253, 0.2);">
                                        <h4 class="mb-0">Easy Mode</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">✦ 20 turns</li>
                                            <li class="mb-2">✦ 5 lives</li>
                                            <li class="mb-2">✦ More obvious differences between real and AI images</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100" style="background-color: #324055; border-color: rgba(116, 103, 253, 0.3);">
                                    <div class="card-header" style="background-color: #0d1117; color: #ffffff; border-bottom: 1px solid rgba(116, 103, 253, 0.2);">
                                        <h4 class="mb-0">Medium Mode</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">✦ 50 turns</li>
                                            <li class="mb-2">✦ 3 lives</li>
                                            <li class="mb-2">✦ More subtle differences between images</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100" style="background-color: #324055; border-color: rgba(116, 103, 253, 0.3);">
                                    <div class="card-header" style="background-color: #0d1117; color: #ffffff; border-bottom: 1px solid rgba(116, 103, 253, 0.2);">
                                        <h4 class="mb-0">Hard Mode</h4>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">✦ 100 turns</li>
                                            <li class="mb-2">✦ 1 life</li>
                                            <li class="mb-2">✦ Very challenging to distinguish between images</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4" style="background-color: #253146; border-color: rgba(116, 103, 253, 0.3);">
                            <div class="card-header" style="background-color: #0d1117; color: #ffffff; border-bottom: 1px solid rgba(116, 103, 253, 0.2);">
                                <h4 class="mb-0">Bonus Mini-Game</h4>
                            </div>
                            <div class="card-body">
                                <p>At various points, 
                                you may have an opportunity to play a bonus mini-game. You'll be shown four images, with only one being real. 
                                If you correctly identify the real image, you'll earn an extra life. However, if you guess incorrectly, 
                                you'll lose half your current score (rounded up). The bonus game is optional - you can choose to skip it if you prefer.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <h3 class="mb-3" style="color: #50b2ea;">Play with Friends (Multiplayer)</h3>
                        <p>
                            Challenge your friends to see who has the better eye for spotting AI-generated images:
                        </p>
                        <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Create a room and share the code with friends</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Each player takes turns identifying images</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Games require at least 2 players to start</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Players can join a game in progress</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Scores are based on accuracy and streaks</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">The winner is determined after all turns are completed</li>
                        </ul>
                    </div>
                    
                    <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <h3 class="mb-3" style="color: #50b2ea;">Endless Mode</h3>
                        <p>
                            Practice your skills with an unlimited number of images:
                        </p>
                        <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">One life</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">No turn limit</li>
                            <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Great for improving your detection skills</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); height: 100%;">
                                <h2 class="mb-3" style="color: #50b2ea;">Scoring System</h2>
                                <p>
                                    Your score increases with each correct answer. Bonus points are awarded for:
                                </p>
                                <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Base score: 10 points per correct answer</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Streak bonus: Up to 50 additional points for consecutive correct answers</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); height: 100%;">
                                <h2 class="mb-3" style="color: #50b2ea;">Achievements</h2>
                                <p>
                                    Unlock special badges and achievements for your accomplishments:
                                </p>
                                <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Completing games at different difficulty levels</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Reaching score milestones (20+, 50+, 100+)</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Playing and winning multiplayer matches</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5 p-4" style="background-color: #27334d; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <h2 class="mb-3" style="color: #50b2ea;">Tips for Success</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Pay attention to fine details like human faces, hands, and text</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Look for inconsistencies in lighting and shadows</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush mb-3" style="background-color: transparent;">
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Check for unnatural blending or distortions</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Practice in Endless Mode to improve your skills</li>
                                    <li class="list-group-item" style="background-color: #324055; color: #ffffff; border-color: rgba(255,255,255,0.1);">Challenge friends to sharpen your abilities</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5 mb-5 pb-5">
                        <a href="tutorial.php" class="btn btn-success btn-lg me-3">Start Tutorial</a>
                        <a href="index.php" class="btn btn-primary btn-lg">Return to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>