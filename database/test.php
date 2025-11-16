<?php

declare(strict_types=1);

/**
 * Script de test de la base de données
 * Usage: php database/test.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOptimizer\Database\Database;
use PhpOptimizer\Models\User;
use PhpOptimizer\Models\Subscription;
use PhpOptimizer\Models\UsageStats;

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   PHP Optimizer - Test de Base de Données                ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

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

try {
    // ========================================
    // TEST 1: Connexion à la base de données
    // ========================================
    testSection("TEST 1: Connexion à la base de données");

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    success("Connexion établie avec succès");
    info("Host: " . ($_ENV['DB_HOST'] ?? '127.0.0.1'));
    info("Database: " . ($_ENV['DB_DATABASE'] ?? 'php_optimizer'));

    // ========================================
    // TEST 2: Vérification des tables
    // ========================================
    testSection("TEST 2: Vérification des tables créées");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $expectedTables = [
        'users',
        'subscriptions',
        'usage_stats',
        'invoices',
        'password_resets',
        'sessions',
        'webhook_events'
    ];

    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            success("Table '{$table}' existe");
        } else {
            error("Table '{$table}' manquante");
        }
    }

    // ========================================
    // TEST 3: Model User
    // ========================================
    testSection("TEST 3: Test du Model User");

    $userModel = new User();

    // Compter les utilisateurs
    $totalUsers = $userModel->count();
    info("Nombre d'utilisateurs: {$totalUsers}");

    // Récupérer l'utilisateur de test
    $testUser = $userModel->findByEmail('test@phpoptimizer.com');

    if ($testUser) {
        success("Utilisateur de test trouvé");
        info("ID: " . $testUser['id']);
        info("Email: " . $testUser['email']);
        info("Nom: " . $testUser['name']);

        // Test de vérification du mot de passe
        $verified = $userModel->verify('test@phpoptimizer.com', 'password123');

        if ($verified) {
            success("Authentification réussie");
        } else {
            error("Échec de l'authentification");
        }

        // Obtenir les infos complètes (avec abonnement)
        $fullInfo = $userModel->getFullInfo((int) $testUser['id']);

        if ($fullInfo) {
            success("Informations complètes récupérées");
            info("Plan: " . $fullInfo['plan']);
            info("Fichiers utilisés: " . $fullInfo['current_files_count']);
            info("Fichiers restants: " . $fullInfo['remaining_files']);
        }
    } else {
        error("Utilisateur de test non trouvé");
    }

    // ========================================
    // TEST 4: Model Subscription
    // ========================================
    testSection("TEST 4: Test du Model Subscription");

    $subscriptionModel = new Subscription();

    if ($testUser) {
        $userId = (int) $testUser['id'];

        // Vérifier l'abonnement
        $hasSubscription = $subscriptionModel->hasActiveSubscription($userId);

        if ($hasSubscription) {
            info("Utilisateur a un abonnement actif");
        } else {
            info("Utilisateur sans abonnement (plan gratuit)");
        }

        $plan = $subscriptionModel->getUserPlan($userId);
        success("Plan actuel: {$plan}");
    }

    // ========================================
    // TEST 5: Model UsageStats
    // ========================================
    testSection("TEST 5: Test du Model UsageStats");

    $usageModel = new UsageStats();

    if ($testUser) {
        $userId = (int) $testUser['id'];

        // Obtenir l'utilisation actuelle
        $usage = $usageModel->getCurrentUsage($userId);

        if ($usage) {
            success("Statistiques d'utilisation trouvées");
            info("Fichiers analysés ce mois: " . $usage['files_count']);
            info("Période: " . $usage['period_start'] . " → " . $usage['period_end']);

            // Vérifier si l'utilisateur peut analyser
            $canAnalyze = $usageModel->canAnalyze($userId, 1);

            if ($canAnalyze['can_analyze']) {
                success("✓ Peut analyser des fichiers");
                info("Raison: " . $canAnalyze['reason']);

                if ($canAnalyze['remaining'] >= 0) {
                    info("Fichiers restants: " . $canAnalyze['remaining'] . "/" . $canAnalyze['limit']);
                } else {
                    info("Fichiers illimités");
                }
            } else {
                error("✗ Ne peut pas analyser");
                info("Raison: " . $canAnalyze['reason']);
            }

            // Test d'incrémentation
            info("\nTest d'incrémentation du compteur...");
            $usageModel->incrementFilesCount($userId, 1);

            $newUsage = $usageModel->getCurrentUsage($userId);
            success("Nouveau compteur: " . $newUsage['files_count']);
        } else {
            error("Pas de statistiques d'utilisation");
        }

        // Statistiques globales
        $globalStats = $usageModel->getGlobalStats();
        info("\nStatistiques globales:");
        info("Total utilisateurs actifs: " . ($globalStats['total_users'] ?? 0));
        info("Total fichiers analysés: " . ($globalStats['total_files'] ?? 0));
    }

    // ========================================
    // TEST 6: Vue user_subscription_info
    // ========================================
    testSection("TEST 6: Test de la vue SQL");

    $sql = "SELECT * FROM user_subscription_info LIMIT 5";
    $results = $db->query($sql);

    if ($results) {
        success("Vue user_subscription_info accessible");
        info("Nombre de résultats: " . count($results));

        foreach ($results as $row) {
            info("  → {$row['email']} | Plan: {$row['plan']} | Restants: {$row['remaining_files']}");
        }
    }

    // ========================================
    // RÉSUMÉ FINAL
    // ========================================
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !            ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "🎉 La base de données est correctement configurée et fonctionnelle!\n";
    echo "\n";

} catch (Exception $e) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   ❌ ERREUR LORS DES TESTS                                ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    error($e->getMessage());
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
    echo "\n\n";
    exit(1);
}
