<?php

declare(strict_types=1);

namespace PhpOptimizer\Services;

use PhpOptimizer\Analyzers\PsrAnalyzer;
use PhpOptimizer\Analyzers\PhpStanAnalyzer;
use PhpOptimizer\Analyzers\CsFixerAnalyzer;
use PhpOptimizer\Analyzers\CodeSnifferAnalyzer;
use PhpOptimizer\Analyzers\RectorAnalyzer;

class AnalysisService
{
    private PsrAnalyzer $psrAnalyzer;
    private PhpStanAnalyzer $phpstanAnalyzer;
    private CsFixerAnalyzer $csFixerAnalyzer;
    private CodeSnifferAnalyzer $codeSnifferAnalyzer;
    private RectorAnalyzer $rectorAnalyzer;
    private string $reportsDirectory;

    public function __construct()
    {
        $this->psrAnalyzer = new PsrAnalyzer();
        $this->phpstanAnalyzer = new PhpStanAnalyzer();
        $this->csFixerAnalyzer = new CsFixerAnalyzer();
        $this->codeSnifferAnalyzer = new CodeSnifferAnalyzer();
        $this->rectorAnalyzer = new RectorAnalyzer();
        $this->reportsDirectory = dirname(__DIR__, 2) . '/storage/reports/';
        $this->ensureReportsDirectoryExists();
    }

    public function analyzeFiles(array $files): array
    {
        $results = [
            'summary' => [
                'compliant' => 0,
                'warnings' => 0,
                'errors' => 0,
                'total_files' => count($files)
            ],
            'files' => []
        ];

        foreach ($files as $fileData) {
            $filePath = $fileData['path'];
            $fileName = $fileData['original_name'];

            try {
                $fileAnalysis = $this->analyzeFile($filePath, $fileName);
                $results['files'][] = $fileAnalysis;

                switch ($fileAnalysis['status']) {
                    case 'success':
                        $results['summary']['compliant']++;
                        break;
                    case 'warning':
                        $results['summary']['warnings']++;
                        break;
                    case 'error':
                        $results['summary']['errors']++;
                        break;
                }
            } catch (\Exception $e) {
                $results['files'][] = [
                    'name' => $fileName,
                    'path' => $filePath,
                    'status' => 'error',
                    'issues' => [[
                        'severity' => 'error',
                        'message' => 'Erreur lors de l\'analyse: ' . $e->getMessage(),
                        'line' => 0,
                        'rule' => 'ANALYSIS_ERROR'
                    ]],
                    'psr_compliance' => []
                ];
                $results['summary']['errors']++;
            }
        }

        $reportId = $this->saveReport($results);
        $results['report_id'] = $reportId;

        return $results;
    }

    private function analyzeFile(string $filePath, string $fileName): array
    {
        $allIssues = [];
        $psrCompliance = [];

        $psrResults = $this->psrAnalyzer->analyze($filePath);
        $allIssues = array_merge($allIssues, $psrResults['issues']);
        $psrCompliance = $psrResults['psr_compliance'];

        $phpstanResults = $this->phpstanAnalyzer->analyze($filePath);
        $allIssues = array_merge($allIssues, $phpstanResults['issues']);

        $csFixerResults = $this->csFixerAnalyzer->analyze($filePath);
        $allIssues = array_merge($allIssues, $csFixerResults['issues']);

        $codeSnifferResults = $this->codeSnifferAnalyzer->analyze($filePath);
        $allIssues = array_merge($allIssues, $codeSnifferResults['issues']);

        // Analyse Rector pour migration PHP 8.4
        $rectorResults = $this->rectorAnalyzer->analyze($filePath);
        $allIssues = array_merge($allIssues, $rectorResults['issues']);

        $status = $this->determineFileStatus($allIssues);

        return [
            'name' => $fileName,
            'path' => $filePath,
            'status' => $status,
            'issues' => $allIssues,
            'psr_compliance' => $psrCompliance,
            'migration_summary' => $rectorResults['migration_summary'] ?? null,
            'metrics' => [
                'total_issues' => count($allIssues),
                'errors' => count(array_filter($allIssues, fn($issue) => $issue['severity'] === 'error')),
                'warnings' => count(array_filter($allIssues, fn($issue) => $issue['severity'] === 'warning')),
                'info' => count(array_filter($allIssues, fn($issue) => $issue['severity'] === 'info')),
                'migration_suggestions' => count(array_filter($allIssues, fn($issue) => ($issue['category'] ?? '') === 'migration'))
            ]
        ];
    }

    private function determineFileStatus(array $issues): string
    {
        $hasErrors = array_filter($issues, fn($issue) => $issue['severity'] === 'error');
        $hasWarnings = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');

        if (!empty($hasErrors)) {
            return 'error';
        }

        if (!empty($hasWarnings)) {
            return 'warning';
        }

        return 'success';
    }

    private function saveReport(array $results): string
    {
        $reportId = 'report_' . time() . '_' . bin2hex(random_bytes(8));
        $reportPath = $this->reportsDirectory . $reportId . '.json';
        
        file_put_contents($reportPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $reportId;
    }

    public function getReport(string $reportId): ?array
    {
        $reportPath = $this->reportsDirectory . $reportId . '.json';
        
        if (!file_exists($reportPath)) {
            return null;
        }

        $content = file_get_contents($reportPath);
        return json_decode($content, true);
    }

    private function ensureReportsDirectoryExists(): void
    {
        if (!is_dir($this->reportsDirectory)) {
            mkdir($this->reportsDirectory, 0755, true);
        }
    }
}