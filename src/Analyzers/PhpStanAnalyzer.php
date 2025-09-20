<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class PhpStanAnalyzer
{
    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            $tempConfigFile = $this->createTempConfig();
            $command = "phpstan analyse --no-progress --error-format=json --configuration={$tempConfigFile} " . escapeshellarg($filePath) . " 2>&1";
            
            $output = shell_exec($command);
            unlink($tempConfigFile);

            if ($output === null) {
                return ['issues' => []];
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['files'])) {
                return ['issues' => []];
            }

            foreach ($result['files'] as $file => $fileData) {
                if (isset($fileData['messages'])) {
                    foreach ($fileData['messages'] as $message) {
                        $issues[] = [
                            'severity' => $this->mapPhpStanSeverity($message),
                            'message' => $message['message'] ?? 'Erreur PHPStan',
                            'line' => $message['line'] ?? 0,
                            'rule' => 'PHPStan.' . ($message['identifier'] ?? 'unknown'),
                            'suggestion' => $this->generateSuggestion($message)
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Erreur lors de l\'analyse PHPStan: ' . $e->getMessage(),
                'line' => 0,
                'rule' => 'PHPStan.analysis_error'
            ];
        }

        return ['issues' => $issues];
    }

    private function createTempConfig(): string
    {
        $config = [
            'parameters' => [
                'level' => 8,
                'paths' => [],
                'checkMissingIterableValueType' => false,
                'checkGenericClassInNonGenericObjectType' => false,
                'reportUnmatchedIgnoredErrors' => false
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_config_');
        file_put_contents($tempFile, "parameters:\n" . 
            "  level: 8\n" .
            "  checkMissingIterableValueType: false\n" .
            "  checkGenericClassInNonGenericObjectType: false\n" .
            "  reportUnmatchedIgnoredErrors: false\n"
        );

        return $tempFile;
    }

    private function mapPhpStanSeverity(array $message): string
    {
        if (isset($message['ignorable']) && $message['ignorable']) {
            return 'warning';
        }

        $messageText = strtolower($message['message'] ?? '');
        
        if (strpos($messageText, 'undefined') !== false || 
            strpos($messageText, 'not found') !== false ||
            strpos($messageText, 'does not exist') !== false) {
            return 'error';
        }

        if (strpos($messageText, 'deprecated') !== false || 
            strpos($messageText, 'should') !== false) {
            return 'warning';
        }

        return 'error';
    }

    private function generateSuggestion(array $message): ?string
    {
        $messageText = strtolower($message['message'] ?? '');

        if (strpos($messageText, 'undefined variable') !== false) {
            return 'Vérifiez que la variable est bien définie avant utilisation';
        }

        if (strpos($messageText, 'undefined method') !== false) {
            return 'Vérifiez que la méthode existe ou ajoutez-la à la classe';
        }

        if (strpos($messageText, 'undefined property') !== false) {
            return 'Vérifiez que la propriété existe ou déclarez-la dans la classe';
        }

        if (strpos($messageText, 'type') !== false && strpos($messageText, 'expects') !== false) {
            return 'Vérifiez les types des paramètres passés à la fonction/méthode';
        }

        if (strpos($messageText, 'return') !== false && strpos($messageText, 'type') !== false) {
            return 'Vérifiez que la valeur retournée correspond au type déclaré';
        }

        if (strpos($messageText, 'deprecated') !== false) {
            return 'Remplacez par une alternative moderne recommandée';
        }

        if (strpos($messageText, 'doc comment') !== false) {
            return 'Ajoutez ou corrigez la documentation PHPDoc';
        }

        return null;
    }
}