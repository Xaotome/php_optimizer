<?php
require_once 'config/database.php';

class Setup {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
            echo "<h1>Configuration de la Collection Grand Archive</h1>";
        } catch (Exception $e) {
            echo "<h1>Erreur de connexion à la base de données</h1>";
            echo "<p>Erreur: " . $e->getMessage() . "</p>";
            echo "<p>Veuillez vérifier que MySQL est démarré et que la base de données existe.</p>";
            exit;
        }
    }

    public function run() {
        echo "<h2>Étape 1: Création des tables</h2>";
        $this->createTables();

        echo "<h2>Étape 2: Import de quelques cartes de test</h2>";
        $this->importTestCards();

        echo "<h2>✅ Configuration terminée avec succès !</h2>";
        echo "<p><a href='index.php' style='color: #6366f1; text-decoration: none; font-weight: bold;'>→ Accéder à votre collection</a></p>";
    }

    private function createTables() {
        $sqlFile = __DIR__ . '/sql/schema.sql';
        
        if (!file_exists($sqlFile)) {
            echo "<p style='color: red;'>❌ Fichier schema.sql non trouvé</p>";
            return;
        }

        $sql = file_get_contents($sqlFile);
        
        // Séparer les requêtes par point-virgule
        $queries = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($queries as $query) {
            if (empty($query) || strpos($query, '--') === 0) continue;
            
            try {
                $this->db->query($query);
                echo "<p style='color: green;'>✅ Requête exécutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠️ Requête ignorée (table existante): " . substr($query, 0, 50) . "...</p>";
            }
        }
        
        echo "<p><strong>Tables créées avec succès !</strong></p>";
    }

    private function importTestCards() {
        // Importer quelques cartes de test depuis l'API
        $testCards = [
            ['AMB', '010'], // Crescent Glaive
            ['AMB', '001'], 
            ['AMB', '002'],
        ];

        $imported = 0;
        
        foreach ($testCards as [$setPrefix, $collectorNumber]) {
            try {
                echo "<p>🔄 Import de {$setPrefix}/{$collectorNumber}...</p>";
                
                $apiUrl = "https://api.gatcg.com/cards/{$setPrefix}/{$collectorNumber}";
                $cardData = @file_get_contents($apiUrl);
                
                if ($cardData === false) {
                    echo "<p style='color: orange;'>⚠️ Impossible de récupérer {$setPrefix}/{$collectorNumber}</p>";
                    continue;
                }

                $cardJson = json_decode($cardData, true);
                
                if (!$cardJson) {
                    echo "<p style='color: orange;'>⚠️ Données invalides pour {$setPrefix}/{$collectorNumber}</p>";
                    continue;
                }

                $this->saveCard($cardJson);
                $imported++;
                echo "<p style='color: green;'>✅ {$cardJson['name']} importée</p>";
                
                // Petite pause pour éviter de surcharger l'API
                usleep(200000); // 200ms
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'import de {$setPrefix}/{$collectorNumber}: " . $e->getMessage() . "</p>";
            }
        }

        echo "<p><strong>{$imported} cartes de test importées !</strong></p>";
        
        if ($imported > 0) {
            echo "<h3>Ajout à votre collection de test</h3>";
            $this->addTestToCollection();
        }
    }

    private function saveCard($cardData) {
        // Insérer ou mettre à jour la carte principale
        $cardSql = "INSERT INTO cards (
            uuid, name, slug, cost_memory, cost_reserve, power, durability, life, level, speed,
            element, effect, effect_raw, effect_html, flavor, rule, types, subtypes, classes, elements, legality
        ) VALUES (
            :uuid, :name, :slug, :cost_memory, :cost_reserve, :power, :durability, :life, :level, :speed,
            :element, :effect, :effect_raw, :effect_html, :flavor, :rule, :types, :subtypes, :classes, :elements, :legality
        ) ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            updated_at = CURRENT_TIMESTAMP";

        $cardParams = [
            ':uuid' => $cardData['uuid'],
            ':name' => $cardData['name'],
            ':slug' => $cardData['slug'],
            ':cost_memory' => $cardData['cost_memory'],
            ':cost_reserve' => $cardData['cost_reserve'],
            ':power' => $cardData['power'],
            ':durability' => $cardData['durability'],
            ':life' => $cardData['life'],
            ':level' => $cardData['level'],
            ':speed' => $cardData['speed'],
            ':element' => $cardData['element'],
            ':effect' => $cardData['effect'],
            ':effect_raw' => $cardData['effect_raw'],
            ':effect_html' => $cardData['effect_html'],
            ':flavor' => $cardData['flavor'],
            ':rule' => json_encode($cardData['rule']),
            ':types' => json_encode($cardData['types']),
            ':subtypes' => json_encode($cardData['subtypes']),
            ':classes' => json_encode($cardData['classes']),
            ':elements' => json_encode($cardData['elements']),
            ':legality' => $cardData['legality']
        ];

        $this->db->query($cardSql, $cardParams);

        // Insérer les éditions
        if (isset($cardData['editions']) && is_array($cardData['editions'])) {
            foreach ($cardData['editions'] as $edition) {
                $this->saveEdition($edition, $cardData['uuid']);
            }
        }
    }

    private function saveEdition($editionData, $cardId) {
        // Sauvegarder l'extension
        if (isset($editionData['set'])) {
            $setSql = "INSERT INTO sets (id, name, prefix, release_date, language) 
                       VALUES (:id, :name, :prefix, :release_date, :language)
                       ON DUPLICATE KEY UPDATE
                       name = VALUES(name),
                       updated_at = CURRENT_TIMESTAMP";

            $setParams = [
                ':id' => $editionData['set']['id'],
                ':name' => $editionData['set']['name'],
                ':prefix' => $editionData['set']['prefix'],
                ':release_date' => $editionData['set']['release_date'],
                ':language' => $editionData['set']['language'] ?? 'EN'
            ];

            $this->db->query($setSql, $setParams);
        }

        // Insérer l'édition
        $editionSql = "INSERT INTO card_editions (
            uuid, card_id, collector_number, set_id, configuration, rarity, illustrator, flavor, image, orientation, effect, effect_raw
        ) VALUES (
            :uuid, :card_id, :collector_number, :set_id, :configuration, :rarity, :illustrator, :flavor, :image, :orientation, :effect, :effect_raw
        ) ON DUPLICATE KEY UPDATE
            rarity = VALUES(rarity),
            updated_at = CURRENT_TIMESTAMP";

        $editionParams = [
            ':uuid' => $editionData['uuid'],
            ':card_id' => $cardId,
            ':collector_number' => $editionData['collector_number'],
            ':set_id' => $editionData['set']['id'],
            ':configuration' => $editionData['configuration'] ?? 'default',
            ':rarity' => $editionData['rarity'],
            ':illustrator' => $editionData['illustrator'],
            ':flavor' => $editionData['flavor'],
            ':image' => $editionData['image'],
            ':orientation' => $editionData['orientation'],
            ':effect' => $editionData['effect'],
            ':effect_raw' => $editionData['effect_raw']
        ];

        $this->db->query($editionSql, $editionParams);
    }

    private function addTestToCollection() {
        // Ajouter quelques cartes à la collection de test
        $sql = "INSERT INTO my_collection (card_uuid, edition_uuid, quantity, is_foil) 
                SELECT c.uuid, ce.uuid, 2, FALSE
                FROM cards c
                JOIN card_editions ce ON c.uuid = ce.card_id
                LIMIT 3
                ON DUPLICATE KEY UPDATE quantity = quantity + 1";
        
        try {
            $this->db->query($sql);
            echo "<p style='color: green;'>✅ Cartes ajoutées à votre collection de test</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Erreur lors de l'ajout à la collection: " . $e->getMessage() . "</p>";
        }
    }
}

// Styles CSS inline pour la page de setup
echo "
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
    h1, h2, h3 {
        color: #6366f1;
    }
    p {
        margin: 10px 0;
    }
    a {
        color: #6366f1;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
";

// Exécuter la configuration
$setup = new Setup();
$setup->run();
?>