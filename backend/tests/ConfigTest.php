<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Config test — verifies that .env values are accessible via getenv().
 * Since we can't fully bootstrap the app without a DB, we just verify the env file exists and has content.
 */
class ConfigTest extends TestCase
{
    public function test_env_file_has_configuration(): void
    {
        $envPath = __DIR__ . '/../.env';
        $this->assertFileExists($envPath, '.env file should exist');

        $content = file_get_contents($envPath);
        $this->assertStringContainsString('MAX_UPLOAD_SIZE', $content, 'MAX_UPLOAD_SIZE should be in .env');
        $this->assertStringContainsString('MAX_SESSION_TURNS', $content, 'MAX_SESSION_TURNS should be in .env');
        $this->assertStringContainsString('RATE_LIMIT_AUTH', $content, 'RATE_LIMIT_AUTH should be in .env');
        $this->assertStringContainsString('RATE_LIMIT_UPLOAD', $content, 'RATE_LIMIT_UPLOAD should be in .env');
        $this->assertStringContainsString('RATE_LIMIT_SESSION_START', $content, 'RATE_LIMIT_SESSION_START should be in .env');
        $this->assertStringContainsString('RATE_LIMIT_SESSION_ANSWER', $content, 'RATE_LIMIT_SESSION_ANSWER should be in .env');
    }

    public function test_env_values_are_reasonable(): void
    {
        $envPath = __DIR__ . '/../.env';
        $content = file_get_contents($envPath);
        // MAX_UPLOAD_SIZE should be ~15MB (15728640 bytes)
        preg_match('/MAX_UPLOAD_SIZE=(\d+)/', $content, $matches);
        $this->assertNotEmpty($matches[1], 'MAX_UPLOAD_SIZE should have a numeric value');
        $this->assertEquals(15728640, (int)$matches[1], 'MAX_UPLOAD_SIZE should be 15MB');

        // MAX_SESSION_TURNS should be 6
        preg_match('/MAX_SESSION_TURNS=(\d+)/', $content, $matches);
        $this->assertNotEmpty($matches[1], 'MAX_SESSION_TURNS should have a numeric value');
        $this->assertEquals(6, (int)$matches[1], 'MAX_SESSION_TURNS should be 6');

        // Rate limits should have comma-separated format max,window
        preg_match('/RATE_LIMIT_AUTH=(\d+),(\d+)/', $content, $matches);
        $this->assertNotEmpty($matches[1], 'RATE_LIMIT_AUTH should match pattern');
        $this->assertEquals(10, (int)$matches[1], 'RATE_LIMIT_AUTH max should be 10');
        $this->assertEquals(60, (int)$matches[2], 'RATE_LIMIT_AUTH window should be 60');

        preg_match('/RATE_LIMIT_SESSION_ANSWER=(\d+),(\d+)/', $content, $matches);
        $this->assertNotEmpty($matches[1], 'RATE_LIMIT_SESSION_ANSWER should match pattern');
        $this->assertEquals(60, (int)$matches[1], 'RATE_LIMIT_SESSION_ANSWER max should be 60');
        $this->assertEquals(60, (int)$matches[2], 'RATE_LIMIT_SESSION_ANSWER window should be 60');
    }
}