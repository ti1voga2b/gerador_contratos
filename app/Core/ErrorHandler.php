<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        $storageDir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $logFile = $storageDir . '/error.log';

        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        ini_set('display_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception): void {
            error_log(self::formatException($exception));

            if (!headers_sent()) {
                http_response_code(500);
            }

            echo 'Ocorreu um erro interno. Consulte storage/error.log.';
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($error['type'], $fatalErrors, true)) {
                return;
            }

            error_log(sprintf(
                '[%s] Fatal error: %s in %s:%d',
                date('c'),
                $error['message'],
                $error['file'],
                $error['line']
            ));
        });
    }

    private static function formatException(Throwable $exception): string
    {
        return sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s",
            date('c'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }
}
