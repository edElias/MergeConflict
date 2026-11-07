<?php
require __DIR__ . '/includes/auth.php';
// MER-18: Page shell from the Figma design. No data or actions are connected yet.
$displayName = $_SESSION['display_name'];
$pageTitle = 'Alerts';
$activePage = 'alerts';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<h1 class="h3 mb-1">Alerts</h1>
<p class="text-body-secondary mb-4">Rule-based warnings about funds that need attention.</p>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <button type="button" class="btn btn-sm rounded-pill btn-dark" disabled>Active</button>
  <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled>Resolved</button>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by severity" disabled><option>Severity: All</option></select>
  <select class="form-select form-select-sm w-auto" aria-label="Filter by fund" disabled><option>Fund: All</option></select>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th scope="col">Severity</th>
          <th scope="col">Alert</th>
          <th scope="col">Fund</th>
          <th scope="col">Date</th>
          <th scope="col"><span class="visually-hidden">Action</span></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="5" class="text-center text-body-secondary py-5">Alerts will appear here once this page is connected.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
