<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class AuthService
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $stmt = $this->connection->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }

    public function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        return $this->check() ? $_SESSION['user'] : null;
    }
}
