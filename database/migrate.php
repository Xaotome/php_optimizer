<?php

declare(strict_types=1);

/**
 * Script de migration de base de données
 * Usage: php database/migrate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOptimizer\Database\Database;

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║   PHP Optimizer - Migration de Base de Données           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Connexion à la base de données
    echo "📡 Connexion à la base de données...\n";
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "✅ Connexion établie avec succès!\n\n";

    // Lecture du fichier de migration
    $migrationFile = __DIR__ . '/migrations/001_create_tables.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Fichier de migration introuvable: {$migrationFile}");
    }

    echo "📄 Lecture du fichier de migration...\n";
    $sql = file_get_contents($migrationFile);

    // Séparer les requêtes (attention aux DELIMITER pour les procédures)
    $statements = explode(';', $sql);

    echo "🚀 Exécution des migrations...\n\n";

    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Ignorer les commentaires et les lignes vides
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
            continue;
        }

        // Ignorer les commandes DELIMITER
        if (stripos($statement, 'DELIMITER') !== false) {
            continue;
        }

        try {
            // Afficher un aperçu de la requête
            $preview = substr($statement, 0, 60);
            if (strlen($statement) > 60) {
                $preview .= '...';
            }

            echo "   → " . trim(preg_replace('/\s+/', ' ', $preview)) . "\n";

            $pdo->exec($statement);
            $executed++;

        } catch (PDOException $e) {
            // Ignorer certaines erreurs courantes (table existe déjà, etc.)
            if (
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false
            ) {
                echo "   ⚠️  Déjà existant (ignoré)\n";
            } else {
                echo "   ❌ Erreur: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   RÉSUMÉ DE LA MIGRATION                                  ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "✅ Requêtes exécutées: {$executed}\n";

    if ($errors > 0) {
        echo "❌ Erreurs rencontrées: {$errors}\n";
    } else {
        echo "✨ Migration complétée sans erreur!\n";
    }

    echo "\n";
    echo "📊 Vérification des tables créées:\n";

    // Liste des tables créées
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Compter les enregistrements
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo "   ✓ {$table} ({$count} enregistrement" . ($count > 1 ? 's' : '') . ")\n";
    }

    echo "\n";
    echo "🎉 Migration terminée avec succès!\n";
    echo "\n";

    // Afficher les informations de test
    echo "═══════════════════════════════════════════════════════════\n";
    echo "COMPTE DE TEST CRÉÉ:\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Email    : test@phpoptimizer.com\n";
    echo "Password : password123\n";
    echo "Plan     : Gratuit (20 fichiers/mois)\n";
    echo "Utilisés : 5 fichiers\n";
    echo "Restants : 15 fichiers\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n\n";
    exit(1);
}
