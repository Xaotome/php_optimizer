<?php

declare(strict_types=1);

// Test du nouvel endpoint admin_cleanup.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test du nouvel endpoint admin_cleanup.php</h1>";

// Déterminer l'URL de base
$basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
    $basePath = '/php_optimizer';
}

$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/admin_cleanup.php';

echo "<h2>URL de test: $url</h2>";

// Compter les fichiers avant
$uploadsDir = dirname(__DIR__) . '/storage/uploads';
$reportsDir = dirname(__DIR__) . '/storage/reports';

$uploadsBefore = count(glob($uploadsDir . '/*'));
$reportsBefore = count(glob($reportsDir . '/*'));

echo "<h3>État avant nettoyage:</h3>";
echo "<p>Fichiers uploads: $uploadsBefore</p>";
echo "<p>Fichiers reports: $reportsBefore</p>";

// Test avec le bon mot de passe pour nettoyer uploads
echo "<h3>Test: Nettoyage du dossier uploads</h3>";

$testData = [
    'password' => '',
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

if ($response) {
    $json = json_decode($response, true);
    if ($json && isset($json['success']) && $json['success']) {
        echo "<p><strong>✅ Nettoyage réussi !</strong></p>";
        echo "<p>Fichiers supprimés: " . $json['data']['deleted_files'] . "</p>";
    }
}

// Vérifier l'état après
$uploadsAfter = count(glob($uploadsDir . '/*'));
$reportsAfter = count(glob($reportsDir . '/*'));

echo "<h3>État après nettoyage:</h3>";
echo "<p>Fichiers uploads: $uploadsAfter (était $uploadsBefore)</p>";
echo "<p>Fichiers reports: $reportsAfter (était $reportsBefore)</p>";

echo "<h2>✅ Test terminé</h2>";
?>