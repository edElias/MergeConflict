
<?php
// FUND-03: Validate and save custom funds.

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';


// Only accept form submissions.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Retrieve submitted values.
$name = trim($_POST['fund_name'] ?? '');
$type = trim($_POST['fund_type'] ?? '');
$restriction = trim($_POST['restriction_designation'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validate required fund name.
if ($name === '') {
    http_response_code(400);
    exit('Error: Fund name cannot be blank.');
}

// Validate fund type.
$validTypes = [
    'General',
    'Special Revenue',
    'Capital Projects',
    'Debt Service',
    'Other'
];

if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    exit('Error: Invalid fund type.');
}

// Validate restriction designation.
$validRestrictions = [
    'Restricted',
    'Unrestricted'
];

if (!in_array($restriction, $validRestrictions, true)) {
    http_response_code(400);
    exit('Error: Invalid restriction designation.');
}

try {
    // New funds start with $0 and are active.

    $stmt = $pdo->prepare(
        'INSERT INTO funds (
        name,
        fund_type,
        fund_category,
        restriction,
        description,
        total_cash,
        restricted_cash,
        committed_funds,
        is_active
        ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 1)' 
    );

    $stmt->execute([
        $name,
        'custom',
        $type,
        strtolower($restriction),
        $description !== '' ? $description : null
    ]);


    // Redirect after successful creation.
    header('Location: funds.php?fund_created=1');
    exit;

} catch (PDOException $e) {
    // SQLite UNIQUE constraint violation.
    if ($e->getCode() === '23000'
        || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
        http_response_code(409);
        exit('Error: A fund with this name already exists.');
    }

    // Do not expose internal database errors.
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to create fund. Please try again.');
}
