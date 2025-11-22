// Application State
let currentUser = null;
let currentSession = null;

// API Base URL - adjust this based on your server setup
const API_BASE = 'api/';

// Screen Management
function showScreen(screenId) {
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    document.getElementById(screenId).classList.add('active');
}

// Show error message
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    errorElement.textContent = message;
    errorElement.classList.add('show');
    setTimeout(() => {
        errorElement.classList.remove('show');
    }, 5000);
}

// Show success message
function showSuccess(elementId, message) {
    const successElement = document.getElementById(elementId);
    successElement.textContent = message;
    successElement.classList.add('show');
    setTimeout(() => {
        successElement.classList.remove('show');
    }, 3000);
}

// Format currency
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Update animal emoji based on type
function updateAnimalEmoji(animalType) {
    const emojiMap = {
        'cat': '🐱',
        'dog': '🐶',
        'bird': '🐦',
        'rabbit': '🐰',
        'hamster': '🐹',
        'fish': '🐠'
    };
    const emoji = emojiMap[animalType.toLowerCase()] || '🐱';
    document.querySelector('.animal-emoji').textContent = emoji;
    document.getElementById('animalType').textContent = animalType.charAt(0).toUpperCase() + animalType.slice(1);
}

// Update stat bars
function updateStats(health, happiness) {
    document.getElementById('healthBar').style.setProperty('--health', health + '%');
    document.getElementById('healthValue').textContent = health;
    
    document.getElementById('happinessBar').style.setProperty('--happiness', happiness + '%');
    document.getElementById('happinessValue').textContent = happiness;
}

// Login Form Handler
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    
    if (!username) {
        showError('loginError', 'Please enter a username');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            document.getElementById('employeeName').textContent = currentUser.name;
            updateAnimalEmoji(currentUser.animal_choice);
            updateStats(currentUser.health, currentUser.happiness);
            
            // Check if there's a session for today
            checkTodaySession();
        } else {
            showError('loginError', data.error || 'Login failed');
        }
    } catch (error) {
        showError('loginError', 'Connection error. Please check if the server is running.');
        console.error('Login error:', error);
    }
});

// Check if user has a session for today
async function checkTodaySession() {
    try {
        const response = await fetch(API_BASE + `session.php?user_id=${currentUser.id}`);
        const data = await response.json();
        
        if (data.success) {
            // Session exists, go to game screen
            currentSession = data.session;
            loadGameScreen();
        } else {
            // No session, ask for work hours
            showScreen('workHoursScreen');
        }
    } catch (error) {
        console.error('Session check error:', error);
        showScreen('workHoursScreen');
    }
}

// Work Hours Form Handler
document.getElementById('workHoursForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const workHours = parseFloat(document.getElementById('workHours').value);
    
    if (workHours <= 0 || workHours > 12) {
        showError('workHoursError', 'Please enter valid work hours (0.5 - 12)');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                work_hours: workHours
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentSession = {
                work_hours: data.work_hours,
                goal_amount: data.goal_amount,
                revenue: 0,
                goal_met: false
            };
            loadGameScreen();
        } else {
            showError('workHoursError', data.error || 'Failed to create work session');
        }
    } catch (error) {
        showError('workHoursError', 'Connection error. Please try again.');
        console.error('Work hours error:', error);
    }
});

// Load Game Screen
async function loadGameScreen() {
    showScreen('gameScreen');
    
    document.getElementById('gameEmployeeName').textContent = currentUser.name;
    updateAnimalEmoji(currentUser.animal_choice);
    updateStats(currentUser.health, currentUser.happiness);
    
    // Load session data
    try {
        const sessionResponse = await fetch(API_BASE + `session.php?user_id=${currentUser.id}`);
        const sessionData = await sessionResponse.json();
        
        if (sessionData.success) {
            currentSession = sessionData.session;
            updateGoalsDisplay();
        }
    } catch (error) {
        console.error('Error loading session:', error);
    }
    
    // Load animal stats
    try {
        const statsResponse = await fetch(API_BASE + `feed.php?user_id=${currentUser.id}`);
        const statsData = await statsResponse.json();
        
        if (statsData.success) {
            updateStats(statsData.stats.health, statsData.stats.happiness);
            document.getElementById('totalRevenue').textContent = formatCurrency(statsData.stats.total_revenue);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Update Goals Display
function updateGoalsDisplay() {
    if (currentSession) {
        document.getElementById('goalWorkHours').textContent = currentSession.work_hours + ' hours';
        document.getElementById('goalAmount').textContent = formatCurrency(currentSession.goal_amount);
        document.getElementById('currentRevenue').textContent = formatCurrency(currentSession.revenue || 0);
        
        const goalStatus = document.getElementById('goalStatus');
        if (currentSession.goal_met || (currentSession.revenue >= currentSession.goal_amount)) {
            goalStatus.className = 'goal-status met';
            goalStatus.innerHTML = '<span class="status-text">🎉 Goal Achieved! Great job!</span>';
        } else {
            goalStatus.className = 'goal-status not-met';
            const remaining = currentSession.goal_amount - (currentSession.revenue || 0);
            goalStatus.innerHTML = `<span class="status-text">Goal not yet achieved. ${formatCurrency(remaining)} remaining</span>`;
        }
    }
}

// Feed Form Handler
document.getElementById('feedForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const revenue = parseFloat(document.getElementById('revenue').value);
    const hasCreditCard = document.getElementById('hasCreditCard').checked;
    const hasPaidMembership = document.getElementById('hasPaidMembership').checked;
    const hasWarranty = document.getElementById('hasWarranty').checked;
    
    if (revenue <= 0) {
        showError('feedError', 'Please enter a valid revenue amount');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'feed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                revenue: revenue,
                has_credit_card: hasCreditCard,
                has_paid_membership: hasPaidMembership,
                has_warranty: hasWarranty
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update stats
            updateStats(data.health, data.happiness);
            document.getElementById('totalRevenue').textContent = formatCurrency(data.total_revenue);
            
            // Update current session revenue
            currentSession.revenue = (currentSession.revenue || 0) + revenue;
            currentSession.goal_met = data.goal_met;
            updateGoalsDisplay();
            
            // Clear form and show success
            document.getElementById('revenue').value = '';
            document.getElementById('hasCreditCard').checked = false;
            document.getElementById('hasPaidMembership').checked = false;
            document.getElementById('hasWarranty').checked = false;
            showSuccess('feedMessage', data.message + ' +' + formatCurrency(revenue));
            
            // Add animation effect
            const animalSprite = document.querySelector('.animal-sprite');
            animalSprite.style.animation = 'none';
            setTimeout(() => {
                animalSprite.style.animation = 'float 3s ease-in-out infinite';
            }, 10);
        } else {
            showError('feedError', data.error || 'Failed to feed animal');
        }
    } catch (error) {
        showError('feedError', 'Connection error. Please try again.');
        console.error('Feed error:', error);
    }
});

// Logout Handler
document.getElementById('logoutBtn').addEventListener('click', () => {
    currentUser = null;
    currentSession = null;
    document.getElementById('loginForm').reset();
    document.getElementById('workHoursForm').reset();
    document.getElementById('feedForm').reset();
    showScreen('loginScreen');
});

// Auto-refresh stats every 30 seconds
setInterval(async () => {
    if (currentUser && document.getElementById('gameScreen').classList.contains('active')) {
        try {
            const response = await fetch(API_BASE + `feed.php?user_id=${currentUser.id}`);
            const data = await response.json();
            
            if (data.success) {
                updateStats(data.stats.health, data.stats.happiness);
            }
        } catch (error) {
            console.error('Auto-refresh error:', error);
        }
    }
}, 30000);
