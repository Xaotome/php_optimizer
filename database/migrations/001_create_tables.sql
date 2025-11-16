-- =====================================================
-- PHP Optimizer - Schema Database pour Micro-SaaS
-- Date: 2025-01-15
-- Description: Tables pour gestion utilisateurs,
--              abonnements Stripe et quotas
-- =====================================================

-- Table: users
-- Description: Stocke les informations des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `stripe_customer_id` VARCHAR(255) NULL,
  `email_verified_at` TIMESTAMP NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_stripe_customer` (`stripe_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: subscriptions
-- Description: Gestion des abonnements Stripe
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `stripe_subscription_id` VARCHAR(255) NULL UNIQUE,
  `stripe_price_id` VARCHAR(255) NULL,
  `status` ENUM('active', 'canceled', 'past_due', 'incomplete', 'trialing', 'unpaid') NOT NULL DEFAULT 'active',
  `plan` ENUM('free', 'pro', 'team') NOT NULL DEFAULT 'free',
  `amount` DECIMAL(10, 2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `current_period_start` TIMESTAMP NULL,
  `current_period_end` TIMESTAMP NULL,
  `cancel_at_period_end` TINYINT(1) DEFAULT 0,
  `canceled_at` TIMESTAMP NULL,
  `ended_at` TIMESTAMP NULL,
  `trial_start` TIMESTAMP NULL,
  `trial_end` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_stripe_subscription` (`stripe_subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: usage_stats
-- Description: Suivi de l'utilisation (nombre de fichiers analysés)
CREATE TABLE IF NOT EXISTS `usage_stats` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `files_count` INT UNSIGNED DEFAULT 0,
  `period_start` TIMESTAMP NOT NULL,
  `period_end` TIMESTAMP NOT NULL,
  `is_current_period` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_period` (`user_id`, `is_current_period`),
  INDEX `idx_period_dates` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: invoices
-- Description: Historique des factures Stripe
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `stripe_invoice_id` VARCHAR(255) NOT NULL UNIQUE,
  `stripe_subscription_id` VARCHAR(255) NULL,
  `amount_paid` DECIMAL(10, 2) NOT NULL,
  `amount_due` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `status` ENUM('draft', 'open', 'paid', 'uncollectible', 'void') NOT NULL,
  `invoice_pdf` TEXT NULL,
  `hosted_invoice_url` TEXT NULL,
  `paid_at` TIMESTAMP NULL,
  `period_start` TIMESTAMP NULL,
  `period_end` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_stripe_invoice` (`stripe_invoice_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets
-- Description: Tokens pour réinitialisation de mot de passe
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sessions
-- Description: Gestion des sessions utilisateurs
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` TEXT NOT NULL,
  `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: webhook_events
-- Description: Log des événements Stripe webhook
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stripe_event_id` VARCHAR(255) NOT NULL UNIQUE,
  `type` VARCHAR(255) NOT NULL,
  `payload` TEXT NOT NULL,
  `processed` TINYINT(1) DEFAULT 0,
  `processed_at` TIMESTAMP NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`),
  INDEX `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Données de test (optionnel)
-- =====================================================

-- Utilisateur de test (mot de passe: password123)
INSERT INTO `users` (`email`, `password`, `name`, `email_verified_at`) VALUES
('test@phpoptimizer.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Utilisateur Test', NOW());

-- Statistiques d'utilisation pour l'utilisateur de test (5 fichiers utilisés sur 20)
INSERT INTO `usage_stats` (`user_id`, `files_count`, `period_start`, `period_end`, `is_current_period`) VALUES
(1, 5, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), LAST_DAY(NOW()), 1);

-- =====================================================
-- Vues utiles
-- =====================================================

-- Vue: user_subscription_info
-- Description: Jointure users + subscriptions pour accès rapide
CREATE OR REPLACE VIEW `user_subscription_info` AS
SELECT
    u.id,
    u.email,
    u.name,
    u.created_at as user_created_at,
    COALESCE(s.plan, 'free') as plan,
    COALESCE(s.status, 'active') as subscription_status,
    s.current_period_end,
    s.cancel_at_period_end,
    us.files_count as current_files_count,
    CASE
        WHEN COALESCE(s.plan, 'free') = 'free' THEN 20 - COALESCE(us.files_count, 0)
        ELSE 999999
    END as remaining_files
FROM users u
LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status IN ('active', 'trialing')
LEFT JOIN usage_stats us ON u.id = us.user_id AND us.is_current_period = 1;

-- =====================================================
-- Triggers
-- =====================================================

-- Trigger: Créer automatiquement les usage_stats pour un nouvel utilisateur
DELIMITER //
CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO usage_stats (user_id, files_count, period_start, period_end, is_current_period)
    VALUES (
        NEW.id,
        0,
        DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'),
        LAST_DAY(NOW()),
        1
    );
END//
DELIMITER ;

-- =====================================================
-- Procédures stockées
-- =====================================================

-- Procédure: Reset mensuel des quotas (à exécuter via CRON)
DELIMITER //
CREATE PROCEDURE reset_monthly_quotas()
BEGIN
    -- Marquer les périodes actuelles comme terminées
    UPDATE usage_stats
    SET is_current_period = 0
    WHERE is_current_period = 1
    AND period_end < NOW();

    -- Créer de nouvelles périodes pour les utilisateurs actifs
    INSERT INTO usage_stats (user_id, files_count, period_start, period_end, is_current_period)
    SELECT
        u.id,
        0,
        DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'),
        LAST_DAY(NOW()),
        1
    FROM users u
    WHERE u.is_active = 1
    AND NOT EXISTS (
        SELECT 1 FROM usage_stats us
        WHERE us.user_id = u.id
        AND us.is_current_period = 1
    );
END//
DELIMITER ;

-- =====================================================
-- Indexes de performance
-- =====================================================

-- Optimisation des requêtes fréquentes
CREATE INDEX idx_users_active ON users(is_active, created_at);
CREATE INDEX idx_subscriptions_active ON subscriptions(status, current_period_end);
CREATE INDEX idx_usage_current ON usage_stats(user_id, is_current_period, files_count);

-- =====================================================
-- Commentaires et documentation
-- =====================================================

ALTER TABLE users COMMENT = 'Gestion des comptes utilisateurs';
ALTER TABLE subscriptions COMMENT = 'Abonnements Stripe et gestion des plans';
ALTER TABLE usage_stats COMMENT = 'Suivi mensuel de l\'utilisation (fichiers analysés)';
ALTER TABLE invoices COMMENT = 'Historique des factures Stripe';
ALTER TABLE webhook_events COMMENT = 'Log des webhooks Stripe pour débogage';

-- =====================================================
-- Fin du script de migration
-- =====================================================
