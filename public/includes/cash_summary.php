<?php
// MER-24: Organization-wide cash summary (DASH-01).
// Expects $cashSummary with keys total_cash, restricted_cash, unrestricted_cash,
// committed_funds, uncommitted_balance (dollar amounts, supplied by MER-25).
//
// Restricted/unrestricted and committed/uncommitted are two different splits of
// the same total cash, so they are shown as separate breakdowns of the total
// rather than as side-by-side cards that look like they should be added up.
require_once __DIR__ . '/format.php';

$total = (float) $cashSummary['total_cash'];

$breakdowns = [
    [
        'title' => 'By restriction',
        'id'    => 'restriction',
        'parts' => [
            ['Restricted cash', (float) $cashSummary['restricted_cash'], 'bg-warning'],
            ['Unrestricted cash', (float) $cashSummary['unrestricted_cash'], 'bg-success'],
        ],
    ],
    [
        'title' => 'By commitment',
        'id'    => 'commitment',
        'parts' => [
            ['Committed funds', (float) $cashSummary['committed_funds'], 'bg-primary'],
            ['Uncommitted balance', (float) $cashSummary['uncommitted_balance'], 'bg-info'],
        ],
    ],
];
?>
<section class="row g-3 mb-4" aria-labelledby="cash-summary-heading">
  <h2 id="cash-summary-heading" class="visually-hidden">Organization-wide cash summary</h2>

  <div class="col-12 col-xl-4">
    <div class="card h-100 cash-total-card">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start">
          <span class="text-body-secondary small">Total cash</span>
          <i class="bi bi-cash-stack text-primary fs-5" aria-hidden="true"></i>
        </div>
        <div class="display-6 fw-semibold mt-2"><?= htmlspecialchars(format_currency($total)) ?></div>
        <p class="text-body-secondary small mt-auto mb-0">
          All cash held across the organization. The breakdowns show two different ways
          this same total is divided.
        </p>
      </div>
    </div>
  </div>

  <?php foreach ($breakdowns as $breakdown): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-header bg-body d-flex justify-content-between align-items-center">
          <span id="breakdown-<?= $breakdown['id'] ?>"><?= htmlspecialchars($breakdown['title']) ?></span>
          <span class="text-body-secondary small">of total cash</span>
        </div>
        <div class="card-body">
          <div class="progress-stacked mb-3" role="img"
               aria-label="<?= htmlspecialchars($breakdown['title']) ?> share of total cash">
            <?php foreach ($breakdown['parts'] as [$label, $amount, $bar]): ?>
              <?php $pct = percent_of($amount, $total); ?>
              <div class="progress" style="width: <?= $pct ?>%">
                <div class="progress-bar <?= $bar ?>"></div>
              </div>
            <?php endforeach; ?>
          </div>
          <dl class="mb-0" aria-labelledby="breakdown-<?= $breakdown['id'] ?>">
            <?php foreach ($breakdown['parts'] as [$label, $amount, $bar]): ?>
              <div class="d-flex align-items-baseline py-1">
                <dt class="fw-normal me-auto">
                  <span class="legend-swatch <?= $bar ?> me-2" aria-hidden="true"></span><?= htmlspecialchars($label) ?>
                </dt>
                <dd class="mb-0 text-end">
                  <span class="fs-5 fw-semibold <?= $amount < 0 ? 'text-danger' : '' ?>"><?= htmlspecialchars(format_currency($amount)) ?></span>
                  <span class="text-body-secondary small d-block"><?= percent_of($amount, $total) ?>% of total</span>
                </dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</section>
