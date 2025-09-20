<?php

declare(strict_types=1);

namespace PhpOptimizer\Services;

use Psr\Http\Message\UploadedFileInterface;

class FileUploadService
{
    private string $uploadDirectory;
    private array $allowedExtensions = ['php'];
    private int $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct()
    {
        $this->uploadDirectory = dirname(__DIR__, 2) . '/storage/uploads/';
        $this->ensureUploadDirectoryExists();
    }

    public function handleUpload(array $uploadedFiles): array
    {
        $uploadedFileData = [];
        $errors = [];

        foreach ($uploadedFiles as $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFileInterface) {
                continue;
            }

            $validation = $this->validateFile($uploadedFile);
            if (!$validation['valid']) {
                $errors[] = [
                    'file' => $uploadedFile->getClientFilename(),
                    'error' => $validation['error']
                ];
                continue;
            }

            $result = $this->saveFile($uploadedFile);
            if ($result['success']) {
                $uploadedFileData[] = $result['data'];
            } else {
                $errors[] = [
                    'file' => $uploadedFile->getClientFilename(),
                    'error' => $result['error']
                ];
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'error_code' => 'UPLOAD_VALIDATION_FAILED',
                'message' => 'Certains fichiers n\'ont pas pu être téléchargés',
                'errors' => $errors
            ];
        }

        if (empty($uploadedFileData)) {
            return [
                'success' => false,
                'error_code' => 'NO_VALID_FILES',
                'message' => 'Aucun fichier valide n\'a été fourni'
            ];
        }

        return [
            'success' => true,
            'data' => [
                'files' => $uploadedFileData,
                'total_files' => count($uploadedFileData)
            ]
        ];
    }

    private function validateFile(UploadedFileInterface $uploadedFile): array
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => $this->getUploadErrorMessage($uploadedFile->getError())
            ];
        }

        $filename = $uploadedFile->getClientFilename();
        if (empty($filename)) {
            return [
                'valid' => false,
                'error' => 'Nom de fichier manquant'
            ];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return [
                'valid' => false,
                'error' => 'Extension de fichier non autorisée. Seuls les fichiers .php sont acceptés'
            ];
        }

        if ($uploadedFile->getSize() > $this->maxFileSize) {
            return [
                'valid' => false,
                'error' => 'Fichier trop volumineux (maximum 5MB)'
            ];
        }

        return ['valid' => true];
    }

    private function saveFile(UploadedFileInterface $uploadedFile): array
    {
        try {
            $filename = $uploadedFile->getClientFilename();
            $uniqueFilename = $this->generateUniqueFilename($filename);
            $filePath = $this->uploadDirectory . $uniqueFilename;

            $uploadedFile->moveTo($filePath);

            return [
                'success' => true,
                'data' => [
                    'original_name' => $filename,
                    'stored_name' => $uniqueFilename,
                    'path' => $filePath,
                    'size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getClientMediaType()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ];
        }
    }

    private function generateUniqueFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return "{$basename}_{$timestamp}_{$random}.{$extension}";
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux',
            UPLOAD_ERR_PARTIAL => 'Téléchargement partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier téléchargé',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
            UPLOAD_ERR_EXTENSION => 'Extension PHP bloquée',
            default => 'Erreur de téléchargement inconnue'
        };
    }
}