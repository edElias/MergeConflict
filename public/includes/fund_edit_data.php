<?php
// FUND-04: Validate and save edits to a fund's descriptive information.
// Only name, type, restriction designation, and description can change here;
// balances and the fund's transactions, commitments, and inflows are never touched.

require_once __DIR__ . '/fund_summary_data.php';

const FUND_RESTRICTIONS = ['unrestricted', 'restricted'];
const FUND_NAME_MAX = 100;
const FUND_DESCRIPTION_MAX = 500;

// Trimmed copy of the editable fields from submitted form data.
function fund_edit_input(array $post): array
{
    return [
        'name'        => trim((string) ($post['name'] ?? '')),
        'fund_type'   => (string) ($post['fund_type'] ?? ''),
        'restriction' => (string) ($post['restriction'] ?? ''),
        'description' => trim((string) ($post['description'] ?? '')),
    ];
}

// Field errors for the edited values, keyed by field name. Empty when valid.
function fund_edit_errors(PDO $pdo, int $id, array $input): array
{
    $errors = [];

    if ($input['name'] === '') {
        $errors['name'] = 'Enter a fund name.';
    } elseif (mb_strlen($input['name']) > FUND_NAME_MAX) {
        $errors['name'] = 'Use ' . FUND_NAME_MAX . ' characters or fewer.';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM funds WHERE lower(name) = lower(?) AND id != ?');
        $stmt->execute([$input['name'], $id]);
        if ($stmt->fetchColumn()) {
            $errors['name'] = 'Another fund already uses this name.';
        }
    }

    if (!in_array($input['fund_type'], FUND_TYPES, true)) {
        $errors['fund_type'] = 'Choose a fund type.';
    }
    if (!in_array($input['restriction'], FUND_RESTRICTIONS, true)) {
        $errors['restriction'] = 'Choose a restriction designation.';
    }
    if (mb_strlen($input['description']) > FUND_DESCRIPTION_MAX) {
        $errors['description'] = 'Use ' . FUND_DESCRIPTION_MAX . ' characters or fewer.';
    }

    return $errors;
}

// Saves already-validated values. Balance columns are deliberately not in this statement.
function update_fund_info(PDO $pdo, int $id, array $input): void
{
    $stmt = $pdo->prepare(
        'UPDATE funds SET name = ?, fund_type = ?, restriction = ?, description = ? WHERE id = ?'
    );
    $stmt->execute([$input['name'], $input['fund_type'], $input['restriction'], $input['description'], $id]);
}
