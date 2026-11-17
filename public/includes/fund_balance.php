<?php
// FIN-01: A fund's cash balance is calculated, not stored: its opening balance plus
// its posted transactions dated on or before the balance date. Transactions store
// inflows as positive amounts and outflows as negative ones, so their sum is inflows
// minus outflows. Every stored transaction is posted activity (TRANS-06, TRANS-07).
// Commitments and expected inflows are plans, not cash, so they are not part of it.

// SQL for the balance of the fund aliased as $alias. Bind :as_of to a YYYY-MM-DD date.
function fund_balance_sql(string $alias = 'f'): string
{
    return "($alias.opening_balance + COALESCE((
        SELECT SUM(t.amount) FROM transactions t
        WHERE t.fund_id = $alias.id AND t.transaction_date <= :as_of
    ), 0))";
}

// The balance date to use: the one given, or today.
function balance_date(?string $asOf): string
{
    return $asOf ?? date('Y-m-d');
}
