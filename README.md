# Best Buy Employee Tamagotchi App

A gamified performance tracking system for Best Buy employees. Keep your virtual pet alive by reaching your daily revenue goals!

## Features

- 🎮 **Tamagotchi-style gameplay** - Choose your animal companion (cat, dog, bird, etc.)
- 📊 **Performance tracking** - Set daily work hours and revenue goals
- 💰 **Revenue-based feeding** - Feed your animal by entering sales revenue
- 📱 **Responsive design** - Works great on both mobile and desktop
- 📈 **Health & Happiness stats** - Watch your animal's wellbeing based on your performance
- 🎯 **Goal tracking** - Monitor progress toward daily targets

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

## Game Mechanics

- **Health**: Decreases over time if not fed (5 points per hour)
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

## Database Schema

- **users** - Employee information and animal choice
- **animal_stats** - Health, happiness, and revenue tracking
- **work_sessions** - Daily work hours, revenue goals, and goal tracking for credit cards/memberships
- **sales** - Individual sale records with special features

## Migration for Existing Installations

If you're updating from a previous version, run the migration script:

```bash
mysql -u root -p bestbuy_tamagotchi < migration.sql
```

This will add the new goal tracking columns to your database.

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
