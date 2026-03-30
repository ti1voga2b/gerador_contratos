<?php

declare(strict_types=1);

namespace App\Models;

final class PlanRepository
{
    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function all(): array
    {
        return [
            ['id' => 100, 'network' => 'TIM', 'standard' => 'Plano Markup 5Gb', 'provider' => 'VOGA 5Gb - TIM', 'price' => 34.99, 'data' => 5120],
            ['id' => 101, 'network' => 'TIM', 'standard' => 'Plano Markup 8Gb', 'provider' => 'VOGA 8Gb - TIM', 'price' => 44.99, 'data' => 8192],
            ['id' => 102, 'network' => 'TIM', 'standard' => 'Plano Markup 12Gb', 'provider' => 'VOGA 12Gb - TIM', 'price' => 49.99, 'data' => 12288],
            ['id' => 103, 'network' => 'TIM', 'standard' => 'Plano Markup 22Gb', 'provider' => 'VOGA 22Gb - TIM', 'price' => 59.99, 'data' => 22528],
            ['id' => 104, 'network' => 'TIM', 'standard' => 'Plano Markup 30Gb', 'provider' => 'VOGA 30Gb - TIM', 'price' => 69.99, 'data' => 30720],
            ['id' => 105, 'network' => 'TIM', 'standard' => 'Plano Markup 40Gb', 'provider' => 'VOGA 40Gb - TIM', 'price' => 79.99, 'data' => 40960],
            ['id' => 106, 'network' => 'TIM', 'standard' => 'Plano Markup 45Gb', 'provider' => 'VOGA 45Gb - TIM', 'price' => 89.99, 'data' => 46080],
            ['id' => 700, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 10 Gb + 5Gb (Port) - Voz Ilimitado', 'provider' => 'VOGA 10 GB + 5GB - VIVO', 'price' => 59.99, 'data' => 15360],
            ['id' => 701, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 15 Gb + 5Gb (Port) - Voz Ilimitado', 'provider' => 'VOGA 15 GB + 5GB - VIVO', 'price' => 64.99, 'data' => 20480],
            ['id' => 702, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 25 Gb + 5Gb (Port) - Voz Ilimitado', 'provider' => 'VOGA 25 GB + 5GB - VIVO', 'price' => 89.99, 'data' => 30720],
            ['id' => 704, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 5 Gb + 3Gb (Port) - Voz Ilimitado', 'provider' => 'VOGA 5 GB + 3GB - VIVO', 'price' => 49.99, 'data' => 8192],
            ['id' => 705, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 3 Gb + 2Gb (Port) - Voz Ilimitado', 'provider' => 'VOGA 3 GB + 2GB - VIVO', 'price' => 39.99, 'data' => 5120],
            ['id' => 706, 'network' => 'VIVO', 'standard' => 'Plano Markup Vivo 1 Gb - Voz Ilimitado', 'provider' => 'VOGA 1 GB - VIVO', 'price' => 24.99, 'data' => 1024],
        ];
    }

    /**
     * @return array<string, int|float|string>|null
     */
    public function findById(int $id): ?array
    {
        foreach ($this->all() as $plan) {
            if ((int) $plan['id'] === $id) {
                return $plan;
            }
        }

        return null;
    }
}
