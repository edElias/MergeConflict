
<?php
// MER-25: Retrieve organization-wide cash summary data for DASH-01.

function getCashSummary(PDO $pdo): array
{
    $sql = '
        SELECT
            COALESCE(SUM(total_cash), 0) AS total_cash,
            COALESCE(SUM(restricted_cash), 0) AS restricted_cash,
            COALESCE(SUM(total_cash - restricted_cash), 0) AS unrestricted_cash,
            COALESCE(SUM(committed_funds), 0) AS committed_funds,
            COALESCE(SUM(total_cash - committed_funds), 0) AS uncommitted_balance
        FROM funds
    ';

    $stmt = $pdo->query($sql);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
