<?php
// AUTH-05: Password reset, step 1 — find the account, then check the confirmation code.
// TODO: demo only. Every account shares this phone number and code; replace with real SMS.
const DEMO_PHONE = '(555) 010-0000';
const DEMO_CODE  = '123456';

session_start();
require __DIR__ . '/includes/db.php';

$pageTitle = 'Reset password';
$bodyClass = 'login-page';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([trim($_POST['username'])]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['reset_pending_id'] = $user['id'];
    } else {
        unset($_SESSION['reset_pending_id']);
        $message = ['danger', 'bi-exclamation-triangle', 'User not found.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'], $_SESSION['reset_pending_id'])) {
    if (trim($_POST['code']) === DEMO_CODE) {
        $_SESSION['reset_user_id']     = $_SESSION['reset_pending_id'];
        $_SESSION['reset_verified_at'] = time();
        unset($_SESSION['reset_pending_id']);
        header('Location: reset.php');
        exit;
    }
    $message = ['danger', 'bi-exclamation-triangle', 'Incorrect code.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Opening the page fresh starts over at the username step.
    unset($_SESSION['reset_pending_id']);
}

$awaitingCode = isset($_SESSION['reset_pending_id']);

require __DIR__ . '/includes/header.php';
?>
<main class="d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card shadow-sm login-card">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock display-5 text-primary"></i>
        <h1 class="h3 mt-2 mb-1">Reset password</h1>
        <p class="text-body-secondary mb-0">
          <?= $awaitingCode ? 'We sent a code to ' . DEMO_PHONE . '.' : 'Enter your username to get started.' ?>
        </p>
      </div>

      <?php if ($message): [$type, $icon, $text] = $message; ?>
        <div class="alert alert-<?= $type ?> d-flex align-items-center" role="alert">
          <i class="bi <?= $icon ?> me-2"></i><div><?= htmlspecialchars($text) ?></div>
        </div>
      <?php endif; ?>

      <form action="forgot.php" method="post" class="needs-validation" novalidate>
        <?php if ($awaitingCode): ?>
          <div class="mb-4">
            <label for="code" class="form-label">Confirmation code</label>
            <input type="text" class="form-control" id="code" name="code"
                   inputmode="numeric" autocomplete="one-time-code" required autofocus>
            <div class="invalid-feedback">Enter the code.</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Verify code</button>
        <?php else: ?>
          <div class="mb-4">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username"
                   autocomplete="username" required autofocus>
            <div class="invalid-feedback">Enter your username.</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Send code</button>
        <?php endif; ?>
      </form>
      <p class="text-center mt-3 mb-0"><a href="login.php">Back to sign in</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
