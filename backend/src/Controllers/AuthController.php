<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\Auth;

final class AuthController
{
    /**
     * POST /api/auth/register
     * Body: { email, password }
     */
    public function register(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email required']);
            return;
        }
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            return;
        }

        $conn = Database::conn();
        $exists = $conn->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exists) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $conn->insert('users', [
            'email'         => $email,
            'password_hash' => $hash,
        ]);
        $userId = $conn->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);

        $token = Auth::issue($userId, $email);
        echo json_encode(['token' => $token, 'user' => ['id' => $userId, 'email' => $email]]);
    }

    /**
     * POST /api/auth/login
     * Body: { email, password }
     */
    public function login(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';

        $conn = Database::conn();
        $user = $conn->fetchAssociative('SELECT id, email, password_hash FROM users WHERE email = ?', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            return;
        }

        $token = Auth::issue($user['id'], $user['email']);
        echo json_encode(['token' => $token, 'user' => ['id' => $user['id'], 'email' => $user['email']]]);
    }
}
