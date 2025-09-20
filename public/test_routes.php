<?php

declare(strict_types=1);

// Test de tous les endpoints

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test de tous les endpoints</h1>";

// Déterminer l'URL de base
$basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
    $basePath = '/php_optimizer';
}

$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath;

echo "<p><strong>URL de base:</strong> $baseUrl</p>";

// Liste des endpoints à tester
$endpoints = [
    ['GET', '/test', 'Test simple'],
    ['GET', '/', 'Page principale'],
    ['POST', '/admin/cleanup', 'Nettoyage admin']
];

foreach ($endpoints as [$method, $path, $description]) {
    echo "<h3>$description ($method $path)</h3>";
    
    $url = $baseUrl . $path;
    echo "<p>URL: $url</p>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($path === '/admin/cleanup') {
            $postData = json_encode(['password' => 'test', 'target' => 'uploads']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>Code HTTP:</strong> $httpCode</p>";
    
    if ($error) {
        echo "<p><strong>Erreur cURL:</strong> $error</p>";
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "<p><strong>Status:</strong> ✅ OK</p>";
    } elseif ($httpCode >= 400 && $httpCode < 500) {
        echo "<p><strong>Status:</strong> ⚠️ Client Error (normal pour certains tests)</p>";
    } elseif ($httpCode >= 500) {
        echo "<p><strong>Status:</strong> ❌ Server Error</p>";
    } else {
        echo "<p><strong>Status:</strong> ❓ Autre ($httpCode)</p>";
    }
    
    if ($response && strlen($response) < 500) {
        echo "<p><strong>Réponse:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } elseif ($response) {
        echo "<p><strong>Réponse:</strong> " . strlen($response) . " caractères (tronquée)</p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 200)) . "...</pre>";
    }
    
    echo "<hr>";
}

echo "<h2>✅ Test terminé</h2>";
?>