<?php
require __DIR__ . '/includes/auth.php';
// FUND-05: Confirm, then archive a fund. Archiving only clears the fund's active
// flag, so its records stay stored and its detail page keeps working. Phase 1 has
// no permanent deletion; FUND-06 (fund_reactivate.php) is the way back.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/fund_summary_data.php';
require_once __DIR__ . '/includes/fund_archive_data.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$fund = $id ? getFundDetails($pdo, $id) : null;
$error = null;

if ($fund === null) {
    http_response_code(404);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. The fund was not archived.';
    } else {
        archive_fund($pdo, $id);
        header('Location: funds.php?archived=' . $id);
        exit;
    }
}

$displayName = $_SESSION['display_name'];
$pageTitle = $fund ? 'Archive ' . $fund['name'] : 'Fund not found';
$activePage = 'funds';
$token = csrf_token();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="funds.php">Funds</a></li>
    <?php if ($fund): ?>
      <li class="breadcrumb-item"><a href="fund.php?id=<?= (int) $fund['id'] ?>"><?= htmlspecialchars($fund['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= $fund ? 'Archive' : 'Fund not found' ?></li>
  </ol>
</nav>

<?php if ($fund === null): ?>
  <h1 class="h3">Fund not found</h1>
  <p class="text-body-secondary">This fund doesn't exist or the link is incorrect.</p>
  <a href="funds.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Funds</a>
<?php elseif (!(int) $fund['is_active']): ?>
  <h1 class="h3 mb-1">Already archived</h1>
  <p class="text-body-secondary mb-4">
    <?= htmlspecialchars($fund['name']) ?> is archived. Its records are still stored.
  </p>
  <a href="fund.php?id=<?= (int) $fund['id'] ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>View fund
  </a>
<?php else: ?>
  <div class="row"><div class="col-12 col-lg-8 col-xl-6">
    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-body">
        <h1 class="h4 mb-3">Archive <?= htmlspecialchars($fund['name']) ?>?</h1>
        <p class="text-body-secondary">
          This fund will no longer appear on the Funds page, and its balance will stop
          counting toward the dashboard totals.
        </p>
        <p class="text-body-secondary">
          Its transactions, commitments, and expected inflows stay stored, and the fund's
          own page keeps working. Nothing is deleted.
        </p>
        <form action="fund_archive.php?id=<?= (int) $fund['id'] ?>" method="post">
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger"><i class="bi bi-archive me-1"></i>Archive fund</button>
            <a href="fund_edit.php?id=<?= (int) $fund['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div></div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/nav_end.php';
require __DIR__ . '/includes/footer.php';
