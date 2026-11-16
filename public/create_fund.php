
<?php
// FUND-03: Custom fund creation form.

// Verify that the user is authenticated first and then connect to SQLite database
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Create Custom Fund</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-body p-4">

            <h2>Create Custom Fund</h2>
            <p class="text-muted">
                Add a new fund to your organization.
            </p>

            <form method="POST" action="save_fund.php">

                <div class="mb-3">
                    <label class="form-label">Fund Name</label>
                    <input type="text"
                           name="fund_name"
                           class="form-control"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Fund Type</label>
                    <select name="fund_type"
                            class="form-select"
                            required>
                        <option value="">Select a type</option>
                        <option value="General">General</option>
                        <option value="Special Revenue">Special Revenue</option>
                        <option value="Capital Projects">Capital Projects</option>
                        <option value="Debt Service">Debt Service</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Restriction Designation
                    </label>
                    <select name="restriction_designation"
                            class="form-select"
                            required>
                        <option value="">Select restriction</option>
                        <option value="Restricted">Restricted</option>
                        <option value="Unrestricted">Unrestricted</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Description (Optional)
                    </label>
                    <textarea name="description"
                              class="form-control"
                              rows="3"></textarea>
                </div>

                <button type="submit"
                        class="btn btn-primary">
                    Create Fund
                </button>

                <a href="dashboard.php"
                   class="btn btn-secondary">
                    Cancel
                </a>

            </form>
        </div>
    </div>
</div>

</body>
</html>
