<?php

declare(strict_types=1);

namespace PhpOptimizer\Services;

use PhpOptimizer\Models\User;

class AuthService
{
    private User $userModel;
    private const SESSION_KEY = 'user_id';
    private const SESSION_EMAIL_KEY = 'user_email';

    public function __construct()
    {
        $this->userModel = new User();
        $this->startSession();
    }

    /**
     * Démarre la session si elle n'est pas déjà démarrée
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(string $email, string $password, string $name): array
    {
        // Validation
        $errors = $this->validateRegistration($email, $password, $name);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        // Vérifier si l'email existe déjà
        if ($this->userModel->findByEmail($email)) {
            return [
                'success' => false,
                'errors' => ['email' => 'Cet email est déjà utilisé']
            ];
        }

        // Créer l'utilisateur
        $userId = $this->userModel->create($email, $password, $name);

        if (!$userId) {
            return [
                'success' => false,
                'errors' => ['general' => 'Erreur lors de la création du compte']
            ];
        }

        // Connecter automatiquement l'utilisateur
        $this->createSession($userId, $email);

        return [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Compte créé avec succès'
        ];
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userModel->verify($email, $password);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'Email ou mot de passe incorrect'
            ];
        }

        if (!$user['is_active']) {
            return [
                'success' => false,
                'error' => 'Votre compte a été désactivé'
            ];
        }

        // Créer la session
        $this->createSession((int) $user['id'], $email);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Connexion réussie'
        ];
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]) && !empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Récupérer l'ID de l'utilisateur connecté
     */
    public function getUserId(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Récupérer l'email de l'utilisateur connecté
     */
    public function getUserEmail(): ?string
    {
        return $_SESSION[self::SESSION_EMAIL_KEY] ?? null;
    }

    /**
     * Récupérer les informations complètes de l'utilisateur connecté
     */
    public function getCurrentUser(): ?array
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return null;
        }

        return $this->userModel->getFullInfo($userId);
    }

    /**
     * Créer une session utilisateur
     */
    private function createSession(int $userId, string $email): void
    {
        // Régénérer l'ID de session pour éviter la fixation de session
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = $userId;
        $_SESSION[self::SESSION_EMAIL_KEY] = $email;
        $_SESSION['login_time'] = time();

        error_log("AuthService: Session créée pour user ID {$userId}");
    }

    /**
     * Valider les données d'inscription
     */
    private function validateRegistration(string $email, string $password, string $name): array
    {
        $errors = [];

        // Email
        if (empty($email)) {
            $errors['email'] = 'L\'email est requis';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide';
        }

        // Mot de passe
        if (empty($password)) {
            $errors['password'] = 'Le mot de passe est requis';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        // Nom
        if (empty($name)) {
            $errors['name'] = 'Le nom est requis';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Le nom doit contenir au moins 2 caractères';
        }

        return $errors;
    }

    /**
     * Vérifier si l'utilisateur a vérifié son email
     */
    public function isEmailVerified(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['email_verified_at'] !== null;
    }

    /**
     * Exiger l'authentification (redirection si non connecté)
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login.html?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}
