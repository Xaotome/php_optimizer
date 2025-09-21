<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class RectorAnalyzer
{
    private string $rectorBinary;
    private string $configPath;
    private string $tempDir;

    public function __construct()
    {
        $this->rectorBinary = dirname(__DIR__, 2) . '/vendor/bin/rector';
        $this->configPath = dirname(__DIR__, 2) . '/rector.php';
        $this->tempDir = dirname(__DIR__, 2) . '/storage/temp/';
        $this->ensureTempDirectoryExists();
    }

    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            // Analyser le fichier avec Rector en mode dry-run
            $suggestions = $this->getRectorSuggestions($filePath);

            foreach ($suggestions as $suggestion) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => $suggestion['message'],
                    'line' => $suggestion['line'] ?? 1,
                    'rule' => 'PHP-8.4.' . $suggestion['rule'],
                    'suggestion' => $suggestion['suggestion'],
                    'category' => 'migration',
                    'php_version' => '8.4',
                    'diff' => $suggestion['diff'] ?? null
                ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Erreur lors de l\'analyse Rector: ' . $e->getMessage(),
                'line' => 1,
                'rule' => 'RECTOR_ERROR',
                'suggestion' => 'Vérifiez la configuration Rector'
            ];
        }

        return [
            'issues' => $issues,
            'migration_summary' => $this->getMigrationSummary($issues)
        ];
    }

    private function getRectorSuggestions(string $filePath): array
    {
        $suggestions = [];

        // Créer une copie temporaire du fichier pour l'analyse
        $tempFile = $this->tempDir . 'rector_analysis_' . basename($filePath);
        copy($filePath, $tempFile);

        try {
            // Exécuter Rector en mode dry-run pour obtenir un diff
            $command = sprintf(
                '%s process %s --dry-run --config=%s --no-progress-bar 2>&1',
                escapeshellarg($this->rectorBinary),
                escapeshellarg($tempFile),
                escapeshellarg($this->configPath)
            );

            $output = shell_exec($command);

            if ($output && strpos($output, 'would change') !== false) {
                $suggestions = $this->parseDryRunOutput($output, $filePath);
            }

            // Analyser le contenu pour des patterns spécifiques à PHP 8.4
            $content = file_get_contents($filePath);
            $suggestions = array_merge($suggestions, $this->analyzeForPhp84Patterns($content));

        } finally {
            // Nettoyer le fichier temporaire
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return $suggestions;
    }

    private function parseDryRunOutput(string $output, string $originalFilePath): array
    {
        $suggestions = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (strpos($line, '--- Original') !== false || strpos($line, '+++ New') !== false) {
                continue;
            }

            if (preg_match('/^\-\s*(.+)$/', $line, $matches)) {
                $suggestions[] = [
                    'message' => 'Code obsolète détecté',
                    'rule' => 'deprecated_code',
                    'suggestion' => 'Moderniser selon PHP 8.4: ' . trim($matches[1]),
                    'diff' => $line
                ];
            } elseif (preg_match('/^\+\s*(.+)$/', $line, $matches)) {
                $suggestions[] = [
                    'message' => 'Amélioration suggérée pour PHP 8.4',
                    'rule' => 'modernization',
                    'suggestion' => 'Code moderne: ' . trim($matches[1]),
                    'diff' => $line
                ];
            }
        }

        return $suggestions;
    }

    private function analyzeForPhp84Patterns(string $content): array
    {
        $suggestions = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les patterns spécifiques à PHP 8.4

            // 1. array_is_list() peut être utilisé au lieu de vérifications manuelles
            if (preg_match('/array_keys\s*\(\s*\$\w+\s*\)\s*===\s*range/', $line)) {
                $suggestions[] = [
                    'message' => 'Utilisez array_is_list() pour vérifier si un tableau est une liste',
                    'line' => $actualLineNumber,
                    'rule' => 'array_is_list',
                    'suggestion' => 'Remplacez par array_is_list($array) (PHP 8.1+)'
                ];
            }

            // 2. str_contains() au lieu de strpos() !== false
            if (preg_match('/strpos\s*\([^)]+\)\s*!==\s*false/', $line)) {
                $suggestions[] = [
                    'message' => 'Utilisez str_contains() au lieu de strpos() !== false',
                    'line' => $actualLineNumber,
                    'rule' => 'str_contains',
                    'suggestion' => 'Remplacez par str_contains() (PHP 8.0+)'
                ];
            }

            // 3. match() au lieu de switch complexes
            if (preg_match('/switch\s*\([^)]+\)/', $line)) {
                $suggestions[] = [
                    'message' => 'Considérez utiliser match() pour des correspondances simples',
                    'line' => $actualLineNumber,
                    'rule' => 'match_expression',
                    'suggestion' => 'match() offre une syntaxe plus concise que switch (PHP 8.0+)'
                ];
            }

            // 4. Propriétés en lecture seule
            if (preg_match('/private\s+[^$]*\$\w+\s*;/', $line) && !preg_match('/readonly/', $line)) {
                $suggestions[] = [
                    'message' => 'Considérez utiliser readonly pour les propriétés immutables',
                    'line' => $actualLineNumber,
                    'rule' => 'readonly_properties',
                    'suggestion' => 'Ajoutez readonly si cette propriété n\'est jamais modifiée (PHP 8.1+)'
                ];
            }

            // 5. Union types au lieu de PHPDoc
            if (preg_match('/@param\s+(string\|int|int\|string|array\|string)/', $line)) {
                $suggestions[] = [
                    'message' => 'Utilisez les union types natifs au lieu de PHPDoc',
                    'line' => $actualLineNumber,
                    'rule' => 'union_types',
                    'suggestion' => 'Remplacez @param par des union types natifs (PHP 8.0+)'
                ];
            }

            // 6. Enum au lieu de constantes de classe
            if (preg_match('/const\s+[A-Z_]+\s*=\s*[\'"][^\'"]+[\'"]/', $line)) {
                $suggestions[] = [
                    'message' => 'Considérez utiliser des Enums pour les constantes liées',
                    'line' => $actualLineNumber,
                    'rule' => 'enum_usage',
                    'suggestion' => 'Les Enums offrent une meilleure structure pour les constantes (PHP 8.1+)'
                ];
            }

            // 7. Fibers pour l'asynchrone
            if (preg_match('/curl_multi_|async|promise/i', $line)) {
                $suggestions[] = [
                    'message' => 'Considérez utiliser Fibers pour la programmation asynchrone',
                    'line' => $actualLineNumber,
                    'rule' => 'fibers',
                    'suggestion' => 'Fibers permet une programmation asynchrone native (PHP 8.1+)'
                ];
            }

            // 8. First-class callable syntax
            if (preg_match('/array\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][^\'"]+[\'"]\s*\)/', $line)) {
                $suggestions[] = [
                    'message' => 'Utilisez la syntaxe first-class callable',
                    'line' => $actualLineNumber,
                    'rule' => 'first_class_callable',
                    'suggestion' => 'Remplacez array($obj, "method") par $obj->method(...) (PHP 8.1+)'
                ];
            }
        }

        return $suggestions;
    }

    private function getMigrationSummary(array $issues): array
    {
        $summary = [
            'total_suggestions' => count($issues),
            'by_category' => [],
            'php_versions' => [],
            'complexity' => 'low'
        ];

        foreach ($issues as $issue) {
            $category = $issue['category'] ?? 'general';
            $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;

            if (isset($issue['php_version'])) {
                $version = $issue['php_version'];
                $summary['php_versions'][$version] = ($summary['php_versions'][$version] ?? 0) + 1;
            }
        }

        // Déterminer la complexité de migration
        if (count($issues) > 20) {
            $summary['complexity'] = 'high';
        } elseif (count($issues) > 5) {
            $summary['complexity'] = 'medium';
        }

        return $summary;
    }

    private function ensureTempDirectoryExists(): void
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
}