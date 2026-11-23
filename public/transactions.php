<?php
require __DIR__ . '/includes/auth.php';
// MER-16: Page shell from the Figma design. Export is not connected yet.
// TRANS-01 (MER-53): Posted transactions, newest first, including archived funds' (labeled).
// Values are read-only: no page edits them, and the database refuses changes (schema.php).
// TRANS-07: Transactions can be added and are listed here, newest first.
// TRANS-02 (MER-54): Search and filter by fund, type, and date range. The filters are a
// GET form, so they combine, show in the URL, and "Clear filters" returns the full history.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/format.php';
require_once __DIR__ . '/includes/transaction_data.php';

$funds = getTransactionFilterFunds($pdo);
[$filters, $values, $filterErrors] = transaction_filters($_GET, $funds);
$transactions = getTransactions($pdo, $filters);
$filtering = $filters !== [];
$total = $filtering ? count(getTransactions($pdo)) : count($transactions);

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

<form action="transactions.php" method="get" class="d-flex flex-wrap align-items-end gap-2 mb-2" role="search"
      aria-label="Filter transactions">
  <div class="input-group fund-search">
    <span class="input-group-text bg-body"><i class="bi bi-search" aria-hidden="true"></i></span>
    <input type="search" class="form-control" name="q" value="<?= htmlspecialchars($values['q']) ?>"
           placeholder="Search transactions" aria-label="Search description or category">
  </div>
  <select class="form-select w-auto" name="fund" aria-label="Filter by fund">
    <option value="">Fund: All</option>
    <?php foreach ($funds as $fund): ?>
      <option value="<?= (int) $fund['id'] ?>" <?= $values['fund'] === (string) $fund['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($fund['name']) ?><?= (int) $fund['is_active'] ? '' : ' (archived)' ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select class="form-select w-auto" name="type" aria-label="Filter by type">
    <option value="">Type: All</option>
    <?php foreach (TRANSACTION_TYPES as $key => $label): ?>
      <option value="<?= $key ?>" <?= $values['type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="input-group w-auto">
    <label class="input-group-text bg-body" for="filter-from">From</label>
    <input type="date" class="form-control <?= isset($filterErrors['from']) ? 'is-invalid' : '' ?>" id="filter-from"
           name="from" value="<?= htmlspecialchars($values['from']) ?>">
  </div>
  <div class="input-group w-auto">
    <label class="input-group-text bg-body" for="filter-to">To</label>
    <input type="date" class="form-control <?= isset($filterErrors['to']) ? 'is-invalid' : '' ?>" id="filter-to"
           name="to" value="<?= htmlspecialchars($values['to']) ?>">
  </div>
  <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Apply</button>
  <?php if ($filtering || $filterErrors || array_filter($values) !== []): ?>
    <a href="transactions.php" class="btn btn-link px-1">Clear filters</a>
  <?php endif; ?>
</form>

<?php if ($filterErrors): ?>
  <div class="alert alert-warning py-2 small" role="alert">
    <?php foreach ($filterErrors as $message): ?>
      <div><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= htmlspecialchars($message) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<p class="small text-body-secondary mb-2" role="status">
  <?= $filtering
      ? 'Showing ' . count($transactions) . ' of ' . $total . ' ' . ($total === 1 ? 'transaction' : 'transactions')
      : 'Showing all ' . $total . ' ' . ($total === 1 ? 'transaction' : 'transactions') ?>
</p>

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
            <td>
              <?= htmlspecialchars($transaction['fund_name']) ?>
              <?php if (!(int) $transaction['fund_is_active']): ?>
                <span class="badge text-bg-light border ms-1">Archived</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($transaction['category'] !== '' ? $transaction['category'] : '—') ?></td>
            <td><?= htmlspecialchars($transaction['type']) ?></td>
            <td class="text-end text-nowrap"><?= htmlspecialchars(format_currency((float) $transaction['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
          <tr>
            <td colspan="6" class="text-center text-body-secondary py-5">
              <?php if ($filtering): ?>
                No transactions match these filters. <a href="transactions.php">Clear filters</a>
              <?php else: ?>
                No transactions yet.
              <?php endif; ?>
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
