<?php

declare(strict_types=1);

// Test simple de l'API analyze

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Test basique
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Vérifier l'upload
    if (empty($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No files uploaded']);
        exit;
    }

    // Réponse de test
    echo json_encode([
        'success' => true,
        'message' => 'Test API fonctionne',
        'data' => [
            'summary' => [
                'compliant' => 1,
                'warnings' => 0,
                'errors' => 0,
                'total_files' => 1
            ],
            'files' => [
                [
                    'name' => 'test.php',
                    'status' => 'success',
                    'issues' => [],
                    'psr_compliance' => [
                        ['standard' => 'PSR-1', 'compliant' => true],
                        ['standard' => 'PSR-2', 'compliant' => true],
                        ['standard' => 'PSR-4', 'compliant' => true],
                        ['standard' => 'PSR-12', 'compliant' => true]
                    ],
                    'metrics' => [
                        'total_issues' => 0,
                        'errors' => 0,
                        'warnings' => 0,
                        'info' => 0
                    ]
                ]
            ]
        ],
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>