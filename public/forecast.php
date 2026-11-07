<?php
require __DIR__ . '/includes/auth.php';
// MER-17: Page shell from the Figma design. No data or actions are connected yet.
$displayName = $_SESSION['display_name'];
$pageTitle = 'Forecast';
$activePage = 'forecast';

$summaries = [
    ['Current cash', 'Active funds'],
    ['Known net change', 'Commitments and expected inflows'],
    ['ML estimated net', 'Estimate, not a scheduled amount'],
    ['Projected balance', 'Known + ML estimate'],
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<h1 class="h3 mb-1">Forecast</h1>
<p class="text-body-secondary mb-4">Projected organization-wide cash, with known amounts shown separately from ML estimates.</p>

<div class="d-flex flex-wrap gap-2 mb-3">
  <button type="button" class="btn btn-sm rounded-pill btn-dark" disabled>30 days</button>
  <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled>90 days</button>
  <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled>120 days</button>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($summaries as [$label, $note]): ?>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small"><?= htmlspecialchars($label) ?></div>
          <div class="fs-4 fw-semibold mt-1">—</div>
          <div class="text-body-secondary small mt-1"><?= htmlspecialchars($note) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card mb-4">
  <div class="card-body d-flex flex-column justify-content-center gap-2">
    <div class="chart-placeholder rounded"></div>
    <p class="text-body-secondary small text-center mb-0">The forecast chart will appear here once this page is connected.</p>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th scope="col">Week ending</th>
          <th scope="col" class="text-end">Known inflows</th>
          <th scope="col" class="text-end">Known outflows</th>
          <th scope="col" class="text-end">ML estimate</th>
          <th scope="col" class="text-end">Balance, known only</th>
          <th scope="col" class="text-end">Balance, with ML</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="6" class="text-center text-body-secondary py-5">Weekly projections will appear here once this page is connected.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<p class="small text-body-secondary mt-3 mb-0">
  Known amounts come from commitments and expected inflows. The ML estimate covers routine activity that isn't
  scheduled, is organization-wide only, and is not a guarantee.
</p>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
