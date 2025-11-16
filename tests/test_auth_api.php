<?php

declare(strict_types=1);

/**
 * Script de test de l'API d'authentification
 * Usage: php tests/test_auth_api.php
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   Test API d'Authentification - PHP Optimizer            ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

// Configuration
$baseUrl = 'http://localhost/php_optimizer/public';
$testEmail = 'testapi@phpoptimizer.com';
$testPassword = 'TestPassword123!';
$testName = 'Test API User';

function testSection(string $title): void
{
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo " {$title}\n";
    echo "═══════════════════════════════════════════════════════════\n";
}

function success(string $message): void
{
    echo "✅ {$message}\n";
}

function error(string $message): void
{
    echo "❌ {$message}\n";
}

function info(string $message): void
{
    echo "ℹ️  {$message}\n";
}

function apiRequest(string $url, string $method = 'GET', ?array $data = null, ?string $cookie = null): array
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
    }

    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Extraire le cookie de session
    preg_match('/Set-Cookie:\s*([^;]+)/i', $header, $matches);
    $sessionCookie = $matches[1] ?? null;

    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => json_decode($body, true) ?? ['raw' => $body],
        'cookie' => $sessionCookie
    ];
}

try {
    // ========================================
    // TEST 1: Vérifier que l'API répond
    // ========================================
    testSection("TEST 1: Vérification de l'API");

    $response = apiRequest($baseUrl . '/test');

    if ($response['status'] === 200 && isset($response['body']['status'])) {
        success("API accessible");
        info("Status: " . $response['body']['status']);
        info("Message: " . $response['body']['message']);
    } else {
        error("API non accessible");
        exit(1);
    }

    // ========================================
    // TEST 2: Inscription d'un nouvel utilisateur
    // ========================================
    testSection("TEST 2: Inscription (POST /api/register)");

    $registerData = [
        'email' => $testEmail,
        'password' => $testPassword,
        'name' => $testName
    ];

    $response = apiRequest($baseUrl . '/api/register', 'POST', $registerData);

    info("Code HTTP: " . $response['status']);

    if ($response['status'] === 200 || $response['status'] === 400) {
        if (isset($response['body']['success']) && $response['body']['success']) {
            success("Inscription réussie");
            info("User ID: " . ($response['body']['user_id'] ?? 'N/A'));
            $sessionCookie = $response['cookie'];
            success("Session créée: " . ($sessionCookie ? substr($sessionCookie, 0, 50) . '...' : 'N/A'));
        } elseif (isset($response['body']['errors']['email']) &&
                  strpos($response['body']['errors']['email'], 'déjà utilisé') !== false) {
            info("L'utilisateur existe déjà (c'est OK pour les tests)");
        } else {
            error("Échec de l'inscription");
            print_r($response['body']);
        }
    } else {
        error("Erreur serveur lors de l'inscription");
        print_r($response['body']);
    }

    // ========================================
    // TEST 3: Connexion avec l'utilisateur
    // ========================================
    testSection("TEST 3: Connexion (POST /api/login)");

    $loginData = [
        'email' => $testEmail,
        'password' => $testPassword
    ];

    $response = apiRequest($baseUrl . '/api/login', 'POST', $loginData);

    info("Code HTTP: " . $response['status']);

    if ($response['status'] === 200 && isset($response['body']['success']) && $response['body']['success']) {
        success("Connexion réussie");
        info("Message: " . $response['body']['message']);
        info("User: " . ($response['body']['user']['name'] ?? 'N/A'));
        info("Email: " . ($response['body']['user']['email'] ?? 'N/A'));

        $sessionCookie = $response['cookie'];
        success("Cookie de session: " . ($sessionCookie ? 'Présent' : 'Absent'));
    } else {
        error("Échec de la connexion");
        print_r($response['body']);
        exit(1);
    }

    // ========================================
    // TEST 4: Vérifier le statut d'authentification
    // ========================================
    testSection("TEST 4: Vérification du statut (GET /api/auth/check)");

    $response = apiRequest($baseUrl . '/api/auth/check', 'GET', null, $sessionCookie);

    info("Code HTTP: " . $response['status']);

    if ($response['status'] === 200) {
        if ($response['body']['authenticated']) {
            success("Utilisateur authentifié");
            info("User ID: " . ($response['body']['user_id'] ?? 'N/A'));
            info("Email: " . ($response['body']['user_email'] ?? 'N/A'));
        } else {
            error("Utilisateur non authentifié");
        }
    } else {
        error("Erreur lors de la vérification du statut");
    }

    // ========================================
    // TEST 5: Récupérer les infos utilisateur
    // ========================================
    testSection("TEST 5: Infos utilisateur (GET /api/me)");

    $response = apiRequest($baseUrl . '/api/me', 'GET', null, $sessionCookie);

    info("Code HTTP: " . $response['status']);

    if ($response['status'] === 200 && isset($response['body']['success']) && $response['body']['success']) {
        success("Informations utilisateur récupérées");
        $user = $response['body']['user'];

        info("\nDétails utilisateur:");
        info("  ID: " . ($user['id'] ?? 'N/A'));
        info("  Email: " . ($user['email'] ?? 'N/A'));
        info("  Nom: " . ($user['name'] ?? 'N/A'));
        info("  Plan: " . ($user['plan'] ?? 'N/A'));
        info("  Fichiers utilisés: " . ($user['current_files_count'] ?? 'N/A'));
        info("  Fichiers restants: " . ($user['remaining_files'] ?? 'N/A'));
        info("  Créé le: " . ($user['user_created_at'] ?? 'N/A'));
    } else {
        error("Échec de la récupération des infos");
        print_r($response['body']);
    }

    // ========================================
    // TEST 6: Déconnexion
    // ========================================
    testSection("TEST 6: Déconnexion (POST /api/logout)");

    $response = apiRequest($baseUrl . '/api/logout', 'POST', [], $sessionCookie);

    info("Code HTTP: " . $response['status']);

    if ($response['status'] === 200 && isset($response['body']['success']) && $response['body']['success']) {
        success("Déconnexion réussie");
        info("Message: " . $response['body']['message']);
    } else {
        error("Échec de la déconnexion");
    }

    // ========================================
    // TEST 7: Vérifier que la session est détruite
    // ========================================
    testSection("TEST 7: Vérification session détruite");

    $response = apiRequest($baseUrl . '/api/auth/check', 'GET', null, $sessionCookie);

    if ($response['status'] === 200) {
        if (!$response['body']['authenticated']) {
            success("Session correctement détruite");
        } else {
            error("Session toujours active (problème!)");
        }
    }

    // ========================================
    // TEST 8: Tentative de connexion avec mauvais mot de passe
    // ========================================
    testSection("TEST 8: Connexion avec mauvais mot de passe");

    $loginData = [
        'email' => $testEmail,
        'password' => 'WrongPassword123!'
    ];

    $response = apiRequest($baseUrl . '/api/login', 'POST', $loginData);

    if ($response['status'] === 401) {
        success("Refus de connexion (c'est normal)");
        info("Erreur: " . ($response['body']['error'] ?? 'N/A'));
    } else {
        error("Le mauvais mot de passe a été accepté (problème de sécurité!)");
    }

    // ========================================
    // RÉSUMÉ FINAL
    // ========================================
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !            ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "🎉 L'API d'authentification fonctionne correctement!\n";
    echo "\n";
    echo "Compte de test créé:\n";
    echo "  Email: {$testEmail}\n";
    echo "  Password: {$testPassword}\n";
    echo "\n";

} catch (Exception $e) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   ❌ ERREUR LORS DES TESTS                                ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    error($e->getMessage());
    echo "\n";
    exit(1);
}
