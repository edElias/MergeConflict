<?php
// FUND-05: Archive a fund by clearing its active flag. Nothing is deleted: the
// fund row stays, and its transactions, commitments, and expected inflows are
// untouched, so the fund's own page and its history keep working.

// Clears the active flag. Running this on an already-archived fund changes nothing.
function archive_fund(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE funds SET is_active = 0 WHERE id = ?');
    $stmt->execute([$id]);
}
