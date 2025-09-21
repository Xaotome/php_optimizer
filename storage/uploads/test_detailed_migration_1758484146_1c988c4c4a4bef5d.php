<?php

declare(strict_types=1);

namespace TestApp\Migration;

/**
 * Fichier de test pour démontrer les suggestions détaillées de migration PHP 8.4
 * @param string|int $userId
 * @param array|null $options
 */
class DetailedMigrationTest
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    private $name;
    private $email;

    public function __construct($name, $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * @param string|int $userId
     * @return string|null
     */
    public function processUser($userId, $options, $debug = false)
    {
        // Test str_contains
        if (strpos($this->email, '@example.com') !== false) {
            return 'valid';
        }

        // Test str_starts_with
        if (strpos($this->name, 'Mr.') === 0) {
            return 'formal';
        }

        // Test switch -> match
        switch ($userId) {
            case 1:
                return 'admin';
            case 2:
                return 'user';
            default:
                return 'guest';
        }
    }

    public function checkArrayList($data)
    {
        // Test array_is_list
        return array_keys($data) === range(0, count($data) - 1);
    }

    public function callMethod($object, $method)
    {
        // Test first-class callable
        return call_user_func(array($object, $method));
    }

    public function processData($items)
    {
        // Test list() -> []
        list($first, $second) = $items;
        return [$first, $second];
    }

    public function makeRequest($url)
    {
        // Test Fibers suggestion
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}