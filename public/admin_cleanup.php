<?php

declare(strict_types=1);

// Endpoint de nettoyage simplifié (contournement)

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error_code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Seules les requêtes POST sont autorisées'
        ]);
        exit;
    }

    // Lire les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Données JSON invalides'
        ]);
        exit;
    }
    
    if (!isset($data['password']) || !isset($data['target'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_code' => 'MISSING_PARAMETERS',
            'message' => 'Mot de passe et cible requis'
        ]);
        exit;
    }
    
    // Hash du mot de passe Xaotome$123
    $expectedHash = '$2y$10$L.D3.YY4leIZHCIw1xIiouHSwhJV4fosS4.RxsjdQpxnWizXIh.3e';
    
    if (!password_verify($data['password'], $expectedHash)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error_code' => 'INVALID_PASSWORD',
            'message' => 'Mot de passe incorrect'
        ]);
        exit;
    }
    
    $target = $data['target'];
    $allowedTargets = ['uploads', 'reports'];
    
    if (!in_array($target, $allowedTargets)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error_code' => 'INVALID_TARGET',
            'message' => 'Cible non autorisée'
        ]);
        exit;
    }
    
    $targetDir = dirname(__DIR__) . "/storage/$target";
    
    if (!is_dir($targetDir)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error_code' => 'DIRECTORY_NOT_FOUND',
            'message' => "Répertoire $target non trouvé"
        ]);
        exit;
    }
    
    $deletedFiles = 0;
    $errors = [];
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $files = glob($targetDir . '/*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deletedFiles++;
                } else {
                    $errors[] = "Impossible de supprimer " . basename($file);
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Nettoyage du dossier $target terminé",
        'data' => [
            'deleted_files' => $deletedFiles,
            'errors' => $errors,
            'target' => $target,
            'target_dir' => $targetDir
        ],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    error_log("Erreur cleanup: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error_code' => 'CLEANUP_ERROR',
        'message' => 'Erreur lors du nettoyage: ' . $e->getMessage(),
        'details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>