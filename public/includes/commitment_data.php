<?php
// PLAN-01: Validate, save, and list commitments.
// A commitment's due date is stored in commitments.commitment_date. Saving one also
// adds its amount to the fund's committed_funds, which is the figure the dashboard,
// Funds page, and fund details already show.

require_once __DIR__ . '/fund_summary_data.php';
require_once __DIR__ . '/format.php';

const COMMITMENT_NAME_MAX = 100;
const COMMITMENT_DESCRIPTION_MAX = 500;

// Trimmed copy of the fields from submitted form data.
function commitment_input(array $post): array
{
    return [
        'name'        => trim((string) ($post['name'] ?? '')),
        'amount'      => trim((string) ($post['amount'] ?? '')),
        'due_date'    => trim((string) ($post['due_date'] ?? '')),
        'fund_id'     => trim((string) ($post['fund_id'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
    ];
}

// Field errors for a new commitment, keyed by field name. Empty when valid.
function commitment_errors(PDO $pdo, array $input): array
{
    $errors = [];

    if ($input['name'] === '') {
        $errors['name'] = 'Enter a name for this commitment.';
    } elseif (mb_strlen($input['name']) > COMMITMENT_NAME_MAX) {
        $errors['name'] = 'Use ' . COMMITMENT_NAME_MAX . ' characters or fewer.';
    }

    $amountValid = preg_match('/^\d+(\.\d{1,2})?$/', $input['amount']) && (float) $input['amount'] > 0;
    if (!$amountValid) {
        $errors['amount'] = 'Enter an amount greater than 0, like 1250.00.';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $input['due_date']);
    if (!$date || $date->format('Y-m-d') !== $input['due_date']) {
        $errors['due_date'] = 'Choose a due date.';
    }

    $fund = ctype_digit($input['fund_id']) ? getFundDetails($pdo, (int) $input['fund_id']) : null;
    if ($fund === null || !(int) $fund['is_active']) {
        $errors['fund_id'] = 'Choose an active fund.';
    } elseif ($amountValid) {
        // funds.committed_funds can never exceed the fund's cash.
        $uncommitted = (float) $fund['current_balance'] - (float) $fund['committed'];
        if ((float) $input['amount'] > $uncommitted) {
            $errors['amount'] = $fund['name'] . ' has only ' . format_currency($uncommitted) . ' uncommitted.';
        }
    }

    if (mb_strlen($input['description']) > COMMITMENT_DESCRIPTION_MAX) {
        $errors['description'] = 'Use ' . COMMITMENT_DESCRIPTION_MAX . ' characters or fewer.';
    }

    return $errors;
}

// Saves already-validated values as a Planned commitment and adds the amount to the
// fund's committed total. Both changes happen together or not at all. Returns the new id.
function create_commitment(PDO $pdo, array $input): int
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO commitments (fund_id, name, amount, commitment_date, description, status)
             VALUES (?, ?, ?, ?, ?, 'Planned')"
        );
        $stmt->execute([
            (int) $input['fund_id'],
            $input['name'],
            (float) $input['amount'],
            $input['due_date'],
            $input['description'],
        ]);
        $id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('UPDATE funds SET committed_funds = committed_funds + ? WHERE id = ?');
        $stmt->execute([(float) $input['amount'], (int) $input['fund_id']]);

        if ($ownTransaction) {
            $pdo->commit();
        }
        return $id;
    } catch (Throwable $e) {
        if ($ownTransaction) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Commitments on active funds, earliest due date first.
function getCommitments(PDO $pdo): array
{
    return $pdo->query('
        SELECT c.id, c.name, c.amount, c.commitment_date AS due_date, c.status, f.name AS fund_name
        FROM commitments c
        JOIN funds f ON f.id = c.fund_id
        WHERE f.is_active = 1
        ORDER BY c.commitment_date, c.id
    ')->fetchAll(PDO::FETCH_ASSOC);
}

// Total of commitments on active funds that fall due from today through the next 30 days.
function getCommitmentsDueTotal(PDO $pdo): float
{
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(c.amount), 0)
        FROM commitments c
        JOIN funds f ON f.id = c.fund_id
        WHERE f.is_active = 1 AND c.commitment_date BETWEEN ? AND ?
    ');
    $stmt->execute([date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]);
    return (float) $stmt->fetchColumn();
}
