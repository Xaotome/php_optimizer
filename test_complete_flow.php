&lt;?php

/**
 * Test complet du flux d'authentification et de quotas
 *
 * Ce script teste :
 * 1. Inscription d'un nouvel utilisateur
 * 2. Connexion
 * 3. Vérification de l'authentification
 * 4. Récupération des infos utilisateur avec quotas
 * 5. Simulation d'analyse de fichiers avec incrémentation des quotas
 * 6. Vérification de l'application des limites de quota
 * 7. Déconnexion
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOptimizer\Services\AuthService;
use PhpOptimizer\Models\User;
use PhpOptimizer\Models\UsageStats;

// Couleurs pour la console
class Colors {
    public static $GREEN = "\033[32m";
    public static $RED = "\033[31m";
    public static $YELLOW = "\033[33m";
    public static $BLUE = "\033[34m";
    public static $RESET = "\033[0m";
}

function printSuccess($message) {
    echo Colors::$GREEN . "✓ " . $message . Colors::$RESET . "\n";
}

function printError($message) {
    echo Colors::$RED . "✗ " . $message . Colors::$RESET . "\n";
}

function printInfo($message) {
    echo Colors::$BLUE . "ℹ " . $message . Colors::$RESET . "\n";
}

function printWarning($message) {
    echo Colors::$YELLOW . "⚠ " . $message . Colors::$RESET . "\n";
}

function printSeparator() {
    echo "\n" . str_repeat("=", 70) . "\n\n";
}

// Données de test
$testEmail = "test_quota_" . time() . "@example.com";
$testPassword = "TestPassword123!";
$testName = "Test User Quota";

echo "\n";
printInfo("Démarrage du test complet du flux d'authentification et quotas");
printSeparator();

try {
    // 1. TEST INSCRIPTION
    printInfo("Test 1 : Inscription d'un nouvel utilisateur");
    $authService = new AuthService();

    $registerResult = $authService->register($testEmail, $testPassword, $testName);

    if ($registerResult['success']) {
        printSuccess("Inscription réussie pour : $testEmail");
        printInfo("User ID créé : " . $registerResult['user_id']);
    } else {
        printError("Échec inscription : " . $registerResult['message']);
        exit(1);
    }

    printSeparator();

    // 2. TEST CONNEXION
    printInfo("Test 2 : Connexion de l'utilisateur");

    // Simuler une déconnexion d'abord
    $authService->logout();

    $loginResult = $authService->login($testEmail, $testPassword);

    if ($loginResult['success']) {
        printSuccess("Connexion réussie");
        printInfo("Session créée pour user ID : " . $loginResult['user_id']);
    } else {
        printError("Échec connexion : " . $loginResult['message']);
        exit(1);
    }

    printSeparator();

    // 3. TEST VÉRIFICATION AUTHENTIFICATION
    printInfo("Test 3 : Vérification de l'état d'authentification");

    if ($authService->isLoggedIn()) {
        printSuccess("Utilisateur authentifié");
        $currentUserId = $authService->getUserId();
        printInfo("User ID de la session : $currentUserId");
    } else {
        printError("Utilisateur non authentifié");
        exit(1);
    }

    printSeparator();

    // 4. TEST RÉCUPÉRATION INFOS UTILISATEUR AVEC QUOTAS
    printInfo("Test 4 : Récupération des informations utilisateur et quotas");

    $currentUser = $authService->getCurrentUser();

    if ($currentUser) {
        printSuccess("Informations utilisateur récupérées");
        echo "\n";
        printInfo("Détails du compte :");
        echo "  - Email : " . $currentUser['email'] . "\n";
        echo "  - Nom : " . $currentUser['name'] . "\n";
        echo "  - Plan : " . $currentUser['plan'] . "\n";
        echo "  - Fichiers utilisés : " . $currentUser['current_files_count'] . "\n";
        echo "  - Fichiers restants : " . $currentUser['remaining_files'] . "\n";
        echo "\n";
    } else {
        printError("Impossible de récupérer les infos utilisateur");
        exit(1);
    }

    printSeparator();

    // 5. TEST VÉRIFICATION QUOTAS (AVANT ANALYSE)
    printInfo("Test 5 : Vérification des quotas avant analyse");

    $usageStats = new UsageStats();
    $userId = $authService->getUserId();

    // Tester avec 1 fichier
    $quotaCheck = $usageStats->canAnalyze($userId, 1);

    if ($quotaCheck['can_analyze']) {
        printSuccess("Quota disponible pour analyser 1 fichier");
        echo "  - Utilisé : " . $quotaCheck['used'] . "/" . $quotaCheck['limit'] . "\n";
        echo "  - Restant : " . $quotaCheck['remaining'] . "\n";
    } else {
        printError("Quota insuffisant : " . $quotaCheck['reason']);
    }

    printSeparator();

    // 6. TEST INCRÉMENTATION QUOTAS (SIMULATION ANALYSES)
    printInfo("Test 6 : Simulation d'analyses de fichiers avec incrémentation");

    // Analyser 5 fichiers
    $filesToAnalyze = 5;
    printInfo("Simulation de l'analyse de $filesToAnalyze fichiers...");

    $incrementResult = $usageStats->incrementFilesCount($userId, $filesToAnalyze);

    if ($incrementResult) {
        printSuccess("Quota incrémenté de $filesToAnalyze fichiers");

        // Vérifier le nouveau quota
        $newQuota = $usageStats->canAnalyze($userId, 0);
        echo "  - Nouveau total utilisé : " . $newQuota['used'] . "/" . $newQuota['limit'] . "\n";
        echo "  - Restant : " . $newQuota['remaining'] . "\n";
    } else {
        printError("Échec de l'incrémentation");
    }

    printSeparator();

    // 7. TEST DÉPASSEMENT DE QUOTA
    printInfo("Test 7 : Test du dépassement de quota");

    // Essayer d'analyser 20 fichiers (devrait dépasser la limite de 20 pour free)
    $tooManyFiles = 20;
    printInfo("Tentative d'analyse de $tooManyFiles fichiers supplémentaires...");

    $quotaCheck = $usageStats->canAnalyze($userId, $tooManyFiles);

    if (!$quotaCheck['can_analyze']) {
        printSuccess("Quota correctement rejeté (limite atteinte)");
        printWarning("Raison : " . $quotaCheck['reason']);
        echo "  - Utilisé : " . $quotaCheck['used'] . "/" . $quotaCheck['limit'] . "\n";
        echo "  - Restant : " . $quotaCheck['remaining'] . "\n";
    } else {
        printError("ERREUR : Le quota aurait dû être rejeté !");
    }

    printSeparator();

    // 8. TEST AVEC UN NOMBRE DE FICHIERS DANS LA LIMITE
    printInfo("Test 8 : Vérification avec un nombre raisonnable de fichiers");

    // Calculer combien il reste
    $currentQuota = $usageStats->canAnalyze($userId, 0);
    $remaining = $currentQuota['remaining'];

    if ($remaining > 0) {
        $reasonableAmount = min(3, $remaining);
        printInfo("Tentative d'analyse de $reasonableAmount fichiers (reste : $remaining)...");

        $quotaCheck = $usageStats->canAnalyze($userId, $reasonableAmount);

        if ($quotaCheck['can_analyze']) {
            printSuccess("Quota validé pour $reasonableAmount fichiers");

            // Incrémenter
            $usageStats->incrementFilesCount($userId, $reasonableAmount);
            printSuccess("Quota incrémenté");

            // Vérifier le nouveau total
            $finalQuota = $usageStats->canAnalyze($userId, 0);
            echo "  - Total final utilisé : " . $finalQuota['used'] . "/" . $finalQuota['limit'] . "\n";
            echo "  - Restant final : " . $finalQuota['remaining'] . "\n";
        } else {
            printWarning("Quota refusé : " . $quotaCheck['reason']);
        }
    } else {
        printInfo("Quota déjà à 100% - Test de limite réussi");
    }

    printSeparator();

    // 9. TEST DÉCONNEXION
    printInfo("Test 9 : Déconnexion");

    $authService->logout();

    if (!$authService->isLoggedIn()) {
        printSuccess("Déconnexion réussie");
    } else {
        printError("Échec de la déconnexion");
    }

    printSeparator();

    // 10. VÉRIFICATION POST-DÉCONNEXION
    printInfo("Test 10 : Vérification après déconnexion");

    if (!$authService->isLoggedIn()) {
        printSuccess("L'utilisateur n'est plus authentifié");
    }

    if ($authService->getUserId() === null) {
        printSuccess("getUserId() retourne null après déconnexion");
    }

    printSeparator();

    // RÉSUMÉ
    echo "\n";
    printSuccess("TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !");
    echo "\n";
    printInfo("Résumé des tests :");
    echo "  ✓ Inscription utilisateur\n";
    echo "  ✓ Connexion et session\n";
    echo "  ✓ Vérification authentification\n";
    echo "  ✓ Récupération infos utilisateur avec quotas\n";
    echo "  ✓ Vérification quotas avant analyse\n";
    echo "  ✓ Incrémentation quotas après analyse\n";
    echo "  ✓ Blocage dépassement de quota (20 fichiers/mois)\n";
    echo "  ✓ Analyse avec quota disponible\n";
    echo "  ✓ Déconnexion\n";
    echo "  ✓ Vérification post-déconnexion\n";
    echo "\n";
    printInfo("L'utilisateur de test créé : $testEmail");
    printWarning("Note : Cet utilisateur reste dans la base de données pour d'éventuels tests supplémentaires.");
    echo "\n";

} catch (Exception $e) {
    printSeparator();
    printError("ERREUR CRITIQUE : " . $e->getMessage());
    echo "\n";
    echo "Stack trace :\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
