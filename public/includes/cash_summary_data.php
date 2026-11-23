<?php
// MER-25: Retrieve organization-wide cash summary data for DASH-01.
// FIN-01: Total cash is the sum of the active funds' calculated balances.
// FIN-02 (MER-49): Restricted cash is the balance of funds classified restricted (their
// restriction designation); the rest is unrestricted, so the two always add up to total
// cash. The older hand-entered funds.restricted_cash value is no longer used. Committed
// amounts are tracked separately and never change total cash.

require_once __DIR__ . '/fund_balance.php';

function getCashSummary(PDO $pdo, ?string $asOf = null): array
{
    $sql = '
        SELECT
            COALESCE(SUM(balance), 0) AS total_cash,
            COALESCE(SUM(CASE WHEN restriction = \'restricted\' THEN balance ELSE 0 END), 0) AS restricted_cash,
            COALESCE(SUM(CASE WHEN restriction = \'restricted\' THEN 0 ELSE balance END), 0) AS unrestricted_cash,
            COALESCE(SUM(committed_funds), 0) AS committed_funds,
            COALESCE(SUM(balance - committed_funds), 0) AS uncommitted_balance
        FROM (
            SELECT f.restriction, f.committed_funds, ' . fund_balance_sql('f') . ' AS balance
            FROM funds f
            WHERE f.is_active = 1
        )
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['as_of' => balance_date($asOf)]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
