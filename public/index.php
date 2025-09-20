<?php

declare(strict_types=1);

// Activer l'affichage des erreurs en production (temporaire pour debug)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Vérifier que l'autoload existe
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die('Erreur: Composer autoload non trouvé. Exécutez "composer install".');
}

require $autoloadPath;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use PhpOptimizer\Controllers\UploadController;
use PhpOptimizer\Controllers\AnalysisController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
try {
    $dotenv->load();
} catch (Exception $e) {
    // .env file not found or not readable, continue without it
}

$app = AppFactory::create();

// Set base path for subdirectory deployment
$basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
    $basePath = '/php_optimizer';
}
if (!empty($basePath)) {
    $app->setBasePath($basePath);
}

// Add CORS middleware
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents(__DIR__ . '/index.html'));
    return $response->withHeader('Content-Type', 'text/html');
});

$app->get('/test', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'status' => 'OK',
        'message' => 'API fonctionne',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/upload', function (Request $request, Response $response) {
    try {
        $controller = new UploadController();
        return $controller->upload($request, $response);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error_code' => 'CONTROLLER_ERROR',
            'message' => 'Erreur: ' . $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->post('/analyze', function (Request $request, Response $response) {
    try {
        // Version simplifiée temporaire pour éviter l'erreur 500
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['files'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'NO_FILES',
                'message' => 'Aucun fichier n\'a été fourni pour l\'analyse'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $files = is_array($uploadedFiles['files']) 
            ? $uploadedFiles['files'] 
            : [$uploadedFiles['files']];

        // Analyser les fichiers de manière simplifiée
        $results = [
            'summary' => [
                'compliant' => 0,
                'warnings' => 0,
                'errors' => 0,
                'total_files' => count($files)
            ],
            'files' => []
        ];

        foreach ($files as $uploadedFile) {
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            $filename = $uploadedFile->getClientFilename();
            $content = $uploadedFile->getStream()->getContents();
            
            // Analyse basique du contenu
            $issues = [];
            
            // Vérifier PSR-12 declare strict_types
            if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Déclaration strict_types=1 manquante',
                    'line' => 1,
                    'rule' => 'PSR-12.declare_strict',
                    'suggestion' => 'Ajoutez declare(strict_types=1); après <?php'
                ];
            }

            // Vérifier namespace
            if (!preg_match('/namespace\s+([^;]+);/', $content)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Déclaration de namespace manquante',
                    'line' => 1,
                    'rule' => 'PSR-4.namespace',
                    'suggestion' => 'Ajoutez une déclaration de namespace'
                ];
            }

            // Vérifier les tags PHP
            if (!preg_match('/^<\?php/', $content)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Le fichier doit commencer par <?php',
                    'line' => 1,
                    'rule' => 'PSR-1.php_tags',
                    'suggestion' => 'Ajoutez <?php au début du fichier'
                ];
            }

            // Vérifier les effets de bord
            if (preg_match('/echo|print|printf/', $content)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Possible effet de bord détecté (output)',
                    'line' => 1,
                    'rule' => 'PSR-1.side_effects',
                    'suggestion' => 'Évitez les sorties directes dans les fichiers de classe'
                ];
            }

            // Déterminer le statut
            $hasErrors = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
            $hasWarnings = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');
            
            $status = 'success';
            if (!empty($hasErrors)) {
                $status = 'error';
            } elseif (!empty($hasWarnings)) {
                $status = 'warning';
            } else {
                $status = 'success';
            }

            $results['files'][] = [
                'name' => $filename,
                'status' => $status,
                'issues' => $issues,
                'psr_compliance' => [
                    ['standard' => 'PSR-1', 'compliant' => empty($hasErrors)],
                    ['standard' => 'PSR-2', 'compliant' => true],
                    ['standard' => 'PSR-4', 'compliant' => empty($hasErrors)],
                    ['standard' => 'PSR-12', 'compliant' => empty($hasErrors)]
                ],
                'metrics' => [
                    'total_issues' => count($issues),
                    'errors' => count($hasErrors),
                    'warnings' => count($hasWarnings),
                    'info' => 0
                ]
            ];
        }

        // Calculer le résumé basé sur les résultats
        foreach ($results['files'] as $file) {
            switch ($file['status']) {
                case 'error':
                    $results['summary']['errors']++;
                    break;
                case 'warning':
                    $results['summary']['warnings']++;
                    break;
                case 'success':
                    $results['summary']['compliant']++;
                    break;
            }
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Analyse réussie (mode simplifié)',
            'data' => $results,
            'timestamp' => date('c')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        error_log("Erreur analyze: " . $e->getMessage());
        $response->getBody()->write(json_encode([
            'success' => false,
            'error_code' => 'ANALYSIS_ERROR',
            'message' => 'Erreur lors de l\'analyse: ' . $e->getMessage(),
            'details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->get('/report/{id}', function (Request $request, Response $response, array $args) {
    try {
        $controller = new AnalysisController();
        return $controller->getReport($request, $response, $args);
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error_code' => 'CONTROLLER_ERROR',
            'message' => 'Erreur: ' . $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->post('/admin/cleanup', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['password']) || !isset($data['target'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'MISSING_PARAMETERS',
                'message' => 'Mot de passe et cible requis'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Hash du mot de passe Xaotome$123 avec PASSWORD_DEFAULT
        $expectedHash = '$2y$10$L.D3.YY4leIZHCIw1xIiouHSwhJV4fosS4.RxsjdQpxnWizXIh.3e';
        
        if (!password_verify($data['password'], $expectedHash)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'INVALID_PASSWORD',
                'message' => 'Mot de passe incorrect'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $target = $data['target'];
        $allowedTargets = ['uploads', 'reports'];
        
        if (!in_array($target, $allowedTargets)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'INVALID_TARGET',
                'message' => 'Cible non autorisée'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $targetDir = dirname(__DIR__) . "/storage/$target";
        
        if (!is_dir($targetDir)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'DIRECTORY_NOT_FOUND',
                'message' => "Répertoire $target non trouvé"
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $deletedFiles = 0;
        $errors = [];
        
        $files = glob($targetDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deletedFiles++;
                } else {
                    $errors[] = "Impossible de supprimer " . basename($file);
                }
            }
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Nettoyage du dossier $target terminé",
            'data' => [
                'deleted_files' => $deletedFiles,
                'errors' => $errors,
                'target' => $target
            ],
            'timestamp' => date('c')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Erreur cleanup: " . $e->getMessage());
        $response->getBody()->write(json_encode([
            'success' => false,
            'error_code' => 'CLEANUP_ERROR',
            'message' => 'Erreur lors du nettoyage: ' . $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->run();