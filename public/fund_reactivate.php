<?php
require __DIR__ . '/includes/auth.php';
// FUND-06: Confirm, then return an archived fund to active use. Only the fund's
// active flag changes, so its records and balances stay exactly as they were.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/fund_archive_data.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$fund = $id ? getFundDetails($pdo, $id) : null;
$error = null;

if ($fund === null) {
    http_response_code(404);
} elseif ((int) $fund['is_active']) {
    header('Location: fund.php?id=' . $id);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. The fund was not reactivated.';
    } else {
        reactivate_fund($pdo, $id);
        header('Location: funds.php?reactivated=' . $id);
        exit;
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = $fund ? 'Reactivate ' . $fund['name'] : 'Fund not found';
$activePage = 'funds';
$token = csrf_token();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <li class="breadcrumb-item"><a href="funds_archived.php">Archived funds</a></li>
    <?php if ($fund): ?>
      <li class="breadcrumb-item"><a href="fund.php?id=<?= (int) $fund['id'] ?>"><?= htmlspecialchars($fund['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= $fund ? 'Reactivate' : 'Fund not found' ?></li>
  </ol>
</nav>

<?php if ($fund === null): ?>
  <h1 class="h3">Fund not found</h1>
  <p class="text-body-secondary">This fund doesn't exist or the link is incorrect.</p>
  <a href="funds_archived.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Archived funds</a>
<?php else: ?>
  <div class="row"><div class="col-12 col-lg-8 col-xl-6">
    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-body">
        <h1 class="h4 mb-3">Reactivate <?= htmlspecialchars($fund['name']) ?>?</h1>
        <p class="text-body-secondary">
          This fund will return to the Funds page, and its balance will count toward the
          dashboard totals again.
        </p>
        <p class="text-body-secondary">
          All of its transactions, commitments, and expected inflows stay as they are.
          Nothing is changed or lost.
        </p>
        <form action="fund_reactivate.php?id=<?= (int) $fund['id'] ?>" method="post">
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reactivate fund</button>
            <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div></div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
