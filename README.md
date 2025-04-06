# Real VS AI Challenge V2

An innovative web-based image identification game that challenges players to distinguish between real and AI-generated photos through engaging, skill-based gameplay mechanics.

## Features

- Different difficulty levels: Easy, Medium, and Hard
- Score tracking and leaderboard
- Achievement system
- User profiles and authentication
- Responsive design for mobile and desktop

## Technology Stack

- PHP 8.2 backend with comprehensive game state management
- SQLite database for user data, game state, and image management
- Vanilla JavaScript for frontend interactivity
- HTML5 & CSS3 for responsive design

## How to Play

1. Choose a difficulty level:
   - Easy: 5 lives, 20 turns
   - Medium: 3 lives, 50 turns
   - Hard: 1 life, 100 turns
2. For each image, decide if it's a real photo or AI-generated
3. Click on the image to select it and submit your answer
4. Try to achieve the highest score!

## Key Files

- `router.php`: Main entry point for the application
- `game.php`: Game interface and setup
- `game_actions.php`: Backend logic for game actions
- `handle_get_next_turn.php`: Handles proceeding to next turn
- CSS/JS in their respective folders under `static/`
