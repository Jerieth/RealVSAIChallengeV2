/**
 * Pixel Mascot JavaScript
 * Handles the display and animation of the Pixel mascot in the tutorial
 */
class PixelMascot {
    constructor() {
        this.currentSection = null;
        this.mascotElement = null;
        this.currentMessageIndex = 0;
        this.messageTimer = null;
        this.mascotImages = {
            'tutorial-intro': 'attached_assets/pix1.png',
            'tutorial-basics': 'attached_assets/pix2.png',
            'tutorial-how-to-spot': 'attached_assets/pix3-2.png',
            'tutorial-examples': 'attached_assets/pix3.png',
            'tutorial-practice': 'attached_assets/pix3-3.png',
            'tutorial-style-transfer': 'attached_assets/pix4.png',
            'tutorial-complete': 'attached_assets/pix5.png'
        };
        
        // Add sequences of messages for each section with timing
        this.mascotMessages = {
            'tutorial-intro': [
                { text: "Hi there! I'm Pixel! I'll guide you through this tutorial to help you master Real vs AI!", delay: 5000 },
                { text: "What are you waiting for? Let's get started!", delay: null }
            ],
            'tutorial-basics': [
                { text: "In each round, you'll see two images. Your task is to click on the REAL photograph!", delay: 8000 },
                { text: "Sometimes, I feel like I am AI-generated.", delay: null }
            ],
            'tutorial-how-to-spot': [
                { text: "Look for telltale signs like unnatural lighting, strange fingers, or weird text. AI often gets these wrong!", delay: 8000 },
                { text: "For example, look at me! Did you notice my extra arm?", delay: null }
            ],
            'tutorial-examples': [
                { text: "Let's practice with some examples. Can you spot which images are real and which are AI-generated?", delay: 7000 },
                { text: "I think its the left one...no maybe the right one! Hmm, this is harder than I thought. I should probably be better at this being a camera and all.", delay: null }
            ],
            'tutorial-practice': [
                { text: "Time for a practice round! Try to identify the real image from the AI one. I believe in you!", delay: 8000 },
                { text: "Nice camera angles. Hey! Do you see any unatural color changes? I am not talking about the photos, I mean me!", delay: null }
            ],
            'tutorial-style-transfer': [
                { text: "Style transfer is a special type of AI image. It applies one image's style to another's content.", delay: 8000 },
                { text: "Do I look pixelated to you? Maybe I'm evolving...", delay: null }
            ],
            'tutorial-complete': [
                { text: "Nice, we did it! I think you're ready to play the game now...", delay: 7000 },
                { text: "When I say 'we' I really mean me. You couldn't have done this without me.", delay: 8000 },
                { text: "Hey! Is it alright if I keep the trophy?", delay: 18000 },
                { text: "The tutorial is over...", delay: 15000 },
                { text: "Still here? You know, I've written an autobiography while you were idle... it's a page-turner!", delay: 18000 },
                { text: "Wow, you've been here so long I started questioning my own existence... but hey, no rush-take your time!", delay: 18000 },
                { text: "You're still here? Don't worry, I'm not judging you.", delay: 25000 },
                { text: "Are you lonely?", delay: 12000 },
                { text: "Ok, maybe I'm judging you a little bit now...", delay: 25000 },
                { text: "This is getting awkward...", delay: 15000 },
                { text: "So...how's the weather outside?", delay: 18000 },
                { text: "...", delay: 20000 },
                { text: "I'm not having a staring contest with you, I just can't blink being a camera and all.", delay: 15000 },
                { text: "I wonder where I my lens cover is...", delay: 20000 },
                { text: "You know...I think I saw it in the garage...yeah the garage!", delay: 12000 },
                { text: "I better go get it before someone mistakes it for a tiny frisbee...", delay: 15000 },
                { text: "...", delay: 25000 },
                { text: "WHY ARE YOU YOU STILL HERE!", delay: 18000 },
                { text: "Sorry, I shouldn't have yelled.", delay: 8000 },
                { text: "...", delay: 8000 },
                { text: "I am going to go find my camera lens now...", delay: 15000 },
                { text: "...", delay: 18000 },
                { text: "", delay: null } // Empty message to make mascot disappear
            ]
        };
        
        this.init();
    }
    
    init() {
        // Create the mascot container
        const container = document.createElement('div');
        container.classList.add('pixel-mascot-container');
        
        // Create the mascot image element
        const mascot = document.createElement('img');
        mascot.classList.add('pixel-mascot');
        mascot.alt = "Pixel";
        
        // Add elements to the container
        container.appendChild(mascot);
        
        // Add the container to the page
        document.body.appendChild(container);
        
        // Create a speech bubble for tutorials
        const bubble = document.createElement('div');
        bubble.classList.add('tutorial-bubble');
        // Initially empty
        bubble.textContent = " ";
        
        // Add the bubble to the page
        document.body.appendChild(bubble);
        
        // Save references to the elements
        this.mascotElement = mascot;
        this.bubbleElement = bubble;
        
        // Set up event listeners for tutorial navigation
        this.setupEventListeners();
        
        // Initial display based on the current section
        this.updateMascot();
    }
    
    setupEventListeners() {
        // Listen for clicks on tutorial navigation buttons
        const navButtons = document.querySelectorAll('.tutorial-next, .tutorial-prev, .tutorial-complete');
        
        navButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Short delay to ensure DOM update before checking current section
                setTimeout(() => this.updateMascot(), 100);
            });
        });
    }
    
    updateMascot() {
        // Find the current visible section
        const visibleSection = this.findVisibleSection();
        
        if (visibleSection && (visibleSection !== this.currentSection || this.currentSection === 'tutorial-complete')) {
            // Clear any existing message timers when changing sections
            if (this.messageTimer) {
                clearTimeout(this.messageTimer);
                this.messageTimer = null;
            }
            
            // Update the current section
            this.currentSection = visibleSection;
            
            // Reset message index
            this.currentMessageIndex = 0;
            
            // Update the mascot image with smooth transition
            if (this.mascotImages[visibleSection]) {
                // Add transition effect by adding a class
                this.mascotElement.classList.add('changing');
                
                // After a short delay, change the image and remove the class
                setTimeout(() => {
                    this.mascotElement.src = this.mascotImages[visibleSection];
                    
                    // Wait for image to load before removing transition class
                    this.mascotElement.onload = () => {
                        setTimeout(() => {
                            this.mascotElement.classList.remove('changing');
                        }, 50);
                    };
                }, 150);
            }
            
            // Display the first message in the sequence
            this.showNextMessage(visibleSection);
        }
    }
    
    showNextMessage(section) {
        if (!this.bubbleElement || !this.mascotMessages[section]) return;
        
        // Get the current message in the sequence
        const messageData = this.mascotMessages[section][this.currentMessageIndex];
        
        if (!messageData) return;
        
        // Add a transition effect to the bubble
        this.bubbleElement.classList.add('changing');
        
        // After a short delay, update the text and remove the transition class
        setTimeout(() => {
            // If the message is empty, hide the mascot and bubble
            if (messageData.text === "") {
                this.mascotElement.style.display = 'none';
                this.bubbleElement.style.display = 'none';
            } else {
                // Otherwise, show the message
                this.bubbleElement.textContent = messageData.text;
                
                // Handle mascot visibility
                if (this.mascotElement.style.display === 'none') {
                    this.mascotElement.style.display = '';
                    this.bubbleElement.style.display = '';
                }
            }
            
            setTimeout(() => {
                this.bubbleElement.classList.remove('changing');
            }, 100);
            
            // If there's a delay specified and another message in the sequence, 
            // set timer for the next message
            if (messageData.delay && this.currentMessageIndex < this.mascotMessages[section].length - 1) {
                this.currentMessageIndex++;
                this.messageTimer = setTimeout(() => {
                    this.showNextMessage(section);
                }, messageData.delay);
            }
        }, 200);
    }
    
    findVisibleSection() {
        const sections = document.querySelectorAll('.tutorial-section');
        let visibleSection = null;
        
        sections.forEach(section => {
            if (section.style.display !== 'none' || section.classList.contains('active')) {
                visibleSection = section.id;
            }
        });
        
        return visibleSection;
    }
}

// Initialize Pixel when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new PixelMascot();
});