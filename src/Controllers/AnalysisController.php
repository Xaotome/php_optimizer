<?php

declare(strict_types=1);

namespace PhpOptimizer\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PhpOptimizer\Services\AnalysisService;
use PhpOptimizer\Services\FileUploadService;
use PhpOptimizer\Models\ResponseModel;

class AnalysisController
{
    private AnalysisService $analysisService;
    private FileUploadService $uploadService;

    public function __construct()
    {
        $this->analysisService = new AnalysisService();
        $this->uploadService = new FileUploadService();
    }

    public function analyze(Request $request, Response $response): Response
    {
        try {
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

            $uploadResult = $this->uploadService->handleUpload($files);
            
            if (!$uploadResult['success']) {
                return $this->jsonResponse($response, ResponseModel::error(
                    $uploadResult['error_code'],
                    $uploadResult['message']
                ), 400);
            }

            $analysisResult = $this->analysisService->analyzeFiles($uploadResult['data']['files']);

            return $this->jsonResponse($response, ResponseModel::success($analysisResult));

        } catch (\Exception $e) {
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