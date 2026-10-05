<?php
// Authenticated app shell: top bar + sidebar navigation.
// Set $activePage to one of the keys below to highlight the current item.
$activePage = $activePage ?? 'dashboard';
$displayName = $displayName ?? 'Signed-in user';

$navItems = [
    'dashboard'    => ['Dashboard', 'bi-speedometer2', 'dashboard.php'],
    'funds'        => ['Funds', 'bi-bank', '#'],
    'transactions' => ['Transactions', 'bi-arrow-left-right', '#'],
    'planning'     => ['Inflows & Commitments', 'bi-calendar-check', '#'],
    'alerts'       => ['Alerts', 'bi-bell', '#'],
    'forecast'     => ['Forecast', 'bi-graph-up', '#'],
];
?>
<nav class="navbar navbar-dark bg-primary sticky-top px-3">
  <button class="btn btn-outline-light d-lg-none me-2" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open navigation">
    <i class="bi bi-list"></i>
  </button>
  <a class="navbar-brand fw-semibold me-auto" href="dashboard.php">
    <i class="bi bi-building-check me-1"></i>CivicFunds
  </a>
  <div class="dropdown">
    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="User menu">
      <i class="bi bi-person-circle"></i><span class="d-none d-sm-inline ms-1"><?= htmlspecialchars($displayName) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Profile &amp; settings</a></li>
      <li><hr class="dropdown-divider"></li>
      <!-- MER-9: logout.php ends the session and redirects to login.php?logged_out=1 -->
      <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
    </ul>
  </div>
</nav>

<div class="app-layout">
  <aside class="offcanvas-lg offcanvas-start app-sidebar border-end" tabindex="-1" id="sidebar" aria-label="Main navigation">
    <div class="offcanvas-header d-lg-none">
      <h2 class="offcanvas-title h5">Menu</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
      <ul class="nav nav-pills flex-column p-3 gap-1 w-100">
        <?php foreach ($navItems as $key => [$label, $icon, $href]): ?>
          <li class="nav-item">
            <a class="nav-link <?= $key === $activePage ? 'active' : 'link-body-emphasis' ?>"
               href="<?= htmlspecialchars($href) ?>"
               <?= $key === $activePage ? 'aria-current="page"' : '' ?>>
              <i class="bi <?= $icon ?> me-2"></i><?= htmlspecialchars($label) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>
  <main class="app-main p-4">
<script src="assets/js/idle.js"></script>