
<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/account.php';


$displayName = $_SESSION['display_name'];
$pageTitle = 'Create Expected Inflow';
$activePage = 'planning';

require_once __DIR__ . '/includes/db.php';

// Only active funds should receive new expected inflows.
$stmt = $pdo->query(
    'SELECT id, name FROM funds
     WHERE is_active = 1
     ORDER BY name'
);

$funds = $stmt->fetchAll(PDO::FETCH_ASSOC);
// MER-38: Generate a CSRF token for the inflow form.
$token = csrf_token();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>


<div class="mb-4">
  <h1 class="h3">Add Expected Inflow</h1>
  <p class="text-body-secondary">
    Record anticipated revenue for an active fund.
  </p>
</div>

<div class="card">
  <div class="card-body">
    <form action="save_inflow.php" method="POST">
        <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-3">
        <label for="source" class="form-label">Source / Name *</label>
        <input type="text" class="form-control"
               id="source" name="source" required>
      </div>

      <div class="mb-3">
        <label for="amount" class="form-label">Amount ($) *</label>
        <input type="number" class="form-control"
               id="amount" name="amount"
               min="0.01" step="0.01" required>
      </div>

      <div class="mb-3">
        <label for="expected_date" class="form-label">Expected Date *</label>
        <input type="date" class="form-control"
               id="expected_date" name="expected_date" required>
      </div>

      <div class="mb-3">
        <label for="fund_id" class="form-label">Associated Fund *</label>
        <select class="form-select" id="fund_id" name="fund_id" required>
          <option value="">Select a fund</option>
          <?php foreach ($funds as $fund): ?>
            <option value="<?= (int) $fund['id'] ?>">
              <?= htmlspecialchars($fund['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label">
          Description (optional)
        </label>
        <textarea class="form-control"
                  id="description" name="description"
                  rows="3"></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="inflows.php" class="btn btn-outline-secondary">
          Cancel
        </a>
        <button type="submit" class="btn btn-primary">
          Save Expected Inflow
        </button>
      </div>

    </form>
  </div>
</div>


<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
