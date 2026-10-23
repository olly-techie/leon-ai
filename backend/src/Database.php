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
            self::$conn = DriverManager::getConnection([
                'url' => getenv('DATABASE_URL'),
            ]);
        }
        return self::$conn;
    }

    public static function direct(): \Doctrine\DBAL\Connection
    {
        return DriverManager::getConnection([
            'url' => getenv('DATABASE_DIRECT_URL') ?: getenv('DATABASE_URL'),
        ]);
    }
}
