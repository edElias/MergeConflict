<?php
// MER-7: Authenticated dashboard shell.
// MER-9: add the session check here (redirect to login.php?expired=1 when not signed in)
// and set $displayName from the session.
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Placeholder layout only — real figures come from SQLite in later sprints.
$summaryCards = [
    ['Cash on hand', 'bi-cash-stack'],
    ['Committed', 'bi-journal-check'],
    ['Available', 'bi-wallet2'],
    ['Active alerts', 'bi-bell'],
];
$funds = ['General Fund', 'Fund 2', 'Fund 3', 'Fund 4'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h1 class="h3 mb-0">Organization dashboard</h1>
  <span class="badge text-bg-secondary">Mock data</span>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($summaryCards as [$label, $icon]): ?>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <span class="text-body-secondary small"><?= htmlspecialchars($label) ?></span>
            <i class="bi <?= $icon ?> text-primary fs-5"></i>
          </div>
          <div class="fs-3 fw-semibold mt-2">$ <span class="placeholder-value">—</span></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card h-100">
      <div class="card-header bg-body">Funds</div>
      <div class="list-group list-group-flush">
        <?php foreach ($funds as $fund): ?>
          <!-- Fund detail page comes in a later sprint. -->
          <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="bi bi-bank me-2 text-body-secondary"></i>
            <span class="me-auto"><?= htmlspecialchars($fund) ?></span>
            <span class="placeholder-value">$ —</span>
            <i class="bi bi-chevron-right ms-2 text-body-secondary small"></i>
          </a>
        <?php endforeach; ?>
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
