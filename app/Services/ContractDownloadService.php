<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class ContractDownloadService
{
    private const SESSION_KEY = 'pending_download';

    public function __construct(
        private ContractPdfGenerator $contractPdfGenerator,
        private FlashService $flashService,
    ) {
    }

    public function queue(array $input): void
    {
        $action = $input['action'] ?? '';
        $clientData = $_SESSION['extracted_data'] ?? null;

        if (!is_array($clientData) || $action !== 'generate_term') {
            $this->flashService->put('error', 'Sua sessao de importacao expirou. Envie a fatura novamente.');
            $this->redirect('index.php');
            return;
        }

        $selectedPlans = is_array($input['selected_plans'] ?? null) ? $input['selected_plans'] : [];
        if ($selectedPlans === []) {
            $this->flashService->put('error', 'Selecione pelo menos um plano antes de gerar o contrato.');
            $this->redirect('index.php');
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
            $this->redirect('index.php');
            return;
        }

        $_SESSION[self::SESSION_KEY] = $document;
        $this->redirect('index.php?download=1');
    }

    public function streamPending(): void
    {
        $document = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($document) || !isset($document['filename'], $document['content'])) {
            $this->flashService->put('error', 'Nenhum arquivo esta pronto para download no momento.');
            $this->redirect('index.php');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
        echo $document['content'];
    }

    public function hasPending(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY]);
    }

    private function redirect(string $location): void
    {
        header('Location: ' . $location, true, 303);
    }
}
