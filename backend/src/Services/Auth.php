<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class Auth
{
    private static ?string $secret = null;

    private static function secret(): string
    {
        if (self::$secret === null) {
            self::$secret = getenv('JWT_SECRET') ?: '';
            if (self::$secret === '') {
                throw new \RuntimeException('JWT_SECRET not set');
            }
        }
        return self::$secret;
    }

    public static function issue(string $userId, string $email): string
    {
        $now = time();
        return JWT::encode([
            'sub'   => $userId,
            'email' => $email,
            'iat'   => $now,
            'exp'   => $now + 86400 * 7, // 7 days
        ], self::secret(), 'HS256');
    }

    /**
     * Validate Authorization: Bearer <token>.
     * Returns ['sub' => userId, 'email' => ...] or null.
     */
    public static function user(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }
        try {
            $decoded = JWT::decode($m[1], new Key(self::secret(), 'HS256'));
            return [
                'sub'   => $decoded->sub,
                'email' => $decoded->email ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Require a valid token or exit 401. Returns the user payload.
     */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        return $user;
    }
}
