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


require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/expected_inflow_data.php';


$stmt = $pdo->query(
    "SELECT e.id, e.source, e.amount,
            e.expected_date, e.status,
            f.name AS fund_name
     FROM expected_inflows e
     JOIN funds f ON e.fund_id = f.id
     ORDER BY e.expected_date ASC, e.id ASC"
);

$inflows = $stmt->fetchAll(PDO::FETCH_ASSOC);



// MER-38: Calculate expected inflows over the next 30 days.
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+30 days'));

$expectedInflowsTotal = getExpectedInflowsTotal(
    $pdo,
    $startDate,
    $endDate
);




require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
  <h1 class="h3 mb-0 me-auto">Inflows &amp; Commitments</h1>

  <a href="commitment_new.php"
     class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
    Add commitment
  </a>

  <a href="create_inflow.php"
     class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
    Add expected inflow
  </a>
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

<div class="fs-4 fw-semibold mt-1">
  <?php if ($label === 'Expected inflows'): ?>
    $<?= htmlspecialchars(number_format($expectedInflowsTotal, 2)) ?>
  <?php else: ?>
    <?= htmlspecialchars($value) ?>
  <?php endif; ?>
</div>
          
// MER-38 / PLAN-01: Display expected inflow totals and commitment
// totals separately while preserving both features.
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

        <!-- PLAN-01 / MER-38: Display both commitments and expected inflows. -->

        <?php foreach ($commitments as $commitment): ?>
          <tr>
            <td>Commitment</td>
            <td><?= htmlspecialchars($commitment['name']) ?></td>
            <td><?= htmlspecialchars($commitment['fund_name']) ?></td>
            <td class="text-nowrap">
              <?= htmlspecialchars(format_date($commitment['due_date'])) ?>
            </td>
            <td>
              <span class="badge text-bg-secondary">
                <?= htmlspecialchars($commitment['status']) ?>
              </span>
            </td>
            <td class="text-end text-nowrap">
              <?= htmlspecialchars(format_currency((float) $commitment['amount'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php foreach ($inflows as $inflow): ?>
          <tr>
            <td>Expected inflow</td>
            <td><?= htmlspecialchars($inflow['source']) ?></td>
            <td><?= htmlspecialchars($inflow['fund_name']) ?></td>
            <td class="text-nowrap">
              <?= htmlspecialchars($inflow['expected_date']) ?>
            </td>
            <td>
              <span class="badge bg-primary-subtle text-primary">
                <?= htmlspecialchars($inflow['status']) ?>
              </span>
            </td>
            <td class="text-end text-nowrap">
              <?= htmlspecialchars(format_currency((float) $inflow['amount'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($commitments) && empty($inflows)): ?>
          <tr>
            <td colspan="6" class="text-center text-body-secondary py-5">
              No commitments or expected inflows have been recorded yet.
            </td>
          </tr>
        <?php endif; ?>

      </tbody>

    </table>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
