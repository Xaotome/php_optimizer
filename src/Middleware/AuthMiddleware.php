<?php

declare(strict_types=1);

namespace PhpOptimizer\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use PhpOptimizer\Services\AuthService;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Process the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->authService->isLoggedIn()) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error_code' => 'UNAUTHORIZED',
                'message' => 'Vous devez être connecté pour accéder à cette ressource',
                'redirect' => '/login.html'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        // Ajouter les informations utilisateur à la requête
        $userId = $this->authService->getUserId();
        $request = $request->withAttribute('user_id', $userId);
        $request = $request->withAttribute('user_email', $this->authService->getUserEmail());

        // Continuer le traitement
        return $handler->handle($request);
    }
}
