<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    /** Count attempts in a sliding window. */
    public static function tooMany(string $bucket, int $max, int $windowSeconds): bool
    {
        $count = (int) DB::value(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
            [$bucket, $windowSeconds]
        );
        return $count >= $max;
    }

    public static function hit(string $bucket): void
    {
        DB::insert('rate_limits', ['bucket' => substr($bucket, 0, 191)]);
        // Opportunistic clean-up of old rows (~1% of requests).
        if (random_int(1, 100) === 1) {
            DB::run('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        }
    }

    /** Hit and throw 429 when the limit is exceeded. */
    public static function guard(string $bucket, int $max, int $windowSeconds): void
    {
        if (self::tooMany($bucket, $max, $windowSeconds)) {
            throw new HttpException(429);
        }
        self::hit($bucket);
    }
}
