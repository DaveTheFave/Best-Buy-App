# Admin Session Notifications - Testing Guide

## Overview
This guide provides manual testing instructions for the hourly pet-feeding notification system with session and time restrictions.

## Features to Test

### 1. Session-Based Notifications
- Notifications are scheduled hourly (at the top of each hour)
- Notifications only occur during an active admin session
- Notifications automatically stop when the shift ends

### 2. Allowed Hours Restriction
- Notifications are only shown between 09:00 and 21:00 (local time)
- Inclusive of 09:00, exclusive of 21:00
- Notifications scheduled outside these hours are skipped

### 3. Session Persistence
- Sessions are saved to localStorage and restored on page reload
- Shift start/end times persist across browser refreshes
- Expired sessions are automatically cleared

## Manual Testing Instructions

### Test 1: Basic Notification with Valid Shift
**Objective**: Verify notifications appear during an active shift within allowed hours

**Steps**:
1. Open `http://localhost:8000/admin.html`
2. Log in with an admin username (e.g., admin username from your database)
3. Set shift times:
   - Start Time: Current time (or leave blank for default)
   - End Time: At least 2 hours from now, before 21:00
4. Click "Login"
5. Wait for the next top-of-hour (e.g., if it's 10:45, wait until 11:00)

**Expected Result**:
- A notification banner should appear at the top of the hour
- Banner shows: "🐾 Pet Feeding Reminder" with message
- Notification should auto-dismiss after 5 seconds or on click
- Check browser console for log message confirming notification

### Test 2: Notifications Stop at Shift End
**Objective**: Verify notifications stop when the shift ends

**Steps**:
1. Log in to admin dashboard
2. Set shift times:
   - Start Time: Current time
   - End Time: 15 minutes from now
3. Wait for shift to end (15 minutes)
4. Observe console logs

**Expected Result**:
- Console should log "Session ended, stopping notifications"
- No more notifications appear after shift end time
- Session remains visible but notifications are inactive

### Test 3: Outside Allowed Hours (Before 09:00 or After 21:00)
**Objective**: Verify notifications don't appear outside 09:00-21:00

**Steps**:
1. If testing before 09:00 or after 21:00:
   - Log in with shift covering the next top-of-hour
   - Wait for the hour to arrive
2. If testing during allowed hours:
   - Use browser dev tools to temporarily modify the time check (advanced)
   - Or schedule test for actual off-hours

**Expected Result**:
- No notifications appear outside 09:00-21:00 window
- Console logs "Notification skipped: outside allowed hours"

### Test 4: Session Reset
**Objective**: Verify "Reset Session" button stops notifications

**Steps**:
1. Log in with a valid shift
2. Wait for at least one notification to appear
3. Click "Reset Session" button in the dashboard header
4. Confirm the reset dialog

**Expected Result**:
- Notification manager stops immediately
- Console logs "Stopping NotificationManager"
- User is returned to login screen
- Session data is cleared from localStorage

### Test 5: Session Persistence Across Page Reload
**Objective**: Verify session survives browser refresh

**Steps**:
1. Log in with shift ending at least 1 hour from now
2. Note the shift start/end times displayed
3. Refresh the browser (F5 or Ctrl+R)

**Expected Result**:
- Dashboard reloads automatically
- Admin name and shift times are restored
- Notification manager resumes scheduling
- Console shows "Session loaded from localStorage"

### Test 6: Expired Session Handling
**Objective**: Verify expired sessions are cleared

**Steps**:
1. Log in with a shift ending in 2 minutes
2. Wait for shift to expire
3. Refresh the browser

**Expected Result**:
- Login screen appears (not dashboard)
- Console logs "Session expired, clearing"
- localStorage is cleared

### Test 7: Logout Cleanup
**Objective**: Verify logout properly stops notifications

**Steps**:
1. Log in with active shift
2. Wait for a notification or check console for scheduled notifications
3. Click "Logout" button

**Expected Result**:
- Returns to login screen
- Console logs "Stopping NotificationManager"
- Session cleared from localStorage
- All notification timers cleared

## Browser Notification Permission

For full browser notifications (not just banner):
1. When prompted, click "Allow" for notifications
2. Notifications will appear as system notifications
3. If permission denied, fallback banner will be used instead

## Debugging Tips

### Check Console Logs
Open browser Developer Tools (F12) → Console tab to see:
- "NotificationManager started" with session details
- "Next notification scheduled in X minutes"
- "Notification triggered at [timestamp]"
- "Session ended, stopping notifications"
- Any error messages

### Check localStorage
Developer Tools → Application → Local Storage → http://localhost:8000
- Look for `adminSession` key
- Contains: adminUser, shiftStart, shiftEnd, loginTime, savedAt

### Check NotificationManager Status
In browser console, type:
```javascript
notificationManager.getStatus()
```
Returns: isRunning, sessionStart, sessionEnd, allowedHours, activeTimers

## Expected Behavior Summary

✅ **DO show notifications when:**
- Current time is between 09:00 and 21:00 (inclusive of 09:00, exclusive of 21:00)
- Current time is within the session shift window
- At the top of an hour (XX:00)

❌ **DO NOT show notifications when:**
- Current time is before 09:00 or at/after 21:00
- Session has ended
- Outside the shift window
- Session is reset or logged out

## Known Limitations

1. **Client-side only**: Sessions are stored in localStorage and can be manipulated
2. **Not production-ready**: For production, implement server-side session management
3. **Local time zone**: All times are in the user's local time zone
4. **Browser tab must be open**: Notifications only work when the tab is active (no service worker)

## Security Notes

⚠️ **Important**: This implementation uses client-side session storage for demonstration purposes only.

- Sessions are stored in localStorage (not HTTP-only cookies)
- Vulnerable to XSS attacks
- Can be manipulated via browser dev tools
- **For production**: Use server-side sessions with HTTP-only secure cookies

## Troubleshooting

### Notifications not appearing?
- Check console for error messages
- Verify shift end time is in the future
- Verify current time is between 09:00-21:00
- Check notification permission status

### Session not persisting?
- Check if localStorage is enabled in browser
- Check if private/incognito mode is clearing storage
- Verify session hasn't expired

### Browser notifications not working?
- Check notification permission in browser settings
- Fallback banner should still appear
- Some browsers block notifications on localhost
