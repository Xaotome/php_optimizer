# Guide de Diagnostic - Erreur 500

## 🚨 Erreur 500 lors de l'appel à `/analyze`

### 🔍 Étapes de diagnostic

#### 1. **Test de base PHP**
Accédez à : `https://thmspcrx.dev/php_optimizer/error_test.php`

Cela va vérifier :
- ✅ Version PHP (doit être ≥ 8.2)
- ✅ Extensions PHP requises
- ✅ Autoload Composer
- ✅ Permissions des répertoires
- ✅ Classes principales

#### 2. **Test API simple**
Accédez à : `https://thmspcrx.dev/php_optimizer/test`

Devrait retourner :
```json
{
  "status": "OK",
  "message": "API fonctionne",
  "timestamp": "2025-09-20T...",
  "php_version": "8.x.x"
}
```

#### 3. **Test upload basique**
Accédez à : `https://thmspcrx.dev/php_optimizer/simple_test.php`
(Via POST avec un fichier)

### 🔧 Corrections probables

#### Si erreur_test.php montre des problèmes :

**Version PHP trop ancienne :**
```bash
# Mettre à jour PHP vers 8.2+
```

**Extensions manquantes :**
```bash
# Activer les extensions :
extension=json
extension=mbstring
extension=fileinfo
```

**Autoload manquant :**
```bash
cd /domains/thmspcrx.dev/public_html/php_optimizer/
composer install --no-dev --optimize-autoloader
```

**Permissions incorrectes :**
```bash
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/reports/
chmod 755 storage/logs/
```

#### Si `/test` fonctionne mais `/analyze` non :

Le problème vient de l'instanciation des classes d'analyse.

**Solution 1 - Vérifier les dépendances :**
- PHPStan peut ne pas être installé correctement
- PHP-CS-Fixer peut manquer
- CodeSniffer peut être absent

**Solution 2 - Mode dégradé temporaire :**
Utiliser `simple_test.php` comme endpoint temporaire.

### 🎯 Tests à faire dans l'ordre

1. **Test général** : `/error_test.php`
2. **Test Slim** : `/test`
3. **Test upload** : POST vers `/simple_test.php`
4. **Test complet** : POST vers `/analyze`

### 📋 Logs à vérifier

- Logs Apache : `/var/log/apache2/error.log`
- Logs PHP : `/var/log/php_errors.log`
- Logs application : `storage/logs/app.log`

### 🔄 Corrections appliquées

- ✅ Affichage d'erreurs activé temporairement
- ✅ Chemins corrigés avec `dirname(__DIR__, 2)`
- ✅ Gestion d'erreur améliorée dans les contrôleurs
- ✅ Route de test `/test` ajoutée
- ✅ Script de diagnostic complet

### 🚀 Prochaines étapes

1. Exécuter `error_test.php`
2. Identifier le problème spécifique
3. Appliquer la correction correspondante
4. Retester l'application complète