<?php
// MER-7: Login page UI.
// Contract with the PHP auth work (MER-9):
//   - The form POSTs `username` and `password` to authenticate.php.
//   - On failure, authenticate.php redirects to login.php?error=1.
//   - After logout, logout.php redirects to login.php?logged_out=1.
//   - Protected pages redirect here with ?expired=1 when the session is missing or expired.
$pageTitle = 'Sign in';
$bodyClass = 'login-page';

$message = null;
if (isset($_GET['error'])) {
    // AUTH-01: keep the error non-specific (don't reveal which field was wrong).
    $message = ['danger', 'bi-exclamation-triangle', 'Invalid username or password.'];
} elseif (isset($_GET['logged_out'])) {
    $message = ['success', 'bi-check-circle', 'You have been logged out.'];
} elseif (isset($_GET['expired'])) {
    $message = ['warning', 'bi-clock-history', 'Please sign in to continue.'];
}

require __DIR__ . '/includes/header.php';
?>
<main class="d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card shadow-sm login-card">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <i class="bi bi-building-check display-5 text-primary"></i>
        <h1 class="h3 mt-2 mb-1">CivicFunds</h1>
        <p class="text-body-secondary mb-0">Public Treasury Dashboard</p>
      </div>

      <?php if ($message): [$type, $icon, $text] = $message; ?>
        <div class="alert alert-<?= $type ?> d-flex align-items-center" role="alert">
          <i class="bi <?= $icon ?> me-2"></i><div><?= htmlspecialchars($text) ?></div>
        </div>
      <?php endif; ?>

      <form action="authenticate.php" method="post" class="needs-validation" novalidate>
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username"
                 autocomplete="username" required autofocus>
          <div class="invalid-feedback">Enter your username.</div>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password"
                 autocomplete="current-password" required>
          <div class="invalid-feedback">Enter your password.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
      </form>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
