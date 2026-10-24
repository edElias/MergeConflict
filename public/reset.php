<?php
// AUTH-05: Password reset, step 2 — choose a new password after the code was verified.
// MER-26: Matches the Figma "Set new password" and "fields invalid" screens.
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/form.php';

// Verification from forgot.php is only good for 10 minutes.
if (empty($_SESSION['reset_user_id']) || time() - ($_SESSION['reset_verified_at'] ?? 0) > 600) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_verified_at']);
    header('Location: forgot.php');
    exit;
}

$pageTitle = 'Set a new password';
$bodyClass = 'login-page';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid($_POST['csrf_token'] ?? null)) {
    $errors['password'] = 'Your session expired. Please try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php form_field('password', 'New password', '', $errors, ['type' => 'password', 'autocomplete' => 'new-password',
            'autofocus' => true, 'hint' => 'At least ' . PASSWORD_MIN_LENGTH . ' characters.']); ?>
        <?php form_field('confirm', 'Confirm new password', '', $errors,
            ['type' => 'password', 'autocomplete' => 'new-password', 'class' => 'mb-4']); ?>
        <button type="submit" class="btn btn-primary w-100">Update password</button>
      </form>
      <p class="text-center small mt-3 mb-0"><a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
