<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceParser
{
    private const COMPANY_SUFFIX_PATTERN = '(?:LTDA|Ltda|ME|EPP|EIRELI|EI|S\/A|SA)';

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
        $clientAddress = 'Endereco extraido do TXT';
        $clientNeighborhood = 'Bairro';
        $clientCity = 'Cidade';
        $clientState = 'UF';
        $clientCep = '';
        $extractedLines = [];

        foreach ($lines as $line) {
            if ($clientCnpj === '' && preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', $line, $matches)) {
                $clientCnpj = $matches[1];
            }

            if (
                ($clientName === 'Cliente nao identificado' || $clientAddress === 'Endereco extraido do TXT')
                && preg_match('/' . self::COMPANY_SUFFIX_PATTERN . '/u', $line)
            ) {
                $companyData = $this->extractCompanyDataFromLine($line);

                if ($companyData['name'] !== '') {
                    $clientName = $companyData['name'];
                }

                if ($companyData['address'] !== '') {
                    $clientAddress = $companyData['address'];
                }

                if ($companyData['neighborhood'] !== '') {
                    $clientNeighborhood = $companyData['neighborhood'];
                }

                if ($companyData['city'] !== '') {
                    $clientCity = $companyData['city'];
                }

                if ($companyData['state'] !== '') {
                    $clientState = $companyData['state'];
                }

                if ($companyData['cep'] !== '') {
                    $clientCep = $companyData['cep'];
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
            'clientAddress' => $clientAddress,
            'clientNeighborhood' => $clientNeighborhood,
            'clientCity' => $clientCity,
            'clientState' => $clientState,
            'clientCEP' => $clientCep,
            'lines' => $this->uniqueLines($extractedLines),
            'totalValue' => '0,00',
        ];
    }

    /**
     * @return array{name:string,address:string,neighborhood:string,city:string,state:string,cep:string}
     */
    private function extractCompanyDataFromLine(string $line): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($line)) ?? trim($line);

        $result = [
            'name' => '',
            'address' => '',
            'neighborhood' => '',
            'city' => '',
            'state' => '',
            'cep' => '',
        ];

        $beforeCnpj = $normalized;
        if (preg_match('/^(.*)\s+\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/u', $normalized, $matches)) {
            $beforeCnpj = trim($matches[1]);
        }

        if (preg_match('/([A-ZÀ-Ú][A-ZÀ-Ú&\-.]*(?:\s+[A-ZÀ-Ú][A-ZÀ-Ú&\-.]*)*\s+' . self::COMPANY_SUFFIX_PATTERN . ')$/u', $beforeCnpj, $matches)) {
            $result['name'] = trim($matches[1]);
        }

        if (preg_match('/((?:AV|AVE|AVENIDA|RUA|ROD|RODOVIA|AL|ALAMEDA|TRAVESSA|TV|PRACA|PCA)\s+[A-ZÀ-Ú0-9 ]+?)\s+(\d{1,6})\s+([A-ZÀ-Ú ]+?)\s+\d{6,}([A-ZÀ-Ú ]+?)\s+(\d{8})([A-Z]{2})/u', $normalized, $matches)) {
            $result['address'] = trim($matches[1]) . ', ' . trim($matches[2]);
            $result['neighborhood'] = trim($matches[3]);
            $result['city'] = trim($matches[4]);
            $result['cep'] = $this->formatCep($matches[5]);
            $result['state'] = trim($matches[6]);
        }

        return $result;
    }

    private function formatCep(string $cep): string
    {
        if (!preg_match('/^\d{8}$/', $cep)) {
            return $cep;
        }

        return substr($cep, 0, 5) . '-' . substr($cep, 5);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function uniqueLines(array $lines): array
    {
        $unique = [];
        $seen = [];

        foreach ($lines as $line) {
            $number = isset($line['number']) ? trim((string) $line['number']) : '';

            if ($number === '' || isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;
            $unique[] = $line;
        }

        return $unique;
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
