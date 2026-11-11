<?php
// Shared display formatting helpers.

// Formats a dollar amount as US currency, e.g. 1234.5 -> "$1,234.50", -20 -> "-$20.00".
function format_currency(float $amount): string
{
    $sign = $amount < 0 ? '-' : '';
    return $sign . '$' . number_format(abs($amount), 2);
}

// Formats a stored YYYY-MM-DD date as "Oct 2, 2026"; an em dash when there is no valid date.
function format_date(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    return $time === false ? '—' : date('M j, Y', $time);
}

// Share of a total as a whole percentage (0 when the total is not positive).
function percent_of(float $part, float $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int) round(max(0, min(100, $part / $total * 100)));
}
