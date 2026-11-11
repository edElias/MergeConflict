<?php
require __DIR__ . '/includes/auth.php';
// PLAN-01: Record an upcoming commitment against an active fund.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/form.php';
require_once __DIR__ . '/includes/commitment_data.php';

$funds = getFundSummaries($pdo);
$form = commitment_input([]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = commitment_input($_POST);

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $errors = commitment_errors($pdo, $form);
    }

    if (!$errors) {
        $id = create_commitment($pdo, $form);
        header('Location: inflows.php?created=' . $id);
        exit;
    }
    if (!isset($errors['form'])) {
        $errors['form'] = 'Some details need fixing. Nothing was saved.';
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = 'Add commitment';
$activePage = 'planning';
$token = csrf_token();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="inflows.php">Inflows &amp; Commitments</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add commitment</li>
  </ol>
</nav>

<h1 class="h3 mb-1">Add commitment</h1>
<p class="text-body-secondary mb-4">
  Record money a fund is expected to pay out. It is saved as Planned and counted in the fund's committed total.
</p>

<?php if (isset($errors['form'])): ?>
  <div class="alert alert-danger d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($errors['form']) ?></div>
  </div>
<?php endif; ?>

<div class="row"><div class="col-12 col-lg-8 col-xl-6">
<div class="card">
  <div class="card-body">
    <form action="commitment_new.php" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $token ?>">

      <?php form_field('name', 'Commitment name', $form['name'], $errors, ['autofocus' => true]); ?>

      <?php $error = $errors['fund_id'] ?? null; ?>
      <div class="mb-3">
        <label for="f-fund_id" class="form-label fw-semibold small">Fund</label>
        <select id="f-fund_id" name="fund_id" class="form-select <?= $error ? 'is-invalid' : '' ?>"
                <?= $error ? 'aria-describedby="f-fund_id-error"' : '' ?>>
          <option value="">Choose a fund</option>
          <?php foreach ($funds as $fund): ?>
            <option value="<?= (int) $fund['id'] ?>" <?= $form['fund_id'] === (string) $fund['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fund['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($error): ?>
          <div id="f-fund_id-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-3">
        <?php form_field('amount', 'Amount ($)', $form['amount'], $errors, ['type' => 'number', 'class' => 'col-sm-6']); ?>
        <?php form_field('due_date', 'Due date', $form['due_date'], $errors, ['type' => 'date', 'class' => 'col-sm-6']); ?>
      </div>

      <?php $error = $errors['description'] ?? null; ?>
      <div class="mb-4">
        <label for="f-description" class="form-label fw-semibold small">Description <span class="fw-normal text-body-secondary">(optional)</span></label>
        <textarea id="f-description" name="description" rows="3" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                  aria-describedby="f-description-<?= $error ? 'error' : 'hint' ?>"><?= htmlspecialchars($form['description']) ?></textarea>
        <?php if ($error): ?>
          <div id="f-description-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
          <div id="f-description-hint" class="form-text">Up to <?= COMMITMENT_DESCRIPTION_MAX ?> characters.</div>
        <?php endif; ?>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save commitment</button>
        <a href="inflows.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div></div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
