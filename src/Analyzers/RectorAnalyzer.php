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
            // Rector non disponible avec shell_exec désactivé
            // Analyser le contenu pour des patterns spécifiques à PHP 8.4
            $content = file_get_contents($filePath);
            $suggestions = $this->analyzeForPhp84Patterns($content);

            // Ajouter un message d'information sur la limitation
            if (empty($suggestions)) {
                $suggestions[] = [
                    'message' => 'Rector non disponible sur cet environnement (shell_exec désactivé)',
                    'rule' => 'environment_limitation',
                    'suggestion' => 'Utilisez un environnement de développement local pour l\'analyse Rector complète'
                ];
            }

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
            if (preg_match('/array_keys\s*\(\s*\$(\w+)\s*\)\s*===\s*range/', $line, $matches)) {
                $varName = $matches[1];
                $suggestions[] = [
                    'message' => 'Utilisez array_is_list() pour vérifier si un tableau est une liste',
                    'line' => $actualLineNumber,
                    'rule' => 'array_is_list',
                    'suggestion' => 'PHP 8.1+ : Remplacez cette vérification manuelle par array_is_list()',
                    'before_code' => "array_keys(\$$varName) === range(0, count(\$$varName) - 1)",
                    'after_code' => "array_is_list(\$$varName)",
                    'explanation' => "array_is_list() est une fonction native PHP 8.1+ qui vérifie efficacement si un tableau est une liste (clés numériques séquentielles à partir de 0)."
                ];
            }

            // 2. str_contains() au lieu de strpos() !== false
            if (preg_match('/strpos\s*\(\s*([^,]+),\s*([^)]+)\s*\)\s*!==\s*false/', $line, $matches)) {
                $haystack = trim($matches[1]);
                $needle = trim($matches[2]);
                $suggestions[] = [
                    'message' => 'Utilisez str_contains() au lieu de strpos() !== false',
                    'line' => $actualLineNumber,
                    'rule' => 'str_contains',
                    'suggestion' => 'PHP 8.0+ : Syntaxe plus claire et performante avec str_contains()',
                    'before_code' => "strpos($haystack, $needle) !== false",
                    'after_code' => "str_contains($haystack, $needle)",
                    'explanation' => "str_contains() est plus lisible et exprime clairement l'intention. Elle retourne directement un booléen sans comparaison nécessaire."
                ];
            }

            // 2b. str_starts_with() au lieu de strpos() === 0
            if (preg_match('/strpos\s*\(\s*([^,]+),\s*([^)]+)\s*\)\s*===\s*0/', $line, $matches)) {
                $haystack = trim($matches[1]);
                $needle = trim($matches[2]);
                $suggestions[] = [
                    'message' => 'Utilisez str_starts_with() au lieu de strpos() === 0',
                    'line' => $actualLineNumber,
                    'rule' => 'str_starts_with',
                    'suggestion' => 'PHP 8.0+ : Fonction dédiée pour vérifier le début d\'une chaîne',
                    'before_code' => "strpos($haystack, $needle) === 0",
                    'after_code' => "str_starts_with($haystack, $needle)",
                    'explanation' => "str_starts_with() est spécialement conçue pour vérifier si une chaîne commence par une sous-chaîne."
                ];
            }

            // 2c. str_ends_with() au lieu de substr() === needle
            if (preg_match('/substr\s*\(\s*([^,]+),\s*-strlen\s*\(\s*([^)]+)\s*\)\s*\)\s*===\s*\2/', $line, $matches)) {
                $haystack = trim($matches[1]);
                $needle = trim($matches[2]);
                $suggestions[] = [
                    'message' => 'Utilisez str_ends_with() au lieu de substr() avec strlen()',
                    'line' => $actualLineNumber,
                    'rule' => 'str_ends_with',
                    'suggestion' => 'PHP 8.0+ : Fonction dédiée pour vérifier la fin d\'une chaîne',
                    'before_code' => "substr($haystack, -strlen($needle)) === $needle",
                    'after_code' => "str_ends_with($haystack, $needle)",
                    'explanation' => "str_ends_with() simplifie la vérification de fin de chaîne et améliore la lisibilité."
                ];
            }

            // 3. match() au lieu de switch complexes
            if (preg_match('/switch\s*\(\s*([^)]+)\s*\)/', $line, $matches)) {
                $variable = trim($matches[1]);
                $suggestions[] = [
                    'message' => 'Considérez utiliser match() pour des correspondances simples',
                    'line' => $actualLineNumber,
                    'rule' => 'match_expression',
                    'suggestion' => 'PHP 8.0+ : match() est plus concis et type-safe',
                    'before_code' => "switch ($variable) {\n    case 'a': return 1;\n    case 'b': return 2;\n    default: return 0;\n}",
                    'after_code' => "return match($variable) {\n    'a' => 1,\n    'b' => 2,\n    default => 0\n};",
                    'explanation' => "match() est une expression (retourne une valeur), supporte la comparaison stricte (===), ne nécessite pas de break, et lève une exception si aucun cas ne correspond (sauf default)."
                ];
            }

            // 4. Propriétés en lecture seule
            if (preg_match('/(private|protected|public)\s+([^$]*\$(\w+))\s*;/', $line, $matches) && !preg_match('/readonly/', $line)) {
                $visibility = $matches[1];
                $propertyDeclaration = $matches[2];
                $propertyName = $matches[3];
                $suggestions[] = [
                    'message' => 'Considérez utiliser readonly pour les propriétés immutables',
                    'line' => $actualLineNumber,
                    'rule' => 'readonly_properties',
                    'suggestion' => 'PHP 8.1+ : readonly garantit l\'immutabilité après initialisation',
                    'before_code' => "$visibility $propertyDeclaration;",
                    'after_code' => "$visibility readonly $propertyDeclaration;",
                    'explanation' => "Les propriétés readonly ne peuvent être assignées qu'une seule fois (dans le constructeur ou à la déclaration). Elles éliminent les getters et améliorent les performances."
                ];
            }

            // 5. Union types au lieu de PHPDoc
            if (preg_match('/@param\s+([\w\|]+)\s+\$(\w+)/', $line, $matches)) {
                $unionType = $matches[1];
                $paramName = $matches[2];

                // Vérifier si c'est un union type valide
                if (strpos($unionType, '|') !== false) {
                    $suggestions[] = [
                        'message' => 'Utilisez les union types natifs au lieu de PHPDoc',
                        'line' => $actualLineNumber,
                        'rule' => 'union_types',
                        'suggestion' => 'PHP 8.0+ : Types natifs plus robustes que la documentation',
                        'before_code' => "/**\n * @param $unionType \$$paramName\n */\nfunction method(\$$paramName)",
                        'after_code' => "function method($unionType \$$paramName)",
                        'explanation' => "Les union types natifs offrent une vérification à l'exécution, une meilleure intégration IDE, et éliminent la divergence entre documentation et code réel."
                    ];
                }
            }

            // 5b. Nullable types avec ?
            if (preg_match('/@param\s+(\w+)\|null\s+\$(\w+)/', $line, $matches)) {
                $type = $matches[1];
                $paramName = $matches[2];
                $suggestions[] = [
                    'message' => 'Utilisez la syntaxe nullable ? au lieu de union avec null',
                    'line' => $actualLineNumber,
                    'rule' => 'nullable_types',
                    'suggestion' => 'PHP 7.1+ : Syntaxe plus concise pour les types nullable',
                    'before_code' => "/**\n * @param $type|null \$$paramName\n */\nfunction method(\$$paramName)",
                    'after_code' => "function method(?$type \$$paramName)",
                    'explanation' => "La syntaxe ?Type est plus concise que Type|null et indique clairement qu'un paramètre peut être null."
                ];
            }

            // 6. Enum au lieu de constantes de classe
            if (preg_match('/const\s+([A-Z_]+)\s*=\s*([\'"][^\'"]+[\'"])/', $line, $matches)) {
                $constName = $matches[1];
                $constValue = $matches[2];
                $suggestions[] = [
                    'message' => 'Considérez utiliser des Enums pour les constantes liées',
                    'line' => $actualLineNumber,
                    'rule' => 'enum_usage',
                    'suggestion' => 'PHP 8.1+ : Enums offrent type-safety et fonctionnalités avancées',
                    'before_code' => "class Status {\n    const $constName = $constValue;\n    const INACTIVE = 'inactive';\n}",
                    'after_code' => "enum Status: string {\n    case " . str_replace('_', '', ucwords(strtolower($constName), '_')) . " = $constValue;\n    case Inactive = 'inactive';\n}",
                    'explanation' => "Les Enums garantissent que seules les valeurs définies sont utilisées, offrent des méthodes intégrées (cases(), name, value), et améliorent la lisibilité du code."
                ];
            }

            // 7. Fibers pour l'asynchrone
            if (preg_match('/(curl_multi_|curl_exec.*curl_close|async|promise)/i', $line, $matches)) {
                $pattern = $matches[1];
                $suggestions[] = [
                    'message' => 'Considérez utiliser Fibers pour la programmation asynchrone',
                    'line' => $actualLineNumber,
                    'rule' => 'fibers',
                    'suggestion' => 'PHP 8.1+ : Fibers permettent une concurrence native sans callbacks',
                    'before_code' => "// Approche bloquante\n\$ch = curl_init();\ncurl_setopt(\$ch, CURLOPT_URL, \$url);\n\$result = curl_exec(\$ch);\ncurl_close(\$ch);",
                    'after_code' => "// Avec Fibers (non-bloquant)\n\$fiber = new Fiber(function() {\n    // Code async ici\n    Fiber::suspend();\n});\n\$fiber->start();",
                    'explanation' => "Les Fibers permettent d'interrompre et reprendre l'exécution de code, idéal pour I/O asynchrone, requêtes parallèles, et programmation coopérative sans complexité des callbacks."
                ];
            }

            // 8. First-class callable syntax
            if (preg_match('/array\s*\(\s*(\$\w+|\w+::class)\s*,\s*[\'"](\w+)[\'"]\s*\)/', $line, $matches)) {
                $object = $matches[1];
                $method = $matches[2];
                $suggestions[] = [
                    'message' => 'Utilisez la syntaxe first-class callable',
                    'line' => $actualLineNumber,
                    'rule' => 'first_class_callable',
                    'suggestion' => 'PHP 8.1+ : Syntaxe plus claire et performante pour les callables',
                    'before_code' => "array($object, '$method')",
                    'after_code' => "$object->$method(...)",
                    'explanation' => "La syntaxe first-class callable (...) est plus lisible, offre une meilleure vérification statique, et est généralement plus performante que les arrays callables."
                ];
            }

            // 8b. Callable avec call_user_func
            if (preg_match('/call_user_func\s*\(\s*array\s*\(\s*(\$\w+),\s*[\'"](\w+)[\'"]\s*\)/', $line, $matches)) {
                $object = $matches[1];
                $method = $matches[2];
                $suggestions[] = [
                    'message' => 'Simplifiez call_user_func avec array callable',
                    'line' => $actualLineNumber,
                    'rule' => 'direct_method_call',
                    'suggestion' => 'PHP 8.1+ : Appel direct plus lisible et performant',
                    'before_code' => "call_user_func(array($object, '$method'))",
                    'after_code' => "$object->$method() // ou $object->$method(...) pour callable",
                    'explanation' => "L'appel direct de méthode est plus rapide et lisible que call_user_func. Utilisez first-class callable (...) si vous passez la méthode comme callback."
                ];
            }

            // 9. Constructor Property Promotion (PHP 8.0+)
            if (preg_match('/function\s+__construct\s*\([^)]*\$(\w+)[^)]*\)/', $line, $matches)) {
                $suggestions[] = [
                    'message' => 'Considérez utiliser Constructor Property Promotion',
                    'line' => $actualLineNumber,
                    'rule' => 'constructor_promotion',
                    'suggestion' => 'PHP 8.0+ : Simplifiez les propriétés et constructeur',
                    'before_code' => "private \$name;\npublic function __construct(string \$name) {\n    \$this->name = \$name;\n}",
                    'after_code' => "public function __construct(private string \$name) {}",
                    'explanation' => "Constructor Property Promotion réduit le code boilerplate en déclarant et assignant les propriétés directement dans les paramètres du constructeur."
                ];
            }

            // 10. Named Arguments (PHP 8.0+)
            if (preg_match('/(\w+)\s*\([^)]*,\s*[^)]*,\s*[^)]*\)/', $line, $matches)) {
                $functionName = $matches[1];
                if (!in_array($functionName, ['if', 'for', 'while', 'foreach', 'switch'])) {
                    $suggestions[] = [
                        'message' => 'Considérez utiliser les arguments nommés pour plus de clarté',
                        'line' => $actualLineNumber,
                        'rule' => 'named_arguments',
                        'suggestion' => 'PHP 8.0+ : Arguments nommés améliorent la lisibilité',
                        'before_code' => "createUser('John', 'Doe', 25, true, false)",
                        'after_code' => "createUser(\n    firstName: 'John',\n    lastName: 'Doe',\n    age: 25,\n    isActive: true,\n    isAdmin: false\n)",
                        'explanation' => "Les arguments nommés permettent de passer les paramètres dans n'importe quel ordre, d'omettre les paramètres optionnels, et rendent le code plus lisible."
                    ];
                }
            }

            // 11. PHP 8.4 - Array destructuring avec clés
            if (preg_match('/list\s*\(/', $line)) {
                $suggestions[] = [
                    'message' => 'Utilisez la syntaxe de destructuration moderne []',
                    'line' => $actualLineNumber,
                    'rule' => 'array_destructuring',
                    'suggestion' => 'PHP 7.1+ : Syntaxe [] plus moderne que list()',
                    'before_code' => "list(\$a, \$b) = \$array;",
                    'after_code' => "[\$a, \$b] = \$array;",
                    'explanation' => "La syntaxe [] est plus consistante avec la déclaration d'arrays et supporte la destructuration avec clés nommées."
                ];
            }

            // 12. PHP 8.2+ - Classes readonly
            if (preg_match('/class\s+(\w+)/', $line, $matches)) {
                $className = $matches[1];
                if (!preg_match('/readonly\s+class/', $line)) {
                    $suggestions[] = [
                        'message' => 'Considérez utiliser readonly class pour les value objects',
                        'line' => $actualLineNumber,
                        'rule' => 'readonly_class',
                        'suggestion' => 'PHP 8.2+ : Classes readonly pour l\'immutabilité complète',
                        'before_code' => "class $className {\n    public readonly string \$name;\n    public readonly int \$age;\n}",
                        'after_code' => "readonly class $className {\n    public string \$name;\n    public int \$age;\n}",
                        'explanation' => "Les classes readonly rendent toutes les propriétés readonly automatiquement, parfait pour les value objects et DTOs immutables."
                    ];
                }
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