<?php
// FUND-05: Archive a fund by clearing its active flag. Nothing is deleted: the
// fund row stays, and its transactions, commitments, and expected inflows are
// untouched, so the fund's own page and its history keep working.
// FUND-06: List archived funds and return them to active use.

// Clears the active flag and records the date. Running this on an already-archived
// fund changes nothing, so a resubmitted confirmation keeps the original date.
function archive_fund(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE funds SET is_active = 0, archived_at = ? WHERE id = ? AND is_active = 1');
    $stmt->execute([date('Y-m-d'), $id]);
}

// Sets the active flag and clears the archive date. Only the fund row changes, so
// its records and balances come back exactly as they were.
function reactivate_fund(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE funds SET is_active = 1, archived_at = NULL WHERE id = ?');
    $stmt->execute([$id]);
}

// Archived funds, most recently archived first. Funds archived before dates were
// recorded have no archived_at and sort last.
function getArchivedFunds(PDO $pdo): array
{
    return $pdo->query('
        SELECT id, name, fund_type, archived_at
        FROM funds
        WHERE is_active = 0
        ORDER BY archived_at IS NULL, archived_at DESC, name
    ')->fetchAll(PDO::FETCH_ASSOC);
}

// Everything stored against a fund, newest first: ['date', 'description', 'type', 'amount'].
function getFundRecords(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("
        SELECT transaction_date AS date, description,
               CASE WHEN amount < 0 THEN 'Payment' ELSE 'Receipt' END AS type, amount
        FROM transactions WHERE fund_id = :id
        UNION ALL
        SELECT commitment_date, description, 'Commitment', amount
        FROM commitments WHERE fund_id = :id
        UNION ALL
        SELECT expected_date, description, 'Expected inflow', amount
        FROM expected_inflows WHERE fund_id = :id
        ORDER BY date DESC
    ");
    $stmt->execute(['id' => $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
