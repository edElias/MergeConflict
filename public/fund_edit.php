<?php
require __DIR__ . '/includes/auth.php';
// FUND-04: Edit a fund's name, type, restriction designation, and description.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/form.php';
require_once __DIR__ . '/includes/fund_edit_data.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$fund = $id ? getFundDetails($pdo, $id) : null;
$errors = [];

if ($fund === null) {
    http_response_code(404);
} else {
    $form = [
        'name'        => $fund['name'],
        'fund_type'   => $fund['fund_type'],
        'restriction' => $fund['restriction'],
        'description' => $fund['description'],
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = fund_edit_input($_POST);

        if (!csrf_valid($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Your session expired. Please try again.';
        } else {
            $errors = fund_edit_errors($pdo, $id, $form);
        }

        if (!$errors) {
            update_fund_info($pdo, $id, $form);
            header('Location: fund.php?id=' . $id . '&updated=1');
            exit;
        }
        if (!isset($errors['form'])) {
            $errors['form'] = 'Some changes need fixing. Nothing was saved.';
        }
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = $fund ? 'Edit ' . $fund['name'] : 'Fund not found';
$activePage = 'funds';
$token = csrf_token();

$choices = [
    'fund_type'   => ['Fund type', ['standard' => 'Standard', 'custom' => 'Custom']],
    'restriction' => ['Restriction designation', ['unrestricted' => 'Unrestricted', 'restricted' => 'Restricted']],
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <?php if ($fund): ?>
      <li class="breadcrumb-item"><a href="fund.php?id=<?= (int) $fund['id'] ?>"><?= htmlspecialchars($fund['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= $fund ? 'Edit' : 'Fund not found' ?></li>
  </ol>
</nav>

<?php if ($fund === null): ?>
  <h1 class="h3">Fund not found</h1>
  <p class="text-body-secondary">This fund doesn't exist or the link is incorrect.</p>
  <a href="funds.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Funds</a>
<?php else: ?>
  <h1 class="h3 mb-1">Edit fund</h1>
  <p class="text-body-secondary mb-4">
    Update how this fund is described. Balances and financial records are not affected.
  </p>

  <?php if (isset($errors['form'])): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($errors['form']) ?></div>
    </div>
  <?php endif; ?>

  <div class="row"><div class="col-12 col-lg-8 col-xl-6">
  <div class="card">
    <div class="card-body">
      <form action="fund_edit.php?id=<?= (int) $fund['id'] ?>" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $token ?>">

        <?php form_field('name', 'Fund name', $form['name'], $errors, ['autofocus' => true]); ?>

        <div class="row g-3 mb-3">
          <?php foreach ($choices as $field => [$label, $options]): ?>
            <?php $error = $errors[$field] ?? null; ?>
            <div class="col-sm-6">
              <label for="f-<?= $field ?>" class="form-label fw-semibold small"><?= htmlspecialchars($label) ?></label>
              <select id="f-<?= $field ?>" name="<?= $field ?>" class="form-select <?= $error ? 'is-invalid' : '' ?>"
                      <?= $error ? 'aria-describedby="f-' . $field . '-error"' : '' ?>>
                <?php foreach ($options as $value => $text): ?>
                  <option value="<?= $value ?>" <?= $form[$field] === $value ? 'selected' : '' ?>><?= $text ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($error): ?>
                <div id="f-<?= $field ?>-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php $error = $errors['description'] ?? null; ?>
        <div class="mb-4">
          <label for="f-description" class="form-label fw-semibold small">Description <span class="fw-normal text-body-secondary">(optional)</span></label>
          <textarea id="f-description" name="description" rows="4" class="form-control <?= $error ? 'is-invalid' : '' ?>"
                    aria-describedby="f-description-<?= $error ? 'error' : 'hint' ?>"><?= htmlspecialchars($form['description']) ?></textarea>
          <?php if ($error): ?>
            <div id="f-description-error" class="invalid-feedback"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
          <?php else: ?>
            <div id="f-description-hint" class="form-text">Up to <?= FUND_DESCRIPTION_MAX ?> characters.</div>
          <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save changes</button>
          <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  </div></div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
