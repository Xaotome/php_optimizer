# Guide de Déploiement - PHP Optimizer

## 🚀 Instructions de déploiement

### 1. Structure des fichiers sur le serveur

```
/domains/thmspcrx.dev/public_html/php_optimizer/
├── .htaccess                 # Redirection vers public/
├── .env.production          # Configuration production
├── composer.json
├── vendor/                  # Dépendances
├── src/                     # Code source
├── storage/                 # Stockage (doit être writable)
│   ├── uploads/
│   ├── reports/
│   └── logs/
└── public/                  # Document root
    ├── .htaccess           # Configuration Apache
    ├── index.php           # Point d'entrée
    ├── index.html
    └── app.js
```

### 2. Permissions requises

```bash
# Exécuter ce script sur le serveur
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/reports/
chmod 755 storage/logs/
chmod 644 public/index.php
```

### 3. Configuration Apache

Le fichier `.htaccess` dans `/public/` gère :
- ✅ Réécriture des URLs
- ✅ Gestion des sous-répertoires
- ✅ Variables d'environnement

### 4. URL d'accès

L'application sera accessible à :
**https://thmspcrx.dev/php_optimizer/**

### 5. Dépendances PHP requises

- PHP 8.2+
- Extensions : json, mbstring, fileinfo
- Composer installé

### 6. Vérifications post-déploiement

1. ✅ Page d'accueil charge
2. ✅ Upload de fichier fonctionne
3. ✅ Analyse des fichiers fonctionne
4. ✅ Filtres interactifs fonctionnent
5. ✅ Pas d'erreur 404

### 7. Debugging

Si erreur 404 persiste :
1. Vérifier que mod_rewrite est activé
2. Vérifier les permissions des fichiers
3. Vérifier les logs Apache
4. Tester l'URL directe : `/php_optimizer/public/`

### 8. Variables importantes

- `SCRIPT_NAME` détecte automatiquement le sous-répertoire
- Base path configuré dynamiquement
- CORS activé pour compatibilité