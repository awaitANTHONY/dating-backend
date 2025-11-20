# User Mood Feature Implementation

## Overview
The "Share your mood with emoji" feature allows users to set an emoji with optional text that automatically expires after 24 hours. Users can edit/update their mood or manually remove it. **No mood history is stored** - only the current active mood per user.

## Database Structure

### Table: `user_moods`
- `id` - Primary key
- `user_id` - Foreign key to users table (UNIQUE - one mood per user)
- `emoji` - Emoji character(s) (max 10 chars)
- `mood_text` - Optional description (max 100 chars)
- `expires_at` - Timestamp when mood expires (24 hours from creation)
- `created_at` / `updated_at` - Standard Laravel timestamps

## Key Features
- ✅ **One mood per user** (no history stored)
- ✅ **Edit mood** (updates existing record)
- ✅ **Auto-expire after 24 hours**
- ✅ **Optional mood text with emoji**
- ✅ **Efficient database structure**
- ✅ **Real-time expiry checking**

## API Endpoints

### 1. Set/Update Mood
**POST** `/api/v1/mood`
```json
{
    "emoji": "😊",
    "mood_text": "Feeling great today!" // optional
}
```

**Response:**
```json
{
    "status": true,
    "message": "Mood updated successfully.",
    "data": {
        "mood": {
            "id": 1,
            "user_id": 123,
            "emoji": "😊",
            "mood_text": "Feeling great today!",
            "expires_at": "2025-11-21T10:30:00.000000Z",
            "created_at": "2025-11-20T10:30:00.000000Z",
            "updated_at": "2025-11-20T10:30:00.000000Z"
        },
        "expires_in": "in 23 hours"
    }
}
```

### 2. Get Current Mood
**GET** `/api/v1/mood`

**Response:**
```json
{
    "status": true,
    "data": {
        "mood": {
            "id": 1,
            "user_id": 123,
            "emoji": "😊",
            "mood_text": "Feeling great today!",
            "expires_at": "2025-11-21T10:30:00.000000Z",
            "created_at": "2025-11-20T10:30:00.000000Z",
            "updated_at": "2025-11-20T10:30:00.000000Z"
        },
        "expires_in": "in 23 hours"
    }
}
```

### 3. Remove Mood
**DELETE** `/api/v1/mood`

**Response:**
```json
{
    "status": true,
    "message": "Mood removed successfully."
}
```

## Auto-Cleanup System

### Console Command
- **Command:** `php artisan moods:cleanup`
- **Schedule:** Runs every hour automatically
- **Function:** Removes expired moods (older than 24 hours)

### Manual Cleanup
You can also run the cleanup manually:
```bash
php artisan moods:cleanup
```

## Integration Points

### 1. User Profile Response
The `GET /api/v1/user` endpoint now includes the user's current mood:
```json
{
    "status": true,
    "data": {
        "id": 123,
        "name": "John Doe",
        // ... other user fields
        "current_mood": {
            "emoji": "😊",
            "mood_text": "Feeling great!",
            "expires_at": "2025-11-21T10:30:00.000000Z"
        }
    }
}
```

### 2. Profile Recommendations
User moods are included in the recommendations API response under `current_mood` field for each profile.

## Usage Examples

### Frontend Implementation Ideas

1. **Mood Selector UI:**
   - Grid of emoji options
   - Optional text input field
   - "Set Mood" button

2. **Mood Display:**
   - Show emoji next to profile picture
   - Display mood text on hover/tap
   - Show time remaining until expiry

3. **Auto-refresh:**
   - Check mood status periodically
   - Remove mood display when expired
   - Show "Set mood" option when no active mood

### Database Migration
Run this command to create the moods table:
```bash
php artisan migrate
```

### Setup Scheduler
Ensure your Laravel scheduler is running (add to cron):
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Model Methods

### UserMood Model
- `UserMood::getCurrentMood($userId)` - Get user's active mood
- `UserMood::setMood($userId, $emoji, $text)` - Set/update user mood (updateOrCreate)
- `UserMood::removeMood($userId)` - Remove user's mood
- `UserMood::cleanupExpired()` - Clean up expired moods

### User Model Relationships
- `$user->currentMood()` - Current active mood (no history)

## Security & Validation

### Input Validation
- **emoji:** Required, max 10 characters
- **mood_text:** Optional, max 100 characters
- Bearer token authentication required

### Data Cleanup
- Automatic hourly cleanup of expired moods
- **No history storage** - minimal database footprint
- Unique constraint ensures one mood per user
- Efficient indexing for fast queries

### Performance Benefits
- **Minimal storage:** Only current mood per user
- **Fast queries:** Unique constraint eliminates duplicates
- **Auto-cleanup:** Prevents database bloat
- **Efficient updates:** Uses updateOrCreate pattern

This implementation provides a complete mood-sharing feature with automatic cleanup and seamless integration into your existing dating app infrastructure.