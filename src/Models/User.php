<?php

declare(strict_types=1);

namespace PhpOptimizer\Models;

use PhpOptimizer\Database\Database;
use PDO;

class User
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function create(string $email, string $password, string $name): ?int
    {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (email, password, name) VALUES (?, ?, ?)";

        try {
            $this->db->execute($sql, [$email, $hashedPassword, $name]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("User Creation Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Trouver un utilisateur par email
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        return $this->db->queryOne($sql, [$email]);
    }

    /**
     * Trouver un utilisateur par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
        return $this->db->queryOne($sql, [$id]);
    }

    /**
     * Vérifier les identifiants de connexion
     */
    public function verify(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Ne pas retourner le mot de passe
            unset($user['password']);
            return $user;
        }

        return null;
    }

    /**
     * Mettre à jour le Stripe Customer ID
     */
    public function updateStripeCustomerId(int $userId, string $customerId): bool
    {
        $sql = "UPDATE users SET stripe_customer_id = ? WHERE id = ?";
        return $this->db->execute($sql, [$customerId, $userId]);
    }

    /**
     * Vérifier l'email d'un utilisateur
     */
    public function verifyEmail(int $userId): bool
    {
        $sql = "UPDATE users SET email_verified_at = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$userId]);
    }

    /**
     * Désactiver un utilisateur
     */
    public function deactivate(int $userId): bool
    {
        $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        return $this->db->execute($sql, [$userId]);
    }

    /**
     * Obtenir les informations complètes d'un utilisateur (avec abonnement et usage)
     */
    public function getFullInfo(int $userId): ?array
    {
        $sql = "SELECT * FROM user_subscription_info WHERE id = ? LIMIT 1";
        return $this->db->queryOne($sql, [$userId]);
    }

    /**
     * Obtenir tous les utilisateurs
     */
    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT id, email, name, created_at, is_active FROM users
                ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->query($sql, [$limit, $offset]);
    }

    /**
     * Compter le nombre total d'utilisateurs
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as total FROM users";
        $result = $this->db->queryOne($sql);
        return (int) ($result['total'] ?? 0);
    }
}
