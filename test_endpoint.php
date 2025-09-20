<?php

declare(strict_types=1);

// Test direct de l'endpoint /analyze sans passer par Slim (pour identifier le problème)

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simuler les données d'upload
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$_FILES = [
    'files' => [
        'name' => 'test.php',
        'type' => 'text/plain',
        'tmp_name' => '/tmp/test',
        'error' => 0,
        'size' => 100
    ]
];

// Créer le contenu de test
$testContent = '<?php

declare(strict_types=1);

namespace TestApp;

class TestClass
{
    public function hello(): string
    {
        return "Hello World";
    }
}
';

// Créer un fichier temporaire
$tempFile = tempnam(sys_get_temp_dir(), 'test_');
file_put_contents($tempFile, $testContent);
$_FILES['files']['tmp_name'] = $tempFile;

echo "<h1>Test direct de la logique d'analyse</h1>";

try {
    // Simuler l'upload PSR-7
    class MockUploadedFile {
        private $content;
        private $filename;
        
        public function __construct($content, $filename) {
            $this->content = $content;
            $this->filename = $filename;
        }
        
        public function getError() { return UPLOAD_ERR_OK; }
        public function getClientFilename() { return $this->filename; }
        public function getStream() {
            return new class($this->content) {
                private $content;
                public function __construct($content) { $this->content = $content; }
                public function getContents() { return $this->content; }
            };
        }
    }
    
    $uploadedFiles = [
        'files' => [new MockUploadedFile($testContent, 'test.php')]
    ];
    
    if (empty($uploadedFiles['files'])) {
        throw new Exception('No files');
    }

    $files = is_array($uploadedFiles['files']) 
        ? $uploadedFiles['files'] 
        : [$uploadedFiles['files']];

    // Analyser les fichiers de manière simplifiée
    $results = [
        'summary' => [
            'compliant' => 0,
            'warnings' => 0,
            'errors' => 0,
            'total_files' => count($files)
        ],
        'files' => []
    ];

    foreach ($files as $uploadedFile) {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            continue;
        }

        $filename = $uploadedFile->getClientFilename();
        $content = $uploadedFile->getStream()->getContents();
        
        echo "<h2>Analyse du fichier: $filename</h2>";
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
        
        // Analyse basique du contenu
        $issues = [];
        
        // Vérifier PSR-12 declare strict_types
        if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Déclaration strict_types=1 manquante',
                'line' => 1,
                'rule' => 'PSR-12.declare_strict',
                'suggestion' => 'Ajoutez declare(strict_types=1); après <?php'
            ];
        }

        // Vérifier namespace
        if (!preg_match('/namespace\s+([^;]+);/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Déclaration de namespace manquante',
                'line' => 1,
                'rule' => 'PSR-4.namespace',
                'suggestion' => 'Ajoutez une déclaration de namespace'
            ];
        }

        // Vérifier les tags PHP
        if (!preg_match('/^<\?php/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'message' => 'Le fichier doit commencer par <?php',
                'line' => 1,
                'rule' => 'PSR-1.php_tags',
                'suggestion' => 'Ajoutez <?php au début du fichier'
            ];
        }

        // Vérifier les effets de bord
        if (preg_match('/echo|print|printf/', $content)) {
            $issues[] = [
                'severity' => 'warning',
                'message' => 'Possible effet de bord détecté (output)',
                'line' => 1,
                'rule' => 'PSR-1.side_effects',
                'suggestion' => 'Évitez les sorties directes dans les fichiers de classe'
            ];
        }

        // Déterminer le statut
        $hasErrors = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $hasWarnings = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');
        
        $status = 'success';
        if (!empty($hasErrors)) {
            $status = 'error';
        } elseif (!empty($hasWarnings)) {
            $status = 'warning';
        } else {
            $status = 'success';
        }

        $results['files'][] = [
            'name' => $filename,
            'status' => $status,
            'issues' => $issues,
            'psr_compliance' => [
                ['standard' => 'PSR-1', 'compliant' => empty($hasErrors)],
                ['standard' => 'PSR-2', 'compliant' => true],
                ['standard' => 'PSR-4', 'compliant' => empty($hasErrors)],
                ['standard' => 'PSR-12', 'compliant' => empty($hasErrors)]
            ],
            'metrics' => [
                'total_issues' => count($issues),
                'errors' => count($hasErrors),
                'warnings' => count($hasWarnings),
                'info' => 0
            ]
        ];
        
        echo "<h3>Issues trouvées:</h3>";
        foreach ($issues as $issue) {
            echo "<p><strong>" . $issue['severity'] . ":</strong> " . $issue['message'] . "</p>";
        }
        echo "<p><strong>Status:</strong> $status</p>";
    }

    // Calculer le résumé basé sur les résultats
    foreach ($results['files'] as $file) {
        switch ($file['status']) {
            case 'error':
                $results['summary']['errors']++;
                break;
            case 'warning':
                $results['summary']['warnings']++;
                break;
            case 'success':
                $results['summary']['compliant']++;
                break;
        }
    }

    $response = [
        'success' => true,
        'message' => 'Analyse réussie (mode simplifié)',
        'data' => $results,
        'timestamp' => date('c')
    ];
    
    echo "<h2>Résultat final:</h2>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erreur:</h2>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Nettoyer
unlink($tempFile);

echo "<h2>✅ Test terminé</h2>";
?>