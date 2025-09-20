<?php

declare(strict_types=1);

// Script de diagnostic avancé pour l'erreur 500

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Diagnostic avancé - Erreur 500</h1>";

echo "<h2>1. Test d'inclusion des fichiers critiques</h2>";

// Test autoload
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
echo "Autoload path: $autoloadPath<br>";
echo "Autoload exists: " . (file_exists($autoloadPath) ? '✅' : '❌') . "<br>";

if (file_exists($autoloadPath)) {
    try {
        require_once $autoloadPath;
        echo "Autoload loaded: ✅<br>";
    } catch (Throwable $e) {
        echo "Autoload error: ❌ " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    }
}

echo "<br><h2>2. Test de chargement des classes</h2>";

// Test classes principales
$classes = [
    'Slim\\Factory\\AppFactory',
    'PhpOptimizer\\Controllers\\UploadController',
    'PhpOptimizer\\Controllers\\AnalysisController',
    'PhpOptimizer\\Services\\AnalysisService',
    'PhpOptimizer\\Services\\FileUploadService'
];

foreach ($classes as $class) {
    try {
        if (class_exists($class)) {
            echo "$class: ✅<br>";
            
            // Test d'instanciation pour les contrôleurs
            if (strpos($class, 'Controller') !== false) {
                try {
                    $instance = new $class();
                    echo "&nbsp;&nbsp;→ Instanciation: ✅<br>";
                } catch (Throwable $e) {
                    echo "&nbsp;&nbsp;→ Instanciation: ❌ " . $e->getMessage() . "<br>";
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
                }
            }
        } else {
            echo "$class: ❌ Not found<br>";
        }
    } catch (Throwable $e) {
        echo "$class: ❌ Error - " . $e->getMessage() . "<br>";
        echo "&nbsp;&nbsp;File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    }
}

echo "<br><h2>3. Test de création de l'application Slim</h2>";

try {
    $app = \Slim\Factory\AppFactory::create();
    echo "Slim app creation: ✅<br>";
    
    // Test base path
    $basePath = '';
    if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
        $basePath = '/php_optimizer';
    }
    if (!empty($basePath)) {
        $app->setBasePath($basePath);
        echo "Base path set: ✅ ($basePath)<br>";
    }
    
} catch (Throwable $e) {
    echo "Slim app creation: ❌ " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<br><h2>4. Test de simulation d'une route</h2>";

try {
    $app->get('/debug-test', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'OK', 'message' => 'Route de test fonctionne']));
        return $response->withHeader('Content-Type', 'application/json');
    });
    echo "Route test added: ✅<br>";
} catch (Throwable $e) {
    echo "Route test error: ❌ " . $e->getMessage() . "<br>";
}

echo "<br><h2>5. Vérification des permissions</h2>";

$dirs = [
    'storage' => dirname(__DIR__) . '/storage',
    'storage/uploads' => dirname(__DIR__) . '/storage/uploads',
    'storage/reports' => dirname(__DIR__) . '/storage/reports',
    'storage/logs' => dirname(__DIR__) . '/storage/logs'
];

foreach ($dirs as $name => $path) {
    echo "$name: ";
    if (is_dir($path)) {
        echo "✅ exists ";
        echo (is_readable($path) ? "✅ readable " : "❌ not readable ");
        echo (is_writable($path) ? "✅ writable" : "❌ not writable");
    } else {
        echo "❌ missing";
    }
    echo "<br>";
}

echo "<br><h2>6. Variables d'environnement critiques</h2>";

echo "PHP_VERSION: " . PHP_VERSION . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'undefined') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'undefined') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'undefined') . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";

echo "<br><h2>✅ Diagnostic terminé</h2>";
echo "<p><strong>Si toutes les vérifications sont OK mais l'erreur 500 persiste, le problème vient probablement d'une erreur dans le code de l'endpoint /analyze lui-même.</strong></p>";

?>