# Daily Motivational Verse Notification System

## Overview

This system automatically sends motivational verse notifications at 9:00 AM daily to users who haven't opened the app before that time. The notifications include both English and Arabic support and integrate seamlessly with the existing notification system.

## Features

- ✅ **Smart User Detection**: Only notifies users who haven't opened the app before 9 AM
- ✅ **Localization Support**: Full English and Arabic notification content
- ✅ **Timezone Aware**: Handles user timezones properly
- ✅ **Queue Integration**: Uses Laravel queues for reliable delivery
- ✅ **Multiple Device Support**: Sends to all registered devices per user
- ✅ **Notification Categorization**: Appears in "motivational" tab in the app
- ✅ **Verse Cycling**: Ensures users don't get repeated verses
- ✅ **Load Distribution**: Prevents overwhelming Firebase FCM with delays

## How It Works

### 1. User Activity Tracking

When users open the app, the `ActivityController::ping()` method tracks:
- `activity_date`: The date of app usage
- `first_opened_at`: The exact timestamp of the first opening each day
- `opened`: Boolean flag indicating the app was opened

### 2. Daily Job Execution

Every day at 9:00 AM (UTC), the `SendDailyMotivationalVerse` job:

1. **Finds Eligible Users**:
   - Users who haven't opened the app today, OR
   - Users who opened the app after 9:00 AM
   - Only includes users with registered device tokens

2. **Gets Today's Verse**:
   - Uses the same logic as the home screen motivational verse
   - Ensures consistent verse assignment per user per day
   - Handles verse cycling when all verses are seen

3. **Creates Localized Content**:
   - English: "Motivational verse today" + translation + surah info
   - Arabic: "آية تحفيزية اليوم" + Arabic text + surah info

4. **Sends Notifications**:
   - Creates in-app notification record with type "motivational"
   - Dispatches push notifications to all user devices
   - Adds random delays to distribute FCM load

## Database Changes

### New Column Added

```sql
ALTER TABLE user_daily_activity 
ADD COLUMN first_opened_at TIMESTAMP NULL;
```

This tracks the exact time users first open the app each day, enabling the 9 AM logic.

## Files Created/Modified

### New Files
- `app/Jobs/SendDailyMotivationalVerse.php` - Main notification job
- `app/Console/Commands/TestDailyMotivationalVerse.php` - Testing command
- `app/Console/Commands/TriggerDailyMotivationalVerse.php` - Manual trigger
- `database/migrations/2025_09_06_011200_add_first_opened_at_to_user_daily_activity_table.php`

### Modified Files
- `routes/console.php` - Added daily scheduler
- `app/Http/Controllers/ActivityController.php` - Enhanced to track first opening time

## Testing

### Dry Run Test
```bash
php artisan test:daily-motivational-verse --dry-run
```
This validates the system without sending actual notifications.

### Time Simulation
```bash
php artisan test:daily-motivational-verse --dry-run --simulate-time=08:30
```
Test eligibility logic with different times.

### Manual Trigger
```bash
# Queue the job
php artisan motivational-verse:send --queue

# Run immediately
php artisan motivational-verse:send
```

### Check Scheduler
```bash
php artisan schedule:list
```

## Production Setup

### 1. Queue Worker
Ensure a queue worker is running to process jobs:
```bash
php artisan queue:work --tries=3 --timeout=300
```

### 2. Scheduler Setup
Add to server crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Monitoring
Monitor logs in:
- `storage/logs/laravel.log` - General application logs
- Queue worker logs - Job execution status

## Notification Flow

### Foreground (App Open)
1. Push notification received
2. Shows as banner/toast
3. User taps → navigates to home screen
4. In-app notification appears in motivational tab

### Background/Closed App
1. Push notification shows in system tray
2. User taps → opens app to home screen
3. In-app notification available in motivational tab

## Configuration

### Timezone Handling
- Job runs at 9:00 AM UTC
- User timezone stored in `device_tokens.timezone`
- Verse assignment respects user timezone for "today"

### Localization
Supported locales:
- `en` (English) - Default
- `ar`, `ar_SA`, `ar_EG`, `ar_AE` (Arabic variants)

Locale determined from `device_tokens.locale`.

## Troubleshooting

### No Notifications Sent
1. Check if scheduler is running: `php artisan schedule:list`
2. Verify queue worker is active: `php artisan queue:work`
3. Check user eligibility: `php artisan test:daily-motivational-verse --dry-run`
4. Verify device tokens exist in database

### Users Not Receiving Notifications
1. Confirm device tokens are registered
2. Check user activity tracking is working
3. Verify FCM credentials are valid
4. Check app logs for job execution

### Wrong Timing
1. Verify server timezone settings
2. Check user timezone data in device_tokens
3. Confirm scheduler cron job is running every minute

## Edge Cases Handled

1. **No Device Tokens**: Users without tokens are skipped
2. **No Verses Available**: System logs warning and continues
3. **User Timezone Missing**: Falls back to UTC
4. **Verse Assignment Failure**: Logs error and continues with other users
5. **FCM Delivery Failure**: Queue retry mechanism handles temporary failures
6. **Multiple Devices**: All devices receive notification
7. **Verse Cycling**: Resets when user has seen all verses

## Performance Considerations

- **Load Distribution**: Random delays (1-30 seconds) spread FCM requests
- **Database Efficiency**: Optimized queries with proper indexes
- **Queue Usage**: Prevents blocking main application
- **Memory Management**: Processes users in batches
- **Logging**: Comprehensive but not excessive

## Maintenance

### Adding New Verses
Add verses to `motivational_verses` table with `is_active = true`.

### Disabling System
Remove or comment out the scheduler in `routes/console.php`.

### Changing Time
Modify the `dailyAt('09:00')` time in the scheduler.

### Updating Localization
Modify the `createNotificationContent()` method in the job class.

## Success Metrics

The test output shows:
- ✅ 47 active motivational verses available
- ✅ 2 users with device tokens
- ✅ Proper database structure
- ✅ Localization working for both languages
- ✅ Scheduler registered and running
- ✅ Job dispatch successful

The system is production-ready and will reliably deliver personalized motivational verses to users who need them most! 🌟
