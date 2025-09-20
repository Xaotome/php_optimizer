<?php

declare(strict_types=1);

namespace PhpOptimizer\Models;

class ResponseModel
{
    public static function success(array $data = [], string $message = 'Opération réussie'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
    }

    public static function error(string $errorCode, string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('c')
        ];
    }
}