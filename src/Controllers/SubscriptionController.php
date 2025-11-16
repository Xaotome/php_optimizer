<?php

declare(strict_types=1);

namespace PhpOptimizer\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PhpOptimizer\Services\AuthService;
use PhpOptimizer\Models\Subscription;
use PhpOptimizer\Models\ResponseModel;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class SubscriptionController
{
    private AuthService $authService;
    private Subscription $subscriptionModel;
    private string $stripeSecretKey;
    private string $stripeWebhookSecret;
    private string $stripePriceId;
    private string $successUrl;
    private string $cancelUrl;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->subscriptionModel = new Subscription();

        // Charger les variables d'environnement Stripe
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->stripeWebhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $this->stripePriceId = $_ENV['STRIPE_PRO_PRICE_ID'] ?? '';
        $this->successUrl = $_ENV['STRIPE_SUCCESS_URL'] ?? '';
        $this->cancelUrl = $_ENV['STRIPE_CANCEL_URL'] ?? '';

        // Configurer Stripe
        if ($this->stripeSecretKey) {
            Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    /**
     * Créer une session Stripe Checkout pour l'abonnement Pro
     */
    public function createCheckoutSession(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            if (!$this->authService->isLoggedIn()) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'UNAUTHORIZED',
                    'Vous devez être connecté pour souscrire à un abonnement'
                ), 401);
            }

            $userId = $this->authService->getUserId();
            $currentUser = $this->authService->getCurrentUser();

            // Vérifier si l'utilisateur n'a pas déjà un abonnement Pro actif
            if ($currentUser['plan'] === 'pro') {
                return $this->jsonResponse($response, ResponseModel::error(
                    'ALREADY_SUBSCRIBED',
                    'Vous êtes déjà abonné au plan Pro'
                ), 400);
            }

            // Créer la session Stripe Checkout
            $checkoutSession = StripeSession::create([
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $this->stripePriceId,
                    'quantity' => 1,
                ]],
                'success_url' => $this->successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->cancelUrl,
                'customer_email' => $currentUser['email'],
                'client_reference_id' => (string)$userId,
                'metadata' => [
                    'user_id' => $userId,
                    'user_email' => $currentUser['email'],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => $userId,
                    ]
                ],
                'allow_promotion_codes' => true,
            ]);

            return $this->jsonResponse($response, ResponseModel::success([
                'checkout_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ]));

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe API Error: " . $e->getMessage());
            return $this->jsonResponse($response, ResponseModel::error(
                'STRIPE_ERROR',
                'Erreur lors de la création de la session de paiement: ' . $e->getMessage()
            ), 500);
        } catch (\Exception $e) {
            error_log("Checkout Error: " . $e->getMessage());
            return $this->jsonResponse($response, ResponseModel::error(
                'CHECKOUT_ERROR',
                'Erreur lors de la création de la session de paiement'
            ), 500);
        }
    }

    /**
     * Webhook Stripe pour gérer les événements de paiement
     */
    public function handleWebhook(Request $request, Response $response): Response
    {
        $payload = (string) $request->getBody();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');

        try {
            // Vérifier la signature du webhook
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            error_log('Webhook error: Invalid payload');
            return $response->withStatus(400);
        } catch (SignatureVerificationException $e) {
            error_log('Webhook error: Invalid signature');
            return $response->withStatus(400);
        }

        // Enregistrer l'événement dans la base de données
        $this->logWebhookEvent($event);

        // Gérer les différents types d'événements
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            default:
                error_log('Unhandled webhook event type: ' . $event->type);
        }

        return $response->withStatus(200);
    }

    /**
     * Annuler l'abonnement de l'utilisateur
     */
    public function cancelSubscription(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            if (!$this->authService->isLoggedIn()) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'UNAUTHORIZED',
                    'Vous devez être connecté'
                ), 401);
            }

            $userId = $this->authService->getUserId();

            // Récupérer l'abonnement actif
            $subscription = $this->subscriptionModel->getActiveSubscription($userId);

            if (!$subscription) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'NO_SUBSCRIPTION',
                    'Aucun abonnement actif trouvé'
                ), 404);
            }

            // Annuler l'abonnement via Stripe
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription['stripe_subscription_id']);
            $stripeSubscription->cancel();

            // Mettre à jour dans la base de données
            $this->subscriptionModel->cancelSubscription($subscription['id']);

            return $this->jsonResponse($response, ResponseModel::success([
                'message' => 'Abonnement annulé avec succès',
                'ends_at' => $subscription['current_period_end']
            ]));

        } catch (\Exception $e) {
            error_log("Cancel subscription error: " . $e->getMessage());
            return $this->jsonResponse($response, ResponseModel::error(
                'CANCEL_ERROR',
                'Erreur lors de l\'annulation de l\'abonnement'
            ), 500);
        }
    }

    /**
     * Récupérer le portail client Stripe
     */
    public function createPortalSession(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            if (!$this->authService->isLoggedIn()) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'UNAUTHORIZED',
                    'Vous devez être connecté'
                ), 401);
            }

            $userId = $this->authService->getUserId();

            // Récupérer l'abonnement actif
            $subscription = $this->subscriptionModel->getActiveSubscription($userId);

            if (!$subscription || !$subscription['stripe_customer_id']) {
                return $this->jsonResponse($response, ResponseModel::error(
                    'NO_CUSTOMER',
                    'Aucun abonnement trouvé'
                ), 404);
            }

            // Créer une session du portail client
            $portalSession = \Stripe\BillingPortal\Session::create([
                'customer' => $subscription['stripe_customer_id'],
                'return_url' => $this->successUrl,
            ]);

            return $this->jsonResponse($response, ResponseModel::success([
                'portal_url' => $portalSession->url
            ]));

        } catch (\Exception $e) {
            error_log("Portal session error: " . $e->getMessage());
            return $this->jsonResponse($response, ResponseModel::error(
                'PORTAL_ERROR',
                'Erreur lors de la création de la session du portail'
            ), 500);
        }
    }

    /**
     * Gérer la complétion d'un checkout
     */
    private function handleCheckoutCompleted($session): void
    {
        $userId = (int) $session->client_reference_id;
        $customerId = $session->customer;
        $subscriptionId = $session->subscription;

        error_log("Checkout completed for user $userId");

        // L'abonnement sera créé/mis à jour par l'événement customer.subscription.created
    }

    /**
     * Gérer la mise à jour d'un abonnement
     */
    private function handleSubscriptionUpdated($stripeSubscription): void
    {
        $userId = (int) $stripeSubscription->metadata->user_id;

        if (!$userId) {
            error_log("No user_id in subscription metadata");
            return;
        }

        // Déterminer le statut
        $status = match ($stripeSubscription->status) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'unpaid',
            default => 'inactive',
        };

        // Créer ou mettre à jour l'abonnement dans la base de données
        $this->subscriptionModel->createOrUpdateSubscription([
            'user_id' => $userId,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'stripe_price_id' => $stripeSubscription->items->data[0]->price->id,
            'status' => $status,
            'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ? 1 : 0,
        ]);

        error_log("Subscription updated for user $userId: $status");
    }

    /**
     * Gérer la suppression d'un abonnement
     */
    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $userId = (int) $stripeSubscription->metadata->user_id;

        if (!$userId) {
            error_log("No user_id in subscription metadata");
            return;
        }

        // Marquer l'abonnement comme annulé
        $subscription = $this->subscriptionModel->getByStripeId($stripeSubscription->id);

        if ($subscription) {
            $this->subscriptionModel->cancelSubscription($subscription['id']);
            error_log("Subscription deleted for user $userId");
        }
    }

    /**
     * Gérer le succès d'un paiement de facture
     */
    private function handleInvoicePaymentSucceeded($invoice): void
    {
        $customerId = $invoice->customer;
        $subscriptionId = $invoice->subscription;

        // Enregistrer la facture dans la base de données
        if ($subscriptionId) {
            $subscription = $this->subscriptionModel->getByStripeId($subscriptionId);

            if ($subscription) {
                $this->subscriptionModel->recordInvoice([
                    'user_id' => $subscription['user_id'],
                    'subscription_id' => $subscription['id'],
                    'stripe_invoice_id' => $invoice->id,
                    'amount' => $invoice->amount_paid / 100, // Stripe utilise les centimes
                    'currency' => strtoupper($invoice->currency),
                    'status' => 'paid',
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'paid_at' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at),
                ]);

                error_log("Invoice paid for user {$subscription['user_id']}: {$invoice->id}");
            }
        }
    }

    /**
     * Gérer l'échec d'un paiement de facture
     */
    private function handleInvoicePaymentFailed($invoice): void
    {
        $customerId = $invoice->customer;
        $subscriptionId = $invoice->subscription;

        if ($subscriptionId) {
            $subscription = $this->subscriptionModel->getByStripeId($subscriptionId);

            if ($subscription) {
                error_log("Invoice payment failed for user {$subscription['user_id']}: {$invoice->id}");

                // TODO: Envoyer un email à l'utilisateur pour l'informer de l'échec du paiement
            }
        }
    }

    /**
     * Enregistrer l'événement webhook dans la base de données
     */
    private function logWebhookEvent($event): void
    {
        try {
            $db = \PhpOptimizer\Database\Database::getInstance();
            $db->execute(
                "INSERT INTO webhook_events (event_id, event_type, payload, processed_at)
                 VALUES (?, ?, ?, NOW())",
                [$event->id, $event->type, json_encode($event->data->object)]
            );
        } catch (\Exception $e) {
            error_log("Failed to log webhook event: " . $e->getMessage());
        }
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
