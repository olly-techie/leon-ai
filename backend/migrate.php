<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$sql = file_get_contents(__DIR__ . '/../migrations/001_init.sql');

$conn = Database::direct();
$conn->executeStatement($sql);

echo "Migration 001_init.sql applied successfully.\n";
