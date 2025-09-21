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

// Détecter le base path de plusieurs façons
if (isset($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir !== '/' && $scriptDir !== '.') {
        $basePath = $scriptDir;
    }
}

// Méthode alternative pour la détection
if (empty($basePath) && isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);

    if ($scriptPath !== '/' && strpos($requestPath, $scriptPath) === 0) {
        $basePath = $scriptPath;
    }
}

// Forcer le base path si on est dans php_optimizer
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/php_optimizer') !== false) {
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

// Route OPTIONS pour CORS preflight
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

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

$app->get('/debug', function (Request $request, Response $response) {
    $uri = $request->getUri();
    $response->getBody()->write(json_encode([
        'method' => $request->getMethod(),
        'path' => $uri->getPath(),
        'query' => $uri->getQuery(),
        'base_path' => $request->getAttribute('basePath'),
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'server' => [
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
            'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'N/A'
        ]
    ], JSON_PRETTY_PRINT));
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
        $controller = new AnalysisController();
        return $controller->analyze($request, $response);
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
        
        $expectedHash = '$2y$10$fHW5MJBXnbNi1TP.TqMe6.15oyYN1y0/owLUM5mp1HE3AYiJ/aksO';
        
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

// Route catch-all pour diagnostiquer les routes non trouvées (à placer en dernier)
$app->any('/{routes:.+}', function (Request $request, Response $response) {
    $uri = $request->getUri();
    $response->getBody()->write(json_encode([
        'error' => 'Route non trouvée',
        'method' => $request->getMethod(),
        'path' => $uri->getPath(),
        'available_routes' => [
            'GET /' => 'Page d\'accueil',
            'GET /test' => 'Test API',
            'GET /debug' => 'Informations de débogage',
            'POST /analyze' => 'Analyse de fichiers',
            'POST /upload' => 'Upload de fichiers',
            'GET /report/{id}' => 'Récupération de rapport'
        ]
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
});

$app->run();