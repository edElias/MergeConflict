<?php
require __DIR__ . '/includes/auth.php';
// MER-16: Page shell from the Figma design. No data or actions are connected yet.
$displayName = $_SESSION['display_name'];
$pageTitle = 'Transactions';
$activePage = 'transactions';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
  <h1 class="h3 mb-0 me-auto">Transactions</h1>
  <button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-download me-1"></i>Export CSV</button>
  <button type="button" class="btn btn-sm btn-primary" disabled><i class="bi bi-plus-lg me-1"></i>Add transaction</button>
</div>
<p class="text-body-secondary mb-4">All recorded money in and out across funds.</p>

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
          <th scope="col">Type</th>
          <th scope="col" class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="5" class="text-center text-body-secondary py-5">Transactions will appear here once this page is connected.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
