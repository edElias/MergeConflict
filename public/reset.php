<?php
// AUTH-05: Password reset, step 2 — choose a new password after the code was verified.
session_start();
require __DIR__ . '/includes/db.php';

// Verification from forgot.php is only good for 10 minutes.
if (empty($_SESSION['reset_user_id']) || time() - ($_SESSION['reset_verified_at'] ?? 0) > 600) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_verified_at']);
    header('Location: forgot.php');
    exit;
}

$pageTitle = 'Choose a new password';
$bodyClass = 'login-page';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['reset_user_id']]);
    $user = $stmt->fetch();

    if ($password === '') {
        $message = ['danger', 'bi-exclamation-triangle', 'Enter a new password.'];
    } elseif ($password !== $confirm) {
        $message = ['danger', 'bi-exclamation-triangle', 'Passwords do not match.'];
    } elseif (password_verify($password, $user['password_hash'])) {
        $message = ['danger', 'bi-exclamation-triangle', 'New password must be different from your current password.'];
    } else {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['reset_user_id']]);

        unset($_SESSION['reset_user_id'], $_SESSION['reset_verified_at']);
        header('Location: login.php?reset=1');
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>
<main class="d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card shadow-sm login-card">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <i class="bi bi-key display-5 text-primary"></i>
        <h1 class="h3 mt-2 mb-1">Choose a new password</h1>
      </div>

      <?php if ($message): [$type, $icon, $text] = $message; ?>
        <div class="alert alert-<?= $type ?> d-flex align-items-center" role="alert">
          <i class="bi <?= $icon ?> me-2"></i><div><?= htmlspecialchars($text) ?></div>
        </div>
      <?php endif; ?>

      <form action="reset.php" method="post" class="needs-validation" novalidate>
        <div class="mb-3">
          <label for="password" class="form-label">New password</label>
          <input type="password" class="form-control" id="password" name="password"
                 autocomplete="new-password" required autofocus>
          <div class="invalid-feedback">Enter a new password.</div>
        </div>
        <div class="mb-4">
          <label for="confirm" class="form-label">Confirm new password</label>
          <input type="password" class="form-control" id="confirm" name="confirm"
                 autocomplete="new-password" required>
          <div class="invalid-feedback">Re-enter the new password.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Update password</button>
      </form>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
