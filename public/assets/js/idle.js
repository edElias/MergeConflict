// Log the user out after 10 minutes without mouse or keyboard activity.
const IDLE_LIMIT = 10 * 60 * 1000;
const PING_EVERY = 60 * 1000;
let idleTimer;
let wasActive = false;

function resetIdleTimer() {
  wasActive = true;
  clearTimeout(idleTimer);
  idleTimer = setTimeout(() => {
    window.location.href = 'logout.php';
  }, IDLE_LIMIT);
}

// While the user is active, tell the server so its idle clock stays in step.
setInterval(() => {
  if (wasActive) {
    fetch('ping.php');
    wasActive = false;
  }
}, PING_EVERY);

document.addEventListener('mousemove', resetIdleTimer);
document.addEventListener('keydown', resetIdleTimer);
resetIdleTimer();