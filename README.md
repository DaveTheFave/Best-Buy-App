# Best Buy Employee Tamagotchi App

A gamified performance tracking system for Best Buy employees. Keep your virtual pet alive by reaching your daily revenue goals!

## Features

- 🎮 **Tamagotchi-style gameplay** - Choose your animal companion (cat, dog, bird, etc.)
- 📊 **Performance tracking** - Set daily work hours and revenue goals
- 💰 **Revenue-based feeding** - Feed your animal by entering sales revenue
- 📱 **Responsive design** - Works great on both mobile and desktop
- 📈 **Health & Happiness stats** - Watch your animal's wellbeing based on your performance
- 🎯 **Goal tracking** - Monitor progress toward daily targets
- 🔄 **Daily health reset** - Health decay timer resets each day for a fresh start
- 👨‍💼 **Admin dashboard** - Monitor all employee pets' health (admin access only)
- 🍪 **Session persistence** - Stay logged in for 7 days with secure cookies
- ⚠️ **High-value alerts** - Confirmation required for revenue entries over $8,000
- 🔔 **Hourly reminders** - Browser notifications to feed your pet on time
- 📱 **Apps & Memberships** - Manage employee apps and memberships

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or PHP built-in server

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/DaveTheFave/Best-Buy-App.git
   cd Best-Buy-App
   ```

2. **Setup MySQL Database**
   ```bash
   mysql -u root -p < database.sql
   ```
   
   Or manually:
   - Create a database named `bestbuy_tamagotchi`
   - Import the `database.sql` file

3. **Configure Database Connection**
   
   Edit `config.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'bestbuy_tamagotchi');
   ```

4. **Add Employee Data**
   
   Insert employee usernames and names into the database:
   ```sql
   INSERT INTO users (username, name, animal_choice) VALUES 
   ('employee_username', 'Employee Full Name', 'cat');
   ```

5. **Start the Server**
   
   Using PHP built-in server:
   ```bash
   php -S localhost:8000
   ```
   
   Or configure Apache/Nginx to serve the application directory.

6. **Access the Application**
   
   Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

## Usage

1. **Login**: Enter your username (no password required)
2. **Set Work Hours**: Input how many hours you're working today
3. **View Goals**: See your targets based on work hours:
   - **Paid Memberships**: 1 per 4 hours worked
   - **Credit Cards**: 1 per 7 hours worked
   - Revenue goal: $500/hour (secondary goal)
4. **Feed Your Animal**: Enter revenue amounts to feed your pet
5. **Track Performance**: Check boxes when you make sales with:
   - ⭐ **Paid Membership** (+20 health, +25 happiness)
   - 💳 **Credit Card** (+20 health, +25 happiness)
   - 🛡️ **Warranty** (+10 health, +10 happiness)
   - Combined bonuses stack!
6. **Change Pet**: Click "Change Pet" button to select a different animal anytime
7. **Track Progress**: Monitor your stats and goal achievement in real-time

### Admin Dashboard

Administrators can access the admin dashboard at `/admin.html` to:
- View all employee pets and their health status
- Monitor team performance metrics
- Track active work sessions
- Manage apps and memberships
- Receive hourly pet-feeding notifications
- Reset session and clear all data
- **Reset workday for all employees** (manual button)

**New Admin Features:**
- **Reset Workday Button**: Manually reset all employees' stats to start fresh (health: 100%, happiness: 0%)
- **Automatic 8am Reset**: All employee stats automatically reset at 8am daily
- **Session Reset Button**: Clear cookies, timers, and log out instantly
- **Cookie Persistence**: Stay logged in for 7 days across browser restarts
- **Hourly Notifications**: Get reminders to feed pets (with in-app fallback)
- **Apps & Memberships**: Add and manage apps/memberships with local persistence

**To create admin users (after initial setup):**
```sql
-- Replace 'your_admin_username' with the actual username
UPDATE users SET is_admin = TRUE WHERE username = 'your_admin_username';
```

**Important Security Note**: Admin access should be granted manually to specific users. Do not create default admin accounts with predictable usernames.

## Game Mechanics

- **Health**: Decreases over time if not fed (5 points per hour during work sessions)
- **Daily Reset**: Health decay timer resets at the start of each new day - pets get a fresh start!
- **Work Sessions**: Health only decays during active work sessions, not on days off
- **Happiness**: Increases when you feed your animal and meet goals
- **Primary Goals**: 
  - 1 Paid Membership per 4 hours worked
  - 1 Credit Card per 7 hours worked
- **Secondary Goal**: Revenue target ($500/hour)
- **Feeding Rewards**:
  - Base: +5 health, +5 happiness per sale
  - $100+ sale: Additional +5 health, +5 happiness
  - **Paid Membership**: +20 health, +25 happiness
  - **Credit Card**: +20 health, +25 happiness
  - **Both together**: Extra +10 health, +10 happiness
  - **Warranty**: +10 health, +10 happiness
- **Pet Selection**: Choose from 6 animals (🐱 🐶 🐦 🐰 🐹 🐠) anytime
- **Auto-update**: Stats refresh automatically every 30 seconds

## Recent Updates

### Goal System Overhaul
- Changed primary goals from revenue-only to **Paid Memberships** and **Credit Cards**
- Goals now calculated based on work hours:
  - 1 Paid Membership expected per 4 hours
  - 1 Credit Card expected per 7 hours
- Revenue goal reduced to $500/hour (secondary metric)
- Increased rewards for achieving primary goals

### Pet Selection Feature
- Added "Change Pet" button on game screen
- Easy-to-use modal with 6 pet options
- Change your pet anytime without affecting stats

## Responsive Design

The app is fully responsive and optimized for:
- 📱 Mobile devices (portrait and landscape)
- 💻 Tablets
- 🖥️ Desktop computers

## API Endpoints

- `POST /api/login.php` - User login
- `GET/POST /api/session.php` - Work session management
- `GET/POST /api/feed.php` - Feed animal and update stats
- `POST /api/change_pet.php` - Change user's pet selection
- `GET /api/admin.php` - Admin dashboard data (requires admin privileges)

## Database Schema

- **users** - Employee information and animal choice
- **animal_stats** - Health, happiness, and revenue tracking
- **work_sessions** - Daily work hours, revenue goals, and goal tracking for credit cards/memberships
- **sales** - Individual sale records with special features

## Migration for Existing Installations

If you're updating from a previous version, run the migration scripts:

```bash
# For daily reset feature
mysql -u root -p bestbuy_tamagotchi < migration_daily_reset.sql

# For high-value override tracking
mysql -u root -p bestbuy_tamagotchi < migration_high_value_override.sql

# For workday reset feature (automatic 8am reset)
mysql -u root -p bestbuy_tamagotchi < migration_workday_reset.sql
```

These migrations will add:
- The `last_health_reset` column for daily health reset tracking
- The `is_admin` column for admin authentication
- The `overridden_high_value` column for tracking high-value revenue overrides
- The `daily_reset_marker` table for tracking automatic 8am workday resets

**After migration, manually grant admin access to authorized users:**
```sql
UPDATE users SET is_admin = TRUE WHERE username = 'your_admin_username';
```

## Security Notes

- This is a simple username-only authentication system
- For production use, implement proper authentication and security measures
- Update database credentials and use secure passwords
- Consider adding HTTPS for production deployment

## Customization

- Modify goal calculations in `api/session.php`:
  - Default: 1 Paid Membership per 4 hours
  - Default: 1 Credit Card per 7 hours
  - Default: $500/hour revenue goal
- Adjust health decrease rate in `api/login.php`
- Modify feeding bonuses in `api/feed.php`
- Change animal emojis in `app.js`
- Customize colors and styling in `styles.css`

## License

This project is open source and available for modification.
