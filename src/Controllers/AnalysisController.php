<?php

declare(strict_types=1);

namespace PhpOptimizer\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PhpOptimizer\Services\AnalysisService;
use PhpOptimizer\Services\FileUploadService;
use PhpOptimizer\Services\AuthService;
use PhpOptimizer\Models\ResponseModel;
use PhpOptimizer\Models\UsageStats;

class AnalysisController
{
    private AnalysisService $analysisService;
    private FileUploadService $uploadService;

    public function __construct()
    {
        try {
            $this->analysisService = new AnalysisService();
            $this->uploadService = new FileUploadService();
        } catch (\Exception $e) {
            error_log("Erreur construction AnalysisController: " . $e->getMessage());
            throw $e;
        }
    }

    public function analyze(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            $authService = new AuthService();

            if (!$authService->isLoggedIn()) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'UNAUTHORIZED',
                    'Vous devez être connecté pour analyser des fichiers'
                ), 401);
            }

            $userId = $authService->getUserId();

            // Vérifier les fichiers uploadés
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles['files'])) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'NO_FILES',
                    'Aucun fichier n\'a été fourni pour l\'analyse'
                ), 400);
            }

            $files = is_array($uploadedFiles['files'])
                ? $uploadedFiles['files']
                : [$uploadedFiles['files']];

            $filesCount = count($files);

            // Vérifier les quotas AVANT l'upload
            $usageStats = new UsageStats();
            $quotaCheck = $usageStats->canAnalyze($userId, $filesCount);

            if (!$quotaCheck['can_analyze']) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error_code' => 'QUOTA_EXCEEDED',
                    'message' => 'Quota mensuel atteint ! Passez à Pro pour continuer.',
                    'quota' => [
                        'used' => $quotaCheck['used'] ?? 0,
                        'limit' => $quotaCheck['limit'],
                        'remaining' => $quotaCheck['remaining']
                    ],
                    'upgrade_url' => './pricing.html'
                ], 429); // 429 Too Many Requests
            }

            // Procéder à l'upload
            $uploadResult = $this->uploadService->handleUpload($files);

            if (!$uploadResult['success']) {
                return $this->jsonResponse($response, ResponseModel::error(
                    $uploadResult['error_code'],
                    $uploadResult['message']
                ), 400);
            }

            // Analyser les fichiers
            $analysisResult = $this->analysisService->analyzeFiles($uploadResult['data']['files']);

            // Incrémenter le compteur de fichiers analysés
            $usageStats->incrementFilesCount($userId, $filesCount);

            // Récupérer les nouveaux quotas
            $newQuotaCheck = $usageStats->canAnalyze($userId, 0);

            // Ajouter les infos de quota à la réponse
            $analysisResult['quota'] = [
                'used' => ($newQuotaCheck['used'] ?? 0),
                'limit' => $newQuotaCheck['limit'],
                'remaining' => $newQuotaCheck['remaining'],
                'files_analyzed' => $filesCount
            ];

            return $this->jsonResponse($response, ResponseModel::success($analysisResult));

        } catch (\Exception $e) {
            error_log("Analysis Error: " . $e->getMessage());
            return $this->jsonResponse($response, ResponseModel::error(
                'ANALYSIS_ERROR',
                'Erreur lors de l\'analyse: ' . $e->getMessage()
            ), 500);
        }
    }

    public function getReport(Request $request, Response $response, array $args): Response
    {
        try {
            $reportId = $args['id'] ?? null;
            
            if (!$reportId) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'MISSING_REPORT_ID',
                    'ID du rapport manquant'
                ), 400);
            }

            $report = $this->analysisService->getReport($reportId);
            
            if (!$report) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'REPORT_NOT_FOUND',
                    'Rapport non trouvé'
                ), 404);
            }

            return $this->jsonResponse($response, ResponseModel::success($report));

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'REPORT_ERROR',
                'Erreur lors de la récupération du rapport: ' . $e->getMessage()
            ), 500);
        }
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}