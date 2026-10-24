<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Per-IP sliding-window rate limiter.
 * Supports Redis backend (when extension available) with file fallback.
 * Fails open if filesystem/unavailable — availability over strictness.
 */
final class RateLimiter
{
    /** @var \Redis|null */
    private static ?\Redis $redis = null;

    /** Cache for Redis connection to avoid reconnecting per request */
    private static function getRedis(): \Redis
    {
        if (self::$redis === null && extension_loaded('redis')) {
            try {
                self::$redis = new \Redis();
                self::$redis->connect('127.0.0.1', 6379);
                // Test connection
                self::$redis->ping();
            } catch (\RedisException $e) {
                self::$redis = null;
            }
        }
        return self::$redis ?? new \Redis(); // return dummy if no extension
    }

    /** Count one hit against $key for the caller's IP.
     * Exits 429 when the window is exhausted.
     */
    public static function hit(string $key, int $max, int $windowSeconds): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Try Redis first if extension available
        if (extension_loaded('redis')) {
            $redis = self::getRedis();
            if ($redis instanceof \Redis) {
                self::hitRedis($redis, $key, $max, $windowSeconds, $ip);
                return;
            }
        }

        // Fallback to file-based rate limiter
        self::hitFile($key, $max, $windowSeconds, $ip);
    }

    /** Redis-backed implementation */
    private static function hitRedis(\Redis $redis, string $key, int $max, int $windowSeconds, string $ip): void
    {
        $combinedKey = $key . '|' . $ip;
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Use Redis sorted set (ZSET) for sliding window
        $redisKey = 'rl:' . sha1($combinedKey);

        // Remove entries outside the window
        $redis->zremrangeByScore($redisKey, '-inf', $windowStart);

        // Count current hits in window
        $count = $redis->zcard($redisKey);

        // Add current timestamp as score
        $redis->zadd($redisKey, $now, $now . '|' . $ip);

        // Set expiry on the key
        $redis->expire($redisKey, $windowSeconds);

        if ($count >= $max) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests, slow down']);
            exit;
        }
    }

    /** File-backed implementation (original, improved) */
    private static function hitFile(string $key, int $max, int $windowSeconds, string $ip): void
    {
        $combinedKey = $key . '|' . $ip;
        $file = sys_get_temp_dir() . '/defence-panel-rl/' . sha1($combinedKey) . '.json';

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $fp = @fopen($file, 'c+');
        if ($fp === null) {
            return; // fail open
        }

        flock($fp, LOCK_EX);
        $now = time();
        $raw = stream_get_contents($fp);
        $hits = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($hits)) {
            $hits = [];
        }
        // Filter to only hits within the window
        $hits = array_values(array_filter($hits, fn($t) => is_numeric($t) && $t > $windowStart));

        if (count($hits) >= $max) {
            flock($fp, LOCK_UN);
            fclose($fp);
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests, slow down']);
            exit;
        }

        $hits[] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}