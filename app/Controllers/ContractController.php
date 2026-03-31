<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Models\PlanRepository;
use App\Services\AuthService;
use App\Services\ContractPdfGenerator;
use App\Services\InvoiceParser;
use Throwable;

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
            $this->forgetFlash();
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

        if (isset($_FILES['invoice_txt'])) {
            $uploadError = $this->handleInvoiceUpload($_FILES['invoice_txt']);
            if ($uploadError !== null) {
                $this->flash('error', $uploadError);
                $extractedData = null;
            } else {
                $extractedData = isset($_SESSION['extracted_data']) && is_array($_SESSION['extracted_data'])
                    ? $_SESSION['extracted_data']
                    : null;
            }
        }

        View::render('contracts/index', [
            'extractedData' => $extractedData,
            'flash' => $this->pullFlash(),
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
            $this->flash('error', 'Sua sessao de importacao expirou. Envie a fatura novamente.');
            header('Location: index.php');
            return;
        }

        $selectedPlans = is_array($_POST['selected_plans'] ?? null) ? $_POST['selected_plans'] : [];
        if ($selectedPlans === []) {
            $this->flash('error', 'Selecione pelo menos um plano antes de gerar o contrato.');
            header('Location: index.php');
            return;
        }

        try {
            $document = $this->contractPdfGenerator->generate([
                'client_data' => $clientData,
                'selected_plans' => $selectedPlans,
                'operator' => (string) ($_POST['operator'] ?? ''),
                'fidelity' => (string) ($_POST['fidelity'] ?? 'none'),
                'commercial_terms' => (string) ($_POST['commercial_terms'] ?? ''),
                'custom_sla' => isset($_POST['sla']),
            ]);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[%s] Falha ao gerar contrato: %s em %s:%d',
                date('c'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            $this->flash('error', 'Nao foi possivel gerar o PDF agora. Verifique os dados enviados e tente novamente.');
            header('Location: index.php');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
        echo $document['content'];
    }

    private function ensureValidCsrf(): void
    {
        if (Csrf::isValid($_POST['_csrf'] ?? null)) {
            return;
        }

        $this->flash('error', 'Sua sessao expirou. Atualize a pagina e tente novamente.');
        http_response_code(419);
        header('Location: index.php');
        exit;
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    private function handleInvoiceUpload(array $uploadedFile): ?string
    {
        $phpUploadError = $this->uploadErrorMessage((int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE));
        if ($phpUploadError !== null) {
            return $phpUploadError;
        }

        $uploadValidationError = $this->validateUploadedInvoice($uploadedFile);
        if ($uploadValidationError !== null) {
            return $uploadValidationError;
        }

        try {
            $extractedData = $this->invoiceParser->parseUploadedFile((string) $uploadedFile['tmp_name']);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[%s] Falha ao processar fatura: %s em %s:%d',
                date('c'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            return 'Nao foi possivel ler a fatura enviada. Confira se o arquivo esta integro e em formato TXT.';
        }

        if (!is_array($extractedData)) {
            return 'Nao foi possivel interpretar a fatura enviada.';
        }

        $_SESSION['extracted_data'] = $extractedData;

        if (($extractedData['lines'] ?? []) === []) {
            $this->flash('warning', 'A fatura foi lida, mas nenhuma linha elegivel foi encontrada para montar o contrato.');
        } else {
            $this->flash('success', 'Fatura carregada com sucesso. Revise os dados e gere o contrato.');
        }

        return null;
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

    private function uploadErrorMessage(int $errorCode): ?string
    {
        return match ($errorCode) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_NO_FILE => 'Selecione um arquivo TXT para continuar.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho maximo permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'O upload foi interrompido antes de terminar. Tente novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor esta sem diretorio temporario para upload.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu gravar o arquivo enviado.',
            UPLOAD_ERR_EXTENSION => 'Uma extensao do PHP bloqueou o upload do arquivo.',
            default => 'Falha inesperada ao enviar o arquivo.',
        };
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    private function forgetFlash(): void
    {
        unset($_SESSION['flash']);
    }
}
