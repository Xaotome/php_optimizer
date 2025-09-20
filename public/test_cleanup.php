<?php

declare(strict_types=1);

// Test de l'endpoint de nettoyage

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test de l'endpoint /admin/cleanup</h1>";

// Test 1: Vérifier que l'endpoint existe
$basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
    $basePath = '/php_optimizer';
}

$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/admin/cleanup';

echo "<h2>URL de test: $url</h2>";

// Test 2: Essayer avec un mauvais mot de passe
echo "<h3>Test 1: Mauvais mot de passe</h3>";
$testData = [
    'password' => 'wrong_password',
    'target' => 'uploads'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Code HTTP:</strong> $httpCode</p>";
if ($error) {
    echo "<p><strong>Erreur cURL:</strong> $error</p>";
}
echo "<p><strong>Réponse:</strong></p>";
echo "<pre>" . htmlspecialchars($response ?: 'Aucune réponse') . "</pre>";

// Test 3: Essayer avec le bon mot de passe
echo "<h3>Test 2: Bon mot de passe</h3>";
$testData = [
    'password' => 'Xaotome$123',
    'target' => 'uploads'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Code HTTP:</strong> $httpCode</p>";
if ($error) {
    echo "<p><strong>Erreur cURL:</strong> $error</p>";
}
echo "<p><strong>Réponse:</strong></p>";
echo "<pre>" . htmlspecialchars($response ?: 'Aucune réponse') . "</pre>";

// Test 4: Vérifier que les dossiers existent
echo "<h3>Test 3: Vérification des dossiers</h3>";
$uploadsDir = dirname(__DIR__) . '/storage/uploads';
$reportsDir = dirname(__DIR__) . '/storage/reports';

echo "<p><strong>Dossier uploads:</strong> $uploadsDir</p>";
echo "<p>Existe: " . (is_dir($uploadsDir) ? '✅' : '❌') . "</p>";
if (is_dir($uploadsDir)) {
    $files = glob($uploadsDir . '/*');
    echo "<p>Fichiers: " . count($files) . "</p>";
}

echo "<p><strong>Dossier reports:</strong> $reportsDir</p>";
echo "<p>Existe: " . (is_dir($reportsDir) ? '✅' : '❌') . "</p>";
if (is_dir($reportsDir)) {
    $files = glob($reportsDir . '/*');
    echo "<p>Fichiers: " . count($files) . "</p>";
}

echo "<h2>✅ Test terminé</h2>";
?>