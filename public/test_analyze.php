<?php

declare(strict_types=1);

// Test script pour vérifier que l'endpoint /analyze simplifié fonctionne

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test de l'endpoint /analyze simplifié</h1>";

// Créer un fichier PHP de test
$testPhpContent = '<?php

declare(strict_types=1);

namespace TestApp;

class TestClass
{
    public function hello(): string
    {
        return "Hello World";
    }
}
';

// Créer un fichier temporaire
$tempFile = tempnam(sys_get_temp_dir(), 'test_php');
file_put_contents($tempFile, $testPhpContent);

echo "<h2>Contenu du fichier de test:</h2>";
echo "<pre>" . htmlspecialchars($testPhpContent) . "</pre>";

// Simuler un upload
$boundary = uniqid();
$postData = "--$boundary\r\n";
$postData .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"test.php\"\r\n";
$postData .= "Content-Type: text/plain\r\n\r\n";
$postData .= $testPhpContent;
$postData .= "\r\n--$boundary--\r\n";

// Déterminer l'URL de base
$basePath = '';
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/php_optimizer') !== false) {
    $basePath = '/php_optimizer';
}

$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/analyze';

echo "<h2>Test de l'endpoint: $url</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: multipart/form-data; boundary=$boundary",
    "Content-Length: " . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Résultat:</h3>";
echo "<p><strong>Code HTTP:</strong> $httpCode</p>";

if ($error) {
    echo "<p><strong>Erreur cURL:</strong> $error</p>";
}

if ($response) {
    echo "<p><strong>Réponse brute:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $json = json_decode($response, true);
    if ($json) {
        echo "<p><strong>Réponse JSON décodée:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    }
} else {
    echo "<p><strong>Aucune réponse reçue</strong></p>";
}

// Nettoyer le fichier temporaire
unlink($tempFile);

echo "<h2>✅ Test terminé</h2>";
?>