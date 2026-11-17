<?php
// TRANS-07: Validate, post, and list transactions.
// The user enters a positive amount and a type. Outflows are stored as negative
// amounts, which is how fund_balance.php subtracts them. A saved transaction is
// posted: the database refuses to change or remove it (see schema.php).

require_once __DIR__ . '/fund_summary_data.php';

const TRANSACTION_TYPES = ['inflow' => 'Inflow', 'outflow' => 'Outflow'];
const TRANSACTION_CATEGORY_MAX = 100;
const TRANSACTION_DESCRIPTION_MAX = 500;

// Trimmed copy of the fields from submitted form data.
function transaction_input(array $post): array
{
    return [
        'fund_id'     => trim((string) ($post['fund_id'] ?? '')),
        'date'        => trim((string) ($post['date'] ?? '')),
        'type'        => trim((string) ($post['type'] ?? '')),
        'amount'      => trim((string) ($post['amount'] ?? '')),
        'category'    => trim((string) ($post['category'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
    ];
}

// Field errors for a new transaction, keyed by field name. Empty when valid.
function transaction_errors(PDO $pdo, array $input): array
{
    $errors = [];

    $fund = ctype_digit($input['fund_id']) ? getFundDetails($pdo, (int) $input['fund_id']) : null;
    if ($fund === null || !(int) $fund['is_active']) {
        $errors['fund_id'] = 'Choose an active fund.';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $input['date']);
    if (!$date || $date->format('Y-m-d') !== $input['date']) {
        $errors['date'] = 'Enter a valid transaction date.';
    }

    if (!isset(TRANSACTION_TYPES[$input['type']])) {
        $errors['type'] = 'Choose inflow or outflow.';
    }

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $input['amount']) || (float) $input['amount'] <= 0) {
        $errors['amount'] = 'Enter an amount greater than 0, like 1250.00.';
    }

    if ($input['category'] === '') {
        $errors['category'] = 'Enter a category.';
    } elseif (mb_strlen($input['category']) > TRANSACTION_CATEGORY_MAX) {
        $errors['category'] = 'Use ' . TRANSACTION_CATEGORY_MAX . ' characters or fewer.';
    }

    if ($input['description'] === '') {
        $errors['description'] = 'Enter a description.';
    } elseif (mb_strlen($input['description']) > TRANSACTION_DESCRIPTION_MAX) {
        $errors['description'] = 'Use ' . TRANSACTION_DESCRIPTION_MAX . ' characters or fewer.';
    }

    return $errors;
}

// Posts already-validated values and returns the new transaction's id.
function create_transaction(PDO $pdo, array $input): int
{
    $amount = (float) $input['amount'] * ($input['type'] === 'outflow' ? -1 : 1);
    $stmt = $pdo->prepare(
        'INSERT INTO transactions (fund_id, transaction_date, amount, category, description) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int) $input['fund_id'], $input['date'], $amount, $input['category'], $input['description']]);
    return (int) $pdo->lastInsertId();
}

// Every transaction, including those of archived funds, newest first.
function getTransactions(PDO $pdo): array
{
    return $pdo->query("
        SELECT t.id, t.transaction_date AS date, t.description, t.category, t.amount,
               CASE WHEN t.amount < 0 THEN 'Outflow' ELSE 'Inflow' END AS type,
               f.name AS fund_name
        FROM transactions t
        JOIN funds f ON f.id = t.fund_id
        ORDER BY t.transaction_date DESC, t.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
