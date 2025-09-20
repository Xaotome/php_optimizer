<?php
// Script de diagnostic pour identifier l'erreur 500

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnostic PHP Optimizer</h1>";

// Test 1: Version PHP
echo "<h2>1. Version PHP</h2>";
echo "Version: " . PHP_VERSION . "<br>";
echo "Minimum requis: 8.2<br>";
echo "Status: " . (version_compare(PHP_VERSION, '8.2.0', '>=') ? '✅ OK' : '❌ Trop ancienne') . "<br><br>";

// Test 2: Extensions PHP
echo "<h2>2. Extensions PHP</h2>";
$requiredExtensions = ['json', 'mbstring', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅ OK' : '❌ Manquante') . "<br>";
}
echo "<br>";

// Test 3: Autoload Composer
echo "<h2>3. Autoload Composer</h2>";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
echo "Chemin: $autoloadPath<br>";
echo "Existe: " . (file_exists($autoloadPath) ? '✅ OK' : '❌ Manquant') . "<br>";

if (file_exists($autoloadPath)) {
    try {
        require_once $autoloadPath;
        echo "Chargement: ✅ OK<br>";
    } catch (Exception $e) {
        echo "Chargement: ❌ Erreur - " . $e->getMessage() . "<br>";
    }
}
echo "<br>";

// Test 4: Répertoires
echo "<h2>4. Répertoires</h2>";
$directories = [
    'storage' => __DIR__ . '/../storage',
    'storage/uploads' => __DIR__ . '/../storage/uploads',
    'storage/reports' => __DIR__ . '/../storage/reports',
    'src' => __DIR__ . '/../src',
    'vendor' => __DIR__ . '/../vendor'
];

foreach ($directories as $name => $path) {
    echo "$name: $path<br>";
    echo "Existe: " . (is_dir($path) ? '✅ OK' : '❌ Manquant') . "<br>";
    if (is_dir($path)) {
        echo "Permissions: " . (is_writable($path) ? '✅ Écriture' : '⚠️ Lecture seule') . "<br>";
    }
    echo "<br>";
}

// Test 5: Classes principales
echo "<h2>5. Classes principales</h2>";
if (class_exists('Slim\\Factory\\AppFactory')) {
    echo "Slim Framework: ✅ OK<br>";
} else {
    echo "Slim Framework: ❌ Non chargé<br>";
}

try {
    if (class_exists('PhpOptimizer\\Controllers\\AnalysisController')) {
        echo "AnalysisController: ✅ OK<br>";
    } else {
        echo "AnalysisController: ❌ Non trouvé<br>";
    }
} catch (Exception $e) {
    echo "AnalysisController: ❌ Erreur - " . $e->getMessage() . "<br>";
}

// Test 6: Variables d'environnement
echo "<h2>6. Variables serveur</h2>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Non défini') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Non défini') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Non défini') . "<br>";

echo "<h2>✅ Diagnostic terminé</h2>";
?>