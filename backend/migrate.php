<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

// createUnsafeImmutable so getenv() sees the values (services read via getenv)
$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$conn = Database::direct();

$migrations = glob(__DIR__ . '/migrations/*.sql');
sort($migrations);

foreach ($migrations as $file) {
    $conn->executeStatement(file_get_contents($file));
    echo 'Applied ' . basename($file) . "\n";
}

echo "All migrations applied successfully.\n";
