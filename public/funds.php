<?php
require __DIR__ . '/includes/auth.php';
// MER-29 (DASH-02): Fund summaries on the Funds page, laid out per the MER-27 Figma design.
$displayName = $_SESSION['display_name'];
$pageTitle = 'Funds';
$activePage = 'funds';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/format.php';

$type = $_GET['type'] ?? null;
if (!in_array($type, FUND_TYPES, true)) {
    $type = null;
}

$funds = getFundSummaries($pdo, $type);
$counts = countActiveFundsByType($pdo);

$filters = [
    ''         => ['All', array_sum($counts)],
    'standard' => ['Standard', $counts['standard']],
    'custom'   => ['Custom', $counts['custom']],
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<h1 class="h3 mb-1">Funds</h1>
<p class="text-body-secondary mb-4">Compare active funds. Select a fund to see its details.</p>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="input-group fund-search">
    <span class="input-group-text bg-body"><i class="bi bi-search" aria-hidden="true"></i></span>
    <input type="search" class="form-control" placeholder="Search funds" aria-label="Search funds" data-fund-search>
  </div>
  <nav class="d-flex flex-wrap gap-2" aria-label="Filter funds by type">
    <?php foreach ($filters as $key => [$label, $count]): ?>
      <?php $isActive = $key === ($type ?? ''); ?>
      <a href="funds.php<?= $key === '' ? '' : '?type=' . urlencode($key) ?>"
         class="btn btn-sm rounded-pill <?= $isActive ? 'btn-dark' : 'btn-outline-secondary' ?>"
         <?= $isActive ? 'aria-current="page"' : '' ?>>
        <?= htmlspecialchars($label) ?> (<?= $count ?>)
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="card">
  <div class="list-group list-group-flush">
    <div class="list-group-item bg-body-tertiary d-none d-md-block small fw-semibold text-body-secondary">
      <div class="row g-2">
        <div class="col-md">Fund</div>
        <div class="col-md-auto fund-num text-end text-nowrap">Current balance</div>
        <div class="col-md-auto fund-num text-end text-nowrap">Committed</div>
        <div class="col-md-auto fund-num text-end text-nowrap">Projected 30-day</div>
        <div class="col-md-auto fund-chevron"></div>
      </div>
    </div>

    <?php foreach ($funds as $fund): ?>
      <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="list-group-item list-group-item-action py-3 fund-row"
         data-fund-name="<?= htmlspecialchars(strtolower($fund['name'])) ?>">
        <div class="row g-2 align-items-center">
          <div class="col-12 col-md">
            <div class="fw-semibold"><?= htmlspecialchars($fund['name']) ?></div>
            <div class="small text-body-secondary"><?= htmlspecialchars(ucfirst($fund['fund_type'])) ?> fund</div>
          </div>
          <div class="col-12 col-md-auto fund-num d-flex d-md-block justify-content-between text-md-end">
            <span class="small text-body-secondary d-md-none">Current balance</span>
            <?= htmlspecialchars(format_currency((float) $fund['current_balance'])) ?>
          </div>
          <div class="col-12 col-md-auto fund-num d-flex d-md-block justify-content-between text-md-end">
            <span class="small text-body-secondary d-md-none">Committed</span>
            <?= htmlspecialchars(format_currency((float) $fund['committed'])) ?>
          </div>
          <div class="col-12 col-md-auto fund-num d-flex d-md-block justify-content-between text-md-end">
            <span class="small text-body-secondary d-md-none">Projected 30-day</span>
            <?= $fund['projected_30_day'] === null
                ? '<span class="text-body-secondary">—</span>'
                : htmlspecialchars(format_currency((float) $fund['projected_30_day'])) ?>
          </div>
          <div class="col-md-auto fund-chevron d-none d-md-block text-body-secondary">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
          </div>
        </div>
      </a>
    <?php endforeach; ?>

    <div class="list-group-item text-body-secondary text-center py-4 <?= $funds ? 'd-none' : '' ?>" data-fund-empty>
      No active funds match.
    </div>
  </div>
</div>

<p class="small text-body-secondary mt-3 mb-0">
  Projected 30-day balances are placeholder estimates until the cash-flow forecast is available.
</p>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
