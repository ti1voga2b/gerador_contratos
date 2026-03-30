<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceParser
{
    /**
     * @return array<string, mixed>
     */
    public function parseUploadedFile(string $uploadedFilePath): array
    {
        $content = file_get_contents($uploadedFilePath);

        if ($content === false) {
            return $this->emptyResult();
        }

        return $this->parse($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $clientName = 'Cliente nao identificado';
        $clientCnpj = '';
        $extractedLines = [];

        foreach ($lines as $line) {
            if (strpos($line, 'DISTRIBUIDORA') !== false) {
                $clientName = trim($line);

                if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', $line, $matches)) {
                    $clientCnpj = $matches[1];
                }
            }

            if (preg_match('/N(\d{2}-\d{5}-\d{4})/', $line, $matches)) {
                $extractedLines[] = [
                    'number' => $matches[1],
                    'plan' => 'Extraido do TXT',
                    'value' => 0,
                ];
            }
        }

        return [
            'clientName' => $clientName,
            'clientCNPJ' => $clientCnpj,
            'clientAddress' => 'Endereco extraido do TXT',
            'clientNeighborhood' => 'Bairro',
            'clientCity' => 'Cidade',
            'clientState' => 'UF',
            'lines' => $extractedLines,
            'totalValue' => '0,00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(): array
    {
        return [
            'clientName' => 'Cliente nao identificado',
            'clientCNPJ' => '',
            'clientAddress' => '',
            'clientNeighborhood' => '',
            'clientCity' => '',
            'clientState' => '',
            'lines' => [],
            'totalValue' => '0,00',
        ];
    }
}
