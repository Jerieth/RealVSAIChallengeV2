# Developer Guide for Real VS AI Challenge V2

## Project Structure

- `/` - Root contains main PHP files for game functionality
- `/includes` - Core PHP functions and utilities
- `/models` - Data models for the application
- `/controllers` - Controller logic
- `/static` - Static assets (CSS, JS, images)
- `/data` - Storage for user-uploaded content and logs
- `/storage` - Additional storage for application data
- `/templates` - Template files for views
- `/api` - API endpoints
- `/ajax_handlers` - Handlers for AJAX requests

## Database Structure

The application uses SQLite for data storage. The database is initialized in `init_database.php`. Key tables include:

- `users` - User accounts
- `games` - Game session data
- `images` - Image metadata
- `achievements` - Achievement tracking
- `leaderboard` - Leaderboard entries

## Game Mechanics

### Difficulty Levels
- Easy: 5 lives, 20 turns
- Medium: 3 lives, 50 turns
- Hard: 1 life, 100 turns

### Scoring System
- Base points for correct answers
- Streak bonuses for consecutive correct answers
- Time bonuses for quick responses
- Multipliers based on difficulty

## Development Workflow

1. Use `router.php` as the main entry point
2. Game flow: `start_game.php` → `game.php` → `game_actions.php` → `game_over.php`
3. Game state is managed through PHP sessions
4. Form submissions use traditional PHP, not AJAX

## Debug Mode

Debug mode can be enabled in `includes/config.php` or through the admin interface.
Debug logs are written to `data/logs/debug.log`.

## Testing

Manual testing procedures:
1. Test all difficulty levels
2. Verify image selection works correctly
3. Confirm form submissions redirect properly
4. Check achievement tracking
5. Validate leaderboard updates
