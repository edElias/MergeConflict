<?php
// Shared page header. Set $pageTitle and optionally $bodyClass before including.
$pageTitle = $pageTitle ?? 'CivicFunds';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> · CivicFunds</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
