<?php
/** @var string $title */
/** @var string $body  */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Forge</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="site-header">
        <a href="/" class="logo">
            <span class="logo-icon">&#9650;</span>
            <span class="logo-text">Forge</span>
        </a>
        <nav class="site-nav">
            <a href="/history" class="nav-link">History</a>
        </nav>
    </header>
    <main class="site-main">
        <?= $body ?>
    </main>
    <script src="/assets/js/app.js"></script>
</body>
</html>
