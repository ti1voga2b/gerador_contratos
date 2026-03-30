<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanRepository;
use FPDF;

final class ContractPdfGenerator
{
    private PlanRepository $planRepository;
    private string $templateDir;

    public function __construct(PlanRepository $planRepository, string $templateDir)
    {
        $this->planRepository = $planRepository;
        $this->templateDir = rtrim($templateDir, '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function generate(array $payload): array
    {
        $clientData = is_array($payload['client_data'] ?? null) ? $payload['client_data'] : [];
        $selectedPlans = is_array($payload['selected_plans'] ?? null) ? $payload['selected_plans'] : [];
        $operator = (string) ($payload['operator'] ?? '');
        $fidelity = (string) ($payload['fidelity'] ?? 'none');
        $commercialTerms = trim((string) ($payload['commercial_terms'] ?? ''));

        $planRows = $this->buildPlanRows($clientData, $selectedPlans);
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $this->addCoverPage($pdf);
        $this->addProposalPage($pdf, $clientData, $operator, $fidelity, $commercialTerms, $planRows);
        $this->addStaticPage($pdf, 3);
        $this->addStaticPage($pdf, 4);
        $this->addStaticPage($pdf, 5);
        $this->addStaticPage($pdf, 6);
        $this->addStaticPage($pdf, 7);
        $this->addStaticPage($pdf, 8);
        $this->addStaticPage($pdf, 9);

        $filenamePrefix = preg_replace('/[^a-z0-9]/i', '_', (string) ($clientData['clientName'] ?? 'cliente')) ?: 'cliente';

        return [
            'filename' => 'Contrato_Voga_' . $filenamePrefix . '.pdf',
            'content' => $pdf->Output('S'),
        ];
    }

    private function addCoverPage(FPDF $pdf): void
    {
        $this->addStaticPage($pdf, 1);
    }

    /**
     * @param array<string, mixed> $clientData
     * @param array<int, array<string, string>> $planRows
     */
    private function addProposalPage(FPDF $pdf, array $clientData, string $operator, string $fidelity, string $commercialTerms, array $planRows): void
    {
        $this->addStaticPage($pdf, 2, function (FPDF $document) use ($clientData, $operator, $fidelity, $commercialTerms, $planRows): void {
            $left = 20.5;
            $smallFont = 9;
            $accent = [237, 94, 91];

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 12);
            $document->SetXY(0, 43);
            $document->Cell(210, 6, $this->normalize('DADOS DA EMPRESA'), 0, 0, 'C');

            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 56, 'Nome Empresarial:');
            $this->writeFieldLabel($document, $left, 65, 'CNPJ:');
            $this->writeFieldLabel($document, $left, 74, 'Endereco:');
            $this->writeFieldLabel($document, $left, 83, 'Bairro:');
            $this->writeFieldLabel($document, 73, 83, 'Cidade:');
            $this->writeFieldLabel($document, 112, 83, 'Estado:');
            $this->writeFieldLabel($document, 141, 83, 'CEP:');
            $this->writeFieldLabel($document, $left, 92, 'Telefone:');
            $this->writeFieldLabel($document, 73, 92, 'E-mail:');
            $this->writeFieldLabel($document, $left, 105, 'Operadora:');

            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', $smallFont);
            $document->SetXY(52, 56);
            $document->Cell(120, 5, $this->normalize((string) ($clientData['clientName'] ?? '')), 0, 0);
            $document->SetXY(35, 65);
            $document->Cell(120, 5, $this->normalize((string) ($clientData['clientCNPJ'] ?? '')), 0, 0);
            $document->SetXY(38, 74);
            $document->Cell(135, 5, $this->normalize((string) ($clientData['clientAddress'] ?? '')), 0, 0);
            $document->SetXY(34, 83);
            $document->Cell(30, 5, $this->normalize((string) ($clientData['clientNeighborhood'] ?? '')), 0, 0);
            $document->SetXY(87, 83);
            $document->Cell(25, 5, $this->normalize((string) ($clientData['clientCity'] ?? '')), 0, 0);
            $document->SetXY(126, 83);
            $document->Cell(12, 5, $this->normalize((string) ($clientData['clientState'] ?? '')), 0, 0);
            $document->SetXY(151, 83);
            $document->Cell(20, 5, $this->normalize((string) ($clientData['clientCEP'] ?? '')), 0, 0);
            $document->SetXY(39, 92);
            $document->Cell(30, 5, $this->normalize((string) ($clientData['clientPhone'] ?? '')), 0, 0);
            $document->SetXY(87, 92);
            $document->Cell(70, 5, $this->normalize((string) ($clientData['clientEmail'] ?? '')), 0, 0);
            $document->SetXY(41, 105);
            $document->Cell(60, 5, $this->normalize($this->formatOperator($operator)), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 11);
            $document->SetXY(0, 121);
            $document->Cell(210, 5, $this->normalize('LINHAS PARA PORTABILIDADES:'), 0, 0, 'C');

            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 131.5, 'Quantidade:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(45, 131.5);
            $document->Cell(20, 5, (string) count($planRows), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 11);
            $document->SetXY($left, 145);
            $document->Cell(80, 5, $this->normalize('LINHAS E PLANOS:'), 0, 0);

            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 8.5);
            $lineY = 134.0;
            $lineHeight = 6.2;
            foreach ($planRows as $rowIndex => $row) {
                if ($rowIndex >= 7) {
                    break;
                }

                $document->SetXY($left, 152 + ($rowIndex * $lineHeight));
                $lineText = sprintf(
                    '%d | %s | %s | %s',
                    $rowIndex + 1,
                    $this->normalize($row['number']),
                    $this->normalize($row['plan']),
                    $this->normalize($row['price'])
                );
                $document->Cell(165, 5, $lineText, 0, 0);
            }

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 221, 'Observacoes:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(46, 221);
            $document->MultiCell(130, 5, $this->normalize($commercialTerms !== '' ? $commercialTerms : 'Sem observacoes'), 0, 'L');

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 236, 'Fidelidade:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(45, 236);
            $document->Cell(60, 5, $this->normalize($this->formatFidelity($fidelity)), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 10.5);
            $this->writeFieldLabel($document, $left, 249, 'Valor total do contrato:');
            $document->SetXY(80, 249);
            $document->Cell(60, 5, $this->normalize($this->formatCurrency($this->sumRows($planRows))), 0, 0);

            if (count($planRows) > 7) {
                $document->SetTextColor(90, 90, 90);
                $document->SetFont('Arial', 'I', 8);
                $document->SetXY(20.5, 257.0);
                $document->Cell(0, 5, $this->normalize('Demais linhas serao listadas em pagina adicional ao final do contrato.'), 0, 0);
            }
        });

        if (count($planRows) > 7) {
            $this->addAdditionalLinesPage($pdf, $planRows);
        }
    }

    private function writeFieldLabel(FPDF $pdf, float $x, float $y, string $label): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 5, $this->normalize($label), 0, 0);
    }

    /**
     * @param callable(FPDF):void|null $overlay
     */
    private function addStaticPage(FPDF $pdf, int $pageNumber, ?callable $overlay = null): void
    {
        $pagePath = $this->templateDir . '/page-' . $pageNumber . '.png';
        $pdf->AddPage();
        $pdf->Image($pagePath, 0, 0, 210, 297);

        if ($overlay !== null) {
            $overlay($pdf);
        }
    }

    /**
     * @param array<int, array<string, string>> $planRows
     */
    private function addAdditionalLinesPage(FPDF $pdf, array $planRows): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(237, 94, 91);
        $pdf->Cell(0, 10, $this->normalize('ANEXO - LINHAS E PLANOS ADICIONAIS'), 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        foreach ($planRows as $index => $row) {
            if ($index < 7) {
                continue;
            }

            $text = sprintf(
                '%d | %s | %s | %s',
                $index + 1,
                $this->normalize($row['number']),
                $this->normalize($row['plan']),
                $this->normalize($row['price'])
            );
            $pdf->MultiCell(0, 7, $text, 0, 'L');
        }

        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
    }

    /**
     * @param array<string, mixed> $clientData
     * @param array<int|string, mixed> $selectedPlans
     * @return array<int, array<string, string>>
     */
    private function buildPlanRows(array $clientData, array $selectedPlans): array
    {
        $rows = [];
        $lines = is_array($clientData['lines'] ?? null) ? $clientData['lines'] : [];

        foreach ($lines as $index => $line) {
            $planId = isset($selectedPlans[$index]) ? (int) $selectedPlans[$index] : 0;
            $plan = $this->planRepository->findById($planId);
            $rows[] = [
                'number' => is_array($line) ? (string) ($line['number'] ?? '') : '',
                'plan' => is_array($plan) ? (string) ($plan['provider'] ?? 'Nao selecionado') : 'Nao selecionado',
                'price' => $this->formatCurrency(is_array($plan) ? (float) ($plan['price'] ?? 0) : 0.0),
            ];
        }

        return $rows;
    }

    private function formatOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            'TIM' => 'TIM (SURF)',
            'VIVO' => 'VIVO (TELECALL)',
            default => $operator,
        };
    }

    private function formatFidelity(string $fidelity): string
    {
        return $fidelity === 'none' ? 'Sem Fidelidade' : $fidelity . ' meses';
    }

    private function formatCurrency(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function sumRows(array $rows): float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $value = str_replace(['R$', '.', ','], ['', '', '.'], $row['price']);
            $total += (float) trim($value);
        }

        return $total;
    }

    private function normalize(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
    }
}
