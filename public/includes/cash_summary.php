<?php
// MER-24: Organization-wide cash summary (DASH-01).
// Expects $cashSummary with keys total_cash, restricted_cash, unrestricted_cash,
// committed_funds, uncommitted_balance (dollar amounts, supplied by MER-25).
//
// Restricted/unrestricted and committed/uncommitted are two different splits of
// the same total cash, so they are shown as separate breakdowns of the total
// rather than as side-by-side cards that look like they should be added up.
// Each breakdown is drawn as its own donut, so every chart adds up to total cash.
// Colors are consistent across both: "set-aside" cash vs. cash free to use.
require_once __DIR__ . '/format.php';

$total = (float) $cashSummary['total_cash'];

$breakdowns = [
    [
        'title' => 'By restriction',
        'id'    => 'restriction',
        'parts' => [
            ['Restricted cash', (float) $cashSummary['restricted_cash'], 'set-aside'],
            ['Unrestricted cash', (float) $cashSummary['unrestricted_cash'], 'free'],
        ],
    ],
    [
        'title' => 'By commitment',
        'id'    => 'commitment',
        'parts' => [
            ['Committed funds', (float) $cashSummary['committed_funds'], 'set-aside'],
            ['Uncommitted balance', (float) $cashSummary['uncommitted_balance'], 'free'],
        ],
    ],
];
?>
<section class="row g-3 mb-4" aria-labelledby="cash-summary-heading">
  <h2 id="cash-summary-heading" class="visually-hidden">Organization-wide cash summary</h2>

  <div class="col-12 d-flex flex-wrap align-items-baseline gap-2">
    <span class="text-body-secondary">Total cash</span>
    <span class="fs-3 fw-semibold"><?= htmlspecialchars(format_currency($total)) ?></span>
    <span class="text-body-secondary small">Each chart below splits this same total a different way.</span>
  </div>

  <?php foreach ($breakdowns as $breakdown): ?>
    <div class="col-12 col-md-6">
      <div class="card h-100">
        <div class="card-header bg-body d-flex justify-content-between align-items-center">
          <span id="breakdown-<?= $breakdown['id'] ?>"><?= htmlspecialchars($breakdown['title']) ?></span>
          <span class="text-body-secondary small">of total cash</span>
        </div>
        <div class="card-body">
          <?php
          // Donut geometry: a circle with circumference 100 so dash lengths are percentages.
          $offset = 0.0;
          $gap = 1.0;
          $shares = array_map(fn($part) => $total > 0 ? max(0, $part[1]) / $total * 100 : 0, $breakdown['parts']);
          $visible = count(array_filter($shares, fn($share) => $share > 0));
          [$leadLabel, $leadAmount] = $breakdown['parts'][0];
          ?>
          <div class="cash-donut mx-auto mb-3">
            <svg viewBox="0 0 42 42" role="img"
                 aria-label="<?= htmlspecialchars($breakdown['title']) ?>: <?php foreach ($breakdown['parts'] as $i => [$label, $amount]): ?><?= htmlspecialchars($label) ?> <?= round($shares[$i]) ?>%<?= $i < count($breakdown['parts']) - 1 ? ', ' : '' ?><?php endforeach; ?> of total cash">
              <circle class="cash-donut-track" cx="21" cy="21" r="15.9155"></circle>
              <?php foreach ($breakdown['parts'] as $i => [$label, $amount, $tone]): ?>
                <?php if ($shares[$i] <= 0) { continue; } ?>
                <?php $length = $visible > 1 ? max(0, $shares[$i] - $gap) : 100; ?>
                <circle class="cash-donut-segment cash-<?= $tone ?>" cx="21" cy="21" r="15.9155"
                        data-percent="<?= percent_of($amount, $total) ?>%"
                        data-name="<?= htmlspecialchars(strtolower(strtok($label, ' '))) ?>"
                        stroke-dasharray="<?= round($length, 3) ?> <?= round(100 - $length, 3) ?>"
                        stroke-dashoffset="<?= round(25 - $offset, 3) ?>">
                  <title><?= htmlspecialchars($label) ?>: <?= htmlspecialchars(format_currency($amount)) ?> (<?= percent_of($amount, $total) ?>% of total cash)</title>
                </circle>
                <?php $offset += $shares[$i]; ?>
              <?php endforeach; ?>
            </svg>
            <div class="cash-donut-center" aria-hidden="true">
              <span class="cash-donut-percent fs-4 fw-semibold"><?= percent_of($leadAmount, $total) ?>%</span>
              <span class="cash-donut-name text-body-secondary small"><?= htmlspecialchars(strtolower(strtok($leadLabel, ' '))) ?></span>
            </div>
          </div>
          <dl class="mb-0" aria-labelledby="breakdown-<?= $breakdown['id'] ?>">
            <?php foreach ($breakdown['parts'] as [$label, $amount, $tone]): ?>
              <div class="d-flex align-items-baseline py-1">
                <dt class="fw-normal me-auto">
                  <span class="legend-swatch cash-<?= $tone ?> me-2" aria-hidden="true"></span><?= htmlspecialchars($label) ?>
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
