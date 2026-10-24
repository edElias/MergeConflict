<?php
// AUTH-05: Password reset, step 1 — find the account, then check the verification code.
// MER-26: Screens follow the Figma "Password reset (MER-26)" flow: same response whether or not
// the username exists, a 6-digit code that expires in 5 minutes, resend, and an expired state.
// TODO: demo only. Every account shares this code; replace with a real SMS code sent to users.phone.
const DEMO_CODE = '123456';
const CODE_TTL_SECONDS = 5 * 60;
const CODE_MAX_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 15 * 60;   // wrong tries are counted over this window, across resends

session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/account.php';

$pageTitle = 'Reset password';
$bodyClass = 'login-page';
$notice = null;     // [type, icon, text] shown above the form
$codeError = null;  // inline error under the code boxes

// Code state for the account being reset: ['sent_at', 'attempts', 'window'].
// Real accounts are tracked in password_resets so a new session or resend can't
// reset the attempt count. Unknown usernames (user_id 0) only live in the session;
// they can never succeed, and they still get the same screens.
function load_code_state(PDO $pdo, array $reset): ?array
{
    if ($reset['user_id'] === 0) {
        return $_SESSION['reset']['state'] ?? null;
    }
    $stmt = $pdo->prepare('SELECT code_sent_at, attempts, window_started_at FROM password_resets WHERE user_id = ?');
    $stmt->execute([$reset['user_id']]);
    $row = $stmt->fetch();
    return $row ? ['sent_at' => (int) $row['code_sent_at'], 'attempts' => (int) $row['attempts'],
                   'window' => (int) $row['window_started_at']] : null;
}

function save_code_state(PDO $pdo, array $reset, array $state): void
{
    if ($reset['user_id'] === 0) {
        $_SESSION['reset']['state'] = $state;
        return;
    }
    $pdo->prepare('INSERT OR REPLACE INTO password_resets (user_id, code_sent_at, attempts, window_started_at)
                   VALUES (?, ?, ?, ?)')
        ->execute([$reset['user_id'], $state['sent_at'], $state['attempts'], $state['window']]);
}

// Sends (or re-sends) a code. Attempts only start over once the lockout window has passed.
function send_code(PDO $pdo, array $reset): array
{
    // A real implementation would generate a random code and text it here.
    $now = time();
    $state = load_code_state($pdo, $reset);
    if (!$state || $now - $state['window'] > LOCKOUT_SECONDS) {
        $state = ['attempts' => 0, 'window' => $now];
    }
    $state['sent_at'] = $now;
    save_code_state($pdo, $reset, $state);
    return $state;
}

$reset = $_SESSION['reset'] ?? null;
$state = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Opening the page fresh starts over at the username step.
    unset($_SESSION['reset']);
    $reset = null;
} elseif (!csrf_valid($_POST['csrf_token'] ?? null)) {
    unset($_SESSION['reset']);
    $reset = null;
    $notice = ['danger', 'bi-exclamation-triangle', 'Your session expired. Please start again.'];
} elseif (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Unknown usernames get the same "code sent" screen so the page doesn't reveal which accounts exist.
    $reset = ['user_id' => $user ? (int) $user['id'] : 0, 'username' => $username];
    $_SESSION['reset'] = $reset;
    $state = send_code($pdo, $reset);
} elseif (($_POST['action'] ?? '') === 'resend' && $reset) {
    $state = send_code($pdo, $reset);
    if ($state['attempts'] < CODE_MAX_ATTEMPTS) {
        $notice = ['success', 'bi-check-circle', 'We sent a new code.'];
    }
} elseif (isset($_POST['digit']) && $reset) {
    $state = load_code_state($pdo, $reset);
    $usable = $state && $state['attempts'] < CODE_MAX_ATTEMPTS && time() - $state['sent_at'] <= CODE_TTL_SECONDS;
    $code = implode('', array_map(fn($d) => preg_replace('/\D/', '', (string) $d), (array) $_POST['digit']));

    if ($usable && $reset['user_id'] > 0 && hash_equals(DEMO_CODE, $code)) {
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
        session_regenerate_id(true);
        $_SESSION['reset_user_id'] = $reset['user_id'];
        $_SESSION['reset_username'] = $reset['username'];
        $_SESSION['reset_verified_at'] = time();
        unset($_SESSION['reset']);
        header('Location: reset.php');
        exit;
    }

    if ($usable) {
        $state['attempts']++;
        save_code_state($pdo, $reset, $state);
        $codeError = strlen($code) < 6 ? 'Enter all 6 digits.' : 'Incorrect code. Try again.';
    }
}

if ($reset) {
    $state ??= load_code_state($pdo, $reset);
}

if (!$reset || !$state) {
    $step = 'request';
} elseif ($state['attempts'] >= CODE_MAX_ATTEMPTS) {
    $step = 'locked';
    $minutesLeft = max(1, (int) ceil(($state['window'] + LOCKOUT_SECONDS - time()) / 60));
} elseif (time() - $state['sent_at'] > CODE_TTL_SECONDS) {
    $step = 'expired';
} else {
    $step = 'code';
}
$token = csrf_token();

require __DIR__ . '/includes/header.php';
?>
<main class="d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card shadow-sm login-card">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock display-5 text-primary"></i>
        <div class="h3 mt-2 mb-1">CivicFunds</div>
        <p class="text-body-secondary mb-0">Public Treasury Dashboard</p>
      </div>

      <?php if ($notice): [$type, $icon, $text] = $notice; ?>
        <div class="alert alert-<?= $type ?> d-flex align-items-center" role="status">
          <i class="bi <?= $icon ?> me-2"></i><div><?= htmlspecialchars($text) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($step === 'request'): ?>
        <h1 class="h5 mb-1">Reset your password</h1>
        <p class="small text-body-secondary mb-4">
          Enter your username. If it matches an account, we'll send a 6-digit verification code to the phone number on file.
        </p>
        <form action="forgot.php" method="post" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <div class="mb-4">
            <label for="username" class="form-label fw-semibold small">Username</label>
            <input type="text" class="form-control" id="username" name="username"
                   autocomplete="username" required autofocus>
            <div class="invalid-feedback">Enter your username.</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Send code</button>
        </form>

      <?php elseif ($step === 'code'): ?>
        <h1 class="h5 mb-1">Enter verification code</h1>
        <p class="small text-body-secondary mb-4">We sent a 6-digit code to the phone number on file.</p>
        <form action="forgot.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <fieldset class="mb-4">
            <legend class="form-label fw-semibold small mb-2">Verification code</legend>
            <div class="d-flex gap-2 code-inputs" data-code-inputs>
              <?php for ($i = 1; $i <= 6; $i++): ?>
                <input type="text" name="digit[]" inputmode="numeric"
                       class="form-control text-center fs-5 fw-semibold <?= $codeError ? 'is-invalid' : '' ?>"
                       aria-label="Digit <?= $i ?> of 6" <?= $i === 1 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"' ?>
                       <?= $codeError ? 'aria-describedby="code-error"' : '' ?>>
              <?php endfor; ?>
            </div>
            <?php if ($codeError): ?>
              <div id="code-error" class="small text-danger mt-2"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($codeError) ?></div>
            <?php endif; ?>
            <div class="small text-body-secondary mt-2"><i class="bi bi-stopwatch me-1"></i>The code expires in 5 minutes.</div>
          </fieldset>
          <button type="submit" class="btn btn-primary w-100">Verify code</button>
        </form>
        <form action="forgot.php" method="post" class="text-center mt-3">
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-link btn-sm p-0">Didn't get a code? Resend code</button>
        </form>

      <?php elseif ($step === 'locked'): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i><div>Too many incorrect codes.</div>
        </div>
        <p class="small text-body-secondary mb-0">
          For your security, you can request a new code in about <?= $minutesLeft ?> minute<?= $minutesLeft === 1 ? '' : 's' ?>.
        </p>

      <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i><div>This verification code has expired.</div>
        </div>
        <p class="small text-body-secondary mb-4">
          For your security, codes work once and expire after 5 minutes. Send a new code to continue.
        </p>
        <form action="forgot.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-primary w-100">Send a new code</button>
        </form>
      <?php endif; ?>

      <p class="text-center small mt-3 mb-0"><a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
