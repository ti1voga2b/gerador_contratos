<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Models\PlanRepository;
use App\Services\AuthService;
use App\Services\ContractPdfGenerator;
use App\Services\InvoiceParser;

final class ContractController
{
    private AuthService $authService;
    private PlanRepository $planRepository;
    private InvoiceParser $invoiceParser;
    private ContractPdfGenerator $contractPdfGenerator;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->planRepository = new PlanRepository();
        $this->invoiceParser = new InvoiceParser();
        $this->contractPdfGenerator = new ContractPdfGenerator($this->planRepository, dirname(__DIR__, 2) . '/assets/pdf-template');
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
            $this->ensureValidCsrf();
            $this->login();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
            $this->ensureValidCsrf();
            $this->authService->logout();
            header('Location: index.php');
            return;
        }

        if (!$this->authService->check()) {
            $this->showLogin();
            return;
        }

        if (isset($_GET['reset']) && $_GET['reset'] === '1') {
            unset($_SESSION['extracted_data']);
            header('Location: index.php');
            return;
        }

        if (isset($_FILES['invoice_txt'])) {
            $this->ensureValidCsrf();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->ensureValidCsrf();
            $this->downloadDocument();
            return;
        }

        $extractedData = isset($_SESSION['extracted_data']) && is_array($_SESSION['extracted_data'])
            ? $_SESSION['extracted_data']
            : null;

        if (isset($_FILES['invoice_txt']) && (int) $_FILES['invoice_txt']['error'] === UPLOAD_ERR_OK) {
            $uploadValidationError = $this->validateUploadedInvoice($_FILES['invoice_txt']);
            if ($uploadValidationError !== null) {
                View::render('contracts/index', [
                    'error' => $uploadValidationError,
                    'extractedData' => null,
                    'plans' => $this->planRepository->all(),
                    'csrfToken' => Csrf::token(),
                    'user' => $this->authService->user(),
                ]);
                return;
            }

            $extractedData = $this->invoiceParser->parseUploadedFile($_FILES['invoice_txt']['tmp_name']);
            $_SESSION['extracted_data'] = $extractedData;
        }

        View::render('contracts/index', [
            'error' => null,
            'extractedData' => $extractedData,
            'plans' => $this->planRepository->all(),
            'csrfToken' => Csrf::token(),
            'user' => $this->authService->user(),
        ]);
    }

    private function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($this->authService->attemptLogin($username, $password)) {
            header('Location: index.php');
            return;
        }

        $this->showLogin('Usuario ou senha invalidos.');
    }

    private function showLogin(?string $error = null): void
    {
        View::render('auth/login', [
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]);
    }

    private function downloadDocument(): void
    {
        $action = $_POST['action'] ?? '';
        $clientData = $_SESSION['extracted_data'] ?? null;

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

    private function ensureValidCsrf(): void
    {
        if (Csrf::isValid($_POST['_csrf'] ?? null)) {
            return;
        }

        http_response_code(419);
        exit('Token CSRF invalido.');
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    private function validateUploadedInvoice(array $uploadedFile): ?string
    {
        $filename = (string) ($uploadedFile['name'] ?? '');
        $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
        $size = (int) ($uploadedFile['size'] ?? 0);

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return 'Envie um arquivo TXT de ate 2 MB.';
        }

        if (!is_uploaded_file($tmpName)) {
            return 'Upload invalido.';
        }

        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'txt') {
            return 'Apenas arquivos TXT sao permitidos.';
        }

        $mimeType = mime_content_type($tmpName);
        if (!is_string($mimeType) || !in_array($mimeType, ['text/plain', 'text/x-asm', 'application/octet-stream'], true)) {
            return 'O arquivo enviado nao parece ser um TXT valido.';
        }

        return null;
    }
}
