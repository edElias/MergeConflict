<?php
require __DIR__ . '/includes/auth.php';
// MER-29: Fund details page opened from a fund summary on the Funds page.
// MER-34 (FUND-01): Expected inflows/outflows and the fund's supporting records.
$displayName = $_SESSION['display_name'];
$activePage = 'funds';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/fund_archive_data.php';
require_once __DIR__ . '/includes/fund_info_data.php';
require_once __DIR__ . '/includes/format.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$fund = $id ? getFundDetails($pdo, $id) : null;

if ($fund === null) {
    http_response_code(404);
}

$pageTitle = $fund['name'] ?? 'Fund not found';
$archived = $fund !== null && !(int) $fund['is_active']; // FUND-06: archived funds are read-only

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <?php if ($archived): ?>
      <li class="breadcrumb-item"><a href="funds_archived.php">Archived funds</a></li>
    <?php endif; ?>
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
      ['Uncommitted balance', format_currency($current - $committed), null],
  ];
  if (!$archived) { // Archived funds aren't part of the forecast.
      $totals = getFundPlanningTotals($pdo, (int) $fund['id']);
      $window = 'Next ' . PLANNING_WINDOW_DAYS . ' days';
      $values[] = ['Expected inflows', format_currency($totals['expected_inflows']), $window];
      $values[] = ['Expected outflows', format_currency($totals['expected_outflows']), $window . ' of commitments'];
      $values[] = ['Projected 30-day balance',
                   $fund['projected_30_day'] === null ? '—' : format_currency((float) $fund['projected_30_day']),
                   'Placeholder estimate until the cash-flow forecast is available.'];
  }
  ?>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0"><?= htmlspecialchars($fund['name']) ?></h1>
    <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst($fund['fund_type'])) ?> fund</span>
    <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst($fund['restriction'])) ?></span>
    <?php if ($archived): ?>
      <span class="badge text-bg-secondary">Archived</span>
      <a href="fund_reactivate.php?id=<?= (int) $fund['id'] ?>" class="btn btn-sm btn-primary ms-auto">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivate fund
      </a>
    <?php else: ?>
      <a href="fund_edit.php?id=<?= (int) $fund['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-pencil me-1"></i>Edit fund
      </a>
    <?php endif; ?>
  </div>

  <?php if ($archived): ?>
    <div class="alert alert-warning d-flex align-items-center" role="status">
      <i class="bi bi-archive me-2"></i>
      <div>
        This fund was archived<?= $fund['archived_at'] ? ' on ' . htmlspecialchars(format_date($fund['archived_at'])) : '' ?>.
        Its information is preserved and read-only until it's reactivated.
      </div>
    </div>
  <?php endif; ?>

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
      <div class="col-12 col-sm-6 col-xl-4">
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

  <?php if ($archived): ?>
    <?php $records = getFundRecords($pdo, (int) $fund['id']); ?>
    <div class="card">
      <div class="card-header bg-body fw-semibold">Preserved records</div>
      <?php if (!$records): ?>
        <div class="card-body text-body-secondary small">This fund has no transactions, commitments, or expected inflows.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Description</th>
                <th scope="col">Type</th>
                <th scope="col" class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($records as $record): ?>
                <tr>
                  <td class="text-nowrap"><?= htmlspecialchars(format_date($record['date'])) ?></td>
                  <td><?= htmlspecialchars($record['description'] ?? '') ?></td>
                  <td><?= htmlspecialchars($record['type']) ?></td>
                  <td class="text-end text-nowrap"><?= htmlspecialchars(format_currency((float) $record['amount'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php
    // MER-34: The fund's supporting records, one tab per kind.
    $transactions = getFundTransactions($pdo, (int) $fund['id']);
    $commitments = getFundCommitments($pdo, (int) $fund['id']);
    $inflows = getFundExpectedInflows($pdo, (int) $fund['id']);

    $shown = count($transactions['rows']);
    $tabs = [
        'transactions' => [
            'label'   => 'Transactions',
            'columns' => ['Date', 'Description', 'Type'],
            'rows'    => array_map(fn($r) => [
                format_date($r['date']), $r['description'] ?? '', $r['amount'] < 0 ? 'Payment' : 'Receipt', $r['amount'],
            ], $transactions['rows']),
            'empty'   => 'This fund has no transactions yet.',
            'footer'  => $transactions['total'] > $shown
                ? "Showing the $shown most recent of {$transactions['total']}"
                : ($shown ? 'Showing all ' . $shown : ''),
            'link'    => ['View all transactions', 'transactions.php'],
        ],
        'commitments' => [
            'label'   => 'Commitments',
            'columns' => ['Commitment', 'Due date'],
            'rows'    => array_map(fn($r) => [$r['description'] ?? '', format_date($r['date']), $r['amount']], $commitments),
            'empty'   => 'This fund has no commitments.',
            'footer'  => $commitments ? 'Total: ' . format_currency(array_sum(array_column($commitments, 'amount'))) : '',
            'link'    => ['View in Inflows & Commitments', 'inflows.php'],
        ],
        'inflows' => [
            'label'   => 'Expected inflows',
            'columns' => ['Source', 'Expected date'],
            'rows'    => array_map(fn($r) => [$r['description'] ?? '', format_date($r['date']), $r['amount']], $inflows),
            'empty'   => 'This fund has no expected inflows.',
            'footer'  => $inflows ? 'Total: ' . format_currency(array_sum(array_column($inflows, 'amount'))) : '',
            'link'    => ['View in Inflows & Commitments', 'inflows.php'],
        ],
    ];
    $first = array_key_first($tabs);
    ?>
    <div class="card">
      <div class="card-header bg-body">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
          <?php foreach ($tabs as $key => $tab): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $key === $first ? 'active' : '' ?>" id="tab-<?= $key ?>" type="button" role="tab"
                      data-bs-toggle="tab" data-bs-target="#pane-<?= $key ?>" aria-controls="pane-<?= $key ?>"
                      aria-selected="<?= $key === $first ? 'true' : 'false' ?>"><?= htmlspecialchars($tab['label']) ?></button>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="tab-content">
        <?php foreach ($tabs as $key => $tab): ?>
          <div class="tab-pane <?= $key === $first ? 'show active' : '' ?>" id="pane-<?= $key ?>" role="tabpanel"
               aria-labelledby="tab-<?= $key ?>" tabindex="0">
            <?php if (!$tab['rows']): ?>
              <div class="card-body text-body-secondary small"><?= htmlspecialchars($tab['empty']) ?></div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead class="table-light small">
                    <tr>
                      <?php foreach ($tab['columns'] as $column): ?>
                        <th scope="col"><?= htmlspecialchars($column) ?></th>
                      <?php endforeach; ?>
                      <th scope="col" class="text-end">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($tab['rows'] as $row): ?>
                      <?php $amount = array_pop($row); ?>
                      <tr>
                        <?php foreach ($row as $cell): ?>
                          <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                        <td class="text-end text-nowrap"><?= htmlspecialchars(format_currency((float) $amount)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            <div class="card-footer bg-body d-flex flex-wrap justify-content-between gap-2 small">
              <span class="text-body-secondary"><?= htmlspecialchars($tab['footer']) ?></span>
              <a href="<?= $tab['link'][1] ?>"><?= htmlspecialchars($tab['link'][0]) ?><i class="bi bi-chevron-right ms-1"></i></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
