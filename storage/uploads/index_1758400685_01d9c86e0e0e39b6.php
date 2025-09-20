<?php
require_once __DIR__ . '/auth/session.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Grand Archive</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1 class="text-gradient">Ma Collection Grand Archive</h1>
                <div class="header-right">
                    <?php if ($currentUser): ?>
                        <div class="user-info">
                            <span class="username">Bonjour, <?php echo htmlspecialchars($currentUser['username']); ?></span>
                            <a href="auth/logout.php" class="logout-btn">
                                Déconnexion
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="auth/login.php" class="auth-btn">
                                Connexion
                            </a>
                            <a href="auth/register.php" class="auth-btn primary">
                                Inscription
                            </a>
                        </div>
                    <?php endif; ?>
                    <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            <nav class="nav" id="main-nav">
                <button class="nav-btn active" data-view="collection">
                    Collection
                </button>
                <button class="nav-btn" data-view="search">
                    Recherche
                </button>
                <?php if ($currentUser): ?>
                <button class="nav-btn" data-view="stats">
                    Statistiques
                </button>
                <?php if ($currentUser["role"] == 'admin'): ?>
                <button class="nav-btn" data-view="sync">
                    Synchronisation
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </header>

        <main class="main">
            <!-- Vue Collection -->
            <div id="collection-view" class="view active">
                <?php if ($currentUser): ?>
                <div class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="collection-search" placeholder="Rechercher dans ma collection...">
                    </div>
                    <div class="collection-filters">
                        <select id="collection-set-filter" class="filter-select ">
                            <option value="">Toutes extensions</option>
                        </select>
                        <select id="collection-class-filter" class="filter-select ">
                            <option value="">Toutes classes</option>
                        </select>
                        <select id="collection-element-filter" class="filter-select ">
                            <option value="">Tous éléments</option>
                        </select>
                    </div>
                    <div class="view-toggle">
                        <button class="view-btn  active" data-view="grid">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn " data-view="list">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
                <div id="collection-cards" class="cards-grid">
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Chargement de votre collection...</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="auth-required ">
                    <div class="auth-message">
                        <i class="fas fa-lock"></i>
                        <h2>Authentification requise</h2>
                        <p>Vous devez être connecté pour accéder à votre collection personnelle.</p>
                        <div class="auth-actions">
                            <a href="auth/login.php" class="btn  primary">
                                <i class="fas fa-sign-in-alt"></i> Se connecter
                            </a>
                            <a href="auth/register.php" class="btn ">
                                <i class="fas fa-user-plus"></i> S'inscrire
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vue Recherche -->
            <div id="search-view" class="view">
                <?php if ($currentUser): ?>
                <div class="search-panel ">
                    <h2 class="text-gradient">Rechercher des cartes</h2>
                    <form id="search-form" class="search-form">
                        <div class="form-group">
                            <label for="card-name">Nom de la carte</label>
                            <input type="text" id="card-name" name="name" class="" placeholder="Rechercher par nom...">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="card-set">Extension</label>
                                <select id="card-set" name="set" class="">
                                    <option value="">Toutes les extensions</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="card-class">Classe</label>
                                <select id="card-class" name="class" class="">
                                    <option value="">Toutes les classes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="card-element">Élément</label>
                                <select id="card-element" name="element" class="">
                                    <option value="">Tous les éléments</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary  primary">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                </div>
                <div id="search-results" class="cards-grid">
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <p>Effectuez une recherche pour découvrir des cartes</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="auth-required">
                    <div class="auth-message">
                        <i class="fas fa-lock"></i>
                        <h2>Authentification requise</h2>
                        <p>Vous devez être connecté pour rechercher des cartes.</p>
                        <div class="auth-actions">
                            <a href="auth/login.php" class="btn primary">
                                <i class="fas fa-sign-in-alt"></i> Se connecter
                            </a>
                            <a href="auth/register.php" class="btn">
                                <i class="fas fa-user-plus"></i> S'inscrire
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vue Statistiques -->
            <div id="stats-view" class="view">
                <?php if ($currentUser): ?>
                <div class="stats-container ">
                    <h2 class="text-gradient">Statistiques de ma collection</h2>
                    <div class="stats-grid">
                        <div class="stat-card ">
                            <i class="fas fa-layer-group"></i>
                            <div class="stat-info">
                                <span class="stat-number" id="total-cards">0</span>
                                <span class="stat-label">Cartes totales</span>
                            </div>
                        </div>
                        <div class="stat-card ">
                            <i class="fas fa-star"></i>
                            <div class="stat-info">
                                <span class="stat-number" id="unique-cards">0</span>
                                <span class="stat-label">Cartes uniques</span>
                            </div>
                        </div>
                        <div class="stat-card ">
                            <i class="fas fa-gem"></i>
                            <div class="stat-info">
                                <span class="stat-number" id="foil-cards">0</span>
                                <span class="stat-label">Cartes foil</span>
                            </div>
                        </div>
                        <div class="stat-card ">
                            <i class="fas fa-box"></i>
                            <div class="stat-info">
                                <span class="stat-number" id="sets-owned">0</span>
                                <span class="stat-label">Extensions</span>
                            </div>
                        </div>
                    </div>
                    <div class="charts-container">
                        <div class="chart-card ">
                            <h3>Répartition par extension</h3>
                            <canvas id="sets-chart"></canvas>
                        </div>
                        <div class="chart-card ">
                            <h3>Répartition par rareté</h3>
                            <canvas id="rarity-chart"></canvas>
                        </div>
                        <div class="chart-card ">
                            <h3>Répartition par élément</h3>
                            <canvas id="elements-chart"></canvas>
                        </div>
                        <div class="chart-card ">
                            <h3>Progression par extension</h3>
                            <canvas id="progress-chart"></canvas>
                        </div>
                        <div class="chart-card ">
                            <h3>Statistiques foil</h3>
                            <canvas id="foil-chart"></canvas>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="auth-required ">
                    <div class="auth-message">
                        <i class="fas fa-lock"></i>
                        <h2>Authentification requise</h2>
                        <p>Vous devez être connecté pour voir les statistiques de votre collection.</p>
                        <div class="auth-actions">
                            <a href="auth/login.php" class="btn  primary">
                                <i class="fas fa-sign-in-alt"></i> Se connecter
                            </a>
                            <a href="auth/register.php" class="btn ">
                                <i class="fas fa-user-plus"></i> S'inscrire
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vue Synchronisation -->
            <div id="sync-view" class="view">
                <div class="sync-container ">
                    <h2 class="text-gradient">Synchronisation des cartes</h2>
                    <div class="sync-info">
                        <div class="sync-status-card ">
                            <i class="fas fa-database"></i>
                            <div class="info">
                                <span class="label">Cartes en base</span>
                                <span class="value" id="cards-in-db">-</span>
                            </div>
                        </div>
                        <div class="sync-status-card ">
                            <i class="fas fa-cloud"></i>
                            <div class="info">
                                <span class="label">Statut</span>
                                <span class="value" id="sync-status">-</span>
                            </div>
                        </div>
                        <div class="sync-status-card ">
                            <i class="fas fa-download"></i>
                            <div class="info">
                                <span class="label">Dernière sync</span>
                                <span class="value" id="last-sync-time">Jamais</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sync-controls">
                        <div class="sync-options ">
                            <div class="form-group">
                                <label for="sync-limit">Limite de pages à traiter (optionnel)</label>
                                <input type="number" id="sync-limit" class="" placeholder="Ex: 5 pages" min="1" max="48" value="">
                                <small>Laisser vide pour traiter toutes les 48 pages (~1440 cartes)</small>
                            </div>
                            <div class="form-group">
                                <label for="sync-offset">Page de départ (pour reprendre une sync)</label>
                                <input type="number" id="sync-offset" class="" value="1" min="1" max="48">
                                <small>Commencer à partir de cette page (1 à 48)</small>
                            </div>
                        </div>
                        
                        <div class="sync-mode">
                            <label>
                                <input type="checkbox" id="sync-all-mode"> 
                                Mode synchronisation complète (récupère TOUTES les cartes disponibles)
                            </label>
                        </div>
                        
                        <button id="start-sync-btn" class="btn-primary sync-btn  primary">
                            <i class="fas fa-sync"></i> Démarrer la synchronisation
                        </button>
                        
                        <button id="check-status-btn" class="btn-secondary ">
                            <i class="fas fa-refresh"></i> Vérifier le statut
                        </button>
                        
                        <button id="test-sync-btn" class="btn-secondary ">
                            <i class="fas fa-vial"></i> Test de synchronisation
                        </button>
                    </div>
                    
                    <div class="sync-progress" id="sync-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="progress-text" id="progress-text">Préparation...</div>
                    </div>
                    
                    <div class="sync-info-box ">
                        <h3><i class="fas fa-info-circle"></i> Information</h3>
                        <p><strong>API GATCG :</strong> L'API retourne 30 cartes par page sur un total de 48 pages (≈1440 cartes).</p>
                        <p><strong>Mode complet :</strong> Parcourt automatiquement toutes les pages pour récupérer l'ensemble des cartes disponibles.</p>
                        <p><strong>Mode par lot :</strong> Utilisez la limite pour traiter un nombre spécifique de pages (utile pour les tests).</p>
                        <p>Si l'API officielle n'est pas accessible, le système utilisera automatiquement des données de test.</p>
                    </div>
                    
                    <div class="sync-log " id="sync-log">
                        <h3>Journal de synchronisation</h3>
                        <div class="log-content" id="log-content">
                            <p class="log-entry">Aucune synchronisation effectuée</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal pour les détails de carte -->
    <div id="card-modal" class="modal">
        <div class="modal-content ">
            <span class="close-modal">&times;</span>
            
            <!-- Nouvelle disposition optimisée : 3 zones principales -->
            <div class="modal-body-optimized">
                <!-- Zone 1: Image de la carte -->
                <div class="modal-image-section">
                    <div class="card-image-wrapper">
                        <img id="modal-card-image" src="" alt="" class="card-image-large">
                    </div>
                </div>
                
                <!-- Zone 2: Contenu principal (Titre + Détails + Effet) -->
                <div class="modal-content-section">
                    <div class="card-header">
                        <h1 id="modal-card-name" class="card-title-large text-gradient"></h1>
                    </div>
                    
                    <div class="card-info-grid">
                        <div class="info-item">
                            <span class="info-label">Extension</span>
                            <span class="info-value" id="modal-card-set"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Numéro</span>
                            <span class="info-value" id="modal-card-number"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Rareté</span>
                            <span class="info-value" id="modal-card-rarity"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Classe</span>
                            <span class="info-value" id="modal-card-class"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value" id="modal-card-type"></span>
                        </div>
                    </div>
                    
                    <div class="card-effect-section">
                        <h3 class="section-title">
                            <i class="fas fa-magic"></i> Effet de la carte
                        </h3>
                        <div id="modal-card-effect" class="effect-content"></div>
                    </div>
                </div>
                
                <!-- Zone 3: Actions collection (sidebar) -->
                <div class="modal-actions-section">
                    <div class="collection-panel">
                        <h3 class="panel-title">
                            <i class="fas fa-folder-plus"></i> Ma Collection
                        </h3>
                        <div class="quantity-section">
                            <label class="quantity-label">Quantité possédée</label>
                            <div class="quantity-controls-modern">
                                <button class="quantity-btn-modern" data-action="decrease">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-display" id="card-quantity">0</span>
                                <button class="quantity-btn-modern" data-action="increase">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="foil-section">
                            <label class="foil-toggle">
                                <input type="checkbox" id="card-foil">
                                <span class="foil-slider"></span>
                                <span class="foil-label">
                                    <i class="fas fa-star"></i> Version Foil
                                </span>
                            </label>
                        </div>
                        
                        <div class="collection-stats">
                            <div class="stat-row">
                                <i class="fas fa-layer-group"></i>
                                <span>Total possédé: <strong id="card-quantity-display">0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Deuxième ligne : Prix JustTCG pleine largeur -->
            <div class="justtcg-section-full" id="justtcg-section" style="display: none;">
                <div class="justtcg-container">
                    <h3><i class="fas fa-chart-line"></i> Prix du marché</h3>
                    <div class="justtcg-loading" id="justtcg-loading">
                        <i class="fas fa-spinner fa-spin"></i> 
                        <span>Chargement des données de marché...</span>
                    </div>
                    <div class="justtcg-data" id="justtcg-data" style="display: none;">
                        <div class="justtcg-prices" id="justtcg-prices">
                            <!-- Les prix seront ajoutés ici dynamiquement -->
                        </div>
                        <div class="justtcg-links">
                            <a id="justtcg-market-link" href="#" target="_blank" class="btn-justtcg">
                                <i class="fas fa-external-link-alt"></i> Voir sur JustTCG
                            </a>
                        </div>
                    </div>
                    <div class="justtcg-error" id="justtcg-error" style="display: none;">
                        <p><i class="fas fa-exclamation-triangle"></i> Impossible de récupérer les données de marché</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="assets/js/api.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/collection.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>