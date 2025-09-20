<?php

declare(strict_types=1);

namespace PhpOptimizer\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PhpOptimizer\Services\FileUploadService;
use PhpOptimizer\Models\ResponseModel;

class UploadController
{
    private FileUploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new FileUploadService();
    }

    public function upload(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            
            if (empty($uploadedFiles['files'])) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'NO_FILES',
                    'Aucun fichier n\'a été téléchargé'
                ), 400);
            }

            $files = is_array($uploadedFiles['files']) 
                ? $uploadedFiles['files'] 
                : [$uploadedFiles['files']];

            $result = $this->uploadService->handleUpload($files);

            if ($result['success']) {
                return $this->jsonResponse($response, ResponseModel::success($result['data']));
            }

            return $this->jsonResponse($response, ResponseModel::error(
                $result['error_code'],
                $result['message']
            ), 400);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ResponseModel::error(
                'UPLOAD_ERROR',
                'Erreur lors du téléchargement: ' . $e->getMessage()
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