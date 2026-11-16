<?php
require __DIR__ . '/includes/auth.php';
// TRANS-07: Record a posted inflow or outflow for one active fund.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/form.php';
require_once __DIR__ . '/includes/transaction_data.php';

$funds = getFundSummaries($pdo);
$form = transaction_input(['date' => date('Y-m-d')]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = transaction_input($_POST);

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $errors = transaction_errors($pdo, $form);
    }

    if (!$errors) {
        $id = create_transaction($pdo, $form);
        header('Location: transactions.php?created=' . $id);
        exit;
    }
    if (!isset($errors['form'])) {
        $errors['form'] = 'Some details need fixing. Nothing was saved.';
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = 'Add transaction';
$activePage = 'transactions';
$token = csrf_token();

$choices = [
    'fund_id' => ['Fund', ['' => 'Choose a fund'] + array_column($funds, 'name', 'id')],
    'type'    => ['Type', ['' => 'Choose a type'] + TRANSACTION_TYPES],
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="transactions.php">Transactions</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add transaction</li>
  </ol>
</nav>

<h1 class="h3 mb-1">Add transaction</h1>
<p class="text-body-secondary mb-4">
  Record money that came into or went out of a fund. Saving posts it to the fund's balance,
  and posted transactions can't be edited or deleted.
</p>

<?php if (isset($errors['form'])): ?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($errors['form']) ?></div>
  </div>
<?php endif; ?>

<div class="row"><div class="col-12 col-lg-8 col-xl-6">
<div class="card">
  <div class="card-body">
    <form action="transaction_new.php" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $token ?>">

      <div class="row g-3 mb-3">
        <?php foreach ($choices as $field => [$label, $options]): ?>
          <?php $error = $errors[$field] ?? null; ?>
          <div class="col-sm-6">
            <label for="f-<?= $field ?>" class="form-label fw-semibold small"><?= htmlspecialchars($label) ?></label>
            <select id="f-<?= $field ?>" name="<?= $field ?>" class="form-select <?= $error ? 'is-invalid' : '' ?>"
                    <?= $error ? 'aria-describedby="f-' . $field . '-error"' : '' ?>>
              <?php foreach ($options as $value => $text): ?>
                <option value="<?= htmlspecialchars((string) $value) ?>" <?= $form[$field] === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($text) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($error): ?>
              <div id="f-<?= $field ?>-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3 mb-3">
        <?php form_field('date', 'Transaction date', $form['date'], $errors, ['type' => 'date', 'class' => 'col-sm-6']); ?>
        <?php form_field('amount', 'Amount ($)', $form['amount'], $errors, ['type' => 'number', 'class' => 'col-sm-6']); ?>
      </div>

      <?php form_field('category', 'Category', $form['category'], $errors, ['hint' => 'For example: Payroll, Sales tax, Utilities.']); ?>

      <?php $error = $errors['description'] ?? null; ?>
      <div class="mb-4">
        <label for="f-description" class="form-label fw-semibold small">Description</label>
        <textarea id="f-description" name="description" rows="3" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                  aria-describedby="f-description-<?= $error ? 'error' : 'hint' ?>"><?= htmlspecialchars($form['description']) ?></textarea>
        <?php if ($error): ?>
          <div id="f-description-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
          <div id="f-description-hint" class="form-text">Up to <?= TRANSACTION_DESCRIPTION_MAX ?> characters.</div>
        <?php endif; ?>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Post transaction</button>
        <a href="transactions.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div></div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
