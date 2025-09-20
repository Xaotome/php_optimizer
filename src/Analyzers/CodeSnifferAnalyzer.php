<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class CodeSnifferAnalyzer
{
    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            $command = "phpcs --standard=PSR12 --report=json " . escapeshellarg($filePath) . " 2>&1";
            $output = shell_exec($command);

            if ($output === null) {
                return ['issues' => []];
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['issues' => []];
            }

            if (isset($result['files'])) {
                foreach ($result['files'] as $file => $fileData) {
                    if (isset($fileData['messages'])) {
                        foreach ($fileData['messages'] as $message) {
                            $issues[] = [
                                'severity' => strtolower($message['type'] ?? 'warning'),
                                'message' => $message['message'] ?? 'Violation de standard détectée',
                                'line' => $message['line'] ?? 0,
                                'rule' => 'CodeSniffer.' . ($message['source'] ?? 'unknown'),
                                'suggestion' => $this->generateSuggestion($message)
                            ];
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Erreur lors de l\'analyse CodeSniffer: ' . $e->getMessage(),
                'line' => 0,
                'rule' => 'CodeSniffer.analysis_error'
            ];
        }

        return ['issues' => $issues];
    }

    private function generateSuggestion(array $message): ?string
    {
        $source = $message['source'] ?? '';
        $messageText = strtolower($message['message'] ?? '');

        if (strpos($source, 'Indentation') !== false || strpos($messageText, 'indent') !== false) {
            return 'Utilisez 4 espaces pour l\'indentation';
        }

        if (strpos($source, 'LineLength') !== false || strpos($messageText, 'line') !== false) {
            return 'Divisez la ligne en plusieurs lignes plus courtes';
        }

        if (strpos($source, 'TrailingWhitespace') !== false || strpos($messageText, 'whitespace') !== false) {
            return 'Supprimez les espaces en fin de ligne';
        }

        if (strpos($source, 'OpeningBrace') !== false || strpos($messageText, 'brace') !== false) {
            return 'Vérifiez la position des accolades selon PSR-12';
        }

        if (strpos($source, 'BlankLine') !== false || strpos($messageText, 'blank') !== false) {
            return 'Ajustez les lignes vides selon les conventions PSR';
        }

        if (strpos($source, 'Visibility') !== false || strpos($messageText, 'visibility') !== false) {
            return 'Ajoutez la visibilité (public, private, protected) à la propriété/méthode';
        }

        if (strpos($source, 'Use') !== false || strpos($messageText, 'use') !== false) {
            return 'Vérifiez l\'ordre et la syntaxe des déclarations use';
        }

        if (strpos($source, 'Space') !== false || strpos($messageText, 'space') !== false) {
            return 'Ajustez les espaces selon les conventions PSR-12';
        }

        if (strpos($source, 'Comment') !== false || strpos($messageText, 'comment') !== false) {
            return 'Vérifiez le format et la syntaxe des commentaires';
        }

        if (strpos($source, 'Declare') !== false || strpos($messageText, 'declare') !== false) {
            return 'Ajoutez declare(strict_types=1); après l\'ouverture PHP';
        }

        return 'Consultez la documentation PSR-12 pour plus de détails';
    }
}