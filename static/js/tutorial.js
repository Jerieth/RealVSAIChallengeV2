
// This class handles the quick tutorial popup on regular game pages
// It's separate from the full tutorial page that uses pixel-mascot.js
class QuickTutorialBubble {
  constructor() {
    this.currentStep = 0;
    this.steps = [
      "In each round, you'll see two images. One is real, and one is AI-generated.",
      "Look carefully at details like lighting, shadows, and textures - these often give away AI-generated images!",
      "Click on the image you think is real, then hit the Submit button to check your answer.",
      "The faster you answer correctly, the more points you'll earn!",
      "Ready to start? Let's play!"
    ];
    
    // Only initialize if we're not on the full tutorial page
    if (!document.querySelector('.tutorial-section')) {
      this.init();
    }
  }

  init() {
    this.createBubble();
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') this.nextStep();
      if (e.key === 'ArrowLeft') this.previousStep();
    });
  }

  createBubble() {
    // Check if the bubble already exists (from pixel-mascot.js)
    let bubble = document.querySelector('.tutorial-bubble');
    
    // Only create it if it doesn't exist
    if (!bubble) {
      const bubbleContainer = document.createElement('div');
      bubbleContainer.innerHTML = `<div class="tutorial-bubble"></div>`;
      document.body.appendChild(bubbleContainer);
      this.bubble = document.querySelector('.tutorial-bubble');
    } else {
      this.bubble = bubble;
    }
    
    // Initialize with empty content
    this.showStep();
  }

  showStep() {
    if (this.bubble) {
      this.bubble.textContent = this.steps[this.currentStep] || ""; // Show current step text
    }
  }

  nextStep() {
    if (this.currentStep < this.steps.length - 1) {
      this.currentStep++;
      this.showStep();
    } else {
      this.complete();
    }
  }

  previousStep() {
    if (this.currentStep > 0) {
      this.currentStep--;
      this.showStep();
    }
  }

  complete() {
    // Don't remove the bubble, just clear it
    if (this.bubble) {
      this.bubble.textContent = "";
    }
    localStorage.setItem('tutorialCompleted', 'true');
    
    // Mark tutorial as completed in database
    fetch('api/complete-tutorial.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }
}

if (!localStorage.getItem('tutorialCompleted')) {
  document.addEventListener('DOMContentLoaded', () => {
    new QuickTutorialBubble();
  });
}
