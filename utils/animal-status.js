// Shared utility function to calculate animal status based on health and happiness
function getAnimalStatus(health, happiness) {
    if (health <= 0) {
        return { status: 'Dead', emoji: '💀', color: '#000000' };
    } else if (health < 20 || happiness < 20) {
        return { status: 'Critical', emoji: '😰', color: '#dc3545' };
    } else if (health < 40 || happiness < 40) {
        return { status: 'Sad', emoji: '😢', color: '#ff6b6b' };
    } else if (health < 60 || happiness < 60) {
        return { status: 'Okay', emoji: '😐', color: '#ffa500' };
    } else if (health < 80 || happiness < 80) {
        return { status: 'Good', emoji: '🙂', color: '#28a745' };
    } else {
        return { status: 'Happy', emoji: '😄', color: '#4caf50' };
    }
}
