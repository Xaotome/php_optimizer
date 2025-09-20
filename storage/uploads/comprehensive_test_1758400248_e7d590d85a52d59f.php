<?php
// Fichier avec TOUS les types d'erreurs pour tester les filtres

// Pas de declare(strict_types=1) = ERROR PSR-12

echo "Output direct"; // WARNING: effet de bord

class test_class // ERROR: nom de classe non conforme 
{
    var $oldProperty; // ERROR: visibilité manquante
    
    function badMethod() // ERROR: visibilité manquante
    {
        $variable= "bad spacing"; // INFO: espaces incorrects
        
        // Ligne trop longue pour tester PSR-2
        $veryLongVariableName = "This is a very long line that should exceed the 120 character limit according to PSR-2 standards and should trigger a warning";
        
        return $variable;
    }   // INFO: espaces en fin de ligne
}

// Pas de namespace = ERROR PSR-4