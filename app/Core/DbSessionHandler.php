<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Stores PHP sessions in the php_sessions table instead of files, so people
 * stay signed in when the server restarts (Render's free plan restarts often).
 */
final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return (string) (DB::value('SELECT data FROM php_sessions WHERE id = ?', [$id]) ?? '');
    }

    public function write(string $id, string $data): bool
    {
        DB::run(
            'INSERT INTO php_sessions (id, data, last_access) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = VALUES(last_access)',
            [$id, $data, time()]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        DB::run('DELETE FROM php_sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return DB::run('DELETE FROM php_sessions WHERE last_access < ?', [time() - $max_lifetime])->rowCount();
    }

    /** Strict mode: only accept session ids this server issued. */
    public function validateId(string $id): bool
    {
        return (bool) DB::value('SELECT 1 FROM php_sessions WHERE id = ?', [$id]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        DB::run('UPDATE php_sessions SET last_access = ? WHERE id = ?', [time(), $id]);
        return true;
    }
}
