<?php
// Shared form markup for the profile and password reset pages.

// Renders a labeled input with an optional hint and inline error.
// $opts: type, readonly, hint, autocomplete, autofocus, class (wrapper class, default 'mb-3').
function form_field(string $name, string $label, string $value, array $errors, array $opts = []): void
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
             <?= !empty($opts['autofocus']) ? 'autofocus' : '' ?>
             <?= $describedBy ? 'aria-describedby="' . $describedBy . '"' : '' ?>>
      <?php if ($error): ?>
        <div id="<?= $id ?>-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php elseif (isset($opts['hint'])): ?>
        <div id="<?= $id ?>-hint" class="form-text"><?= htmlspecialchars($opts['hint']) ?></div>
      <?php endif; ?>
    </div>
    <?php
}
