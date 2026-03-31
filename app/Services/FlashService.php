<?php

declare(strict_types=1);

namespace App\Services;

final class FlashService
{
    public function put(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function pull(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    public function forget(): void
    {
        unset($_SESSION['flash']);
    }
}
