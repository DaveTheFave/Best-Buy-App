# Testing Guide for Admin Page Improvements

This document provides manual testing instructions for the new features added to the Best Buy Tamagotchi App.

## Prerequisites

1. Ensure you have a running instance of the application
2. Run the database migration: `mysql -u root -p bestbuy_tamagotchi < migration_high_value_override.sql`
3. Have at least one admin user in the database
4. Have at least one regular employee user

## Test 1: Cookie Persistence for Employee Login

**Objective**: Verify that employees remain logged in across browser restarts.

### Steps:
1. Open `index.html` in your browser
2. Login as a regular employee (not admin)
3. Note that you're logged in successfully
4. Close the browser completely (not just the tab)
5. Reopen the browser and navigate to `index.html`

### Expected Results:
- ✅ User should automatically be logged in without entering credentials
- ✅ All previous session data should be restored
- ✅ Cookie should persist for 7 days (check browser dev tools > Application > Cookies)
- ✅ Cookie should have `SameSite=Lax` attribute

### Verification:
Check browser console (F12) for session restore messages. Check cookies in dev tools to verify the `bestbuy_employee_session` cookie exists.

---

## Test 2: Admin Page Session Reset Button

**Objective**: Verify the Reset Session button properly clears all data and logs out.

### Steps:
1. Open `admin.html` in your browser
2. Login as an admin user
3. Enable notifications if prompted
4. Add an app/membership to the list
5. Click the "🔄 Reset Session" button
6. Confirm the dialog

### Expected Results:
- ✅ User is logged out and redirected to login screen
- ✅ All cookies are cleared (check dev tools)
- ✅ Notification timers are stopped
- ✅ Apps & Memberships list is retained (stored in localStorage)
- ✅ Cannot access admin dashboard without logging in again

### Verification:
- Check browser cookies - `bestbuy_employee_session` should be deleted
- Check localStorage - apps data may still exist (this is expected for data persistence)
- Try navigating back - should stay on login screen

---

## Test 3: Revenue High-Value Alert with Override

**Objective**: Verify that revenue entries over $8,000 trigger a confirmation modal.

### Steps:
1. Open `index.html` and login as an employee
2. Set your work hours if needed
3. In the "Feed Your Animal" form, enter a revenue amount of **9000**
4. Click "Feed Animal"
5. Observe the warning modal appears
6. Click "Cancel" button
7. Repeat steps 3-4, then click "Override and Submit"

### Expected Results:
- ✅ Modal appears showing the amount ($9,000.00)
- ✅ Modal has warning message about unusually high amount
- ✅ "Cancel" button closes modal without submitting
- ✅ Revenue input field remains populated after cancel
- ✅ "Override and Submit" processes the sale successfully
- ✅ Success message includes "(High-value override applied)"
- ✅ Database sales table has `overridden_high_value = 1` for this entry

### Verification:
Check the database:
```sql
SELECT * FROM sales WHERE revenue > 8000 ORDER BY created_at DESC LIMIT 1;
```
The `overridden_high_value` column should be `1`.

---

## Test 4: Hourly Pet-Feeding Notifications

**Objective**: Verify that notifications fire at the top of each hour.

### Steps:
1. Open `admin.html` and login as an admin
2. Click "Enable Notifications" button in the banner
3. Grant permission when browser prompts
4. Wait for the next hour (or adjust system time to test immediately)
5. Alternatively, open browser console and run: `notificationManager.showNotification()`

### Expected Results:
- ✅ Permission request appears when clicking "Enable Notifications"
- ✅ Banner disappears after granting permission
- ✅ Notification fires at the top of the hour (e.g., 10:00, 11:00)
- ✅ Notification shows: "🐾 Time to feed your pet!"
- ✅ If permission denied, in-app banner appears instead
- ✅ Notifications stop when logging out or clicking "Reset Session"

### Fallback Test (if notifications blocked):
1. Block notifications in browser settings
2. Login to admin page
3. Run `notificationManager.showNotification()` in console
4. In-app banner should appear at the top of the page

### Verification:
- Check browser notification area
- Console should log notification scheduling info
- Verify notification auto-closes after 10 seconds

---

## Test 5: Apps & Memberships Management

**Objective**: Verify adding, viewing, and removing apps/memberships.

### Steps:
1. Open `admin.html` and login as an admin
2. Scroll to "Apps & Memberships" section
3. In the form, enter:
   - Name: "Spotify Premium"
   - Type: "Membership"
   - Notes: "Personal music subscription"
4. Click "➕ Add" button
5. Verify the item appears in the list above
6. Add another item: "Microsoft 365" (type: "Service")
7. Refresh the page (or close and reopen browser)
8. Login again as admin
9. Verify both items are still in the list
10. Click "Remove" button for one of the items
11. Confirm the removal

### Expected Results:
- ✅ Items appear in the list immediately after adding
- ✅ Form clears after successful add
- ✅ Items persist across page refreshes
- ✅ Items persist across browser restarts
- ✅ Remove button deletes the item from the list
- ✅ Removal requires confirmation dialog
- ✅ Empty state message shows when no items exist

### Verification:
Check localStorage and cookies in browser dev tools:
- localStorage key: `admin_apps_memberships`
- Cookie name: `admin_apps_memberships`
Both should contain JSON array of items.

---

## Test 6: Session Auto-Restore

**Objective**: Verify sessions restore automatically on page load.

### Steps:
1. Login to `index.html` as an employee
2. Note the page you're on (should be game screen)
3. Close the tab
4. Open a new tab and navigate to `index.html`

### Expected Results:
- ✅ User is automatically logged in
- ✅ Game screen loads without going through login screen
- ✅ All stats are displayed correctly
- ✅ Session data is restored (work hours, goals, etc.)

### Admin Version:
1. Login to `admin.html` as an admin
2. Close and reopen
3. Should automatically show admin dashboard

---

## Test 7: Security Verification

**Objective**: Ensure no XSS vulnerabilities.

### Steps:
1. Open `admin.html` and login as admin
2. Try adding an app with malicious name: `<script>alert('XSS')</script>`
3. Add the item
4. Try adding notes with HTML: `<img src=x onerror=alert('XSS')>`

### Expected Results:
- ✅ Script tags are escaped and displayed as text
- ✅ No alert popups appear
- ✅ HTML is rendered as plain text, not executed
- ✅ Items display correctly with escaped content

### Verification:
Inspect the DOM - HTML should be escaped entities like `&lt;script&gt;`

---

## Test 8: Logout and Session Clear

**Objective**: Verify logout properly clears session data.

### Steps:
1. Login to `index.html` as an employee
2. Click the "Logout" button in the game screen
3. Check if redirected to login screen
4. Try using browser back button

### Expected Results:
- ✅ User is logged out and returned to login screen
- ✅ Session cookie is deleted
- ✅ Cannot access game screen using back button
- ✅ Must login again to access the app

---

## Test 9: Cookie Fallback to localStorage

**Objective**: Verify localStorage fallback when cookies are disabled.

### Steps:
1. Disable cookies in browser settings
2. Open `index.html` and login
3. Close and reopen browser
4. Navigate to `index.html`

### Expected Results:
- ✅ Login works without cookies
- ✅ Session data saved to localStorage
- ✅ Session restored from localStorage on reload
- ✅ App functions normally

### Verification:
Check dev tools > Application > Local Storage for `bestbuy_employee_session`

---

## Test 10: Multi-Browser Compatibility

**Objective**: Verify features work across different browsers.

### Browsers to Test:
- Chrome/Chromium
- Firefox
- Safari (if available)
- Edge

### Test Each Browser:
1. Login with cookie persistence
2. Test revenue override modal
3. Test notification permissions (may vary by browser)
4. Test apps & memberships

### Expected Results:
- ✅ All features work consistently
- ✅ Cookies persist in all browsers
- ✅ Notifications work or fallback banner appears
- ✅ UI renders correctly

---

## Troubleshooting

### Issue: Cookies not persisting
- Check browser settings - cookies must be enabled
- Verify domain is not localhost (some browsers treat it specially)
- Check if Secure flag is causing issues (requires HTTPS)

### Issue: Notifications not showing
- Check browser notification permissions
- Try the fallback banner test
- Some browsers require user gesture before notifications

### Issue: Database errors on revenue submission
- Run the migration: `migration_high_value_override.sql`
- Verify sales table has `overridden_high_value` column
- Check PHP error logs

### Issue: Session not restoring
- Clear all cookies and localStorage
- Verify utility scripts are loaded (check console for errors)
- Check if session is expired (> 7 days)

---

## Success Criteria

All tests should pass with ✅ results. If any test fails:
1. Check browser console for JavaScript errors
2. Check PHP error logs for backend issues
3. Verify database schema is up to date
4. Ensure all files are properly uploaded

## Reporting Issues

If you find any bugs during testing:
1. Note which test failed
2. Capture browser console logs
3. Record steps to reproduce
4. Include browser and version info
