// Application State
let currentUser = null;
let currentSession = null;
let pendingRevenueData = null; // For high-value override flow

// API Base URL - adjust this based on your server setup
const API_BASE = 'api/';

// Try to restore session on page load
window.addEventListener('DOMContentLoaded', () => {
    const savedSession = restoreSession();
    if (savedSession && !savedSession.is_admin) {
        // Restore employee session
        currentUser = savedSession;
        checkTodaySession();
    }
});

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

// getAnimalStatus function is now in utils/animal-status.js

// Update animation speed based on health and happiness
function updateAnimationSpeed(health, happiness) {
    const animalSprite = document.querySelector('.animal-sprite');
    const animalEmoji = document.querySelector('.animal-emoji');
    
    if (!animalSprite || !animalEmoji) return;
    
    // Calculate average of health and happiness for overall vitality
    const vitality = (health + happiness) / 2;
    
    // Animation speeds: faster when healthy/happy, slower when not
    // Base speeds (in seconds)
    const floatSpeedBase = 3; // Base float animation
    const wiggleSpeedBase = 2; // Base wiggle animation
    
    // Calculate speed multiplier (slower = higher number, faster = lower number)
    // When vitality is high (100), speed = 1x (normal)
    // When vitality is low (0), speed = 5x (very slow)
    let speedMultiplier;
    if (health <= 0) {
        // Dead - no animation
        speedMultiplier = 999;
    } else if (vitality >= 80) {
        speedMultiplier = 0.8; // Fast
    } else if (vitality >= 60) {
        speedMultiplier = 1.2; // Normal-ish
    } else if (vitality >= 40) {
        speedMultiplier = 2.0; // Slower
    } else if (vitality >= 20) {
        speedMultiplier = 3.0; // Much slower
    } else {
        speedMultiplier = 5.0; // Very slow
    }
    
    const floatSpeed = floatSpeedBase * speedMultiplier;
    const wiggleSpeed = wiggleSpeedBase * speedMultiplier;
    
    if (health <= 0) {
        // Stop animations when dead
        animalSprite.style.animation = 'none';
        animalEmoji.style.animation = 'none';
    } else {
        animalSprite.style.animation = `float ${floatSpeed}s ease-in-out infinite`;
        animalEmoji.style.animation = `wiggle ${wiggleSpeed}s ease-in-out infinite`;
    }
}

// Update stat bars with status display
function updateStats(health, happiness) {
    document.getElementById('healthBar').style.setProperty('--health', health + '%');
    document.getElementById('healthValue').textContent = health;
    
    document.getElementById('happinessBar').style.setProperty('--happiness', happiness + '%');
    document.getElementById('happinessValue').textContent = happiness;
    
    // Update animal status display
    const statusInfo = getAnimalStatus(health, happiness);
    const statusElement = document.getElementById('animalStatus');
    if (statusElement) {
        statusElement.textContent = statusInfo.emoji + ' ' + statusInfo.status;
        statusElement.style.color = statusInfo.color;
    }
    
    // Update animation speed
    updateAnimationSpeed(health, happiness);
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
            
            // Save session with cookie persistence (only for non-admin users)
            if (!currentUser.is_admin) {
                saveSession(currentUser);
            }
            
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
                goal_paid_memberships: data.goal_paid_memberships,
                goal_credit_cards: data.goal_credit_cards,
                revenue: 0,
                current_paid_memberships: 0,
                current_credit_cards: 0,
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
        
        // Update Paid Memberships goal
        const currentPM = currentSession.current_paid_memberships || 0;
        const goalPM = currentSession.goal_paid_memberships || 0;
        document.getElementById('goalPaidMemberships').textContent = `${currentPM} / ${goalPM}`;
        
        // Update Credit Cards goal
        const currentCC = currentSession.current_credit_cards || 0;
        const goalCC = currentSession.goal_credit_cards || 0;
        document.getElementById('goalCreditCards').textContent = `${currentCC} / ${goalCC}`;
        
        const goalStatus = document.getElementById('goalStatus');
        if (currentSession.goal_met || (currentPM >= goalPM && currentCC >= goalCC)) {
            goalStatus.className = 'goal-status met';
            goalStatus.innerHTML = '<span class="status-text">🎉 Goal Achieved! Great job!</span>';
        } else {
            goalStatus.className = 'goal-status not-met';
            const remainingPM = Math.max(0, goalPM - currentPM);
            const remainingCC = Math.max(0, goalCC - currentCC);
            let statusText = 'Keep going! Need: ';
            if (remainingPM > 0) statusText += `${remainingPM} Paid Membership${remainingPM > 1 ? 's' : ''} `;
            if (remainingCC > 0) statusText += `${remainingCC} Credit Card${remainingCC > 1 ? 's' : ''}`;
            goalStatus.innerHTML = `<span class="status-text">${statusText}</span>`;
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
    
    // Check for high-value revenue (> 8000)
    if (revenue > 8000) {
        // Store pending data and show override modal
        pendingRevenueData = {
            revenue: revenue,
            hasCreditCard: hasCreditCard,
            hasPaidMembership: hasPaidMembership,
            hasWarranty: hasWarranty,
            overriddenHighValue: false
        };
        
        showRevenueOverrideModal(revenue);
        return;
    }
    
    // Process normal revenue
    await submitRevenue({
        revenue: revenue,
        hasCreditCard: hasCreditCard,
        hasPaidMembership: hasPaidMembership,
        hasWarranty: hasWarranty,
        overriddenHighValue: false
    });
});

// Revenue Override Modal Functions
function showRevenueOverrideModal(amount) {
    document.getElementById('highRevenueAmount').textContent = formatCurrency(amount);
    document.getElementById('revenueOverrideModal').style.display = 'flex';
}

function hideRevenueOverrideModal() {
    document.getElementById('revenueOverrideModal').style.display = 'none';
    pendingRevenueData = null;
}

// Handle Cancel button in override modal
document.getElementById('cancelRevenueBtn').addEventListener('click', () => {
    hideRevenueOverrideModal();
});

// Handle Override button in override modal
document.getElementById('overrideRevenueBtn').addEventListener('click', async () => {
    if (pendingRevenueData) {
        pendingRevenueData.overriddenHighValue = true;
        await submitRevenue(pendingRevenueData);
        hideRevenueOverrideModal();
    }
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('revenueOverrideModal');
    if (e.target === modal) {
        hideRevenueOverrideModal();
    }
});

// Submit revenue function (extracted for reuse)
async function submitRevenue(data) {
    try {
        const response = await fetch(API_BASE + 'feed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                revenue: data.revenue,
                has_credit_card: data.hasCreditCard,
                has_paid_membership: data.hasPaidMembership,
                has_warranty: data.hasWarranty,
                overridden_high_value: data.overriddenHighValue
            })
        });
        
        const responseData = await response.json();
        
        if (responseData.success) {
            // Update stats
            updateStats(responseData.health, responseData.happiness);
            document.getElementById('totalRevenue').textContent = formatCurrency(responseData.total_revenue);
            
            // Update current session revenue and counters
            currentSession.revenue = (currentSession.revenue || 0) + data.revenue;
            if (data.hasCreditCard) currentSession.current_credit_cards = (currentSession.current_credit_cards || 0) + 1;
            if (data.hasPaidMembership) currentSession.current_paid_memberships = (currentSession.current_paid_memberships || 0) + 1;
            currentSession.goal_met = responseData.goal_met;
            updateGoalsDisplay();
            
            // Clear form and show success
            document.getElementById('revenue').value = '';
            document.getElementById('hasCreditCard').checked = false;
            document.getElementById('hasPaidMembership').checked = false;
            document.getElementById('hasWarranty').checked = false;
            
            let successMessage = responseData.message + ' +' + formatCurrency(data.revenue);
            if (data.overriddenHighValue) {
                successMessage += ' (High-value override applied)';
            }
            showSuccess('feedMessage', successMessage);
            
            // Add animation effect
            const animalSprite = document.querySelector('.animal-sprite');
            animalSprite.style.animation = 'none';
            setTimeout(() => {
                animalSprite.style.animation = 'float 3s ease-in-out infinite';
            }, 10);
        } else {
            showError('feedError', responseData.error || 'Failed to feed animal');
        }
    } catch (error) {
        showError('feedError', 'Connection error. Please try again.');
        console.error('Feed error:', error);
    }
}

// Logout Handler
document.getElementById('logoutBtn').addEventListener('click', () => {
    // Clear session
    clearSession();
    
    currentUser = null;
    currentSession = null;
    document.getElementById('loginForm').reset();
    document.getElementById('workHoursForm').reset();
    document.getElementById('feedForm').reset();
    showScreen('loginScreen');
});

// Pet Selection Modal Handlers
document.getElementById('changePetBtn').addEventListener('click', () => {
    document.getElementById('petModal').style.display = 'flex';
});

document.getElementById('closePetModal').addEventListener('click', () => {
    document.getElementById('petModal').style.display = 'none';
});

// Handle pet selection
document.querySelectorAll('.pet-option').forEach(button => {
    button.addEventListener('click', async () => {
        const petType = button.getAttribute('data-pet');
        
        try {
            const response = await fetch(API_BASE + 'change_pet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: currentUser.id,
                    animal_choice: petType
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                currentUser.animal_choice = petType;
                updateAnimalEmoji(petType);
                document.getElementById('petModal').style.display = 'none';
                
                // Show success message briefly
                const animalSprite = document.querySelector('.animal-sprite');
                animalSprite.style.animation = 'none';
                setTimeout(() => {
                    animalSprite.style.animation = 'float 3s ease-in-out infinite';
                }, 10);
            } else {
                alert('Failed to change pet. Please try again.');
            }
        } catch (error) {
            console.error('Change pet error:', error);
            alert('Connection error. Please try again.');
        }
    });
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('petModal');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
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
