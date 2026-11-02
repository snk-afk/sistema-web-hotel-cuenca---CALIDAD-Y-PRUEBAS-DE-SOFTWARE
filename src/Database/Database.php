<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Config;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            /** @var string $host */
            $host = Config::get('database.host', '127.0.0.1');
            /** @var int|string $port */
            $port = Config::get('database.port', 3306);
            /** @var string $db_name */
            $db_name = Config::get('database.database', 'cs_hotel');
            /** @var string $charset */
            $charset = Config::get('database.charset', 'utf8mb4');
            /** @var string $username */
            $username = Config::get('database.username', 'root');
            /** @var string $password */
            $password = Config::get('database.password', '');
            /** @var array<int, mixed> $options */
            $options = Config::get('database.options', []);

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, (int) $port, $db_name, $charset);

            self::$connection = new PDO($dsn, $username, $password, $options);
            self::$connection->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        }

        return self::$connection;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }
}
