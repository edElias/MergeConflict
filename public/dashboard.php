<?php
require __DIR__ . '/includes/auth.php';
$displayName = $_SESSION['display_name'];
// MER-7: Authenticated dashboard shell.
// MER-9: add the session check here (redirect to login.php?expired=1 when not signed in)
// and set $displayName from the session.
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// MER-24: Sprint 1 mock figures for the cash summary.
// MER-25 replaces this with values queried from SQLite (same keys).


//$cashSummary = [
//    'total_cash'          => 4250000.00,
//    'restricted_cash'     => 1600000.00,
//    'unrestricted_cash'   => 2650000.00,
//    'committed_funds'     => 1875000.00,
//    'uncommitted_balance' => 2375000.00,
//];


 // MER-25: Load the DASH-01 cash summary from SQLite.
 require_once __DIR__ . '/includes/db.php';
 require_once __DIR__ . '/includes/cash_summary_data.php';

 $cashSummary = getCashSummary($pdo);





// MER-29: Funds panel lists the same active funds as the Funds page; each opens its details.
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/format.php';
$funds = getFundSummaries($pdo);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h1 class="h3 mb-0">Organization dashboard</h1>
</div>

<?php require __DIR__ . '/includes/cash_summary.php'; ?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card h-100">
      <div class="card-header bg-body d-flex justify-content-between align-items-center">
        <span>Funds</span>
        <a href="funds.php" class="small">View all funds<i class="bi bi-chevron-right ms-1"></i></a>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($funds as $fund): ?>
          <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="bi bi-bank me-2 text-body-secondary"></i>
            <span class="me-auto"><?= htmlspecialchars($fund['name']) ?></span>
            <span><?= htmlspecialchars(format_currency((float) $fund['current_balance'])) ?></span>
            <i class="bi bi-chevron-right ms-2 text-body-secondary small"></i>
          </a>
        <?php endforeach; ?>
        <?php if (!$funds): ?>
          <div class="list-group-item text-body-secondary">No active funds.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header bg-body">30-day forecast</div>
      <div class="card-body d-flex flex-column justify-content-center gap-2">
        <div class="chart-placeholder rounded"></div>
        <p class="text-body-secondary small text-center mb-0">30-day forecast chart (later sprint)</p>
      </div>
    </div>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
