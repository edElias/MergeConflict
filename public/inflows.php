<?php
require __DIR__ . '/includes/auth.php';
// MER-15: Page shell from the Figma design. Expected inflows and the filters are not connected yet.
// PLAN-01: Commitments can be added and are listed here.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/commitment_data.php';

$commitments = getCommitments($pdo);

// Name the commitment that was just saved, so the confirmation reads back.
$createdId = filter_input(INPUT_GET, 'created', FILTER_VALIDATE_INT);
$created = $createdId ? (array_column($commitments, null, 'id')[$createdId] ?? null) : null;

$displayName = $_SESSION['display_name'];
$pageTitle = 'Inflows & Commitments';
$activePage = 'planning';

$summaries = [
    'Commitments due'  => format_currency(getCommitmentsDueTotal($pdo)),
    'Expected inflows' => '—',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
  <h1 class="h3 mb-0 me-auto">Inflows &amp; Commitments</h1>
  <a href="commitment_new.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i>Add commitment</a>
  <button type="button" class="btn btn-sm btn-primary" disabled><i class="bi bi-plus-lg me-1"></i>Add expected inflow</button>
</div>
<p class="text-body-secondary mb-4">Upcoming money in and out across active funds.</p>

<?php if ($created): ?>
  <div class="alert alert-success d-flex align-items-center" role="status">
    <i class="bi bi-check-circle me-2"></i>
    <div><?= htmlspecialchars($created['name']) ?> was saved as a <?= htmlspecialchars($created['status']) ?> commitment on <?= htmlspecialchars($created['fund_name']) ?>.</div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($summaries as $label => $value): ?>
    <div class="col-12 col-sm-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-body-secondary small"><?= htmlspecialchars($label) ?></div>
          <div class="fs-4 fw-semibold mt-1"><?= htmlspecialchars($value) ?></div>
          <div class="text-body-secondary small mt-1">Next 30 days</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <button type="button" class="btn btn-sm rounded-pill btn-dark" disabled>All</button>
  <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled>Commitments</button>
  <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled>Expected inflows</button>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by fund" disabled><option>Fund: All</option></select>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by status" disabled><option>Status: All</option></select>
  <select class="form-select form-select-sm w-auto" aria-label="Sort" disabled><option>Sort: Date</option></select>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th scope="col">Type</th>
          <th scope="col">Name</th>
          <th scope="col">Fund</th>
          <th scope="col">Date</th>
          <th scope="col">Status</th>
          <th scope="col" class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($commitments as $commitment): ?>
          <tr>
            <td>Commitment</td>
            <td><?= htmlspecialchars($commitment['name']) ?></td>
            <td><?= htmlspecialchars($commitment['fund_name']) ?></td>
            <td class="text-nowrap"><?= htmlspecialchars(format_date($commitment['due_date'])) ?></td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars($commitment['status']) ?></span></td>
            <td class="text-end text-nowrap"><?= htmlspecialchars(format_currency((float) $commitment['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$commitments): ?>
          <tr>
            <td colspan="6" class="text-center text-body-secondary py-5">No commitments yet. Expected inflows will appear here once they are connected.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
