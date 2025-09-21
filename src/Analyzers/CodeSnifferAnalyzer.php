<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class CodeSnifferAnalyzer
{
    public function analyze(string $filePath): array
    {
        $issues = [];

        try {
            // Analyse PSR12 simple sans shell_exec
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            $issues = array_merge($issues, $this->analyzePsr12Compliance($content, $lines));

        } catch (\Exception $e) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'PHP_CodeSniffer non disponible sur cet environnement (shell_exec désactivé)',
                'line' => 0,
                'rule' => 'CodeSniffer.environment_limitation',
                'suggestion' => 'Utilisez un environnement de développement local pour l\'analyse CodeSniffer complète'
            ];
        }

        return ['issues' => $issues];
    }

    private function analyzePsr12Compliance(string $content, array $lines): array
    {
        $issues = [];

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            // Vérifier les lignes trop longues (PSR-12 recommande 120 caractères max)
            if (strlen(rtrim($line)) > 120) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Ligne trop longue (' . strlen(rtrim($line)) . ' caractères)',
                    'line' => $actualLineNumber,
                    'rule' => 'CodeSniffer.PSR12.line_length',
                    'suggestion' => 'Limitez les lignes à 120 caractères maximum'
                ];
            }

            // Vérifier l'indentation (4 espaces)
            if (preg_match('/^(\s+)/', $line, $matches)) {
                $indent = $matches[1];
                if (strpos($indent, "\t") !== false) {
                    $issues[] = [
                        'severity' => 'error',
                        'message' => 'Utilisation de tabulations pour l\'indentation',
                        'line' => $actualLineNumber,
                        'rule' => 'CodeSniffer.PSR12.indentation',
                        'suggestion' => 'Utilisez 4 espaces pour l\'indentation'
                    ];
                } elseif (strlen($indent) % 4 !== 0 && !empty(trim($line))) {
                    $issues[] = [
                        'severity' => 'warning',
                        'message' => 'Indentation incorrecte (doit être un multiple de 4 espaces)',
                        'line' => $actualLineNumber,
                        'rule' => 'CodeSniffer.PSR12.indentation',
                        'suggestion' => 'Utilisez des multiples de 4 espaces pour l\'indentation'
                    ];
                }
            }

            // Vérifier les espaces en fin de ligne
            if (preg_match('/\s+$/', $line) && !empty(trim($line))) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Espaces en fin de ligne',
                    'line' => $actualLineNumber,
                    'rule' => 'CodeSniffer.PSR12.trailing_whitespace',
                    'suggestion' => 'Supprimez les espaces en fin de ligne'
                ];
            }

            // Vérifier les accolades des classes et méthodes
            if (preg_match('/^(class|interface|trait)\s+\w+.*\{$/', trim($line))) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'L\'accolade ouvrante de classe doit être sur une nouvelle ligne',
                    'line' => $actualLineNumber,
                    'rule' => 'CodeSniffer.PSR12.class_brace',
                    'suggestion' => 'Placez l\'accolade ouvrante sur la ligne suivante'
                ];
            }

            if (preg_match('/function\s+\w+.*\{$/', trim($line))) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'L\'accolade ouvrante de méthode doit être sur une nouvelle ligne',
                    'line' => $actualLineNumber,
                    'rule' => 'CodeSniffer.PSR12.method_brace',
                    'suggestion' => 'Placez l\'accolade ouvrante sur la ligne suivante'
                ];
            }

            // Vérifier les mots-clés de visibilité
            if (preg_match('/\$\w+\s*;/', $line) && !preg_match('/(public|private|protected)\s+/', $line)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Propriété sans modificateur de visibilité',
                    'line' => $actualLineNumber,
                    'rule' => 'CodeSniffer.PSR12.visibility',
                    'suggestion' => 'Ajoutez public, private ou protected'
                ];
            }
        }

        return $issues;
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