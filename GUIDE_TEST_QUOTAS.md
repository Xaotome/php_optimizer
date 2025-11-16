# Guide de Test - Authentification et Quotas

## Option 1 : Test via Script PHP (Recommandé)

Depuis Windows PowerShell ou CMD, exécutez :

```bash
cd C:\wamp64\www\php_optimizer
php test_complete_flow.php
```

Ce script testera automatiquement :
- ✓ Inscription d'un nouvel utilisateur
- ✓ Connexion et création de session
- ✓ Vérification de l'authentification
- ✓ Récupération des infos utilisateur avec quotas
- ✓ Vérification des quotas avant analyse
- ✓ Incrémentation des quotas après analyse simulée
- ✓ Blocage lors du dépassement de quota (20 fichiers/mois)
- ✓ Analyse avec quota disponible
- ✓ Déconnexion
- ✓ Vérification post-déconnexion

---

## Option 2 : Test Manuel via l'Interface Web

### 1. Test de l'Inscription

1. Ouvrez votre navigateur et allez sur : `http://localhost/php_optimizer/public/register.html`
2. Remplissez le formulaire :
   - **Nom** : Test User
   - **Email** : test@example.com
   - **Mot de passe** : TestPassword123!
3. Cliquez sur "Créer mon compte gratuit"
4. **Résultat attendu** : Redirection automatique vers le dashboard

### 2. Vérification du Dashboard

Après l'inscription, vous devriez voir :
- ✅ Votre plan : **Gratuit**
- ✅ Utilisation : **0 / 20** fichiers
- ✅ Barre de progression à 0%
- ✅ Bouton "Passer à Pro"

### 3. Test de l'Analyse avec Quotas

1. Cliquez sur "Analyser des fichiers" (ou allez sur `analyzer.html`)
2. **Vérification 1** : Vous devez voir une bannière de quota :
   - "Votre utilisation ce mois-ci"
   - 0 / 20 fichiers analysés
   - 20 restants

3. Uploadez **1 fichier PHP** (par exemple, créez un fichier test.php simple) :
   ```php
   <?php
   echo "Hello World";
   ```

4. Cliquez sur "Analyser les fichiers"
5. **Résultat attendu** :
   - L'analyse se termine avec succès
   - La réponse JSON contient `"quota": { "used": 1, "limit": 20, "remaining": 19 }`
   - Le fichier est automatiquement supprimé après l'analyse (RGPD)

### 4. Vérification de l'Incrémentation du Quota

1. Rechargez le dashboard (`dashboard.html`)
2. **Résultat attendu** :
   - Utilisation : **1 / 20** fichiers
   - Barre de progression à 5% (1/20)
   - Restant : 19

### 5. Test de Dépassement de Quota (Optionnel)

Si vous voulez tester la limite :

1. Dans la base de données, modifiez manuellement le quota :
   ```sql
   UPDATE usage_stats
   SET current_files_count = 20
   WHERE user_id = (SELECT id FROM users WHERE email = 'test@example.com');
   ```

2. Retournez sur `analyzer.html`
3. **Résultat attendu** :
   - La bannière **"Quota mensuel atteint !"** s'affiche en rouge
   - Le bouton "Analyser les fichiers" est **désactivé**
   - Message : "Passez à Pro (5€/mois) pour des analyses illimitées"

4. Essayez quand même d'analyser un fichier via l'API :
   - Ouvrez la console du navigateur (F12)
   - Vous devriez voir une réponse **429 Too Many Requests** avec :
     ```json
     {
       "success": false,
       "error_code": "QUOTA_EXCEEDED",
       "message": "Quota mensuel atteint ! Passez à Pro pour continuer.",
       "quota": {
         "used": 20,
         "limit": 20,
         "remaining": 0
       }
     }
     ```

### 6. Test de Déconnexion

1. Cliquez sur "Déconnexion" dans la navigation
2. **Résultat attendu** :
   - Redirection vers `login.html`
   - Session détruite

3. Essayez d'accéder à `dashboard.html` ou `analyzer.html` sans être connecté
4. **Résultat attendu** :
   - Redirection automatique vers `login.html` avec paramètre `?redirect=...`

### 7. Test de Reconnexion

1. Sur `login.html`, reconnectez-vous avec :
   - **Email** : test@example.com
   - **Mot de passe** : TestPassword123!
2. **Résultat attendu** :
   - Redirection vers le dashboard
   - Vos quotas sont conservés (1/20 ou 20/20 selon le test précédent)

---

## Vérification de la Base de Données

Vous pouvez vérifier directement dans MySQL que tout fonctionne :

### 1. Voir tous les utilisateurs avec leurs quotas

```sql
SELECT * FROM user_subscription_info;
```

Cette vue devrait afficher :
- email
- name
- plan (free)
- current_files_count
- remaining_files
- reset_date (date du prochain reset)

### 2. Vérifier les statistiques d'utilisation

```sql
SELECT
    u.email,
    u.name,
    us.current_files_count,
    us.reset_date,
    us.last_reset_date
FROM users u
JOIN usage_stats us ON u.id = us.user_id
WHERE u.email = 'test@example.com';
```

### 3. Vérifier les sessions actives

```sql
SELECT
    s.id,
    s.user_id,
    u.email,
    s.expires_at,
    IF(s.expires_at > NOW(), 'ACTIVE', 'EXPIRED') as status
FROM sessions s
JOIN users u ON s.user_id = u.id
WHERE u.email = 'test@example.com';
```

---

## Réinitialisation Manuelle des Quotas

Si vous voulez tester le reset mensuel des quotas :

```sql
-- Réinitialiser les quotas de tous les utilisateurs
UPDATE usage_stats
SET current_files_count = 0,
    last_reset_date = NOW(),
    reset_date = DATE_ADD(NOW(), INTERVAL 1 MONTH);
```

Ou appelez la procédure stockée :

```sql
CALL reset_monthly_quotas();
```

---

## Points de Vérification RGPD

1. **Suppression automatique des fichiers** :
   - Après chaque analyse, vérifiez que les fichiers dans `storage/uploads/` ont été supprimés
   - Vérifiez les logs PHP pour confirmer : "RGPD Cleanup: Fichier supprimé - ..."

2. **Suppression des rapports** :
   - Les fichiers dans `storage/reports/` doivent aussi être supprimés
   - Ceci garantit qu'aucune trace du code analysé n'est conservée

---

## Résumé des Fonctionnalités Testées

| Fonctionnalité | Statut | Description |
|----------------|--------|-------------|
| Inscription | ✅ | Création de compte avec hashage bcrypt |
| Connexion | ✅ | Authentication par session |
| Dashboard | ✅ | Affichage des infos utilisateur et quotas |
| Quotas Free Plan | ✅ | Limite de 20 fichiers/mois |
| Vérification Quota | ✅ | Blocage avant upload si quota dépassé |
| Incrémentation | ✅ | Compteur incrémenté après analyse réussie |
| Déconnexion | ✅ | Destruction de session |
| RGPD | ✅ | Suppression auto des fichiers uploadés |
| Redirection Auth | ✅ | Pages protégées redirigent vers login |
| UI Quotas | ✅ | Bannières, progress bar, alertes |

---

## Prochaines Étapes

Une fois ces tests validés, nous pourrons passer à la **Phase 3 : Intégration Stripe**

1. Création d'un compte Stripe
2. Configuration des webhooks
3. Création de la page pricing.html
4. Implémentation du Stripe Checkout
5. Gestion des abonnements Pro
6. Gestion des annulations et des échecs de paiement
