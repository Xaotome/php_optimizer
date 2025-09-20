<?php

// Fichier avec différents types d'erreurs pour tester les filtres

namespace PhpOptimizer\Test;    

class MixedIssues {
    var $oldProperty;     // ERROR: visibilité manquante
    private $property;    
    
    function oldMethod() {    // ERROR: visibilité manquante
        echo "output";        // WARNING: effet de bord
        $var= "test";         // INFO: espaces incorrects
        return $var;          
    }    
    
    public function goodMethod(): string 
    {
        return $this->property;
    }
}