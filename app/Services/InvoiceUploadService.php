<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class InvoiceUploadService
{
    private const MAX_FILE_SIZE = 10485760;

    public function __construct(
        private InvoiceParser $invoiceParser,
        private FlashService $flashService,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function handle(array $uploadedFile): ?string
    {
        $phpUploadError = $this->uploadErrorMessage((int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE));
        if ($phpUploadError !== null) {
            return $phpUploadError;
        }

        $uploadValidationError = $this->validate($uploadedFile);
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
            $this->flashService->put('warning', 'A fatura foi lida, mas nenhuma linha elegivel foi encontrada para montar o contrato.');
        } else {
            $this->flashService->put('success', 'Fatura carregada com sucesso. Revise os dados e gere o contrato.');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    private function validate(array $uploadedFile): ?string
    {
        $filename = (string) ($uploadedFile['name'] ?? '');
        $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
        $size = (int) ($uploadedFile['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return 'Envie um arquivo TXT de ate 10 MB.';
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
}
