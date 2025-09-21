<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class CsFixerAnalyzer
{
    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            // Analyse de style simple sans shell_exec
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            $issues = array_merge($issues, $this->analyzeCodeStyle($content, $lines));

        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'PHP-CS-Fixer non disponible sur cet environnement (shell_exec désactivé)',
                'line' => 0,
                'rule' => 'CS-Fixer.environment_limitation',
                'suggestion' => 'Utilisez un environnement de développement local pour l\'analyse CS-Fixer complète'
            ];
        }

        return ['issues' => $issues];
    }

    private function analyzeCodeStyle(string $content, array $lines): array
    {
        $issues = [];

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les espaces en fin de ligne
            if (preg_match('/\s+$/', $line) && !empty(trim($line))) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Espaces en fin de ligne détectés',
                    'line' => $actualLineNumber,
                    'rule' => 'CS-Fixer.trailing_whitespace',
                    'suggestion' => 'Supprimez les espaces en fin de ligne'
                ];
            }

            // Vérifier l'indentation avec des tabulations
            if (preg_match('/^\t/', $line)) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Indentation avec des tabulations détectée',
                    'line' => $actualLineNumber,
                    'rule' => 'CS-Fixer.indentation',
                    'suggestion' => 'Utilisez 4 espaces pour l\'indentation au lieu des tabulations'
                ];
            }

            // Vérifier les espaces autour des opérateurs
            if (preg_match('/\$\w+=[^=]/', $line) && !preg_match('/\$\w+\s*=\s*/', $line)) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Espaces manquants autour de l\'opérateur d\'assignation',
                    'line' => $actualLineNumber,
                    'rule' => 'CS-Fixer.operator_spacing',
                    'suggestion' => 'Ajoutez des espaces autour des opérateurs'
                ];
            }

            // Vérifier l'utilisation de array() au lieu de []
            if (preg_match('/array\s*\(/', $line)) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Utilisation de array() au lieu de la syntaxe courte []',
                    'line' => $actualLineNumber,
                    'rule' => 'CS-Fixer.array_syntax',
                    'suggestion' => 'Remplacez array() par [] pour une syntaxe plus moderne'
                ];
            }

            // Vérifier les guillemets doubles inutiles
            if (preg_match('/"[^"\\\\$]*"/', $line) && !preg_match('/"[^"]*\$[^"]*"/', $line)) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Utilisation de guillemets doubles sans interpolation',
                    'line' => $actualLineNumber,
                    'rule' => 'CS-Fixer.single_quote',
                    'suggestion' => 'Utilisez des guillemets simples quand il n\'y a pas d\'interpolation'
                ];
            }
        }

        return $issues;
    }

    private function createTempConfig(): string
    {
        $config = [
            '@PSR12' => true,
            '@PHP84Migration' => true,
            'array_syntax' => ['syntax' => 'short'],
            'binary_operator_spaces' => true,
            'blank_line_after_opening_tag' => true,
            'blank_line_before_statement' => ['statements' => ['return']],
            'cast_spaces' => true,
            'class_attributes_separation' => ['elements' => ['method' => 'one']],
            'concat_space' => ['spacing' => 'one'],
            'declare_strict_types' => true,
            'function_typehint_space' => true,
            'include' => true,
            'lowercase_cast' => true,
            'magic_constant_casing' => true,
            'method_argument_space' => true,
            'native_function_casing' => true,
            'new_with_braces' => true,
            'no_blank_lines_after_class_opening' => true,
            'no_blank_lines_after_phpdoc' => true,
            'no_empty_statement' => true,
            'no_extra_blank_lines' => true,
            'no_leading_import_slash' => true,
            'no_leading_namespace_whitespace' => true,
            'no_mixed_echo_print' => ['use' => 'echo'],
            'no_multiline_whitespace_around_double_arrow' => true,
            'no_short_bool_cast' => true,
            'no_singleline_whitespace_before_semicolons' => true,
            'no_spaces_around_offset' => true,
            'no_trailing_comma_in_list_call' => true,
            'no_trailing_comma_in_singleline_array' => true,
            'no_unneeded_control_parentheses' => true,
            'no_unused_imports' => true,
            'no_whitespace_before_comma_in_array' => true,
            'no_whitespace_in_blank_line' => true,
            'normalize_index_brace' => true,
            'object_operator_without_whitespace' => true,
            'ordered_imports' => true,
            'php_unit_fqcn_annotation' => true,
            'phpdoc_align' => true,
            'phpdoc_annotation_without_dot' => true,
            'phpdoc_indent' => true,
            'phpdoc_inline_tag' => true,
            'phpdoc_no_access' => true,
            'phpdoc_no_alias_tag' => true,
            'phpdoc_no_empty_return' => true,
            'phpdoc_no_package' => true,
            'phpdoc_scalar' => true,
            'phpdoc_separation' => true,
            'phpdoc_single_line_var_spacing' => true,
            'phpdoc_summary' => true,
            'phpdoc_to_comment' => true,
            'phpdoc_trim' => true,
            'phpdoc_types' => true,
            'phpdoc_var_without_name' => true,
            'return_type_declaration' => true,
            'semicolon_after_instruction' => true,
            'short_scalar_cast' => true,
            'single_blank_line_before_namespace' => true,
            'single_class_element_per_statement' => true,
            'single_line_comment_style' => true,
            'single_quote' => true,
            'space_after_semicolon' => true,
            'standardize_not_equals' => true,
            'ternary_operator_spaces' => true,
            'trailing_comma_in_multiline' => true,
            'trim_array_spaces' => true,
            'unary_operator_spaces' => true,
            'whitespace_after_comma_in_array' => true
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'cs_fixer_config_');
        file_put_contents($tempFile, '<?php return ' . var_export(['rules' => $config], true) . ';');

        return $tempFile;
    }

    private function getFixerSeverity(string $fixer): string
    {
        $errorFixers = [
            'declare_strict_types',
            'no_unused_imports',
            'no_empty_statement',
            'php_unit_fqcn_annotation'
        ];

        $warningFixers = [
            'array_syntax',
            'single_quote',
            'trailing_comma_in_multiline',
            'ordered_imports'
        ];

        if (in_array($fixer, $errorFixers, true)) {
            return 'error';
        }

        if (in_array($fixer, $warningFixers, true)) {
            return 'warning';
        }

        return 'info';
    }

    private function getFixerMessage(string $fixer): string
    {
        $messages = [
            'declare_strict_types' => 'Déclaration strict_types manquante',
            'array_syntax' => 'Utilisation de la syntaxe array() au lieu de []',
            'single_quote' => 'Utilisation de guillemets doubles inutiles',
            'no_unused_imports' => 'Imports inutilisés détectés',
            'ordered_imports' => 'Les imports ne sont pas triés',
            'trailing_comma_in_multiline' => 'Virgule finale manquante dans les tableaux multilignes',
            'binary_operator_spaces' => 'Espaces incorrects autour des opérateurs binaires',
            'concat_space' => 'Espaces incorrects autour de l\'opérateur de concaténation',
            'method_argument_space' => 'Espaces incorrects dans les arguments de méthode',
            'no_whitespace_in_blank_line' => 'Espaces dans les lignes vides',
            'phpdoc_align' => 'Alignement PHPDoc incorrect',
            'return_type_declaration' => 'Espaces incorrects dans la déclaration de type de retour'
        ];

        return $messages[$fixer] ?? "Problème de formatage détecté par {$fixer}";
    }

    private function getFixerSuggestion(string $fixer): string
    {
        $suggestions = [
            'declare_strict_types' => 'Ajoutez declare(strict_types=1); après <?php',
            'array_syntax' => 'Remplacez array() par []',
            'single_quote' => 'Utilisez des guillemets simples quand possible',
            'no_unused_imports' => 'Supprimez les déclarations use inutilisées',
            'ordered_imports' => 'Triez les imports par ordre alphabétique',
            'trailing_comma_in_multiline' => 'Ajoutez une virgule après le dernier élément',
            'binary_operator_spaces' => 'Ajoutez des espaces autour des opérateurs',
            'concat_space' => 'Ajoutez un espace avant et après le point de concaténation',
            'method_argument_space' => 'Vérifiez les espaces dans les paramètres de méthode',
            'no_whitespace_in_blank_line' => 'Supprimez les espaces des lignes vides',
            'phpdoc_align' => 'Alignez correctement les commentaires PHPDoc',
            'return_type_declaration' => 'Vérifiez les espaces autour du type de retour'
        ];

        return $suggestions[$fixer] ?? 'Exécutez php-cs-fixer pour corriger automatiquement';
    }
}