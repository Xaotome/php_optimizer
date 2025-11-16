<?php

declare(strict_types=1);

namespace PhpOptimizer\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PhpOptimizer\Services\AuthService;
use PhpOptimizer\Models\ResponseModel;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Inscription d'un nouvel utilisateur
     * POST /api/register
     */
    public function register(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $name = $data['name'] ?? '';

            $result = $this->authService->register($email, $password, $name);

            if (!$result['success']) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'errors' => $result['errors']
                ], 400);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => $result['message'],
                'user_id' => $result['user_id']
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'REGISTRATION_ERROR',
                'Erreur lors de l\'inscription: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     * POST /api/login
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            $result = $this->authService->login($email, $password);

            if (!$result['success']) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => $result['error']
                ], 401);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => $result['message'],
                'user' => [
                    'id' => $result['user']['id'],
                    'email' => $result['user']['email'],
                    'name' => $result['user']['name']
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'LOGIN_ERROR',
                'Erreur lors de la connexion: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     * POST /api/logout
     */
    public function logout(Request $request, Response $response): Response
    {
        try {
            $this->authService->logout();

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'LOGOUT_ERROR',
                'Erreur lors de la déconnexion: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Récupérer les informations de l'utilisateur connecté
     * GET /api/me
     */
    public function me(Request $request, Response $response): Response
    {
        try {
            if (!$this->authService->isLoggedIn()) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Non connecté'
                ], 401);
            }

            $user = $this->authService->getCurrentUser();

            if (!$user) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Utilisateur introuvable'
                ], 404);
            }

            // Ne pas retourner le mot de passe
            unset($user['password']);

            return $this->jsonResponse($response, [
                'success' => true,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'USER_INFO_ERROR',
                'Erreur lors de la récupération des informations: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Vérifier le statut d'authentification
     * GET /api/auth/check
     */
    public function checkAuth(Request $request, Response $response): Response
    {
        return $this->jsonResponse($response, [
            'authenticated' => $this->authService->isLoggedIn(),
            'user_id' => $this->authService->getUserId(),
            'user_email' => $this->authService->getUserEmail()
        ]);
    }

    /**
     * Helper pour retourner une réponse JSON
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
