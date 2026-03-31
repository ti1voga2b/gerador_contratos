<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanRepository;
use FPDF;

final class ContractPdfGenerator
{
    private const PLAN_ROWS_PER_PAGE = 24;

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
        $pdf = new class('P', 'mm', 'A4') extends FPDF {
            public function Footer(): void
            {
                $label = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', 'Pagina ' . $this->PageNo() . ' de {nb}');
                $this->SetY(-12);
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(237, 94, 91);
                $this->Cell(0, 5, $label !== false ? $label : 'Pagina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
            }
        };
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $this->addCoverPage($pdf);
        $this->addProposalPage($pdf, $clientData, $operator, $fidelity, $commercialTerms, $planRows);
        $this->addPlanDetailPages($pdf, $planRows);
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
            $this->writeFieldLabel($document, 123, 83, 'Cidade:');
            $this->writeFieldLabel($document, 112, 92, 'Estado:');
            $this->writeFieldLabel($document, $left, 92, 'CEP:');
            $this->writeFieldLabel($document, 73, 92, 'Telefone:');
            $this->writeFieldLabel($document, $left, 101, 'E-mail:');
            $this->writeFieldLabel($document, $left, 114, 'Operadora:');

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
            $document->SetXY(137, 83);
            $document->Cell(25, 5, $this->normalize((string) ($clientData['clientCity'] ?? '')), 0, 0);
            $document->SetXY(126, 92);
            $document->Cell(12, 5, $this->normalize((string) ($clientData['clientState'] ?? '')), 0, 0);
            $document->SetXY(29, 92);
            $document->Cell(25, 5, $this->normalize((string) ($clientData['clientCEP'] ?? '')), 0, 0);
            $document->SetXY(92, 92);
            $document->Cell(35, 5, $this->normalize((string) ($clientData['clientPhone'] ?? '')), 0, 0);
            $document->SetXY(35, 101);
            $document->Cell(90, 5, $this->normalize((string) ($clientData['clientEmail'] ?? '')), 0, 0);
            $document->SetXY(41, 114);
            $document->Cell(60, 5, $this->normalize($this->formatOperator($operator)), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 11);
            $document->SetXY(0, 130);
            $document->Cell(210, 5, $this->normalize('LINHAS PARA PORTABILIDADES:'), 0, 0, 'C');

            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 140.5, 'Quantidade:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(45, 140.5);
            $document->Cell(20, 5, (string) count($planRows), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 11);
            $document->SetXY($left, 159);
            $document->Cell(90, 5, $this->normalize('LINHAS E PLANOS:'), 0, 0);
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY($left, 168);
            $document->MultiCell(160, 5, $this->normalize('O detalhamento completo das linhas e dos planos contratados segue nas paginas exclusivas logo apos esta proposta comercial.'), 0, 'L');

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 214, 'Observacoes:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(46, 214);
            $document->MultiCell(130, 5, $this->normalize($commercialTerms !== '' ? $commercialTerms : 'Sem observacoes'), 0, 'L');

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 9.5);
            $this->writeFieldLabel($document, $left, 233, 'Fidelidade:');
            $document->SetTextColor(90, 90, 90);
            $document->SetFont('Arial', '', 9);
            $document->SetXY(45, 233);
            $document->Cell(60, 5, $this->normalize($this->formatFidelity($fidelity)), 0, 0);

            $document->SetTextColor(...$accent);
            $document->SetFont('Arial', 'B', 10.5);
            $this->writeFieldLabel($document, $left, 246, 'Valor total do contrato:');
            $document->SetXY(80, 246);
            $document->Cell(60, 5, $this->normalize($this->formatCurrency($this->sumRows($planRows))), 0, 0);
        });
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
    private function addPlanDetailPages(FPDF $pdf, array $planRows): void
    {
        $pages = array_chunk($planRows, self::PLAN_ROWS_PER_PAGE);

        if ($pages === []) {
            $pages = [[]];
        }

        foreach ($pages as $pageIndex => $rows) {
            $this->addStaticPageFromAsset($pdf, 'page-lines.png', function (FPDF $document) use ($rows, $pageIndex): void {
                $accent = [237, 94, 91];
                $left = 20.5;

                $document->SetFont('Arial', 'B', 9.5);
                $document->SetXY($left, 58);
                $document->Cell(15, 6, '#', 0, 0);
                $document->Cell(42, 6, $this->normalize('Numero'), 0, 0);
                $document->Cell(84, 6, $this->normalize('Plano'), 0, 0);
                $document->Cell(30, 6, $this->normalize('Valor'), 0, 0);

                $document->SetDrawColor(...$accent);
                $document->Line($left, 64, 189, 64);

                $document->SetTextColor(90, 90, 90);
                $document->SetFont('Arial', '', 9);
                $y = 69;

                if ($rows === []) {
                    $document->SetXY($left, $y);
                    $document->Cell(140, 6, $this->normalize('Nenhuma linha encontrada para esta proposta.'), 0, 0);
                    return;
                }

                foreach ($rows as $rowOffset => $row) {
                    $index = ($pageIndex * self::PLAN_ROWS_PER_PAGE) + $rowOffset + 1;

                    $document->SetXY($left, $y);
                    $document->Cell(15, 6, (string) $index, 0, 0);
                    $document->Cell(42, 6, $this->normalize($row['number']), 0, 0);
                    $document->Cell(84, 6, $this->normalize($row['plan']), 0, 0);
                    $document->Cell(30, 6, $this->normalize($row['price']), 0, 0, 'R');
                    $y += 8.5;
                }
            });
        }
    }

    /**
     * @param callable(FPDF):void|null $overlay
     */
    private function addStaticPageFromAsset(FPDF $pdf, string $assetName, ?callable $overlay = null): void
    {
        $pagePath = $this->templateDir . '/' . $assetName;
        $pdf->AddPage();
        $pdf->Image($pagePath, 0, 0, 210, 297);

        if ($overlay !== null) {
            $overlay($pdf);
        }
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
        $seenNumbers = [];

        foreach ($lines as $index => $line) {
            $number = is_array($line) ? trim((string) ($line['number'] ?? '')) : '';

            if ($number === '' || isset($seenNumbers[$number])) {
                continue;
            }

            $seenNumbers[$number] = true;
            $planId = isset($selectedPlans[$index]) ? (int) $selectedPlans[$index] : 0;
            if ($planId === 0) {
                $rows[] = [
                    'number' => $number,
                    'plan' => 'Cancelar',
                    'price' => $this->formatCurrency(0.0),
                ];
                continue;
            }

            $plan = $this->planRepository->findById($planId);
            $rows[] = [
                'number' => $number,
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
