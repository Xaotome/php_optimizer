<?php

declare(strict_types=1);

namespace PhpOptimizer\Models;

use PhpOptimizer\Database\Database;

class UsageStats
{
    private Database $db;
    private const FREE_PLAN_LIMIT = 20;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtenir l'utilisation actuelle d'un utilisateur
     */
    public function getCurrentUsage(int $userId): ?array
    {
        $sql = "SELECT * FROM usage_stats
                WHERE user_id = ? AND is_current_period = 1
                LIMIT 1";

        return $this->db->queryOne($sql, [$userId]);
    }

    /**
     * Incrémenter le compteur de fichiers analysés
     */
    public function incrementFilesCount(int $userId, int $count = 1): bool
    {
        $sql = "UPDATE usage_stats
                SET files_count = files_count + ?
                WHERE user_id = ? AND is_current_period = 1";

        return $this->db->execute($sql, [$count, $userId]);
    }

    /**
     * Vérifier si l'utilisateur peut analyser des fichiers
     */
    public function canAnalyze(int $userId, int $filesCount = 1): array
    {
        // Récupérer le plan de l'utilisateur
        $subscriptionModel = new Subscription();
        $plan = $subscriptionModel->getUserPlan($userId);

        // Si plan PRO ou supérieur, accès illimité
        if (in_array($plan, ['pro', 'team'])) {
            return [
                'can_analyze' => true,
                'reason' => 'unlimited',
                'remaining' => -1, // -1 = illimité
                'limit' => -1
            ];
        }

        // Plan gratuit : vérifier le quota
        $usage = $this->getCurrentUsage($userId);

        if (!$usage) {
            return [
                'can_analyze' => false,
                'reason' => 'no_usage_record',
                'remaining' => 0,
                'limit' => self::FREE_PLAN_LIMIT
            ];
        }

        $filesUsed = (int) $usage['files_count'];
        $remaining = self::FREE_PLAN_LIMIT - $filesUsed;

        $canAnalyze = ($filesUsed + $filesCount) <= self::FREE_PLAN_LIMIT;

        return [
            'can_analyze' => $canAnalyze,
            'reason' => $canAnalyze ? 'within_quota' : 'quota_exceeded',
            'remaining' => max(0, $remaining),
            'limit' => self::FREE_PLAN_LIMIT,
            'used' => $filesUsed
        ];
    }

    /**
     * Obtenir l'historique d'utilisation d'un utilisateur
     */
    public function getHistory(int $userId, int $limit = 12): array
    {
        $sql = "SELECT * FROM usage_stats
                WHERE user_id = ?
                ORDER BY period_start DESC
                LIMIT ?";

        return $this->db->query($sql, [$userId, $limit]);
    }

    /**
     * Créer une nouvelle période d'utilisation
     */
    public function createNewPeriod(int $userId): ?int
    {
        // Marquer l'ancienne période comme non-actuelle
        $this->db->execute(
            "UPDATE usage_stats SET is_current_period = 0 WHERE user_id = ? AND is_current_period = 1",
            [$userId]
        );

        // Créer la nouvelle période
        $sql = "INSERT INTO usage_stats
                (user_id, files_count, period_start, period_end, is_current_period)
                VALUES (?, 0, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), LAST_DAY(NOW()), 1)";

        try {
            $this->db->execute($sql, [$userId]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Create Period Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Réinitialiser le compteur mensuel (pour les CRON)
     */
    public function resetMonthlyQuotas(): int
    {
        try {
            // Appeler la procédure stockée
            $this->db->execute("CALL reset_monthly_quotas()");

            // Compter combien de périodes ont été créées
            $result = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM usage_stats WHERE is_current_period = 1"
            );

            return (int) ($result['count'] ?? 0);
        } catch (\PDOException $e) {
            error_log("Reset Quotas Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtenir les statistiques globales d'utilisation
     */
    public function getGlobalStats(): array
    {
        $sql = "SELECT
                    COUNT(DISTINCT user_id) as total_users,
                    SUM(files_count) as total_files,
                    AVG(files_count) as avg_files_per_user
                FROM usage_stats
                WHERE is_current_period = 1";

        return $this->db->queryOne($sql) ?? [];
    }
}
