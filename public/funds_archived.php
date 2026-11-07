<?php
require __DIR__ . '/includes/auth.php';
// FUND-06: List of archived funds. Each row opens the fund's preserved information.
$displayName = $_SESSION['display_name'];
$pageTitle = 'Archived funds';
$activePage = 'funds';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/fund_archive_data.php';
require_once __DIR__ . '/includes/format.php';

$funds = getArchivedFunds($pdo);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <li class="breadcrumb-item active" aria-current="page">Archived funds</li>
  </ol>
</nav>

<h1 class="h3 mb-1">Archived funds</h1>
<p class="text-body-secondary mb-4">
  Archived funds are hidden from the Funds page and dashboard totals. Their records are kept.
</p>

<div class="card">
  <?php if (!$funds): ?>
    <div class="card-body text-center py-5">
      <div class="fw-semibold">No archived funds</div>
      <div class="small text-body-secondary">Funds you archive will appear here.</div>
    </div>
  <?php else: ?>
    <div class="list-group list-group-flush">
      <div class="list-group-item bg-body-tertiary d-none d-md-block small fw-semibold text-body-secondary">
        <div class="row g-2">
          <div class="col-md">Fund</div>
          <div class="col-md-2">Type</div>
          <div class="col-md-2">Status</div>
          <div class="col-md-2">Archived on</div>
          <div class="col-md-auto fund-chevron"></div>
        </div>
      </div>

      <?php foreach ($funds as $fund): ?>
        <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="list-group-item list-group-item-action py-3">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md fw-semibold"><?= htmlspecialchars($fund['name']) ?></div>
            <div class="col-12 col-md-2 d-flex d-md-block justify-content-between">
              <span class="small text-body-secondary d-md-none">Type</span>
              <?= htmlspecialchars(ucfirst($fund['fund_type'])) ?> fund
            </div>
            <div class="col-12 col-md-2 d-flex d-md-block justify-content-between">
              <span class="small text-body-secondary d-md-none">Status</span>
              <span><span class="badge text-bg-light border">Archived</span></span>
            </div>
            <div class="col-12 col-md-2 d-flex d-md-block justify-content-between">
              <span class="small text-body-secondary d-md-none">Archived on</span>
              <?= htmlspecialchars(format_date($fund['archived_at'])) ?>
            </div>
            <div class="col-md-auto fund-chevron d-none d-md-block text-body-secondary">
              <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
