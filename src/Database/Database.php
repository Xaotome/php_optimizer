<?php

declare(strict_types=1);

namespace PhpOptimizer\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    private static ?self $instance = null;

    private function __construct()
    {
        // Singleton pattern - empêche l'instanciation directe
    }

    /**
     * Récupère l'instance unique de la classe Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Établit et retourne la connexion PDO
     */
    public function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
                $port = $_ENV['DB_PORT'] ?? '3306';
                $dbname = $_ENV['DB_DATABASE'] ?? 'php_optimizer';
                $username = $_ENV['DB_USERNAME'] ?? 'root';
                $password = $_ENV['DB_PASSWORD'] ?? '';

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);

                error_log("Database: Connexion établie avec succès à {$dbname}");
            } catch (PDOException $e) {
                error_log("Database Error: " . $e->getMessage());
                throw new \RuntimeException('Impossible de se connecter à la base de données: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    /**
     * Execute une requête SELECT et retourne les résultats
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Execute une requête SELECT et retourne un seul résultat
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Execute une requête INSERT, UPDATE ou DELETE
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Execute Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Retourne l'ID de la dernière insertion
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Vérifie si la connexion est établie
     */
    public function isConnected(): bool
    {
        return self::$connection !== null;
    }

    /**
     * Ferme la connexion
     */
    public function disconnect(): void
    {
        self::$connection = null;
        error_log("Database: Connexion fermée");
    }
}
