<?php
// Fichier de test avec plusieurs erreurs PSR

class test_Class {
    var $property;
    
    function method() {
        echo "Hello world";
        $variable= "test";
        return $variable;
    }
}