<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\PlanRepository;
use App\Services\ContractPdfGenerator;
use App\Services\InvoiceParser;

final class ContractController
{
    private PlanRepository $planRepository;
    private InvoiceParser $invoiceParser;
    private ContractPdfGenerator $contractPdfGenerator;

    public function __construct()
    {
        $this->planRepository = new PlanRepository();
        $this->invoiceParser = new InvoiceParser();
        $this->contractPdfGenerator = new ContractPdfGenerator($this->planRepository, dirname(__DIR__, 2) . '/assets/pdf-template');
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->downloadDocument();
            return;
        }

        $extractedData = null;

        if (isset($_FILES['invoice_txt']) && (int) $_FILES['invoice_txt']['error'] === UPLOAD_ERR_OK) {
            $extractedData = $this->invoiceParser->parseUploadedFile($_FILES['invoice_txt']['tmp_name']);
        }

        View::render('contracts/index', [
            'extractedData' => $extractedData,
            'plans' => $this->planRepository->all(),
        ]);
    }

    private function downloadDocument(): void
    {
        $action = $_POST['action'] ?? '';
        $clientData = json_decode($_POST['client_data'] ?? '[]', true);

        if (!is_array($clientData) || $action !== 'generate_term') {
            http_response_code(400);
            echo 'Requisicao invalida.';
            return;
        }

        $selectedPlans = is_array($_POST['selected_plans'] ?? null) ? $_POST['selected_plans'] : [];
        $document = $this->contractPdfGenerator->generate([
            'client_data' => $clientData,
            'selected_plans' => $selectedPlans,
            'operator' => (string) ($_POST['operator'] ?? ''),
            'fidelity' => (string) ($_POST['fidelity'] ?? 'none'),
            'commercial_terms' => (string) ($_POST['commercial_terms'] ?? ''),
            'custom_sla' => isset($_POST['sla']),
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
        echo $document['content'];
    }
}
