<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class ContractDownloadService
{
    public function __construct(
        private ContractPdfGenerator $contractPdfGenerator,
        private FlashService $flashService,
    ) {
    }

    public function download(array $input): void
    {
        $action = $input['action'] ?? '';
        $clientData = $_SESSION['extracted_data'] ?? null;

        if (!is_array($clientData) || $action !== 'generate_term') {
            $this->flashService->put('error', 'Sua sessao de importacao expirou. Envie a fatura novamente.');
            header('Location: index.php');
            return;
        }

        $selectedPlans = is_array($input['selected_plans'] ?? null) ? $input['selected_plans'] : [];
        if ($selectedPlans === []) {
            $this->flashService->put('error', 'Selecione pelo menos um plano antes de gerar o contrato.');
            header('Location: index.php');
            return;
        }

        try {
            $document = $this->contractPdfGenerator->generate([
                'client_data' => $clientData,
                'selected_plans' => $selectedPlans,
                'operator' => (string) ($input['operator'] ?? ''),
                'fidelity' => (string) ($input['fidelity'] ?? 'none'),
                'commercial_terms' => (string) ($input['commercial_terms'] ?? ''),
                'custom_sla' => isset($input['sla']),
            ]);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[%s] Falha ao gerar contrato: %s em %s:%d',
                date('c'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            $this->flashService->put('error', 'Nao foi possivel gerar o PDF agora. Verifique os dados enviados e tente novamente.');
            header('Location: index.php');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
        echo $document['content'];
    }
}
