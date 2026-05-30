<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

/**
 * Sincroniza uma assinatura do Stripe para a tabela local `subscriptions`
 * de forma idempotente.
 *
 * Usado tanto pelo webhook `checkout.session.completed` quanto pelo endpoint
 * de retorno do app mobile, para que o status premium não dependa do timing
 * nem da configuração de eventos do webhook do Stripe.
 */
class StripeSubscriptionSync
{
    /**
     * Recupera a assinatura no Stripe pelo ID e sincroniza para o banco.
     */
    public function syncById(string $subscriptionId): ?Subscription
    {
        Stripe::setApiKey(config('cashier.secret'));

        $stripeSubscription = StripeSubscription::retrieve([
            'id' => $subscriptionId,
            'expand' => ['items.data.price'],
        ]);

        return $this->sync($stripeSubscription);
    }

    /**
     * Sincroniza um objeto de assinatura do Stripe para o banco (cria ou atualiza).
     */
    public function sync(StripeSubscription $data): ?Subscription
    {
        $user = Cashier::findBillable($data->customer);

        if (! $user) {
            return null;
        }

        $items = $data->items->data;
        $firstItem = $items[0];
        $isSinglePrice = count($items) === 1;

        $subscription = $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $data->id],
            [
                'type' => $data->metadata->type ?? $data->metadata->name ?? 'default',
                'stripe_status' => $data->status,
                'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
                'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
                'trial_ends_at' => $data->trial_end ? Carbon::createFromTimestamp($data->trial_end) : null,
                'ends_at' => $this->resolveEndsAt($data, $firstItem),
            ]
        );

        $itemIds = [];

        foreach ($items as $item) {
            $itemIds[] = $item->id;

            $subscription->items()->updateOrCreate(
                ['stripe_id' => $item->id],
                [
                    'stripe_product' => $item->price->product,
                    'stripe_price' => $item->price->id,
                    'quantity' => $item->quantity ?? null,
                ]
            );
        }

        $subscription->items()->whereNotIn('stripe_id', $itemIds)->delete();

        // Encerra o trial genérico do usuário, se existir.
        if (! is_null($user->trial_ends_at)) {
            $user->trial_ends_at = null;
            $user->save();
        }

        return $subscription;
    }

    /**
     * Determina a data de término da assinatura (cancelamentos).
     *
     * Em versões recentes da API do Stripe `current_period_end` vive no item da
     * assinatura, não na raiz — por isso o fallback para `$firstItem`.
     */
    private function resolveEndsAt(StripeSubscription $data, $firstItem): ?Carbon
    {
        $currentPeriodEnd = $data->current_period_end ?? ($firstItem->current_period_end ?? null);

        if ($data->cancel_at_period_end && $currentPeriodEnd) {
            return Carbon::createFromTimestamp($currentPeriodEnd);
        }

        if (! empty($data->cancel_at) || ! empty($data->canceled_at)) {
            return Carbon::createFromTimestamp($data->cancel_at ?? $data->canceled_at);
        }

        return null;
    }
}
