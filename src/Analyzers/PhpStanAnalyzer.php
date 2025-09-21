<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class PhpStanAnalyzer
{
    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            // Analyse statique simple du code PHP sans shell_exec
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            $issues = array_merge($issues, $this->analyzePhpSyntax($content, $lines));
            $issues = array_merge($issues, $this->analyzeTypeHints($content, $lines));
            $issues = array_merge($issues, $this->analyzeDeprecatedFeatures($content, $lines));

        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'PHPStan non disponible sur cet environnement (shell_exec désactivé)',
                'line' => 0,
                'rule' => 'PHPStan.environment_limitation',
                'suggestion' => 'Utilisez un environnement de développement local pour l\'analyse PHPStan complète'
            ];
        }

        return ['issues' => $issues];
    }

    private function analyzePhpSyntax(string $content, array $lines): array
    {
        $issues = [];

        // Vérifier la syntaxe PHP de base
        $tokens = @token_get_all($content);
        if ($tokens === false) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Erreur de syntaxe PHP détectée',
                'line' => 1,
                'rule' => 'PHPStan.syntax_error',
                'suggestion' => 'Corrigez les erreurs de syntaxe PHP'
            ];
            return $issues;
        }

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les variables non définies (pattern simple)
            if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*/', $line, $matches)) {
                if (preg_match('/echo\s+\$\w+|print\s+\$\w+/', $line)) {
                    $issues[] = [
                        'severity' => 'warning',
                        'message' => 'Variable potentiellement non définie utilisée dans un echo/print',
                        'line' => $actualLineNumber,
                        'rule' => 'PHPStan.undefined_variable',
                        'suggestion' => 'Vérifiez que la variable est définie avant utilisation'
                    ];
                }
            }

            // Vérifier les appels de méthodes sur null potentiel
            if (preg_match('/\$\w+->\w+/', $line)) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Appel de méthode détecté - vérifiez que l\'objet n\'est pas null',
                    'line' => $actualLineNumber,
                    'rule' => 'PHPStan.null_safety',
                    'suggestion' => 'Ajoutez une vérification null avant l\'appel de méthode'
                ];
            }
        }

        return $issues;
    }

    private function analyzeTypeHints(string $content, array $lines): array
    {
        $issues = [];

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les fonctions sans type de retour
            if (preg_match('/function\s+\w+\s*\([^)]*\)\s*{/', $line) &&
                !preg_match('/function\s+\w+\s*\([^)]*\)\s*:\s*\w+/', $line)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Fonction sans type de retour déclaré',
                    'line' => $actualLineNumber,
                    'rule' => 'PHPStan.missing_return_type',
                    'suggestion' => 'Ajoutez un type de retour explicite à la fonction'
                ];
            }

            // Vérifier les paramètres sans type
            if (preg_match('/function\s+\w+\s*\(\s*\$\w+/', $line) &&
                !preg_match('/function\s+\w+\s*\(\s*\w+\s+\$\w+/', $line)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Paramètre de fonction sans type déclaré',
                    'line' => $actualLineNumber,
                    'rule' => 'PHPStan.missing_param_type',
                    'suggestion' => 'Ajoutez des types aux paramètres de fonction'
                ];
            }
        }

        return $issues;
    }

    private function analyzeDeprecatedFeatures(string $content, array $lines): array
    {
        $issues = [];

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les fonctionnalités dépréciées
            if (preg_match('/create_function\s*\(/', $line)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'create_function() est déprécié depuis PHP 7.2',
                    'line' => $actualLineNumber,
                    'rule' => 'PHPStan.deprecated_function',
                    'suggestion' => 'Utilisez des fonctions anonymes ou des closures'
                ];
            }

            if (preg_match('/each\s*\(/', $line)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'each() est déprécié depuis PHP 7.2',
                    'line' => $actualLineNumber,
                    'rule' => 'PHPStan.deprecated_function',
                    'suggestion' => 'Utilisez foreach() ou des alternatives modernes'
                ];
            }
        }

        return $issues;
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