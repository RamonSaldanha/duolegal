<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StripeSubscriptionSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\EphemeralKey;
use Stripe\PromotionCode;
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    /**
     * GET /v1/subscription/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasActive = $user->hasActiveSubscription();

        $endsAt = null;
        $cancelled = false;
        $nextBillingDate = null;

        try {
            if ($user->subscribed('default')) {
                $subscription = $user->subscription('default');
                $onGracePeriod = $subscription->onGracePeriod();
                $cancelAtPeriodEnd = $subscription->cancel_at_period_end;
                $cancelled = $onGracePeriod || $cancelAtPeriodEnd;

                $stripeSubscription = $subscription->asStripeSubscription();

                if ($cancelled) {
                    if ($subscription->ends_at) {
                        $endsAt = $subscription->ends_at->format('d/m/Y');
                    } elseif ($cancelAtPeriodEnd && isset($stripeSubscription->current_period_end)) {
                        $endsAt = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('d/m/Y');
                    }
                } elseif (isset($stripeSubscription->current_period_end)) {
                    $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('d/m/Y');
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar status da assinatura', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'has_active_subscription' => $hasActive,
            'ends_at' => $endsAt,
            'cancelled' => $cancelled,
            'next_billing_date' => $nextBillingDate,
            'plans' => $this->getAllPlans(),
        ]);
    }

    /**
     * POST /v1/subscription/setup-intent
     * Cria SetupIntent + EphemeralKey para uso com Stripe Mobile Payment Sheet.
     */
    public function setupIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if (empty($user->stripe_id)) {
                $user->createOrGetStripeCustomer();
                $user = $user->fresh();
            }

            Stripe::setApiKey(config('cashier.secret'));

            $intent = $user->createSetupIntent();

            $ephemeralKey = EphemeralKey::create(
                ['customer' => $user->stripe_id],
                ['stripe_version' => '2024-06-20']
            );

            return response()->json([
                'client_secret' => $intent->client_secret,
                'customer_id' => $user->stripe_id,
                'ephemeral_key' => $ephemeralKey->secret,
                'publishable_key' => config('cashier.key'),
                'plans' => $this->getAllPlans(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar SetupIntent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível iniciar o pagamento.',
            ], 500);
        }
    }

    /**
     * POST /v1/subscription/confirm
     * Cria a assinatura usando o payment_method gerado pelo Payment Sheet.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method_id' => 'required|string',
            'price_id' => 'nullable|string',
            'promotion_code_id' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user->hasActiveSubscription()) {
            return response()->json([
                'success' => true,
                'already_active' => true,
                'message' => 'Você já possui uma assinatura ativa.',
            ]);
        }

        try {
            if (empty($user->stripe_id)) {
                $user->createOrGetStripeCustomer();
                $user = $user->fresh();
            }

            $priceId = $validated['price_id'] ?? config('subscription.plans.monthly');
            if (empty($priceId)) {
                return response()->json(['message' => 'Plano não configurado.'], 422);
            }

            $validPrices = array_values(config('subscription.plans', []));
            if (! empty($validPrices) && ! in_array($priceId, $validPrices)) {
                return response()->json(['message' => 'Plano inválido.'], 422);
            }

            $builder = $user->newSubscription('default', $priceId)->skipTrial();

            if (! empty($validated['promotion_code_id'])) {
                $builder->withPromotionCode($validated['promotion_code_id']);
            }

            $builder->create($validated['payment_method_id']);

            return response()->json([
                'success' => true,
                'has_active_subscription' => $user->fresh()->hasActiveSubscription(),
            ]);
        } catch (IncompletePayment $exception) {
            return response()->json([
                'success' => false,
                'requires_action' => true,
                'message' => 'Pagamento exige confirmação adicional.',
            ], 402);
        } catch (\Exception $e) {
            Log::error('Erro ao criar assinatura via API', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /v1/subscription/checkout-session
     * Cria uma Stripe Checkout Session (página hospedada) e devolve a URL para
     * abrir num browser in-app. Compatível com Expo Go (sem módulo nativo do Stripe).
     */
    public function checkoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
            'success_url' => 'nullable|string',
            'cancel_url' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Você já possui uma assinatura ativa.',
            ], 422);
        }

        $validPrices = array_values(config('subscription.plans', []));
        if (! empty($validPrices) && ! in_array($validated['price_id'], $validPrices)) {
            return response()->json(['message' => 'Plano inválido.'], 422);
        }

        try {
            if (empty($user->stripe_id)) {
                $user->createOrGetStripeCustomer();
                $user = $user->fresh();
            }

            Stripe::setApiKey(config('cashier.secret'));

            $successUrl = $validated['success_url']
                ?? 'memorize://subscription/return?status=success&session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $validated['cancel_url']
                ?? 'memorize://subscription/return?status=cancelled';

            $session = StripeCheckoutSession::create([
                'mode' => 'subscription',
                'customer' => $user->stripe_id,
                'line_items' => [[
                    'price' => $validated['price_id'],
                    'quantity' => 1,
                ]],
                'allow_promotion_codes' => true,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'user_id' => $user->id,
                    'source' => 'mobile_app',
                ],
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar Checkout Session', [
                'user_id' => $user->id,
                'price_id' => $validated['price_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível iniciar o checkout.',
            ], 500);
        }
    }

    /**
     * POST /v1/subscription/sync
     * Sincroniza a assinatura criada via Checkout Session para o banco local,
     * a partir do session_id recebido no deep-link de retorno do app.
     * Garante o status premium imediato, sem depender do webhook.
     */
    public function syncFromCheckout(Request $request, StripeSubscriptionSync $sync): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
        ]);

        $user = $request->user();

        try {
            Stripe::setApiKey(config('cashier.secret'));

            $session = StripeCheckoutSession::retrieve($validated['session_id']);

            // Garante que a sessão pertence ao usuário autenticado.
            $belongsToUser = $session->customer === $user->stripe_id
                || (string) ($session->metadata->user_id ?? '') === (string) $user->id;

            if (! $belongsToUser) {
                return response()->json(['message' => 'Sessão não pertence ao usuário.'], 403);
            }

            if ($session->mode === 'subscription' && $session->subscription) {
                $sync->syncById($session->subscription);
            }

            return response()->json([
                'success' => true,
                'has_active_subscription' => $user->fresh()->hasActiveSubscription(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar assinatura do checkout', [
                'user_id' => $user->id,
                'session_id' => $validated['session_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível sincronizar a assinatura.',
            ], 500);
        }
    }

    /**
     * POST /v1/subscription/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Você não possui uma assinatura ativa.',
            ], 422);
        }

        try {
            $subscription = $user->subscription('default');
            $subscription->cancel();

            $endsAt = $subscription->ends_at?->format('d/m/Y');

            return response()->json([
                'success' => true,
                'ends_at' => $endsAt,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao cancelar assinatura', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Não foi possível cancelar.',
            ], 500);
        }
    }

    /**
     * POST /v1/subscription/resume
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasActiveSubscription() && ! $user->subscription('default')?->onGracePeriod()) {
            return response()->json([
                'message' => 'Assinatura já está ativa.',
            ], 422);
        }

        try {
            if ($user->subscription('default') && $user->subscription('default')->onGracePeriod()) {
                $user->subscription('default')->resume();

                return response()->json(['success' => true]);
            }

            return response()->json([
                'message' => 'Assinatura expirada. Crie uma nova.',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao retomar assinatura', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Não foi possível retomar.'], 500);
        }
    }

    /**
     * POST /v1/subscription/validate-coupon
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string|max:50',
            'price_id' => 'nullable|string',
        ]);

        try {
            Stripe::setApiKey(config('cashier.secret'));

            $couponCode = strtoupper(trim($validated['coupon_code']));

            $promotionCodes = PromotionCode::all([
                'code' => $couponCode,
                'active' => true,
                'limit' => 1,
            ]);

            if (empty($promotionCodes->data)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Cupom não encontrado ou expirado.',
                ]);
            }

            $promotionCode = $promotionCodes->data[0];
            $coupon = $promotionCode->coupon;

            if (! $coupon->valid) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este cupom não está mais disponível.',
                ]);
            }

            if ($promotionCode->max_redemptions !== null && $promotionCode->times_redeemed >= $promotionCode->max_redemptions) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este cupom já atingiu o limite de usos.',
                ]);
            }

            $priceId = $validated['price_id'] ?? config('subscription.plans.monthly');
            $originalPrice = 0;
            if ($priceId) {
                try {
                    $originalPrice = \Stripe\Price::retrieve($priceId)->unit_amount;
                } catch (\Exception $e) {
                    // ignore
                }
            }

            $discountAmount = 0;
            $description = '';

            if ($coupon->percent_off) {
                $discountAmount = (int) round($originalPrice * ($coupon->percent_off / 100));
                $description = "{$coupon->percent_off}% de desconto";
            } elseif ($coupon->amount_off) {
                $discountAmount = $coupon->amount_off;
                $description = 'R$ '.number_format($coupon->amount_off / 100, 2, ',', '.').' de desconto';
            }

            if ($coupon->duration === 'once') {
                $description .= ' (primeiro pagamento)';
            } elseif ($coupon->duration === 'repeating' && $coupon->duration_in_months) {
                $description .= " (por {$coupon->duration_in_months} meses)";
            }

            return response()->json([
                'valid' => true,
                'description' => $description,
                'discount_amount' => $discountAmount,
                'percent_off' => $coupon->percent_off,
                'amount_off' => $coupon->amount_off,
                'promotion_code_id' => $promotionCode->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao validar cupom', [
                'coupon_code' => $validated['coupon_code'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Erro ao validar o cupom.',
            ]);
        }
    }

    private function getAllPlans(): array
    {
        $plans = config('subscription.plans', []);
        $result = [];

        try {
            Stripe::setApiKey(config('cashier.secret'));

            foreach ($plans as $key => $priceId) {
                try {
                    $price = \Stripe\Price::retrieve($priceId);
                    $intervalCount = $price->recurring->interval_count ?? 1;
                    $interval = $price->recurring->interval ?? 'month';

                    $monthlyPrice = $price->unit_amount;
                    if ($interval === 'year') {
                        $monthlyPrice = (int) round($price->unit_amount / 12);
                    } elseif ($interval === 'month' && $intervalCount > 1) {
                        $monthlyPrice = (int) round($price->unit_amount / $intervalCount);
                    }

                    $result[$key] = [
                        'price_id' => $priceId,
                        'price' => $price->unit_amount,
                        'monthly_price' => $monthlyPrice,
                        'interval' => $interval,
                        'interval_count' => $intervalCount,
                        'currency' => $price->currency,
                    ];
                } catch (\Exception $e) {
                    Log::warning("Erro ao buscar preço {$key}", ['error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar planos', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}
