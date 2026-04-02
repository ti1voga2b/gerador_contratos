<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanRepository;
use App\Models\VogaCompany;

final class ContractTermGenerator
{
    private PlanRepository $planRepository;
    private VogaCompany $vogaCompany;

    public function __construct(PlanRepository $planRepository, VogaCompany $vogaCompany)
    {
        $this->planRepository = $planRepository;
        $this->vogaCompany = $vogaCompany;
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
        $sla = !empty($payload['custom_sla']) ? 'Customizado' : 'Padrao (99%)';
        $timestamp = date('d/m/Y');
        $filenamePrefix = preg_replace('/[^a-z0-9]/i', '_', (string) ($clientData['clientName'] ?? 'cliente')) ?: 'cliente';
        $issuer = $this->vogaCompany->details();

        $content = "TERMO DE ADESAO\n\n";
        $content .= "DADOS DO EMITENTE\n";
        $content .= "Empresa: {$issuer['name']}\n";
        $content .= "CNPJ: {$issuer['cnpj']}\n";
        $content .= "Endereco: {$issuer['address']}, {$issuer['neighborhood']}, {$issuer['city']} - {$issuer['state']}, CEP {$issuer['cep']}\n\n";

        $content .= "DADOS DO CLIENTE\n";
        $content .= 'Nome/Razao Social: ' . ($clientData['clientName'] ?? '') . "\n";
        $content .= 'CNPJ/CPF: ' . ($clientData['clientCNPJ'] ?? '') . "\n";
        $content .= 'Endereco: ' . ($clientData['clientAddress'] ?? '') . ', ' . ($clientData['clientNeighborhood'] ?? '') . ', ' . ($clientData['clientCity'] ?? '') . ' - ' . ($clientData['clientState'] ?? '') . "\n\n";

        $content .= "DADOS DO CONTRATO\n";
        $content .= "Operadora: {$operator}\n";
        $content .= 'Fidelidade: ' . ($fidelity === 'none' ? 'Sem Fidelidade' : $fidelity . " meses\n");
        if ($fidelity === 'none') {
            $content .= "\n";
        }
        $content .= "SLA: {$sla}\n\n";

        $content .= "LINHAS E PLANOS VOGA\n";
        $totalNew = 0.0;
        $clientLines = is_array($clientData['lines'] ?? null) ? $clientData['lines'] : [];

        foreach ($clientLines as $index => $line) {
            $selection = isset($selectedPlans[$index]) ? trim((string) $selectedPlans[$index]) : '';
            $plan = $selection === '' || $selection === '0'
                ? null
                : $this->planRepository->findBySelection($selection);
            $planName = $selection === '0' ? 'Cancelar' : ($plan['provider'] ?? 'Nao selecionado');
            $planPrice = $selection === '0' ? 0.0 : (isset($plan['price']) ? (float) $plan['price'] : 0.0);
            $totalNew += $planPrice;
            $number = is_array($line) ? (string) ($line['number'] ?? '') : '';

            $content .= "Linha: {$number} | Plano: {$planName} | Valor: R$ " . number_format($planPrice, 2, ',', '.') . "\n";
        }

        $content .= "\nTOTAL MENSAL: R$ " . number_format($totalNew, 2, ',', '.') . "\n\n";
        $content .= "CONDICOES COMERCIAIS\n" . ($commercialTerms !== '' ? $commercialTerms : 'Sem observacoes') . "\n\n";
        $content .= "Data: {$timestamp}";

        return [
            'filename' => 'Termo_Adesao_' . $filenamePrefix . '.txt',
            'content' => $content,
        ];
    }
}
