<?php
require __DIR__ . '/includes/auth.php';
// MER-16: Page shell from the Figma design. Search, filters, and export are not connected yet.
// TRANS-07: Transactions can be added and are listed here, newest first.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/format.php';
require_once __DIR__ . '/includes/transaction_data.php';

$transactions = getTransactions($pdo);

// Name the transaction that was just posted, so the confirmation reads back.
$createdId = filter_input(INPUT_GET, 'created', FILTER_VALIDATE_INT);
$created = $createdId ? (array_column($transactions, null, 'id')[$createdId] ?? null) : null;

$displayName = $_SESSION['display_name'];
$pageTitle = 'Transactions';
$activePage = 'transactions';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
  <h1 class="h3 mb-0 me-auto">Transactions</h1>
  <button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-download me-1"></i>Export CSV</button>
  <a href="transaction_new.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add transaction</a>
</div>
<p class="text-body-secondary mb-4">All recorded money in and out across funds.</p>

<?php if ($created): ?>
  <div class="alert alert-success d-flex align-items-center" role="status">
    <i class="bi bi-check-circle me-2"></i>
    <div>
      <?= htmlspecialchars($created['type']) ?> of <?= htmlspecialchars(format_currency(abs((float) $created['amount']))) ?>
      posted to <?= htmlspecialchars($created['fund_name']) ?>.
    </div>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="input-group fund-search">
    <span class="input-group-text bg-body"><i class="bi bi-search" aria-hidden="true"></i></span>
    <input type="search" class="form-control" placeholder="Search transactions" aria-label="Search transactions" disabled>
  </div>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by fund" disabled><option>Fund: All</option></select>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by type" disabled><option>Type: All</option></select>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by date" disabled><option>Date: All</option></select>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th scope="col">Date</th>
          <th scope="col">Description</th>
          <th scope="col">Fund</th>
          <th scope="col">Category</th>
          <th scope="col">Type</th>
          <th scope="col" class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $transaction): ?>
          <tr>
            <td class="text-nowrap"><?= htmlspecialchars(format_date($transaction['date'])) ?></td>
            <td><?= htmlspecialchars($transaction['description'] ?? '') ?></td>
            <td><?= htmlspecialchars($transaction['fund_name']) ?></td>
            <td><?= htmlspecialchars($transaction['category'] !== '' ? $transaction['category'] : '—') ?></td>
            <td><?= htmlspecialchars($transaction['type']) ?></td>
            <td class="text-end text-nowrap"><?= htmlspecialchars(format_currency((float) $transaction['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
          <tr>
            <td colspan="6" class="text-center text-body-secondary py-5">No transactions yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
