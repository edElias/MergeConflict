
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
        opening_balance,
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
        $description 
    ]);


    // Redirect after successful creation.
    header('Location: funds.php?fund_created=1');
    exit;

} catch (PDOException $e) {
    // FUND-03: Handle duplicate fund names.
    if (str_contains($e->getMessage(), 'UNIQUE constraint failed: funds.name')) {

        // Check whether the existing fund is active or archived.
        $stmt = $pdo->prepare(
            'SELECT is_active FROM funds WHERE name = ?'
        );
        $stmt->execute([$name]);

        $existingFund = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingFund && (int) $existingFund['is_active'] === 0) {
            http_response_code(409);
            exit(
                'Error: A fund with this name already exists but is archived. ' .
                'Please reactivate the existing fund or choose another name.'
            );
        }

        http_response_code(409);
        exit('Error: An active fund with this name already exists.');
    }

    // Handle unexpected database errors.
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to create fund. Please try again.');
}

