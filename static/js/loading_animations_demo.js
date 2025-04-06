/**
 * Loading Animations Demo JavaScript
 * This file contains all the animation logic for the loading animations demo
 */

// Animation descriptions for each demo
const animationDescriptions = {
    1: "This animation simulates a pixel-by-pixel image sorting process. Colorful columns rise at different speeds, creating a visually engaging effect that could be used during image processing phases of the game.",
    2: "A camera shutter animation that mimics the opening and closing of a real camera. This could be used when transitioning between game states or when revealing images.",
    3: "An audio wave-like visualization that represents AI analyzing images. The varying heights create a dynamic effect that conveys processing activity.",
    4: "This animation simulates an AI recognition system analyzing an image grid by grid. The highlighted cells and detection frames represent the AI identifying objects or patterns.",
    5: "A visual representation of Real vs AI battling each other. Particle effects shoot between the two sides, creating a dynamic combat-like animation.",
    6: "A simple yet effective progress bar with a glowing effect. Perfect for showing loading progress, score calculations, or time remaining in game modes.",
    7: "An interactive slider that divides the screen between 'Real' and 'AI' sections. This could be used for tutorial sections or comparison challenges.",
    8: "An animated game logo that brings energy to the title. Particles emanate from the text, creating a dynamic intro animation.",
    9: "A complete loading screen with progress bar and dynamic messages. This provides players with both visual feedback and entertaining messages during loading times.",
    10: "A fullscreen overlay simulation that demonstrates how a loading animation would appear during actual gameplay. This creates an immersive transition between game states."
};

// Code samples for each demo
const codeSamples = {
    1: `// Initialize pixel sorting animation
function initPixelSortAnimation() {
    const container = document.createElement('div');
    container.className = 'pixel-sorting-container';
    
    // Create columns
    const columnCount = Math.floor(container.clientWidth / 6);
    
    for (let i = 0; i < columnCount; i++) {
        const column = document.createElement('div');
        column.className = 'pixel-column';
        column.style.left = (i * 6) + 'px';
        container.appendChild(column);
    }
    
    // Animate columns with random heights
    animateColumns(container);
    
    return container;
}

function animateColumns(container) {
    const columns = container.querySelectorAll('.pixel-column');
    
    columns.forEach(column => {
        const randomHeight = Math.random() * 200 + 20;
        column.style.height = randomHeight + 'px';
    });
    
    setTimeout(() => animateColumns(container), 800);
}`,
    2: `// Initialize camera shutter animation
function initCameraShutterAnimation() {
    const container = document.createElement('div');
    container.className = 'camera-shutter-container';
    
    // Create shutter blades
    const bladePositions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
    
    bladePositions.forEach(position => {
        const blade = document.createElement('div');
        blade.className = 'shutter-blade ' + position;
        container.appendChild(blade);
    });
    
    // Add lens
    const lens = document.createElement('div');
    lens.className = 'shutter-lens';
    container.appendChild(lens);
    
    // Animate shutter
    let isClosed = false;
    
    function toggleShutter() {
        if (isClosed) {
            container.classList.remove('closed');
        } else {
            container.classList.add('closed');
        }
        isClosed = !isClosed;
        setTimeout(toggleShutter, 2000);
    }
    
    setTimeout(toggleShutter, 1000);
    
    return container;
}`,
    3: `// Initialize AI analysis wave animation
function initAiAnalysisWave() {
    const container = document.createElement('div');
    container.className = 'ai-wave-container';
    
    const wave = document.createElement('div');
    wave.className = 'ai-wave';
    container.appendChild(wave);
    
    // Create bars
    const barCount = 30;
    
    for (let i = 0; i < barCount; i++) {
        const bar = document.createElement('div');
        bar.className = 'ai-wave-bar';
        wave.appendChild(bar);
    }
    
    // Animate bars
    function animateWave() {
        const bars = wave.querySelectorAll('.ai-wave-bar');
        
        bars.forEach(bar => {
            const randomHeight = Math.random() * 40 + 10;
            bar.style.height = randomHeight + 'px';
        });
        
        setTimeout(animateWave, 100);
    }
    
    animateWave();
    
    return container;
}`,
    4: `// Initialize image recognition animation
function initImageRecognition() {
    const container = document.createElement('div');
    container.className = 'image-recognition-container';
    
    // Create grid
    const grid = document.createElement('div');
    grid.className = 'recognition-grid';
    container.appendChild(grid);
    
    // Create cells
    for (let i = 0; i < 100; i++) {
        const cell = document.createElement('div');
        cell.className = 'recognition-cell';
        grid.appendChild(cell);
    }
    
    // Create recognition frame
    const frame = document.createElement('div');
    frame.className = 'recognition-frame';
    container.appendChild(frame);
    
    // Animate recognition
    function animateRecognition() {
        // Reset all cells
        const cells = grid.querySelectorAll('.recognition-cell');
        cells.forEach(cell => cell.classList.remove('highlight'));
        
        // Highlight random cells
        const cellCount = Math.floor(Math.random() * 15) + 5;
        for (let i = 0; i < cellCount; i++) {
            const randomIndex = Math.floor(Math.random() * cells.length);
            cells[randomIndex].classList.add('highlight');
        }
        
        // Show recognition frame
        setTimeout(() => {
            const x = Math.random() * 60 + 20;
            const y = Math.random() * 60 + 20;
            const width = Math.random() * 80 + 40;
            const height = Math.random() * 80 + 40;
            
            frame.style.left = x + 'px';
            frame.style.top = y + 'px';
            frame.style.width = width + 'px';
            frame.style.height = height + 'px';
            frame.style.opacity = 1;
            
            setTimeout(() => {
                frame.style.opacity = 0;
            }, 500);
        }, 800);
        
        setTimeout(animateRecognition, 2000);
    }
    
    animateRecognition();
    
    return container;
}`,
    5: `// Initialize Real vs AI battle animation
function initBattleAnimation() {
    const container = document.createElement('div');
    container.className = 'battle-container';
    
    // Create Real side
    const realSide = document.createElement('div');
    realSide.className = 'battle-side battle-real';
    realSide.innerHTML = '<div class="battle-text">REAL</div>';
    container.appendChild(realSide);
    
    // Create AI side
    const aiSide = document.createElement('div');
    aiSide.className = 'battle-side battle-ai';
    aiSide.innerHTML = '<div class="battle-text">AI</div>';
    container.appendChild(aiSide);
    
    // Create particles container
    const particles = document.createElement('div');
    particles.className = 'battle-particles';
    container.appendChild(particles);
    
    // Create and animate particles
    function createParticles() {
        // Clear existing particles
        particles.innerHTML = '';
        
        // Create new particles
        const particleCount = Math.floor(Math.random() * 5) + 5;
        
        for (let i = 0; i < particleCount; i++) {
            createBattleParticle(particles);
        }
        
        setTimeout(createParticles, 1000);
    }
    
    createParticles();
    
    return container;
}

// Create a single battle particle with random animation
function createBattleParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'battle-particle';
    
    // Random color (green for real, blue for AI)
    const isRealToAi = Math.random() > 0.5;
    const color = isRealToAi ? '#28a745' : '#007bff';
    particle.style.backgroundColor = color;
    
    // Random starting position
    const startX = isRealToAi ? '15%' : '85%';
    const startY = Math.random() * 80 + 10 + '%';
    particle.style.left = startX;
    particle.style.top = startY;
    
    container.appendChild(particle);
    
    // Animate to opposite side
    setTimeout(() => {
        const endX = isRealToAi ? '85%' : '15%';
        particle.style.transform = \`translateX(\${isRealToAi ? 500 : -500}px)\`;
        particle.style.opacity = 0;
    }, 50);
    
    // Remove particle after animation
    setTimeout(() => {
        particle.remove();
    }, 1000);
}`,
    6: `// Initialize progress bar animation
function initProgressBar() {
    const container = document.createElement('div');
    container.className = 'progress-bar-container';
    
    // Create progress bar
    const progressBar = document.createElement('div');
    progressBar.className = 'custom-progress';
    container.appendChild(progressBar);
    
    // Create progress fill
    const progressFill = document.createElement('div');
    progressFill.className = 'progress-fill';
    progressBar.appendChild(progressFill);
    
    // Create glow effect
    const progressGlow = document.createElement('div');
    progressGlow.className = 'progress-glow';
    progressFill.appendChild(progressGlow);
    
    // Create text
    const progressText = document.createElement('div');
    progressText.className = 'progress-text';
    progressText.textContent = '0%';
    progressBar.appendChild(progressText);
    
    // Animate progress
    let progress = 0;
    let direction = 1;
    
    function animateProgress() {
        progress += direction * 1;
        
        if (progress >= 100) {
            direction = -1;
        } else if (progress <= 0) {
            direction = 1;
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
        
        setTimeout(animateProgress, 50);
    }
    
    animateProgress();
    
    return container;
}`,
    7: `// Initialize comparison slider animation
function initComparisonSlider() {
    const container = document.createElement('div');
    container.className = 'comparison-slider-container';
    
    // Create real side
    const realSide = document.createElement('div');
    realSide.className = 'comparison-side comparison-real';
    realSide.textContent = 'REAL';
    container.appendChild(realSide);
    
    // Create AI side
    const aiSide = document.createElement('div');
    aiSide.className = 'comparison-side comparison-ai';
    aiSide.textContent = 'AI';
    container.appendChild(aiSide);
    
    // Create handle
    const handle = document.createElement('div');
    handle.className = 'comparison-handle';
    container.appendChild(handle);
    
    // Create handle line
    const handleLine = document.createElement('div');
    handleLine.className = 'comparison-handle-line';
    handle.appendChild(handleLine);
    
    // Create handle grip
    const handleGrip = document.createElement('div');
    handleGrip.className = 'comparison-handle-grip';
    handleGrip.innerHTML = '<i class="fas fa-grip-lines-vertical"></i>';
    handle.appendChild(handleGrip);
    
    // Make it interactive
    let isDragging = false;
    let startX, startLeft;
    
    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', startDrag);
    
    function startDrag(e) {
        isDragging = true;
        startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
        startLeft = parseInt(window.getComputedStyle(handle).left, 10);
        
        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag);
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);
    }
    
    function drag(e) {
        if (!isDragging) return;
        
        const x = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
        const deltaX = x - startX;
        
        const containerWidth = container.offsetWidth;
        let newLeft = startLeft + deltaX;
        
        // Constrain to container bounds
        if (newLeft < 0) newLeft = 0;
        if (newLeft > containerWidth) newLeft = containerWidth;
        
        const leftPercent = (newLeft / containerWidth) * 100;
        
        handle.style.left = leftPercent + '%';
        realSide.style.width = leftPercent + '%';
    }
    
    function stopDrag() {
        isDragging = false;
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('mouseup', stopDrag);
        document.removeEventListener('touchend', stopDrag);
    }
    
    // Animate handle at start
    function animateSlider() {
        let position = 50;
        let direction = -1;
        let animating = true;
        
        function step() {
            if (!animating) return;
            
            position += direction * 0.5;
            
            if (position <= 30) {
                direction = 1;
            } else if (position >= 70) {
                direction = -1;
            }
            
            handle.style.left = position + '%';
            realSide.style.width = position + '%';
            
            if (animating) {
                requestAnimationFrame(step);
            }
        }
        
        step();
        
        // Stop animation on interaction
        container.addEventListener('mousedown', () => {
            animating = false;
        });
    }
    
    animateSlider();
    
    return container;
}`,
    8: `// Initialize game logo animation
function initGameLogoAnimation() {
    const container = document.createElement('div');
    container.className = 'game-logo-container';
    
    // Create logo
    const logo = document.createElement('div');
    logo.className = 'game-logo';
    logo.innerHTML = '<div class="logo-real">REAL</div><div class="logo-vs">VS</div><div class="logo-ai">AI</div>';
    container.appendChild(logo);
    
    // Create particles
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'logo-particle';
        
        // Random color
        const colors = ['#28a745', '#5D4FFF', '#63D8FF', '#007bff'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        particle.style.backgroundColor = randomColor;
        
        container.appendChild(particle);
    }
    
    // Animate particles
    function animateParticles() {
        const particles = container.querySelectorAll('.logo-particle');
        
        particles.forEach(particle => {
            // Random position
            const x = Math.random() * 300 - 150;
            const y = Math.random() * 150 - 75;
            
            // Random size
            const size = Math.random() * 6 + 2;
            
            // Setup animation
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.opacity = 0;
            particle.style.transform = 'translate(-50%, -50%)';
            
            // Get random starting position from logo
            const startX = (Math.random() * 180 - 90) + 'px';
            const startY = (Math.random() * 80 - 40) + 'px';
            
            particle.style.left = 'calc(50% + ' + startX + ')';
            particle.style.top = 'calc(50% + ' + startY + ')';
            
            // Animate
            setTimeout(() => {
                particle.style.opacity = 0.7;
                particle.style.transform = \`translate(-50%, -50%) translate(\${x}px, \${y}px)\`;
                
                setTimeout(() => {
                    particle.style.opacity = 0;
                }, 800);
            }, Math.random() * 1000);
        });
        
        setTimeout(animateParticles, 1500);
    }
    
    // Pulse animation for logo
    function pulseLogo() {
        const real = logo.querySelector('.logo-real');
        const vs = logo.querySelector('.logo-vs');
        const ai = logo.querySelector('.logo-ai');
        
        real.style.animation = 'pulse 1.5s';
        
        setTimeout(() => {
            vs.style.animation = 'pulse 1.5s';
        }, 500);
        
        setTimeout(() => {
            ai.style.animation = 'pulse 1.5s';
        }, 1000);
        
        setTimeout(() => {
            real.style.animation = '';
            vs.style.animation = '';
            ai.style.animation = '';
        }, 2500);
        
        setTimeout(pulseLogo, 3000);
    }
    
    animateParticles();
    pulseLogo();
    
    return container;
}`,
    9: `// Show a sample loading screen with progress
function simulateLoading(containerId) {
    const container = document.createElement('div');
    container.className = 'loading-screen-container';
    
    // Create header
    const header = document.createElement('div');
    header.className = 'loading-screen-header';
    container.appendChild(header);
    
    const title = document.createElement('div');
    title.className = 'loading-screen-title';
    title.textContent = 'Loading Game';
    header.appendChild(title);
    
    const subtitle = document.createElement('div');
    subtitle.className = 'loading-screen-subtitle';
    subtitle.textContent = 'Preparing Real vs AI Challenge';
    header.appendChild(subtitle);
    
    // Create progress bar
    const progress = document.createElement('div');
    progress.className = 'loading-screen-progress';
    container.appendChild(progress);
    
    const progressFill = document.createElement('div');
    progressFill.className = 'loading-screen-progress-fill';
    progress.appendChild(progressFill);
    
    // Create message
    const message = document.createElement('div');
    message.className = 'loading-screen-message';
    container.appendChild(message);
    
    // Create spinner
    const spinner = document.createElement('div');
    spinner.className = 'loading-screen-spinner';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    container.appendChild(spinner);
    
    // Messages to show during loading
    const messages = [
        "Loading high-resolution images...",
        "Calibrating AI detection algorithms...",
        "Preparing challenge scenarios...",
        "Analyzing difficulty levels...",
        "Setting up game environment...",
        "Optimizing neural networks...",
        "Arranging image dataset...",
        "Configuring multiplayer support...",
        "Initializing scoring system...",
        "Getting everything ready for you..."
    ];
    
    // Simulate loading progress
    let progressValue = 0;
    let currentMessage = 0;
    
    function updateLoading() {
        if (progressValue < 100) {
            // Increment with random amount
            progressValue += Math.random() * 3 + 1;
            if (progressValue > 100) progressValue = 100;
            
            // Update progress bar
            progressFill.style.width = progressValue + '%';
            
            // Update messages at certain points
            if (progressValue > currentMessage * 10 && currentMessage < messages.length) {
                message.textContent = messages[currentMessage];
                currentMessage++;
            }
            
            if (progressValue < 100) {
                setTimeout(updateLoading, 200);
            } else {
                // Complete
                message.textContent = "Ready to play!";
                spinner.innerHTML = '<i class="fas fa-check-circle"></i>';
                
                // Restart animation after a delay
                setTimeout(() => {
                    progressValue = 0;
                    currentMessage = 0;
                    progressFill.style.width = '0%';
                    setTimeout(updateLoading, 1000);
                }, 3000);
            }
        }
    }
    
    updateLoading();
    
    return container;
}`,
    10: `// Initialize fullscreen simulation
function initFullscreenSimulation() {
    const container = document.createElement('div');
    container.className = 'fullscreen-simulation';
    
    // Create content
    const content = document.createElement('div');
    content.className = 'simulation-content';
    content.innerHTML = 'Fullscreen Simulation Mode<br>Press ESC to exit fullscreen mode';
    container.appendChild(content);
    
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'simulation-overlay';
    container.appendChild(overlay);
    
    // Create spinner
    const spinner = document.createElement('div');
    spinner.className = 'simulation-spinner';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    overlay.appendChild(spinner);
    
    // Create loading text
    const text = document.createElement('div');
    text.className = 'simulation-text';
    text.innerHTML = 'Loading next challenge<br>Preparing high-resolution images';
    overlay.appendChild(text);
    
    // Simulate overlay disappearing
    setTimeout(() => {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 500);
    }, 3000);
    
    return container;
}
`
};

/**
 * Load a specific demo
 * @param {number} demoId - The ID of the demo to load
 */
function loadDemo(demoId) {
    // Clear previous content
    $('#demoContainer').empty();
    $('#animationControls').empty();
    
    // Update info
    $('#animationInfo').html(animationDescriptions[demoId]);
    $('#demoCode').html(codeSamples[demoId]);
    
    // Initialize the selected animation
    switch(demoId) {
        case 1:
            $('#demoContainer').append(initPixelSortAnimation());
            break;
        case 2:
            $('#demoContainer').append(initCameraShutterAnimation());
            break;
        case 3:
            $('#demoContainer').append(initAiAnalysisWave());
            break;
        case 4:
            $('#demoContainer').append(initImageRecognition());
            break;
        case 5:
            $('#demoContainer').append(initBattleAnimation());
            break;
        case 6:
            $('#demoContainer').append(initProgressBar());
            addProgressControls();
            break;
        case 7:
            $('#demoContainer').append(initComparisonSlider());
            break;
        case 8:
            $('#demoContainer').append(initGameLogoAnimation());
            break;
        case 9:
            $('#demoContainer').append(simulateLoading());
            break;
        case 10:
            $('#demoContainer').append(initFullscreenSimulation());
            break;
    }
}

/**
 * Initialize fullscreen animation
 * @param {number} demoId - The ID of the demo to initialize in fullscreen
 */
function initFullscreenAnimation(demoId) {
    // Initialize the selected animation for fullscreen
    switch(demoId) {
        case 1:
            $('#fullscreenAnimation').append(initPixelSortAnimation());
            break;
        case 2:
            $('#fullscreenAnimation').append(initCameraShutterAnimation());
            break;
        case 3:
            $('#fullscreenAnimation').append(initAiAnalysisWave());
            break;
        case 4:
            $('#fullscreenAnimation').append(initImageRecognition());
            break;
        case 5:
            $('#fullscreenAnimation').append(initBattleAnimation());
            break;
        case 6:
            $('#fullscreenAnimation').append(initProgressBar());
            break;
        case 7:
            $('#fullscreenAnimation').append(initComparisonSlider());
            break;
        case 8:
            $('#fullscreenAnimation').append(initGameLogoAnimation());
            break;
        case 9:
            $('#fullscreenAnimation').append(simulateLoading());
            break;
        case 10:
            $('#fullscreenAnimation').append(initFullscreenSimulation());
            break;
    }
}

/**
 * Add controls for the progress bar demo
 */
function addProgressControls() {
    const controls = $('#animationControls');
    
    controls.append(`
        <button class="btn btn-sm btn-outline-light me-2" id="setProgress25">
            25%
        </button>
        <button class="btn btn-sm btn-outline-light me-2" id="setProgress50">
            50%
        </button>
        <button class="btn btn-sm btn-outline-light me-2" id="setProgress75">
            75%
        </button>
        <button class="btn btn-sm btn-outline-light" id="setProgress100">
            100%
        </button>
    `);
    
    $('#setProgress25').click(function() {
        $('.progress-fill').css('width', '25%');
        $('.progress-text').text('25%');
    });
    
    $('#setProgress50').click(function() {
        $('.progress-fill').css('width', '50%');
        $('.progress-text').text('50%');
    });
    
    $('#setProgress75').click(function() {
        $('.progress-fill').css('width', '75%');
        $('.progress-text').text('75%');
    });
    
    $('#setProgress100').click(function() {
        $('.progress-fill').css('width', '100%');
        $('.progress-text').text('100%');
    });
}

/**
 * Initialize pixel sorting animation by creating columns
 */
function initPixelSortAnimation() {
    const container = document.createElement('div');
    container.className = 'pixel-sorting-container';
    
    // Create columns
    const columnCount = Math.floor(container.clientWidth / 6) || 50; // Fallback if clientWidth is 0
    
    for (let i = 0; i < columnCount; i++) {
        const column = document.createElement('div');
        column.className = 'pixel-column';
        column.style.left = (i * 6) + 'px';
        container.appendChild(column);
    }
    
    // Animate columns with random heights
    setTimeout(() => animateColumns(container), 100);
    
    return container;
}

/**
 * Animate columns with random heights
 */
function animateColumns(container) {
    const columns = container.querySelectorAll('.pixel-column');
    
    columns.forEach(column => {
        const randomHeight = Math.random() * 200 + 20;
        column.style.height = randomHeight + 'px';
    });
    
    setTimeout(() => animateColumns(container), 800);
}

/**
 * Initialize camera shutter animation
 */
function initCameraShutterAnimation() {
    const container = document.createElement('div');
    container.className = 'camera-shutter-container';
    
    // Create shutter blades
    const bladePositions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
    
    bladePositions.forEach(position => {
        const blade = document.createElement('div');
        blade.className = 'shutter-blade ' + position;
        container.appendChild(blade);
    });
    
    // Add lens
    const lens = document.createElement('div');
    lens.className = 'shutter-lens';
    container.appendChild(lens);
    
    // Animate shutter
    let isClosed = false;
    
    function toggleShutter() {
        if (isClosed) {
            container.classList.remove('closed');
        } else {
            container.classList.add('closed');
        }
        isClosed = !isClosed;
        setTimeout(toggleShutter, 2000);
    }
    
    setTimeout(toggleShutter, 1000);
    
    return container;
}

/**
 * Initialize AI analysis wave animation
 */
function initAiAnalysisWave() {
    const container = document.createElement('div');
    container.className = 'ai-wave-container';
    
    const wave = document.createElement('div');
    wave.className = 'ai-wave';
    container.appendChild(wave);
    
    // Create bars
    const barCount = 30;
    
    for (let i = 0; i < barCount; i++) {
        const bar = document.createElement('div');
        bar.className = 'ai-wave-bar';
        wave.appendChild(bar);
    }
    
    // Animate bars
    function animateWave() {
        const bars = wave.querySelectorAll('.ai-wave-bar');
        
        bars.forEach(bar => {
            const randomHeight = Math.random() * 40 + 10;
            bar.style.height = randomHeight + 'px';
        });
        
        setTimeout(animateWave, 100);
    }
    
    animateWave();
    
    return container;
}

/**
 * Initialize image recognition animation
 */
function initImageRecognition() {
    const container = document.createElement('div');
    container.className = 'image-recognition-container';
    
    // Create grid
    const grid = document.createElement('div');
    grid.className = 'recognition-grid';
    container.appendChild(grid);
    
    // Create cells
    for (let i = 0; i < 100; i++) {
        const cell = document.createElement('div');
        cell.className = 'recognition-cell';
        grid.appendChild(cell);
    }
    
    // Create recognition frame
    const frame = document.createElement('div');
    frame.className = 'recognition-frame';
    container.appendChild(frame);
    
    // Animate recognition
    function animateRecognition() {
        // Reset all cells
        const cells = grid.querySelectorAll('.recognition-cell');
        cells.forEach(cell => cell.classList.remove('highlight'));
        
        // Highlight random cells
        const cellCount = Math.floor(Math.random() * 15) + 5;
        for (let i = 0; i < cellCount; i++) {
            const randomIndex = Math.floor(Math.random() * cells.length);
            cells[randomIndex].classList.add('highlight');
        }
        
        // Show recognition frame
        setTimeout(() => {
            const x = Math.random() * 60 + 20;
            const y = Math.random() * 60 + 20;
            const width = Math.random() * 80 + 40;
            const height = Math.random() * 80 + 40;
            
            frame.style.left = x + 'px';
            frame.style.top = y + 'px';
            frame.style.width = width + 'px';
            frame.style.height = height + 'px';
            frame.style.opacity = 1;
            
            setTimeout(() => {
                frame.style.opacity = 0;
            }, 500);
        }, 800);
        
        setTimeout(animateRecognition, 2000);
    }
    
    animateRecognition();
    
    return container;
}

/**
 * Initialize Real vs AI battle animation
 */
function initBattleAnimation() {
    const container = document.createElement('div');
    container.className = 'battle-container';
    
    // Create Real side
    const realSide = document.createElement('div');
    realSide.className = 'battle-side battle-real';
    realSide.innerHTML = '<div class="battle-text">REAL</div>';
    container.appendChild(realSide);
    
    // Create AI side
    const aiSide = document.createElement('div');
    aiSide.className = 'battle-side battle-ai';
    aiSide.innerHTML = '<div class="battle-text">AI</div>';
    container.appendChild(aiSide);
    
    // Create particles container
    const particles = document.createElement('div');
    particles.className = 'battle-particles';
    container.appendChild(particles);
    
    // Create and animate particles
    function createParticles() {
        // Clear existing particles
        particles.innerHTML = '';
        
        // Create new particles
        const particleCount = Math.floor(Math.random() * 5) + 5;
        
        for (let i = 0; i < particleCount; i++) {
            createBattleParticle(particles);
        }
        
        setTimeout(createParticles, 1000);
    }
    
    createParticles();
    
    return container;
}

/**
 * Create a single battle particle with random animation
 */
function createBattleParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'battle-particle';
    
    // Random color (green for real, blue for AI)
    const isRealToAi = Math.random() > 0.5;
    const color = isRealToAi ? '#28a745' : '#007bff';
    particle.style.backgroundColor = color;
    
    // Random starting position
    const startX = isRealToAi ? '15%' : '85%';
    const startY = Math.random() * 80 + 10 + '%';
    particle.style.left = startX;
    particle.style.top = startY;
    
    container.appendChild(particle);
    
    // Animate to opposite side
    setTimeout(() => {
        const endX = isRealToAi ? '85%' : '15%';
        particle.style.transform = `translateX(${isRealToAi ? 500 : -500}px)`;
        particle.style.opacity = 0;
    }, 50);
    
    // Remove particle after animation
    setTimeout(() => {
        particle.remove();
    }, 1000);
}

/**
 * Initialize progress bar animation
 */
function initProgressBar() {
    const container = document.createElement('div');
    container.className = 'progress-bar-container';
    
    // Create progress bar
    const progressBar = document.createElement('div');
    progressBar.className = 'custom-progress';
    container.appendChild(progressBar);
    
    // Create progress fill
    const progressFill = document.createElement('div');
    progressFill.className = 'progress-fill';
    progressBar.appendChild(progressFill);
    
    // Create glow effect
    const progressGlow = document.createElement('div');
    progressGlow.className = 'progress-glow';
    progressFill.appendChild(progressGlow);
    
    // Create text
    const progressText = document.createElement('div');
    progressText.className = 'progress-text';
    progressText.textContent = '0%';
    progressBar.appendChild(progressText);
    
    // Animate progress
    let progress = 0;
    let direction = 1;
    
    function animateProgress() {
        progress += direction * 1;
        
        if (progress >= 100) {
            direction = -1;
        } else if (progress <= 0) {
            direction = 1;
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
        
        setTimeout(animateProgress, 50);
    }
    
    animateProgress();
    
    return container;
}

/**
 * Initialize comparison slider animation
 */
function initComparisonSlider() {
    const container = document.createElement('div');
    container.className = 'comparison-slider-container';
    
    // Create real side
    const realSide = document.createElement('div');
    realSide.className = 'comparison-side comparison-real';
    realSide.textContent = 'REAL';
    container.appendChild(realSide);
    
    // Create AI side
    const aiSide = document.createElement('div');
    aiSide.className = 'comparison-side comparison-ai';
    aiSide.textContent = 'AI';
    container.appendChild(aiSide);
    
    // Create handle
    const handle = document.createElement('div');
    handle.className = 'comparison-handle';
    container.appendChild(handle);
    
    // Create handle line
    const handleLine = document.createElement('div');
    handleLine.className = 'comparison-handle-line';
    handle.appendChild(handleLine);
    
    // Create handle grip
    const handleGrip = document.createElement('div');
    handleGrip.className = 'comparison-handle-grip';
    handleGrip.innerHTML = '<i class="fas fa-grip-lines-vertical"></i>';
    handle.appendChild(handleGrip);
    
    // Auto-animate the handle
    let position = 50;
    let direction = -1;
    
    function animateHandle() {
        position += direction * 0.5;
        
        if (position <= 30) {
            direction = 1;
        } else if (position >= 70) {
            direction = -1;
        }
        
        handle.style.left = position + '%';
        realSide.style.width = position + '%';
        
        setTimeout(animateHandle, 50);
    }
    
    animateHandle();
    
    return container;
}

/**
 * Initialize game logo animation
 */
function initGameLogoAnimation() {
    const container = document.createElement('div');
    container.className = 'game-logo-container';
    
    // Create logo
    const logo = document.createElement('div');
    logo.className = 'game-logo';
    logo.innerHTML = '<div class="logo-real">REAL</div><div class="logo-vs">VS</div><div class="logo-ai">AI</div>';
    container.appendChild(logo);
    
    // Create particles
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'logo-particle';
        
        // Random color
        const colors = ['#28a745', '#5D4FFF', '#63D8FF', '#007bff'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        particle.style.backgroundColor = randomColor;
        
        container.appendChild(particle);
    }
    
    // Animate particles
    function animateParticles() {
        const particles = container.querySelectorAll('.logo-particle');
        
        particles.forEach(particle => {
            // Random position
            const x = Math.random() * 300 - 150;
            const y = Math.random() * 150 - 75;
            
            // Random size
            const size = Math.random() * 6 + 2;
            
            // Setup animation
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.opacity = 0;
            particle.style.transform = 'translate(-50%, -50%)';
            
            // Get random starting position from logo
            const startX = (Math.random() * 180 - 90) + 'px';
            const startY = (Math.random() * 80 - 40) + 'px';
            
            particle.style.left = 'calc(50% + ' + startX + ')';
            particle.style.top = 'calc(50% + ' + startY + ')';
            
            // Animate
            setTimeout(() => {
                particle.style.opacity = 0.7;
                particle.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px)`;
                
                setTimeout(() => {
                    particle.style.opacity = 0;
                }, 800);
            }, Math.random() * 1000);
        });
        
        setTimeout(animateParticles, 1500);
    }
    
    // Pulse animation for logo
    function pulseLogo() {
        const real = logo.querySelector('.logo-real');
        const vs = logo.querySelector('.logo-vs');
        const ai = logo.querySelector('.logo-ai');
        
        real.style.animation = 'pulse 1.5s';
        
        setTimeout(() => {
            vs.style.animation = 'pulse 1.5s';
        }, 500);
        
        setTimeout(() => {
            ai.style.animation = 'pulse 1.5s';
        }, 1000);
        
        setTimeout(() => {
            real.style.animation = '';
            vs.style.animation = '';
            ai.style.animation = '';
        }, 2500);
        
        setTimeout(pulseLogo, 3000);
    }
    
    animateParticles();
    pulseLogo();
    
    return container;
}

/**
 * Show a sample loading screen with progress
 */
function simulateLoading() {
    const container = document.createElement('div');
    container.className = 'loading-screen-container';
    
    // Create header
    const header = document.createElement('div');
    header.className = 'loading-screen-header';
    container.appendChild(header);
    
    const title = document.createElement('div');
    title.className = 'loading-screen-title';
    title.textContent = 'Loading Game';
    header.appendChild(title);
    
    const subtitle = document.createElement('div');
    subtitle.className = 'loading-screen-subtitle';
    subtitle.textContent = 'Preparing Real vs AI Challenge';
    header.appendChild(subtitle);
    
    // Create progress bar
    const progress = document.createElement('div');
    progress.className = 'loading-screen-progress';
    container.appendChild(progress);
    
    const progressFill = document.createElement('div');
    progressFill.className = 'loading-screen-progress-fill';
    progress.appendChild(progressFill);
    
    // Create message
    const message = document.createElement('div');
    message.className = 'loading-screen-message';
    container.appendChild(message);
    
    // Create spinner
    const spinner = document.createElement('div');
    spinner.className = 'loading-screen-spinner';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    container.appendChild(spinner);
    
    // Messages to show during loading
    const messages = [
        "Loading high-resolution images...",
        "Calibrating AI detection algorithms...",
        "Preparing challenge scenarios...",
        "Analyzing difficulty levels...",
        "Setting up game environment...",
        "Optimizing neural networks...",
        "Arranging image dataset...",
        "Configuring multiplayer support...",
        "Initializing scoring system...",
        "Getting everything ready for you..."
    ];
    
    // Simulate loading progress
    let progressValue = 0;
    let currentMessage = 0;
    
    function updateLoading() {
        if (progressValue < 100) {
            // Increment with random amount
            progressValue += Math.random() * 3 + 1;
            if (progressValue > 100) progressValue = 100;
            
            // Update progress bar
            progressFill.style.width = progressValue + '%';
            
            // Update messages at certain points
            if (progressValue > currentMessage * 10 && currentMessage < messages.length) {
                message.textContent = messages[currentMessage];
                currentMessage++;
            }
            
            if (progressValue < 100) {
                setTimeout(updateLoading, 200);
            } else {
                // Complete
                message.textContent = "Ready to play!";
                spinner.innerHTML = '<i class="fas fa-check-circle"></i>';
                
                // Restart animation after a delay
                setTimeout(() => {
                    progressValue = 0;
                    currentMessage = 0;
                    progressFill.style.width = '0%';
                    setTimeout(updateLoading, 1000);
                }, 3000);
            }
        }
    }
    
    updateLoading();
    
    return container;
}

/**
 * Initialize fullscreen simulation
 */
function initFullscreenSimulation() {
    const container = document.createElement('div');
    container.className = 'fullscreen-simulation';
    
    // Create content
    const content = document.createElement('div');
    content.className = 'simulation-content';
    content.innerHTML = 'Fullscreen Simulation Mode<br>Press ESC to exit fullscreen mode';
    container.appendChild(content);
    
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'simulation-overlay';
    container.appendChild(overlay);
    
    // Create spinner
    const spinner = document.createElement('div');
    spinner.className = 'simulation-spinner';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    overlay.appendChild(spinner);
    
    // Create loading text
    const text = document.createElement('div');
    text.className = 'simulation-text';
    text.innerHTML = 'Loading next challenge<br>Preparing high-resolution images';
    overlay.appendChild(text);
    
    // Simulate overlay disappearing
    setTimeout(() => {
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 500);
    }, 3000);
    
    return container;
}