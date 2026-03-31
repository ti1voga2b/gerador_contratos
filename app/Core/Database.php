<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $storageDir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(
                'O driver PDO SQLite nao esta habilitado neste servidor. Instale/habilite a extensao pdo_sqlite/sqlite3 no PHP antes de iniciar a aplicacao.'
            );
        }

        $databasePath = Env::get('DB_DATABASE', 'storage/app.sqlite');
        $databasePath = str_starts_with($databasePath, '/')
            ? $databasePath
            : dirname(__DIR__, 2) . '/' . ltrim($databasePath, '/');

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::migrate($pdo);
        self::$connection = $pdo;

        return self::$connection;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }
}
