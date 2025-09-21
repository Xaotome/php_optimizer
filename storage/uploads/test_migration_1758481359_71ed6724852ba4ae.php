<?php

declare(strict_types=1);

namespace TestApp\Models;

/**
 * Classe de test pour vérifier les suggestions de migration PHP 8.4
 * @param string|int $id
 */
class LegacyUser
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';

    public $name;
    private $email;
    protected $id;

    public function __construct($id, $name, $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * @param string|int $userId
     * @return string|null
     */
    public function getUserStatus($userId)
    {
        // Utilisation de strpos au lieu de str_contains
        if (strpos($this->email, '@example.com') !== false) {
            return self::STATUS_ACTIVE;
        }

        // Switch qui pourrait être remplacé par match
        switch ($userId) {
            case 1:
                return 'admin';
            case 2:
                return 'user';
            case 3:
                return 'guest';
            default:
                return 'unknown';
        }
    }

    public function checkArrayIsList($data)
    {
        // Vérification manuelle qui pourrait utiliser array_is_list()
        return array_keys($data) === range(0, count($data) - 1);
    }

    public function processCallable($object, $method)
    {
        // Syntaxe array callable qui pourrait utiliser first-class callable
        return call_user_func(array($object, $method));
    }

    public function asyncOperation()
    {
        // Code qui pourrait bénéficier de Fibers
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.example.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}