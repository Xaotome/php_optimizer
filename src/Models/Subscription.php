<?php

declare(strict_types=1);

namespace PhpOptimizer\Models;

use PhpOptimizer\Database\Database;

class Subscription
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un nouvel abonnement
     */
    public function create(array $data): ?int
    {
        $sql = "INSERT INTO subscriptions
                (user_id, stripe_subscription_id, stripe_price_id, status, plan, amount, currency,
                 current_period_start, current_period_end, trial_start, trial_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->db->execute($sql, [
                $data['user_id'],
                $data['stripe_subscription_id'] ?? null,
                $data['stripe_price_id'] ?? null,
                $data['status'] ?? 'active',
                $data['plan'] ?? 'pro',
                $data['amount'] ?? 5.00,
                $data['currency'] ?? 'EUR',
                $data['current_period_start'] ?? null,
                $data['current_period_end'] ?? null,
                $data['trial_start'] ?? null,
                $data['trial_end'] ?? null,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Subscription Creation Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Trouver l'abonnement actif d'un utilisateur
     */
    public function findActiveByUserId(int $userId): ?array
    {
        $sql = "SELECT * FROM subscriptions
                WHERE user_id = ? AND status IN ('active', 'trialing')
                ORDER BY created_at DESC LIMIT 1";

        return $this->db->queryOne($sql, [$userId]);
    }

    /**
     * Trouver un abonnement par Stripe Subscription ID
     */
    public function findByStripeId(string $stripeSubscriptionId): ?array
    {
        $sql = "SELECT * FROM subscriptions WHERE stripe_subscription_id = ? LIMIT 1";
        return $this->db->queryOne($sql, [$stripeSubscriptionId]);
    }

    /**
     * Mettre à jour le statut d'un abonnement
     */
    public function updateStatus(int $subscriptionId, string $status): bool
    {
        $sql = "UPDATE subscriptions SET status = ? WHERE id = ?";
        return $this->db->execute($sql, [$status, $subscriptionId]);
    }

    /**
     * Mettre à jour un abonnement depuis un webhook Stripe
     */
    public function updateFromStripe(string $stripeSubscriptionId, array $data): bool
    {
        $sql = "UPDATE subscriptions SET
                status = ?,
                current_period_start = ?,
                current_period_end = ?,
                cancel_at_period_end = ?
                WHERE stripe_subscription_id = ?";

        return $this->db->execute($sql, [
            $data['status'] ?? 'active',
            $data['current_period_start'] ?? null,
            $data['current_period_end'] ?? null,
            $data['cancel_at_period_end'] ?? 0,
            $stripeSubscriptionId
        ]);
    }

    /**
     * Annuler un abonnement (à la fin de la période)
     */
    public function cancelAtPeriodEnd(int $subscriptionId): bool
    {
        $sql = "UPDATE subscriptions SET cancel_at_period_end = 1, canceled_at = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$subscriptionId]);
    }

    /**
     * Annuler immédiatement un abonnement
     */
    public function cancelNow(int $subscriptionId): bool
    {
        $sql = "UPDATE subscriptions SET
                status = 'canceled',
                canceled_at = NOW(),
                ended_at = NOW()
                WHERE id = ?";

        return $this->db->execute($sql, [$subscriptionId]);
    }

    /**
     * Vérifier si un utilisateur a un abonnement actif
     */
    public function hasActiveSubscription(int $userId): bool
    {
        $subscription = $this->findActiveByUserId($userId);
        return $subscription !== null;
    }

    /**
     * Obtenir le plan d'un utilisateur (free, pro, team)
     */
    public function getUserPlan(int $userId): string
    {
        $subscription = $this->findActiveByUserId($userId);
        return $subscription['plan'] ?? 'free';
    }

    /**
     * Alias pour findActiveByUserId (pour compatibilité avec SubscriptionController)
     */
    public function getActiveSubscription(int $userId): ?array
    {
        return $this->findActiveByUserId($userId);
    }

    /**
     * Alias pour findByStripeId (pour compatibilité avec SubscriptionController)
     */
    public function getByStripeId(string $stripeSubscriptionId): ?array
    {
        return $this->findByStripeId($stripeSubscriptionId);
    }

    /**
     * Créer ou mettre à jour un abonnement depuis Stripe
     */
    public function createOrUpdateSubscription(array $data): bool
    {
        // Vérifier si l'abonnement existe déjà
        $existing = $this->findByStripeId($data['stripe_subscription_id']);

        if ($existing) {
            // Mettre à jour l'abonnement existant
            $sql = "UPDATE subscriptions SET
                    stripe_customer_id = ?,
                    stripe_price_id = ?,
                    status = ?,
                    current_period_start = ?,
                    current_period_end = ?,
                    cancel_at_period_end = ?
                    WHERE stripe_subscription_id = ?";

            return $this->db->execute($sql, [
                $data['stripe_customer_id'] ?? null,
                $data['stripe_price_id'] ?? null,
                $data['status'] ?? 'active',
                $data['current_period_start'] ?? null,
                $data['current_period_end'] ?? null,
                $data['cancel_at_period_end'] ?? 0,
                $data['stripe_subscription_id']
            ]);
        } else {
            // Créer un nouvel abonnement
            $sql = "INSERT INTO subscriptions
                    (user_id, stripe_subscription_id, stripe_customer_id, stripe_price_id,
                     status, plan, current_period_start, current_period_end, cancel_at_period_end)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            return $this->db->execute($sql, [
                $data['user_id'],
                $data['stripe_subscription_id'],
                $data['stripe_customer_id'] ?? null,
                $data['stripe_price_id'] ?? null,
                $data['status'] ?? 'active',
                'pro', // Plan par défaut
                $data['current_period_start'] ?? null,
                $data['current_period_end'] ?? null,
                $data['cancel_at_period_end'] ?? 0,
            ]);
        }
    }

    /**
     * Annuler un abonnement
     */
    public function cancelSubscription(int $subscriptionId): bool
    {
        $sql = "UPDATE subscriptions SET
                status = 'canceled',
                canceled_at = NOW(),
                ended_at = NOW()
                WHERE id = ?";

        return $this->db->execute($sql, [$subscriptionId]);
    }

    /**
     * Enregistrer une facture
     */
    public function recordInvoice(array $data): ?int
    {
        $sql = "INSERT INTO invoices
                (user_id, subscription_id, stripe_invoice_id, amount, currency, status, invoice_pdf, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->db->execute($sql, [
                $data['user_id'],
                $data['subscription_id'] ?? null,
                $data['stripe_invoice_id'],
                $data['amount'],
                $data['currency'] ?? 'EUR',
                $data['status'] ?? 'paid',
                $data['invoice_pdf'] ?? null,
                $data['paid_at'] ?? null,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Invoice Recording Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer toutes les factures d'un utilisateur
     */
    public function getUserInvoices(int $userId): array
    {
        $sql = "SELECT * FROM invoices
                WHERE user_id = ?
                ORDER BY paid_at DESC";

        return $this->db->query($sql, [$userId]) ?: [];
    }
}
