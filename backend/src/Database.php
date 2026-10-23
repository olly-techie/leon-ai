<?php

declare(strict_types=1);

namespace App;

use Doctrine\DBAL\DriverManager;

final class Database
{
    private static ?\Doctrine\DBAL\Connection $conn = null;

    public static function conn(): \Doctrine\DBAL\Connection
    {
        if (self::$conn === null) {
            self::$conn = DriverManager::getConnection(self::params(getenv('DATABASE_URL')));
        }
        return self::$conn;
    }

    public static function direct(): \Doctrine\DBAL\Connection
    {
        return DriverManager::getConnection(
            self::params(getenv('DATABASE_DIRECT_URL') ?: getenv('DATABASE_URL'))
        );
    }

    private static function params(string $url): array
    {
        $u = parse_url($url);
        return [
            'driver'   => 'pdo_pgsql',
            'host'     => $u['host'] ?? 'localhost',
            'port'     => $u['port'] ?? 5432,
            'user'     => $u['user'] ?? '',
            'password' => $u['pass'] ?? '',
            'dbname'   => ltrim($u['path'] ?? '', '/'),
            'sslmode'  => 'require',
        ];
    }
}
