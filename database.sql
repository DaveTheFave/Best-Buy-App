-- Best Buy Tamagotchi App Database Schema

CREATE DATABASE IF NOT EXISTS bestbuy_tamagotchi;
USE bestbuy_tamagotchi;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    animal_choice VARCHAR(50) DEFAULT 'cat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Animal stats table
CREATE TABLE IF NOT EXISTS animal_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    health INT DEFAULT 100,
    happiness INT DEFAULT 100,
    last_fed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_revenue DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Work sessions table
CREATE TABLE IF NOT EXISTS work_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    work_hours DECIMAL(4, 2) NOT NULL,
    session_date DATE NOT NULL,
    revenue DECIMAL(10, 2) DEFAULT 0.00,
    goal_amount DECIMAL(10, 2) NOT NULL,
    goal_met BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample data (you can insert actual employee data here)
INSERT INTO users (username, name, animal_choice) VALUES 
('employee1', 'John Doe', 'cat'),
('employee2', 'Jane Smith', 'dog'),
('employee3', 'Bob Johnson', 'bird')
ON DUPLICATE KEY UPDATE username=username;

-- Initialize animal stats for sample users
INSERT INTO animal_stats (user_id, health, happiness) 
SELECT id, 100, 100 FROM users
ON DUPLICATE KEY UPDATE user_id=user_id;
