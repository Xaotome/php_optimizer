# PHP Optimizer SAAS

Un outil SAAS moderne pour analyser et optimiser le code PHP selon les standards PSR et les meilleures pratiques de PHP 8.4.

## Fonctionnalités

- **Analyse PSR** : Vérification de la conformité aux standards PSR-1, PSR-2, PSR-4 et PSR-12
- **PHP 8.4** : Optimisation pour les dernières fonctionnalités de PHP 8.4
- **Outils intégrés** : PHPStan, PHP-CS-Fixer, PHP_CodeSniffer
- **Interface moderne** : Interface web responsive avec rapports détaillés
- **Upload multiple** : Support pour l'analyse de plusieurs fichiers simultanément
- **Rapports détaillés** : Codes d'erreur, suggestions d'amélioration et métriques
- **🔒 Conformité RGPD** : Suppression automatique des fichiers après analyse - Aucune traçabilité

## Installation

1. Clonez le repository :
```bash
git clone <repository-url>
cd php_optimizer
```

2. Installez les dépendances :
```bash
composer install
```

3. Configurez l'environnement :
```bash
cp .env.example .env
# Modifiez les paramètres selon vos besoins
```

4. Créez les répertoires nécessaires :
```bash
mkdir -p storage/{uploads,reports,logs}
chmod 755 storage/{uploads,reports,logs}
```

## Utilisation

### Démarrage du serveur de développement
```bash
composer serve
# ou
php -S localhost:8000 -t public
```

### Accès à l'application
Ouvrez votre navigateur et accédez à `http://localhost:8000`

### Structure de l'application
- **Page d'accueil** (`/`) : Présentation de l'application et de ses fonctionnalités
- **Analyseur** (`/analyzer.html`) : Interface d'upload et d'analyse de fichiers

### Analyse de fichiers
1. Accédez à la page "Analyser mon code"
2. Sélectionnez ou glissez-déposez vos fichiers PHP
3. Cliquez sur "Analyser les fichiers"
4. Consultez les rapports détaillés avec :
   - Conformité PSR
   - Erreurs, avertissements et informations
   - Suggestions d'amélioration contextuelles
   - Métriques de qualité par fichier
5. **🔒 Vos fichiers sont automatiquement supprimés après l'analyse** (conformité RGPD)

### Filtres par sévérité
- **Filtrez les résultats** par type de problème :
  - 🔴 **Erreurs** : Problèmes critiques à corriger
  - 🟡 **Avertissements** : Recommandations importantes
  - 🔵 **Informations** : Suggestions d'amélioration
- **Compteurs dynamiques** : Affichage en temps réel des problèmes visibles
- **Bouton "Tout masquer/afficher"** : Contrôle rapide de tous les filtres
- **Masquage automatique** : Les fichiers sans problèmes visibles sont cachés

## Outils d'analyse intégrés

### PHPStan (Niveau 8)
- Analyse statique avancée
- Détection des erreurs de type
- Vérification de la cohérence du code

### PHP-CS-Fixer
- Formatage automatique selon PSR-12
- Support des fonctionnalités PHP 8.4
- Correction automatique possible

### PHP_CodeSniffer
- Vérification des standards de codage
- Détection des violations PSR
- Rapports détaillés

### Analyseur PSR personnalisé
- Vérification spécifique PSR-1, PSR-2, PSR-4, PSR-12
- Messages d'erreur en français
- Suggestions contextuelles

## API

### POST /analyze
Upload et analyse de fichiers PHP

**Paramètres :**
- `files[]` : Fichiers PHP à analyser (multipart/form-data)

**Réponse :**
```json
{
  "success": true,
  "data": {
    "summary": {
      "compliant": 2,
      "warnings": 1,
      "errors": 0,
      "total_files": 3
    },
    "files": [
      {
        "name": "example.php",
        "status": "success",
        "issues": [],
        "psr_compliance": [
          {"standard": "PSR-1", "compliant": true},
          {"standard": "PSR-2", "compliant": true},
          {"standard": "PSR-4", "compliant": true},
          {"standard": "PSR-12", "compliant": true}
        ]
      }
    ]
  }
}
```

### GET /report/{id}
Récupération d'un rapport d'analyse

## Codes d'erreur

| Code | Description |
|------|-------------|
| `NO_FILES` | Aucun fichier fourni |
| `UPLOAD_VALIDATION_FAILED` | Échec de validation des fichiers |
| `NO_VALID_FILES` | Aucun fichier PHP valide |
| `ANALYSIS_ERROR` | Erreur durant l'analyse |
| `REPORT_NOT_FOUND` | Rapport introuvable |

## Standards PSR supportés

- **PSR-1** : Standards de codage de base
- **PSR-2** : Guide de style de codage (legacy)
- **PSR-4** : Autoloading
- **PSR-12** : Style de codage étendu

## Développement

### Tests
```bash
composer test
# ou
vendor/bin/phpunit
```

### Analyse statique
```bash
composer stan
# ou
vendor/bin/phpstan analyse
```

### Formatage du code
```bash
composer cs-fix
# ou
vendor/bin/php-cs-fixer fix
```

## Configuration

### Variables d'environnement (.env)

```env
APP_NAME="PHP Optimizer"
MAX_FILE_SIZE=5242880        # 5MB
MAX_FILES_PER_UPLOAD=10
PHPSTAN_LEVEL=8
CS_FIXER_RULES="@PSR12,@PHP84Migration"
```

### Personnalisation des règles

Modifiez les fichiers de configuration :
- `.php-cs-fixer.php` : Règles PHP-CS-Fixer
- `phpstan.neon` : Configuration PHPStan

## Sécurité et RGPD

### Sécurité
- Validation stricte des fichiers uploadés
- Limite de taille et de nombre de fichiers
- Noms de fichiers uniques avec timestamp
- Répertoires temporaires sécurisés

### Conformité RGPD
- ✅ **Suppression automatique** : Tous les fichiers uploadés sont supprimés immédiatement après l'analyse
- ✅ **Aucune traçabilité** : Aucun historique n'est conservé
- ✅ **Aucun stockage permanent** : Les rapports d'analyse ne sont pas sauvegardés après affichage
- ✅ **Confidentialité totale** : Votre code reste privé et n'est jamais conservé sur nos serveurs
- ✅ **Logs sécurisés** : Seuls les événements de suppression sont enregistrés (sans contenu)

## Licence

Ce projet est sous licence MIT.

## Support

Pour toute question ou problème, créez une issue sur le repository GitHub.