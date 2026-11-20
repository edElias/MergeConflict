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
// TRANS-02: $filters (from transaction_filters()) narrows the list; filters combine.
function getTransactions(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];

    if (isset($filters['fund_id'])) {
        $where[] = 't.fund_id = :fund_id';
        $params['fund_id'] = $filters['fund_id'];
    }
    if (isset($filters['type'])) {
        $where[] = $filters['type'] === 'outflow' ? 't.amount < 0' : 't.amount >= 0';
    }
    if (isset($filters['from'])) {
        $where[] = 't.transaction_date >= :from';
        $params['from'] = $filters['from'];
    }
    if (isset($filters['to'])) {
        $where[] = 't.transaction_date <= :to';
        $params['to'] = $filters['to'];
    }
    if (isset($filters['q'])) {
        // Match the text anywhere in the description or category; % and _ are literal.
        $where[] = "(t.description LIKE :q ESCAPE '\\' OR t.category LIKE :q ESCAPE '\\')";
        $params['q'] = '%' . addcslashes($filters['q'], '%_\\') . '%';
    }

    $stmt = $pdo->prepare("
        SELECT t.id, t.transaction_date AS date, t.description, t.category, t.amount,
               CASE WHEN t.amount < 0 THEN 'Outflow' ELSE 'Inflow' END AS type,
               f.name AS fund_name
        FROM transactions t
        JOIN funds f ON f.id = t.fund_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY t.transaction_date DESC, t.id DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// TRANS-02: Funds offered in the fund filter: active funds first, then archived ones
// (their transactions stay in the history), each group by name.
function getTransactionFilterFunds(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, is_active FROM funds ORDER BY is_active DESC, name')
               ->fetchAll(PDO::FETCH_ASSOC);
}

// TRANS-02: Reads the filters from query-string values. Returns [$filters, $values, $errors]:
// $filters holds only valid, non-empty filters (for getTransactions); $values holds what to
// show back in the form; $errors explains anything that was left out.
function transaction_filters(array $get, array $funds): array
{
    $values = [];
    foreach (['q', 'fund', 'type', 'from', 'to'] as $key) {
        $values[$key] = is_string($get[$key] ?? null) ? trim($get[$key]) : '';
    }

    $filters = [];
    $errors = [];

    if ($values['q'] !== '') {
        $filters['q'] = $values['q'];
    }

    if ($values['fund'] !== '') {
        if (ctype_digit($values['fund']) && in_array((int) $values['fund'], array_map('intval', array_column($funds, 'id')), true)) {
            $filters['fund_id'] = (int) $values['fund'];
        } else {
            $errors['fund'] = 'That fund no longer exists, so all funds are shown.';
            $values['fund'] = '';
        }
    }

    if ($values['type'] !== '') {
        if (isset(TRANSACTION_TYPES[$values['type']])) {
            $filters['type'] = $values['type'];
        } else {
            $values['type'] = '';
        }
    }

    foreach (['from' => 'start', 'to' => 'end'] as $key => $word) {
        if ($values[$key] === '') {
            continue;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $values[$key]);
        if ($date && $date->format('Y-m-d') === $values[$key]) {
            $filters[$key] = $values[$key];
        } else {
            $errors[$key] = "Enter a valid $word date.";
        }
    }

    if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
        $errors['to'] = 'The end date is before the start date, so the date range was not applied.';
        unset($filters['from'], $filters['to']);
    }

    return [$filters, $values, $errors];
}
