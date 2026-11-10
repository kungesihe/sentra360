<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query uses prepared statements with bound
 * parameters — never string-concatenated user input.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('DB_HOST', '127.0.0.1'),
                env('DB_PORT', '3306'),
                env('DB_NAME', 'sentra360')
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            // Encrypted connection to a hosted database (e.g. Aiven). DB_SSL_CA is a file path
            // or the PEM text itself (handy for hosting dashboards).
            if ($ca = self::caFile()) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
            self::$pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $options);
            self::$pdo->exec("SET time_zone = '+02:00'");
        }
        return self::$pdo;
    }

    /** Create the database named in DB_NAME if it does not exist (used by the installer). */
    public static function createDatabaseIfMissing(): void
    {
        $name = env('DB_NAME', 'sentra360');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('DB_NAME may only contain letters, numbers and underscores.');
        }
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if ($ca = self::caFile()) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'));
        (new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $options))
            ->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private static function caFile(): ?string
    {
        $ca = env('DB_SSL_CA');
        if (!$ca) {
            return null;
        }
        if (str_contains($ca, '-----BEGIN CERTIFICATE-----')) {
            $file = sys_get_temp_dir() . '/sentra-db-ca-' . substr(sha1($ca), 0, 12) . '.pem';
            if (!is_file($file)) {
                file_put_contents($file, str_replace('\n', "\n", $ca));
            }
            return $file;
        }
        $path = str_starts_with($ca, '/') || preg_match('/^[A-Za-z]:/', $ca) ? $ca : BASE_PATH . '/' . $ca;
        return is_file($path) ? $path : throw new \RuntimeException('DB_SSL_CA file not found: ' . $ca);
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Insert a row; column names are code-controlled, values are bound. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        foreach ($cols as $c) {
            self::assertIdentifier($c);
        }
        self::assertIdentifier($table);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** Update rows matching $where (column => value). */
    public static function update(string $table, array $data, array $where): int
    {
        self::assertIdentifier($table);
        $set = [];
        $params = [];
        foreach ($data as $c => $v) {
            self::assertIdentifier($c);
            $set[] = "`$c` = ?";
            $params[] = $v;
        }
        $cond = [];
        foreach ($where as $c => $v) {
            self::assertIdentifier($c);
            $cond[] = "`$c` = ?";
            $params[] = $v;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $cond));
        return self::run($sql, $params)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: $name");
        }
    }
}
