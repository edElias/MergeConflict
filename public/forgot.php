<?php
// AUTH-05: Password reset, step 1 — find the account, then check the verification code.
// MER-26: Screens follow the Figma "Password reset (MER-26)" flow: same response whether or not
// the username exists, a 6-digit code that expires in 5 minutes, resend, and an expired state.
// TODO: demo only. Every account shares this code; replace with a real SMS code sent to users.phone.
const DEMO_CODE = '123456';
const CODE_TTL_SECONDS = 5 * 60;
const CODE_MAX_ATTEMPTS = 5;

session_start();
require __DIR__ . '/includes/db.php';

$pageTitle = 'Reset password';
$bodyClass = 'login-page';
$notice = null;     // [type, icon, text] shown above the form
$codeError = null;  // inline error under the code boxes

function send_code(): void
{
    // A real implementation would generate a random code and text it here.
    $_SESSION['reset']['sent_at'] = time();
    $_SESSION['reset']['attempts'] = 0;
}

$reset = $_SESSION['reset'] ?? null;
$expired = $reset && (time() - $reset['sent_at'] > CODE_TTL_SECONDS || $reset['attempts'] >= CODE_MAX_ATTEMPTS);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Opening the page fresh starts over at the username step.
    unset($_SESSION['reset']);
    $reset = null;
    $expired = false;
} elseif (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Unknown usernames get the same "code sent" screen so the page doesn't reveal which accounts exist.
    $_SESSION['reset'] = ['user_id' => $user ? (int) $user['id'] : 0, 'username' => $username];
    send_code();
    $reset = $_SESSION['reset'];
    $expired = false;
} elseif (($_POST['action'] ?? '') === 'resend' && $reset) {
    send_code();
    $reset = $_SESSION['reset'];
    $expired = false;
    $notice = ['success', 'bi-check-circle', 'We sent a new code.'];
} elseif (isset($_POST['digit']) && $reset && !$expired) {
    $code = implode('', array_map(fn($d) => preg_replace('/\D/', '', (string) $d), (array) $_POST['digit']));

    if ($reset['user_id'] > 0 && hash_equals(DEMO_CODE, $code)) {
        session_regenerate_id(true);
        $_SESSION['reset_user_id'] = $reset['user_id'];
        $_SESSION['reset_username'] = $reset['username'];
        $_SESSION['reset_verified_at'] = time();
        unset($_SESSION['reset']);
        header('Location: reset.php');
        exit;
    }

    $_SESSION['reset']['attempts']++;
    $reset = $_SESSION['reset'];
    $expired = $reset['attempts'] >= CODE_MAX_ATTEMPTS;
    $codeError = strlen($code) < 6 ? 'Enter all 6 digits.' : 'Incorrect code. Try again.';
}

$step = !$reset ? 'request' : ($expired ? 'expired' : 'code');

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
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-link btn-sm p-0">Didn't get a code? Resend code</button>
        </form>

      <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i><div>This verification code has expired.</div>
        </div>
        <p class="small text-body-secondary mb-4">
          For your security, codes work once and expire after 5 minutes. Send a new code to continue.
        </p>
        <form action="forgot.php" method="post">
          <input type="hidden" name="action" value="resend">
          <button type="submit" class="btn btn-primary w-100">Send a new code</button>
        </form>
      <?php endif; ?>

      <p class="text-center small mt-3 mb-0"><a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
