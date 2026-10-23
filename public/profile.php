<?php
require __DIR__ . '/includes/auth.php';
// MER-26: Profile & settings, laid out per the Figma "Profile & settings (MER-26)" frames.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';

$userId = (int) $_SESSION['user_id'];

function load_user(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT username, first_name, last_name, phone, role, avatar_file, password_hash FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$user = load_user($pdo, $userId);
$errors = [];      // field name => message
$form = $user;     // values shown in the profile form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // The upload was larger than PHP's post_max_size, so PHP dropped the whole form.
        $errors['photo'] = 'Photo must be 2 MB or smaller.';
    } elseif (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif ($action === 'profile') {
        $form['first_name'] = trim($_POST['first_name'] ?? '');
        $form['last_name'] = trim($_POST['last_name'] ?? '');
        $form['phone'] = trim($_POST['phone'] ?? '');
        $phone = format_phone($form['phone']);

        foreach (['first_name' => 'first', 'last_name' => 'last'] as $field => $word) {
            if ($form[$field] === '') {
                $errors[$field] = "Enter your $word name.";
            } elseif (strlen($form[$field]) > 50) {
                $errors[$field] = 'Use 50 characters or fewer.';
            }
        }
        if ($phone === null) {
            $errors['phone'] = 'Enter a 10-digit phone number.';
        }

        if (!$errors) {
            $displayName = $form['first_name'] . ' ' . $form['last_name'];
            $stmt = $pdo->prepare(
                'UPDATE users SET first_name = ?, last_name = ?, phone = ?, display_name = ? WHERE id = ?'
            );
            $stmt->execute([$form['first_name'], $form['last_name'], $phone, $displayName, $userId]);
            $_SESSION['display_name'] = $displayName;
            header('Location: profile.php?saved=1');
            exit;
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        $errors += new_password_errors($new, $_POST['confirm'] ?? '');
        if (!isset($errors['password']) && !isset($errors['current_password']) && $new === $current) {
            $errors['password'] = 'Choose a password different from your current one.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            header('Location: profile.php?saved=1');
            exit;
        }
    } elseif ($action === 'photo') {
        $upload = $_FILES['photo'] ?? null;
        $info = null;

        if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = 'Choose a photo to upload.';
        } elseif (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || $upload['size'] > AVATAR_MAX_BYTES) {
            $errors['photo'] = 'Photo must be 2 MB or smaller.';
        } elseif ($upload['error'] !== UPLOAD_ERR_OK
            || !($info = @getimagesize($upload['tmp_name']))
            || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $errors['photo'] = 'Upload a JPG or PNG image.';
        }

        if (!$errors) {
            $file = $userId . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
            if (!is_dir(AVATAR_DIR)) {
                mkdir(AVATAR_DIR, 0775, true);
            }
            if ($user['avatar_file'] && $user['avatar_file'] !== $file) {
                @unlink(AVATAR_DIR . '/' . basename($user['avatar_file']));
            }
            if (move_uploaded_file($upload['tmp_name'], AVATAR_DIR . '/' . $file)) {
                $pdo->prepare('UPDATE users SET avatar_file = ? WHERE id = ?')->execute([$file, $userId]);
                $_SESSION['avatar_version'] = filemtime(AVATAR_DIR . '/' . $file);
                header('Location: profile.php?saved=1');
                exit;
            }
            $errors['photo'] = 'The photo could not be saved. Please try again.';
        }
    } elseif ($action === 'remove_photo') {
        if ($user['avatar_file']) {
            @unlink(AVATAR_DIR . '/' . basename($user['avatar_file']));
        }
        $pdo->prepare('UPDATE users SET avatar_file = NULL WHERE id = ?')->execute([$userId]);
        unset($_SESSION['avatar_version']);
        header('Location: profile.php?saved=1');
        exit;
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = 'Profile & settings';
$activePage = 'profile';
$token = csrf_token();
sync_avatar_session(load_user($pdo, $userId)['avatar_file']);
$hasPhoto = !empty($_SESSION['avatar_version']);

// Renders a labeled input with an optional hint and inline error.
function field(string $name, string $label, string $value, array $errors, array $opts = []): void
{
    $id = 'f-' . $name;
    $error = $errors[$name] ?? null;
    $type = $opts['type'] ?? 'text';
    $readonly = !empty($opts['readonly']);
    $describedBy = $error ? "$id-error" : (isset($opts['hint']) ? "$id-hint" : null);
    ?>
    <div class="<?= $opts['class'] ?? 'mb-3' ?>">
      <label for="<?= $id ?>" class="form-label fw-semibold small"><?= htmlspecialchars($label) ?></label>
      <input type="<?= $type ?>" id="<?= $id ?>" <?= $readonly ? 'readonly' : 'name="' . $name . '"' ?>
             class="form-control <?= $readonly ? 'bg-body-secondary text-body-secondary' : '' ?> <?= $error ? 'is-invalid' : '' ?>"
             value="<?= htmlspecialchars($value) ?>"
             <?= isset($opts['autocomplete']) ? 'autocomplete="' . $opts['autocomplete'] . '"' : '' ?>
             <?= $describedBy ? 'aria-describedby="' . $describedBy . '"' : '' ?>>
      <?php if ($error): ?>
        <div id="<?= $id ?>-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php elseif (isset($opts['hint'])): ?>
        <div id="<?= $id ?>-hint" class="form-text"><?= htmlspecialchars($opts['hint']) ?></div>
      <?php endif; ?>
    </div>
    <?php
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<h1 class="h3 mb-1">Profile &amp; settings</h1>
<?php if (isset($_GET['saved']) && !$errors): ?>
  <div class="alert alert-success d-flex align-items-center mt-3" role="status">
    <i class="bi bi-check-circle me-2"></i><div>Changes saved.</div>
  </div>
<?php else: ?>
  <p class="text-body-secondary mb-4">Update your profile details and password.</p>
<?php endif; ?>
<?php if (isset($errors['form'])): ?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($errors['form']) ?></div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-body fw-semibold">Profile</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
          <?php if ($hasPhoto): ?>
            <img src="avatar.php?v=<?= (int) $_SESSION['avatar_version'] ?>" alt="Your profile photo"
                 class="rounded-circle object-fit-cover profile-photo">
          <?php else: ?>
            <i class="bi bi-person-circle text-body-secondary profile-photo-placeholder" role="img" aria-label="No profile photo"></i>
          <?php endif; ?>
          <div>
            <div class="fw-semibold small">Profile photo</div>
            <div class="small text-body-secondary mb-2">JPG or PNG, up to 2 MB.</div>
            <div class="d-flex align-items-center gap-3">
              <form action="profile.php" method="post" enctype="multipart/form-data" data-photo-form>
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="action" value="photo">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= AVATAR_MAX_BYTES ?>">
                <label class="btn btn-outline-secondary btn-sm mb-0">
                  Change photo
                  <input type="file" name="photo" accept="image/png,image/jpeg" class="visually-hidden" aria-label="Change photo" data-photo-input>
                </label>
                <noscript><button type="submit" class="btn btn-sm btn-primary ms-1">Upload</button></noscript>
              </form>
              <?php if ($hasPhoto): ?>
                <form action="profile.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?= $token ?>">
                  <input type="hidden" name="action" value="remove_photo">
                  <button type="submit" class="btn btn-link btn-sm p-0">Remove</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if (isset($errors['photo'])): ?>
              <div class="small text-danger mt-2" role="alert"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($errors['photo']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <form action="profile.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <input type="hidden" name="action" value="profile">
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <?php field('first_name', 'First name', $form['first_name'], $errors, ['class' => '', 'autocomplete' => 'given-name']); ?>
            </div>
            <div class="col-sm-6">
              <?php field('last_name', 'Last name', $form['last_name'], $errors, ['class' => '', 'autocomplete' => 'family-name']); ?>
            </div>
          </div>
          <?php field('phone', 'Phone number', $form['phone'], $errors,
              ['type' => 'tel', 'autocomplete' => 'tel', 'hint' => 'Format: (555) 123-4567']); ?>
          <?php field('username', 'Username', $user['username'], $errors,
              ['readonly' => true, 'hint' => 'Used to sign in. Contact an administrator to change it.']); ?>
          <?php field('role', 'Role', $user['role'], $errors, ['readonly' => true, 'class' => 'mb-4']); ?>
          <button type="submit" class="btn btn-primary w-100">Save changes</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-body fw-semibold">Change password</div>
      <div class="card-body">
        <p class="small text-body-secondary">Enter your current password, then choose a new one.</p>
        <form action="profile.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <input type="hidden" name="action" value="password">
          <?php field('current_password', 'Current password', '', $errors,
              ['type' => 'password', 'autocomplete' => 'current-password']); ?>
          <?php field('password', 'New password', '', $errors,
              ['type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least ' . PASSWORD_MIN_LENGTH . ' characters.']); ?>
          <?php field('confirm', 'Confirm new password', '', $errors,
              ['type' => 'password', 'autocomplete' => 'new-password', 'class' => 'mb-4']); ?>
          <button type="submit" class="btn btn-primary w-100">Update password</button>
        </form>
        <p class="text-center small mt-3 mb-0">
          <a href="forgot.php">Forgot your current password? Reset it</a>
        </p>
      </div>
    </div>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
