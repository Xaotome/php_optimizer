# Guide de Configuration Stripe

Ce guide vous explique comment configurer Stripe pour activer les paiements et abonnements dans PHP Optimizer.

---

## Étape 1 : Créer un compte Stripe

1. Allez sur [https://stripe.com](https://stripe.com)
2. Cliquez sur "Commencer maintenant" ou "Sign up"
3. Créez votre compte avec :
   - Votre email
   - Un mot de passe fort
   - Les informations de votre entreprise

4. **Activez le mode Test** (recommandé pour commencer)
   - En haut à droite, assurez-vous que le toggle "Test mode" est activé
   - Cela vous permet de tester sans vrais paiements

---

## Étape 2 : Récupérer vos clés API

### Clés de test (pour développement)

1. Dans le dashboard Stripe, allez dans **Developers** > **API keys**
2. Vous verrez deux clés en mode test :
   - **Publishable key** (commence par `pk_test_...`)
   - **Secret key** (commence par `sk_test_...`) - Cliquez sur "Reveal test key"

3. **IMPORTANT** : Ne partagez JAMAIS votre secret key !

### Où les utiliser ?

Éditez votre fichier `.env` et remplacez :

```env
STRIPE_SECRET_KEY="sk_test_VOTRE_CLE_SECRETE_ICI"
STRIPE_PUBLISHABLE_KEY="pk_test_VOTRE_CLE_PUBLIQUE_ICI"
```

---

## Étape 3 : Créer le produit "Pro" et son prix

### 3.1 Créer le produit

1. Dans le dashboard Stripe, allez dans **Products** > **Add product**
2. Remplissez :
   - **Name** : `PHP Optimizer Pro`
   - **Description** : `Accès illimité à PHP Optimizer avec support prioritaire`
   - **Pricing model** : `Standard pricing`

### 3.2 Configurer le prix récurrent

1. Dans la section **Pricing** :
   - **Price** : `5.00`
   - **Currency** : `EUR`
   - **Billing period** : `Monthly`

2. Cliquez sur **Add pricing**
3. Cliquez sur **Save product**

### 3.3 Récupérer le Price ID

1. Une fois le produit créé, cliquez dessus
2. Dans la section **Pricing**, vous verrez un **Price ID** qui commence par `price_...`
3. **Copiez ce Price ID**

4. Éditez votre fichier `.env` et remplacez :

```env
STRIPE_PRO_PRICE_ID="price_VOTRE_PRICE_ID_ICI"
```

---

## Étape 4 : Configurer les Webhooks

Les webhooks permettent à Stripe de notifier votre application des événements (paiements réussis, annulations, etc.)

### 4.1 Ajouter un endpoint webhook

1. Dans le dashboard Stripe, allez dans **Developers** > **Webhooks**
2. Cliquez sur **Add endpoint**
3. Remplissez :
   - **Endpoint URL** : `http://localhost/php_optimizer/public/api/webhooks/stripe`
     - ⚠️ Pour le développement local, vous aurez besoin d'exposer votre serveur local (voir section 4.3)
     - En production : `https://votre-domaine.com/api/webhooks/stripe`

### 4.2 Sélectionner les événements

Cochez les événements suivants :
- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

Cliquez sur **Add endpoint**

### 4.3 Récupérer le Webhook Secret

1. Une fois l'endpoint créé, cliquez dessus
2. Dans la section **Signing secret**, cliquez sur **Reveal**
3. Copiez le secret qui commence par `whsec_...`

4. Éditez votre fichier `.env` :

```env
STRIPE_WEBHOOK_SECRET="whsec_VOTRE_WEBHOOK_SECRET_ICI"
```

### 4.4 Tester les webhooks en local (Développement)

**Option 1 : Stripe CLI (Recommandé)**

1. Installez Stripe CLI : [https://stripe.com/docs/stripe-cli](https://stripe.com/docs/stripe-cli)

2. Authentifiez-vous :
   ```bash
   stripe login
   ```

3. Lancez le forwarding :
   ```bash
   stripe listen --forward-to http://localhost/php_optimizer/public/api/webhooks/stripe
   ```

4. Le CLI vous donnera un **webhook signing secret** temporaire commençant par `whsec_...`
   - Utilisez-le dans votre `.env` pendant le développement

**Option 2 : ngrok (Alternative)**

1. Installez ngrok : [https://ngrok.com/](https://ngrok.com/)

2. Exposez votre serveur local :
   ```bash
   ngrok http 80
   ```

3. Utilisez l'URL HTTPS fournie par ngrok comme endpoint webhook dans Stripe

---

## Étape 5 : Installer la bibliothèque Stripe PHP

Exécutez depuis Windows PowerShell :

```bash
cd C:\wamp64\www\php_optimizer
composer require stripe/stripe-php
```

Ou depuis WSL :

```bash
cd /mnt/c/wamp64/www/php_optimizer
composer require stripe/stripe-php
```

---

## Étape 6 : Vérification finale de la configuration

Votre fichier `.env` devrait ressembler à ceci :

```env
# ... autres configurations ...

# Stripe
STRIPE_SECRET_KEY="sk_test_51A..."
STRIPE_PUBLISHABLE_KEY="pk_test_51A..."
STRIPE_WEBHOOK_SECRET="whsec_..."
STRIPE_PRO_PRICE_ID="price_1A..."
STRIPE_SUCCESS_URL="http://localhost/php_optimizer/public/subscription-success.html"
STRIPE_CANCEL_URL="http://localhost/php_optimizer/public/pricing.html"
```

---

## Étape 7 : Tester le flux de paiement

### 7.1 Numéros de carte de test

Stripe fournit des cartes de test. Utilisez :

**Carte réussie** :
- Numéro : `4242 4242 4242 4242`
- Date d'expiration : N'importe quelle date future (ex: `12/34`)
- CVC : N'importe quel nombre à 3 chiffres (ex: `123`)

**Carte avec échec de paiement** :
- Numéro : `4000 0000 0000 0002`

Plus de cartes de test : [https://stripe.com/docs/testing](https://stripe.com/docs/testing)

### 7.2 Scénario de test complet

1. **Inscription** : Créez un compte sur `register.html`
2. **Vérification** : Allez sur le dashboard, vérifiez le plan "Gratuit"
3. **Upgrade** : Allez sur `pricing.html`, cliquez sur "Passer à Pro"
4. **Paiement** :
   - Vous serez redirigé vers Stripe Checkout
   - Utilisez la carte de test `4242 4242 4242 4242`
   - Remplissez les informations (email, nom, etc.)
   - Validez

5. **Vérifications après paiement** :
   - Vous devriez être redirigé vers `subscription-success.html`
   - Dans le dashboard, votre plan devrait être "Pro"
   - Vos quotas devraient afficher "Illimité"
   - Dans Stripe dashboard, vous devriez voir :
     - Un nouveau customer
     - Un abonnement actif
     - Un paiement réussi

6. **Vérifier la base de données** :
   ```sql
   SELECT * FROM subscriptions WHERE user_id = YOUR_USER_ID;
   SELECT * FROM invoices WHERE user_id = YOUR_USER_ID;
   ```

---

## Étape 8 : Passer en production

⚠️ **NE FAITES CECI QUE QUAND TOUT EST TESTÉ !**

### 8.1 Activer votre compte Stripe

1. Dans le dashboard Stripe, complétez les informations de votre entreprise
2. Ajoutez vos informations bancaires pour recevoir les paiements
3. Activez votre compte (bouton "Activate your account")

### 8.2 Créer le produit en mode live

1. Désactivez le "Test mode" (toggle en haut à droite)
2. Recréez le produit "PHP Optimizer Pro" en mode **Live**
3. Récupérez le nouveau **Price ID** (commence par `price_...` mais différent du test)

### 8.3 Récupérer les clés de production

1. Allez dans **Developers** > **API keys** (en mode Live)
2. Récupérez :
   - **Publishable key** (commence par `pk_live_...`)
   - **Secret key** (commence par `sk_live_...`)

### 8.4 Configurer le webhook en production

1. Dans **Developers** > **Webhooks** (en mode Live)
2. Ajoutez un endpoint avec l'URL de production : `https://votre-domaine.com/api/webhooks/stripe`
3. Sélectionnez les mêmes événements qu'en test
4. Récupérez le **webhook secret** de production

### 8.5 Mettre à jour le .env de production

```env
# Stripe PRODUCTION
STRIPE_SECRET_KEY="sk_live_..."
STRIPE_PUBLISHABLE_KEY="pk_live_..."
STRIPE_WEBHOOK_SECRET="whsec_..."
STRIPE_PRO_PRICE_ID="price_..."  # Price ID en mode live
STRIPE_SUCCESS_URL="https://votre-domaine.com/subscription-success.html"
STRIPE_CANCEL_URL="https://votre-domaine.com/pricing.html"
```

---

## Dépannage

### Le webhook ne fonctionne pas

1. Vérifiez que le `STRIPE_WEBHOOK_SECRET` est correct dans `.env`
2. Vérifiez que l'URL du webhook est accessible depuis Internet
3. En développement local, utilisez Stripe CLI ou ngrok
4. Consultez les logs dans **Developers** > **Webhooks** > **Your endpoint** > **Events**

### Erreur "Price ID not found"

1. Vérifiez que vous utilisez le bon Price ID (test vs live)
2. Assurez-vous que le produit existe dans Stripe
3. Vérifiez le mode (test/live) dans Stripe et dans votre `.env`

### Le paiement est accepté mais l'abonnement n'est pas créé

1. Vérifiez que les webhooks fonctionnent
2. Consultez les logs PHP (`storage/logs/app.log` ou logs Apache)
3. Vérifiez la table `webhook_events` dans MySQL pour voir si les événements arrivent
4. Vérifiez la table `subscriptions` pour voir si l'abonnement a été créé

### Erreur "This customer has no attached payment source"

1. Assurez-vous d'utiliser Stripe Checkout (qui gère automatiquement le payment method)
2. Ne créez pas de customer manuellement avant le checkout

---

## Ressources utiles

- **Documentation Stripe PHP** : [https://stripe.com/docs/api/php](https://stripe.com/docs/api/php)
- **Stripe Checkout** : [https://stripe.com/docs/payments/checkout](https://stripe.com/docs/payments/checkout)
- **Webhooks** : [https://stripe.com/docs/webhooks](https://stripe.com/docs/webhooks)
- **Cartes de test** : [https://stripe.com/docs/testing](https://stripe.com/docs/testing)
- **Stripe CLI** : [https://stripe.com/docs/stripe-cli](https://stripe.com/docs/stripe-cli)

---

## Support

Si vous rencontrez des problèmes :

1. Consultez les logs PHP et MySQL
2. Vérifiez les événements webhook dans le dashboard Stripe
3. Testez avec Stripe CLI pour voir les événements en temps réel
4. Contactez le support Stripe si nécessaire (excellent support !)

---

## Résumé des fichiers modifiés

- ✅ `.env` - Configuration Stripe
- ✅ `composer.json` - Ajout de stripe/stripe-php
- ✅ `src/Controllers/SubscriptionController.php` - Gestion des abonnements
- ✅ `src/Models/Subscription.php` - Modèle étendu
- ✅ `public/index.php` - Routes Stripe
- ✅ `public/pricing.html` - Page de tarification
- ✅ `public/subscription.html` - Gestion d'abonnement
- ✅ `public/subscription-success.html` - Page de succès

**Prochaine étape** : Installer Stripe PHP avec `composer require stripe/stripe-php` puis tester le flux complet !
