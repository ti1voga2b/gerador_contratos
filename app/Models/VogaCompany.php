<?php

declare(strict_types=1);

namespace App\Models;

final class VogaCompany
{
    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return [
            'name' => 'VOGA INOVACOES TECNOLOGICAS LTDA',
            'cnpj' => '34.490.277/0001-61',
            'address' => 'R ARISTIDES THOMAZ BALLERINI, 185',
            'neighborhood' => 'JARDIM IPE',
            'city' => 'POCOS DE CALDAS',
            'state' => 'MG',
            'cep' => '37.704-206',
        ];
    }
}
