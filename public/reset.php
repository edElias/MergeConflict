<?php
// AUTH-05: Password reset, step 2 — choose a new password after the code was verified.
// MER-26: Matches the Figma "Set new password" and "fields invalid" screens.
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/account.php';

// Verification from forgot.php is only good for 10 minutes.
if (empty($_SESSION['reset_user_id']) || time() - ($_SESSION['reset_verified_at'] ?? 0) > 600) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_verified_at']);
    header('Location: forgot.php');
    exit;
}

$pageTitle = 'Set a new password';
$bodyClass = 'login-page';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $errors = new_password_errors($password, $_POST['confirm'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['reset_user_id']]);
    $user = $stmt->fetch();

    if (!isset($errors['password']) && password_verify($password, $user['password_hash'])) {
        $errors['password'] = 'Choose a password different from your current one.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $_SESSION['reset_user_id']]);

        unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_verified_at']);
        header('Location: login.php?reset=1');
        exit;
    }
}

$fields = [
    ['password', 'New password', 'At least ' . PASSWORD_MIN_LENGTH . ' characters.', 'mb-3', true],
    ['confirm', 'Confirm new password', null, 'mb-4', false],
];

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

      <h1 class="h5 mb-1">Set a new password</h1>
      <p class="small text-body-secondary mb-4">
        Resetting the password for: <span class="fw-semibold"><?= htmlspecialchars($_SESSION['reset_username'] ?? '') ?></span>
      </p>

      <form action="reset.php" method="post" novalidate>
        <?php foreach ($fields as [$name, $label, $hint, $spacing, $autofocus]): ?>
          <?php $error = $errors[$name] ?? null; ?>
          <div class="<?= $spacing ?>">
            <label for="<?= $name ?>" class="form-label fw-semibold small"><?= $label ?></label>
            <input type="password" class="form-control <?= $error ? 'is-invalid' : '' ?>" id="<?= $name ?>" name="<?= $name ?>"
                   autocomplete="new-password" <?= $autofocus ? 'autofocus' : '' ?>
                   <?= $error || $hint ? 'aria-describedby="' . $name . '-' . ($error ? 'error' : 'hint') . '"' : '' ?>>
            <?php if ($error): ?>
              <div id="<?= $name ?>-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php elseif ($hint): ?>
              <div id="<?= $name ?>-hint" class="form-text"><?= htmlspecialchars($hint) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary w-100">Update password</button>
      </form>
      <p class="text-center small mt-3 mb-0"><a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
