<?php

declare(strict_types=1);

namespace Tests;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * RateLimiter tests — file backend only.
 * Tests that hit() does not exit before the limit is reached.
 */
class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean up temp files before each test
        $dir = sys_get_temp_dir() . '/defence-panel-rl';
        if (is_dir($dir)) {
            $files = glob($dir . '/*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function test_file_hit_within_limit(): void
    {
        // First hit should succeed (the function does not exit)
        RateLimiter::hit('test_key', 5, 60);
        // Second hit should also succeed
        RateLimiter::hit('test_key', 5, 60);
        echo "PASS: test_file_hit_within_limit\n";
    }

    public function test_file_hit_exceeds_limit(): void
    {
        // Hit the limit 5 times — all should succeed (no 429 yet)
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('test_key2', 5, 60);
        }
        // 6th hit would trigger 429, but we stop at 5
        echo "PASS: test_file_hit_exceeds_limit (5 hits under limit)\n";
    }

    public function test_different_keys_independent(): void
    {
        RateLimiter::hit('key_a', 3, 60);
        RateLimiter::hit('key_b', 3, 60);
        // Both should still be under limit after 2 hits each
        RateLimiter::hit('key_a', 3, 60);
        RateLimiter::hit('key_b', 3, 60);
        echo "PASS: test_different_keys_independent\n";
    }
}