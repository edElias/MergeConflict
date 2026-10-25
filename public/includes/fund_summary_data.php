<?php
// MER-29: Retrieve per-fund summaries for DASH-02.
// projected_30_day comes from fund_projections and is NULL when a fund has no
// projection yet. Until the ML forecast (MER-17) lands, those rows are dummy data.

const FUND_TYPES = ['standard', 'custom'];

// Active funds, standard funds first, then by name. Pass 'standard' or 'custom'
// to limit the list to one type.
function getFundSummaries(PDO $pdo, ?string $type = null): array
{
    $sql = '
        SELECT
            f.id,
            f.name,
            f.fund_type,
            f.total_cash      AS current_balance,
            f.committed_funds AS committed,
            p.projected_30_day,
            p.source          AS projection_source
        FROM funds f
        LEFT JOIN fund_projections p ON p.fund_id = f.id
        WHERE f.is_active = 1
    ';
    $params = [];

    if ($type !== null && in_array($type, FUND_TYPES, true)) {
        $sql .= ' AND f.fund_type = ?';
        $params[] = $type;
    }

    $sql .= " ORDER BY f.fund_type = 'custom', f.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Number of active funds of each type, e.g. ['standard' => 4, 'custom' => 1].
function countActiveFundsByType(PDO $pdo): array
{
    $counts = array_fill_keys(FUND_TYPES, 0);
    $rows = $pdo->query(
        'SELECT fund_type, COUNT(*) AS n FROM funds WHERE is_active = 1 GROUP BY fund_type'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $counts[$row['fund_type']] = (int) $row['n'];
    }

    return $counts;
}

// One fund with its balances and projection, or null if the id does not exist.
function getFundDetails(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT
            f.id,
            f.name,
            f.fund_type,
            f.is_active,
            f.restriction,
            f.description,
            f.total_cash      AS current_balance,
            f.restricted_cash,
            f.committed_funds AS committed,
            p.projected_30_day,
            p.source          AS projection_source
        FROM funds f
        LEFT JOIN fund_projections p ON p.fund_id = f.id
        WHERE f.id = ?
    ');
    $stmt->execute([$id]);
    $fund = $stmt->fetch(PDO::FETCH_ASSOC);

    return $fund === false ? null : $fund;
}
