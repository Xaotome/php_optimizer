<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use PhpOptimizer\Controllers\UploadController;
use PhpOptimizer\Controllers\AnalysisController;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
try {
    $dotenv->load();
} catch (Exception $e) {
    // .env file not found or not readable, continue without it
}

$app = AppFactory::create();

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
        $response->getBody()->write(json_encode([
            'success' => false,
            'error_code' => 'CONTROLLER_ERROR',
            'message' => 'Erreur: ' . $e->getMessage()
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

$app->run();