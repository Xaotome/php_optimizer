<?php
// Script d'initialisation simple pour créer les tables de base
require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "<h2>Initialisation de la base de données</h2>";
    
    // Tables de base minimales
    $tables = [
        'sets' => "CREATE TABLE IF NOT EXISTS sets (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            prefix VARCHAR(10) NOT NULL,
            release_date DATE,
            language VARCHAR(5) DEFAULT 'EN',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'cards' => "CREATE TABLE IF NOT EXISTS cards (
            uuid VARCHAR(50) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            cost_memory INT,
            cost_reserve INT,
            power INT,
            durability INT,
            life INT,
            level INT,
            speed VARCHAR(50),
            element VARCHAR(50),
            effect TEXT,
            effect_raw TEXT,
            effect_html TEXT,
            flavor TEXT,
            rule JSON,
            types JSON,
            subtypes JSON,
            classes JSON,
            elements JSON,
            legality VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_name (name),
            INDEX idx_slug (slug),
            INDEX idx_element (element)
        )",
        
        'card_editions' => "CREATE TABLE IF NOT EXISTS card_editions (
            uuid VARCHAR(50) PRIMARY KEY,
            card_id VARCHAR(50) NOT NULL,
            collector_number VARCHAR(20) NOT NULL,
            set_id VARCHAR(50) NOT NULL,
            configuration VARCHAR(50) DEFAULT 'default',
            rarity INT NOT NULL,
            illustrator VARCHAR(255),
            flavor TEXT,
            image VARCHAR(500),
            orientation VARCHAR(20) DEFAULT 'portrait',
            effect TEXT,
            effect_raw TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (card_id) REFERENCES cards(uuid) ON DELETE CASCADE,
            FOREIGN KEY (set_id) REFERENCES sets(id) ON DELETE CASCADE,
            
            INDEX idx_card (card_id),
            INDEX idx_set (set_id),
            INDEX idx_collector (collector_number)
        )",
        
        'my_collection' => "CREATE TABLE IF NOT EXISTS my_collection (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_uuid VARCHAR(50) NOT NULL,
            edition_uuid VARCHAR(50) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            is_foil BOOLEAN NOT NULL DEFAULT FALSE,
            condition_card ENUM('MINT', 'NEAR_MINT', 'EXCELLENT', 'GOOD', 'LIGHT_PLAYED', 'PLAYED', 'POOR') DEFAULT 'NEAR_MINT',
            notes TEXT,
            acquired_date DATE,
            price_paid DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_collection_entry (card_uuid, edition_uuid, is_foil),
            INDEX idx_card (card_uuid),
            INDEX idx_edition (edition_uuid),
            INDEX idx_foil (is_foil)
        )",
        
        'sync_status' => "CREATE TABLE IF NOT EXISTS sync_status (
            id INT PRIMARY KEY AUTO_INCREMENT,
            status ENUM('idle', 'running', 'completed', 'error') DEFAULT 'idle',
            message TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($tables as $tableName => $sql) {
        try {
            $db->query($sql);
            echo "<p style='color: green;'>✅ Table '$tableName' créée avec succès</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Table '$tableName': " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>✅ Initialisation terminée</h3>";
    echo "<p><a href='index.php'>→ Accéder à votre collection</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Erreur de connexion</h2>";
    echo "<p>Erreur: " . $e->getMessage() . "</p>";
    echo "<p>Veuillez vérifier que MySQL est démarré et accessible.</p>";
}
?>

<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #0f172a;
    color: #f8fafc;
    line-height: 1.6;
}
h2, h3 {
    color: #6366f1;
}
p {
    margin: 10px 0;
}
a {
    color: #6366f1;
    text-decoration: none;
    font-weight: bold;
}
a:hover {
    text-decoration: underline;
}
</style>