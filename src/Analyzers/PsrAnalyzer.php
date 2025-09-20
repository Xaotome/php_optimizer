<?php

declare(strict_types=1);

namespace PhpOptimizer\Analyzers;

class PsrAnalyzer
{
    private array $psrRules = [
        'PSR-1' => [
            'php_tags' => 'Le fichier doit utiliser uniquement les tags <?php ou <?=',
            'encoding' => 'Le fichier doit être encodé en UTF-8 sans BOM',
            'side_effects' => 'Le fichier ne doit pas avoir d\'effets de bord'
        ],
        'PSR-2' => [
            'indentation' => 'L\'indentation doit utiliser 4 espaces',
            'line_length' => 'Les lignes ne doivent pas dépasser 120 caractères',
            'blank_lines' => 'Il doit y avoir une ligne vide après le namespace et les use'
        ],
        'PSR-4' => [
            'namespace' => 'Le namespace doit correspondre à la structure des répertoires',
            'class_name' => 'Le nom de la classe doit correspondre au nom du fichier'
        ],
        'PSR-12' => [
            'declare_strict' => 'Le fichier doit avoir declare(strict_types=1)',
            'imports' => 'Les imports doivent être triés alphabétiquement',
            'visibility' => 'Toutes les propriétés et méthodes doivent avoir une visibilité déclarée'
        ]
    ];

    public function analyze(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        $issues = [];
        $psrCompliance = [];

        foreach ($this->psrRules as $psrStandard => $rules) {
            $compliant = true;
            $standardIssues = [];

            switch ($psrStandard) {
                case 'PSR-1':
                    $standardIssues = $this->checkPsr1($content, $lines);
                    break;
                case 'PSR-2':
                    $standardIssues = $this->checkPsr2($content, $lines);
                    break;
                case 'PSR-4':
                    $standardIssues = $this->checkPsr4($content, $lines, $filePath);
                    break;
                case 'PSR-12':
                    $standardIssues = $this->checkPsr12($content, $lines);
                    break;
            }

            if (!empty($standardIssues)) {
                $compliant = false;
                $issues = array_merge($issues, $standardIssues);
            }

            $psrCompliance[] = [
                'standard' => $psrStandard,
                'compliant' => $compliant,
                'issues_count' => count($standardIssues)
            ];
        }

        return [
            'issues' => $issues,
            'psr_compliance' => $psrCompliance
        ];
    }

    private function checkPsr1(string $content, array $lines): array
    {
        $issues = [];

        if (!preg_match('/^<\?php/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Le fichier doit commencer par <?php',
                'line' => 1,
                'rule' => 'PSR-1.php_tags',
                'suggestion' => 'Ajoutez <?php au début du fichier'
            ];
        }

        if (preg_match('/<\?(?!php|=)/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Utilisation de tags PHP non autorisés',
                'line' => 1,
                'rule' => 'PSR-1.php_tags',
                'suggestion' => 'Utilisez uniquement <?php ou <?='
            ];
        }

        $bom = pack('H*','EFBBBF');
        if (substr($content, 0, 3) === $bom) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Le fichier contient un BOM UTF-8',
                'line' => 1,
                'rule' => 'PSR-1.encoding',
                'suggestion' => 'Supprimez le BOM UTF-8 du fichier'
            ];
        }

        if (preg_match('/echo|print|printf/', $content)) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Possible effet de bord détecté (output)',
                'line' => 1,
                'rule' => 'PSR-1.side_effects',
                'suggestion' => 'Évitez les sorties directes dans les fichiers de classe'
            ];
        }

        return $issues;
    }

    private function checkPsr2(string $content, array $lines): array
    {
        $issues = [];

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            if (preg_match('/^\t/', $line)) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Utilisation de tabulations au lieu d\'espaces',
                    'line' => $actualLineNumber,
                    'rule' => 'PSR-2.indentation',
                    'suggestion' => 'Utilisez 4 espaces pour l\'indentation'
                ];
            }

            if (strlen(rtrim($line)) > 120) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Ligne trop longue (' . strlen(rtrim($line)) . ' caractères)',
                    'line' => $actualLineNumber,
                    'rule' => 'PSR-2.line_length',
                    'suggestion' => 'Divisez la ligne en plusieurs lignes'
                ];
            }

            if (preg_match('/\s+$/', $line)) {
                $issues[] = [
                    'severity' => 'info',
                    'message' => 'Espaces en fin de ligne',
                    'line' => $actualLineNumber,
                    'rule' => 'PSR-2.trailing_whitespace',
                    'suggestion' => 'Supprimez les espaces en fin de ligne'
                ];
            }
        }

        if (preg_match('/namespace\s+[^;]+;(?!\s*\n\s*\n)/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Ligne vide manquante après la déclaration du namespace',
                'line' => 1,
                'rule' => 'PSR-2.blank_lines',
                'suggestion' => 'Ajoutez une ligne vide après le namespace'
            ];
        }

        return $issues;
    }

    private function checkPsr4(string $content, array $lines, string $filePath): array
    {
        $issues = [];

        if (!preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Déclaration de namespace manquante',
                'line' => 1,
                'rule' => 'PSR-4.namespace',
                'suggestion' => 'Ajoutez une déclaration de namespace'
            ];
            return $issues;
        }

        if (!preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            return $issues;
        }

        $className = $classMatches[1];
        $fileName = pathinfo($filePath, PATHINFO_FILENAME);

        if ($className !== $fileName) {
            $issues[] = [
                'severity' => 'error',
                'message' => "Le nom de la classe '$className' ne correspond pas au nom du fichier '$fileName'",
                'line' => 1,
                'rule' => 'PSR-4.class_name',
                'suggestion' => "Renommez la classe ou le fichier pour qu'ils correspondent"
            ];
        }

        return $issues;
    }

    private function checkPsr12(string $content, array $lines): array
    {
        $issues = [];

        if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Déclaration strict_types manquante',
                'line' => 1,
                'rule' => 'PSR-12.declare_strict',
                'suggestion' => 'Ajoutez declare(strict_types=1); après <?php'
            ];
        }

        preg_match_all('/use\s+([^;]+);/', $content, $useMatches);
        if (count($useMatches[1]) > 1) {
            $uses = $useMatches[1];
            $sortedUses = $uses;
            sort($sortedUses);
            
            if ($uses !== $sortedUses) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => 'Les déclarations use ne sont pas triées alphabétiquement',
                    'line' => 1,
                    'rule' => 'PSR-12.imports',
                    'suggestion' => 'Triez les déclarations use par ordre alphabétique'
                ];
            }
        }

        if (preg_match('/(?:public|private|protected)\s+(?:static\s+)?\$\w+|(?:public|private|protected)\s+(?:static\s+)?function/', $content)) {
            $publicCount = preg_match_all('/public\s+/', $content);
            $privateCount = preg_match_all('/private\s+/', $content);
            $protectedCount = preg_match_all('/protected\s+/', $content);
            $totalMembers = preg_match_all('/(?:function\s+\w+|\$\w+\s*[=;])/', $content);
            
            if (($publicCount + $privateCount + $protectedCount) < $totalMembers) {
                $issues[] = [
                    'severity' => 'error',
                    'message' => 'Certaines propriétés ou méthodes n\'ont pas de visibilité déclarée',
                    'line' => 1,
                    'rule' => 'PSR-12.visibility',
                    'suggestion' => 'Ajoutez public, private ou protected à toutes les propriétés et méthodes'
                ];
            }
        }

        return $issues;
    }
}