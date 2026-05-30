<?php

namespace App\Http\Controllers;

use App\Services\StripeSubscriptionSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(private StripeSubscriptionSync $subscriptionSync)
    {
        parent::__construct();
    }

    /**
     * Handle a Stripe webhook.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        Log::info('Webhook do Stripe recebido', [
            'event' => $request->input('type'),
            'data' => $request->all(),
        ]);

        return parent::handleWebhook($request);
    }

    /**
     * Handle customer subscription created.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        Log::info('Assinatura criada via webhook', [
            'payload' => $payload,
        ]);

        return parent::handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Handle customer subscription updated.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        Log::info('Assinatura atualizada via webhook', [
            'payload' => $payload,
        ]);

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        Log::info('Assinatura cancelada via webhook', [
            'payload' => $payload,
        ]);

        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Handle completed checkout session.
     *
     * Assinaturas criadas via Stripe Checkout Session (app mobile) não passam
     * pelo Cashier no servidor — só são gravadas localmente quando o webhook
     * `customer.subscription.created` chega. Para não depender disso,
     * sincronizamos a assinatura aqui de forma idempotente.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = $payload['data']['object'];

        Log::info('Checkout session concluída via webhook', [
            'session_id' => $session['id'] ?? null,
            'mode' => $session['mode'] ?? null,
        ]);

        if (($session['mode'] ?? null) === 'subscription' && ! empty($session['subscription'])) {
            try {
                $this->subscriptionSync->syncById($session['subscription']);
            } catch (\Throwable $e) {
                Log::error('Erro ao sincronizar assinatura via checkout.session.completed', [
                    'session_id' => $session['id'] ?? null,
                    'subscription_id' => $session['subscription'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->successMethod();
    }
}
