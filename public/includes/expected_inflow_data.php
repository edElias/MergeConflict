
<?php

// MER-38: Total expected inflows within a date range.
// Only scheduled inflows with Expected status are included.
function getExpectedInflowsTotal(
    PDO $pdo,
    string $startDate,
    string $endDate
): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(e.amount), 0)
         FROM expected_inflows e
         JOIN funds f ON f.id = e.fund_id
         WHERE e.status = 'Expected'
           AND f.is_active = 1
           AND e.expected_date >= ?
           AND e.expected_date <= ?"
    );

    $stmt->execute([$startDate, $endDate]);

    return (float) $stmt->fetchColumn();
}
