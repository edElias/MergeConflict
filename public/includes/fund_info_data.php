<?php
// FUND-01 (MER-34): Supporting totals and records for one fund's information page.
// Expected outflows are the fund's commitments. "Next N days" counts records dated
// from today through today + N days, so past-due items aren't counted.

const PLANNING_WINDOW_DAYS = 30;

// ['expected_inflows' => float, 'expected_outflows' => float] due in the window.
function getFundPlanningTotals(PDO $pdo, int $id, int $days = PLANNING_WINDOW_DAYS): array
{
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime("+$days days"));

    $stmt = $pdo->prepare('
        SELECT
            (SELECT COALESCE(SUM(amount), 0) FROM expected_inflows
             WHERE fund_id = :id AND expected_date BETWEEN :from AND :to) AS expected_inflows,
            (SELECT COALESCE(SUM(amount), 0) FROM commitments
             WHERE fund_id = :id AND commitment_date BETWEEN :from AND :to) AS expected_outflows
    ');
    $stmt->execute(['id' => $id, 'from' => $from, 'to' => $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return array_map('floatval', $row);
}

// The fund's most recent transactions, newest first, and how many it has in all.
function getFundTransactions(PDO $pdo, int $id, int $limit = 5): array
{
    $stmt = $pdo->prepare('
        SELECT transaction_date AS date, description, amount
        FROM transactions WHERE fund_id = ?
        ORDER BY transaction_date DESC, id DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE fund_id = ?');
    $count->execute([$id]);

    return ['rows' => $rows, 'total' => (int) $count->fetchColumn()];
}

// All of the fund's commitments, soonest first.
function getFundCommitments(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('
        SELECT commitment_date AS date, description, amount
        FROM commitments WHERE fund_id = ?
        ORDER BY commitment_date, id
    ');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// All of the fund's expected inflows, soonest first.
function getFundExpectedInflows(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('
        SELECT expected_date AS date, description, amount
        FROM expected_inflows WHERE fund_id = ?
        ORDER BY expected_date, id
    ');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
