<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\View;
use App\Models\PlanRepository;
use App\Services\AuthService;
use App\Services\ContractDownloadService;
use App\Services\ContractPdfGenerator;
use App\Services\FlashService;
use App\Services\InvoiceUploadService;
use App\Services\InvoiceParser;

final class ContractController
{
    private AuthService $authService;
    private ContractDownloadService $contractDownloadService;
    private FlashService $flashService;
    private InvoiceUploadService $invoiceUploadService;
    private PlanRepository $planRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->flashService = new FlashService();
        $this->planRepository = new PlanRepository();
        $this->invoiceUploadService = new InvoiceUploadService(new InvoiceParser(), $this->flashService);
        $this->contractDownloadService = new ContractDownloadService(
            new ContractPdfGenerator($this->planRepository, dirname(__DIR__, 2) . '/assets/pdf-template'),
            $this->flashService
        );
    }

    public function handleRequest(): void
    {
        $autoDownload = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
            $this->ensureValidCsrf();
            $this->login();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
            $this->ensureValidCsrf();
            $this->authService->logout();
            $this->redirect('index.php');
            return;
        }

        if (!$this->authService->check()) {
            $this->showLogin();
            return;
        }

        if (isset($_GET['download_file']) && $_GET['download_file'] === '1') {
            $this->contractDownloadService->streamPending();
            return;
        }

        if (isset($_GET['download']) && $_GET['download'] === '1') {
            if (!$this->contractDownloadService->hasPending()) {
                $this->flashService->put('error', 'Nenhum arquivo esta pronto para download no momento.');
                $this->redirect('index.php');
                return;
            }

            $autoDownload = true;
        }

        if (isset($_GET['reset']) && $_GET['reset'] === '1') {
            unset($_SESSION['extracted_data']);
            $this->flashService->forget();
            $this->redirect('index.php');
            return;
        }

        if (isset($_FILES['invoice_txt'])) {
            $this->ensureValidCsrf();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->ensureValidCsrf();
            $this->contractDownloadService->queue($_POST);
            return;
        }

        $extractedData = isset($_SESSION['extracted_data']) && is_array($_SESSION['extracted_data'])
            ? $_SESSION['extracted_data']
            : null;

        if (isset($_FILES['invoice_txt'])) {
            $uploadError = $this->invoiceUploadService->handle($_FILES['invoice_txt']);
            if ($uploadError !== null) {
                $this->flashService->put('error', $uploadError);
            }

            $this->redirect('index.php');
            return;
        }

        View::render('contracts/index', [
            'autoDownload' => $autoDownload,
            'extractedData' => $extractedData,
            'flash' => $this->flashService->pull(),
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
            $this->redirect('index.php');
            return;
        }

        $this->flashService->put('error', 'Usuario ou senha invalidos.');
        $this->redirect('index.php');
    }

    private function showLogin(): void
    {
        View::render('auth/login', [
            'csrfToken' => Csrf::token(),
            'flash' => $this->flashService->pull(),
        ]);
    }

    private function ensureValidCsrf(): void
    {
        if (Csrf::isValid($_POST['_csrf'] ?? null)) {
            return;
        }

        $this->flashService->put('error', 'Sua sessao expirou. Atualize a pagina e tente novamente.');
        $this->redirect('index.php');
        exit;
    }

    private function redirect(string $location): void
    {
        header('Location: ' . $location, true, 303);
    }
}
