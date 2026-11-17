
<?php
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/account.php';

// Only accept form submissions.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create_inflow.php');
    exit;
}


 // MER-38: Verify the CSRF token before processing the form.
 if (!csrf_valid($_POST['csrf_token'] ?? null)) {
     http_response_code(403);
     exit('Invalid or missing security token.');
 }


// Get submitted values.
$source = trim($_POST['source'] ?? '');
$amountInput = trim((string) ($_POST['amount'] ?? ''));
$date = trim($_POST['expected_date'] ?? '');
$fundInput = trim((string) ($_POST['fund_id'] ?? ''));
$description = trim($_POST['description'] ?? '');

// Validate required fields.
if ($source === '' ||
    $amountInput === '' ||
    $date === '' ||
    $fundInput === '') {
    http_response_code(400);
    exit('All required fields must be completed.');
}


// MER-38: Validate a positive monetary amount.
// Allow whole dollars or up to two decimal places.
if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountInput) ||
    !is_finite((float) $amountInput) ||
    (float) $amountInput <= 0) {
    http_response_code(400);
    exit('Amount must be positive with no more than two decimal places.');
}


// Validate the date format and actual calendar date.
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    http_response_code(400);
    exit('Please enter a valid expected date.');
}

// Validate the selected fund ID.
$fundId = filter_var($fundInput, FILTER_VALIDATE_INT);

if ($fundId === false || $fundId <= 0) {
    http_response_code(400);
    exit('Invalid fund selected.');
}

// Confirm the fund exists and is active.
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM funds WHERE id = ? AND is_active = 1'
);
$stmt->execute([$fundId]);

if ((int) $stmt->fetchColumn() !== 1) {
    http_response_code(400);
    exit('The selected fund is not available.');
}

// Insert the expected inflow.
$stmt = $pdo->prepare(
    'INSERT INTO expected_inflows
     (source, amount, expected_date, fund_id, description, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);

$stmt->execute([
    $source,
    (float) $amountInput,
    $date,
    $fundId,
    $description,
    'Expected'
]);

// Return to the inflows page.
header('Location: inflows.php?created=1');
exit;
