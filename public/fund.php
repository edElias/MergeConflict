<?php
require __DIR__ . '/includes/auth.php';
// MER-29: Basic fund details page opened from a fund summary on the Funds page.
// Fuller details (transactions, commitments, forecast) come with the Funds & Fund Details epic (MER-14).
$displayName = $_SESSION['display_name'];
$activePage = 'funds';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/format.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$fund = $id ? getFundDetails($pdo, $id) : null;

if ($fund === null) {
    http_response_code(404);
}

$pageTitle = $fund['name'] ?? 'Fund not found';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageTitle) ?></li>
  </ol>
</nav>

<?php if ($fund === null): ?>
  <h1 class="h3">Fund not found</h1>
  <p class="text-body-secondary">This fund doesn't exist or the link is incorrect.</p>
  <a href="funds.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Funds</a>
<?php else: ?>
  <?php
  $current = (float) $fund['current_balance'];
  $committed = (float) $fund['committed'];
  $values = [
      ['Current balance', format_currency($current), null],
      ['Committed', format_currency($committed), null],
      ['Uncommitted', format_currency($current - $committed), null],
      ['Projected 30-day',
       $fund['projected_30_day'] === null ? '—' : format_currency((float) $fund['projected_30_day']),
       'Placeholder estimate until the cash-flow forecast is available.'],
  ];
  ?>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0"><?= htmlspecialchars($fund['name']) ?></h1>
    <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst($fund['fund_type'])) ?> fund</span>
    <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst($fund['restriction'])) ?></span>
    <?php if (!(int) $fund['is_active']): ?>
      <span class="badge text-bg-secondary">Inactive</span>
    <?php endif; ?>
    <a href="fund_edit.php?id=<?= (int) $fund['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto">
      <i class="bi bi-pencil me-1"></i>Edit fund
    </a>
  </div>

  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success d-flex align-items-center" role="status">
      <i class="bi bi-check-circle me-2"></i><div>Fund information saved. Balances and records were not changed.</div>
    </div>
  <?php endif; ?>

  <?php if ($fund['description'] !== ''): ?>
    <p class="text-body-secondary mb-4"><?= nl2br(htmlspecialchars($fund['description'])) ?></p>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <?php foreach ($values as [$label, $value, $note]): ?>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="text-body-secondary small"><?= htmlspecialchars($label) ?></div>
            <div class="fs-4 fw-semibold mt-1"><?= htmlspecialchars($value) ?></div>
            <?php if ($note): ?>
              <div class="text-body-secondary small mt-1"><?= htmlspecialchars($note) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-header bg-body">Fund details</div>
    <div class="card-body d-flex flex-column justify-content-center gap-2">
      <div class="chart-placeholder rounded"></div>
      <p class="text-body-secondary small text-center mb-0">Transactions, commitments, and forecast for this fund (later sprint)</p>
    </div>
  </div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
