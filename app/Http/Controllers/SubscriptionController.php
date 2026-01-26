<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\PromotionCode;
use Stripe\Stripe;
use Stripe\Webhook;

class SubscriptionController extends Controller
{
    /**
     * Mostra a página de assinatura
     */
    public function index()
    {
        $user = Auth::user();

        // Verifica se o usuário já tem um ID do Stripe
        if (empty($user->stripe_id)) {
            try {
                // Cria um cliente no Stripe para o usuário
                $user->createOrGetStripeCustomer();
            } catch (\Exception $e) {
                // Silenciosamente falha e continua
            }
        }

        // Tenta criar um setup intent, mas com tratamento de erro
        $intent = null;
        try {
            $intent = $user->createSetupIntent();
        } catch (\Exception $e) {
            // Cria um intent vazio para não quebrar o frontend
            $intent = ['client_secret' => ''];
        }

        // Verificar se o usuário tem uma assinatura cancelada mas ainda ativa
        $subscription = null;
        $subscriptionEndsAt = null;
        $subscriptionCancelled = false;
        $nextBillingDate = null;

        try {
            // Verifica se o usuário tem uma assinatura
            if ($user->subscribed('default')) {
                $subscription = $user->subscription('default');

                // Verifica se a assinatura foi cancelada
                $onGracePeriod = $subscription->onGracePeriod();
                $cancelAtPeriodEnd = $subscription->cancel_at_period_end;

                // Uma assinatura é considerada cancelada se estiver no período de graça ou se for cancelada no final do período
                $subscriptionCancelled = $onGracePeriod || $cancelAtPeriodEnd;

                // Obtém a data do próximo billing/término
                $stripeSubscription = $subscription->asStripeSubscription();

                if ($subscriptionCancelled) {
                    // Se a assinatura foi cancelada, obter a data de término
                    if ($subscription->ends_at) {
                        $subscriptionEndsAt = $subscription->ends_at->format('d/m/Y');
                    } elseif ($cancelAtPeriodEnd && isset($stripeSubscription->current_period_end)) {
                        $endTimestamp = $stripeSubscription->current_period_end;
                        $endDate = \Carbon\Carbon::createFromTimestamp($endTimestamp);
                        $subscriptionEndsAt = $endDate->format('d/m/Y');
                    }
                } else {
                    // Se a assinatura está ativa, obter a data da próxima cobrança
                    if (isset($stripeSubscription->current_period_end)) {
                        $nextBillingTimestamp = $stripeSubscription->current_period_end;
                        $nextBillingDate = \Carbon\Carbon::createFromTimestamp($nextBillingTimestamp)->format('d/m/Y');
                    }
                }
            }
        } catch (\Exception $e) {
            // Silenciosamente falha e continua
        }

        // Busca informações do preço no Stripe
        $priceInfo = $this->getPriceInfo();

        // Busca todos os planos disponíveis
        $plans = $this->getAllPlans();

        return Inertia::render('Subscription/Index', [
            'hasActiveSubscription' => $user->hasActiveSubscription(),
            'intent' => $intent,
            'subscriptionEndsAt' => $subscriptionEndsAt,
            'subscriptionCancelled' => $subscriptionCancelled,
            'nextBillingDate' => $nextBillingDate,
            'price' => $priceInfo['price'],
            'planInterval' => $priceInfo['interval'],
            'plans' => $plans,
        ]);
    }

    /**
     * Busca informações do preço no Stripe (usado na página de assinatura ativa)
     */
    private function getPriceInfo(): array
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));
            // Usa o plano mensal como referência
            $priceId = config('subscription.plans.monthly');

            if (empty($priceId)) {
                return ['price' => 0000, 'interval' => 'mês'];
            }

            $price = \Stripe\Price::retrieve($priceId);

            $intervalMap = [
                'year' => 'ano',
                'month' => 'mês',
                'week' => 'semana',
                'day' => 'dia',
            ];

            return [
                'price' => $price->unit_amount,
                'interval' => $intervalMap[$price->recurring->interval] ?? 'período',
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar informações do preço', ['error' => $e->getMessage()]);

            return ['price' => 0000, 'interval' => 'mês'];
        }
    }

    /**
     * Busca todos os planos de preços do Stripe
     * Os IDs dos preços são configurados em config/subscription.php
     */
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

                    // Calcula o preço mensal equivalente
                    $monthlyPrice = $price->unit_amount;
                    if ($interval === 'year') {
                        $monthlyPrice = round($price->unit_amount / 12);
                    } elseif ($interval === 'month' && $intervalCount > 1) {
                        $monthlyPrice = round($price->unit_amount / $intervalCount);
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

    /**
     * Valida um código de cupom/promocional
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        try {
            Stripe::setApiKey(config('cashier.secret'));

            $couponCode = strtoupper(trim($request->coupon_code));

            // Busca o código promocional pelo código
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

            // Verifica se o cupom está ativo
            if (! $coupon->valid) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este cupom não está mais disponível.',
                ]);
            }

            // Verifica se atingiu o limite de usos
            if ($promotionCode->max_redemptions !== null && $promotionCode->times_redeemed >= $promotionCode->max_redemptions) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Este cupom já atingiu o limite de usos.',
                ]);
            }

            // Calcula o desconto
            $priceInfo = $this->getPriceInfo();
            $originalPrice = $priceInfo['price'];
            $discountAmount = 0;
            $description = '';

            if ($coupon->percent_off) {
                $discountAmount = (int) round($originalPrice * ($coupon->percent_off / 100));
                $description = "{$coupon->percent_off}% de desconto";
            } elseif ($coupon->amount_off) {
                $discountAmount = $coupon->amount_off;
                $description = 'R$ '.number_format($coupon->amount_off / 100, 2, ',', '.').' de desconto';
            }

            // Se o cupom tem duração específica
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
                'coupon_code' => $request->coupon_code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Erro ao validar o cupom. Tente novamente.',
            ]);
        }
    }

    /**
     * Processa a assinatura
     */
    public function subscribe(Request $request)
    {
        $user = Auth::user();

        try {
            $request->validate([
                'payment_method' => 'required|string',
                'promotion_code_id' => 'nullable|string',
                'price_id' => 'nullable|string',
            ]);

            // Verifica se o usuário já tem uma assinatura ativa
            if ($user->hasActiveSubscription()) {
                return redirect()->route('subscription.index')
                    ->with('success', 'Você já possui uma assinatura ativa com vidas infinitas.');
            }

            try {
                Log::info('Iniciando processo de assinatura', [
                    'user_id' => $user->id,
                    'payment_method' => $request->payment_method,
                    'promotion_code_id' => $request->promotion_code_id,
                    'price_id' => $request->price_id,
                ]);

                // Verifica se o usuário já tem um ID do Stripe
                if (empty($user->stripe_id)) {
                    Log::info('Criando cliente Stripe para o usuário', ['user_id' => $user->id]);
                    // Cria um cliente no Stripe para o usuário
                    $user->createOrGetStripeCustomer();

                    // Recarrega o usuário para garantir que o stripe_id esteja atualizado
                    $user = $user->fresh();
                }

                // Verifica novamente se o stripe_id foi criado
                if (empty($user->stripe_id)) {
                    throw new \Exception('Não foi possível criar um cliente Stripe para o usuário');
                }

                Log::info('Cliente Stripe criado/obtido', ['stripe_id' => $user->stripe_id]);

                // Usa o preço enviado pelo frontend ou o plano mensal como padrão
                $priceId = $request->price_id ?: config('subscription.plans.monthly');

                if (empty($priceId)) {
                    throw new \Exception('Nenhum plano de preço configurado');
                }

                // Lista de preços válidos (configurados em config/subscription.php)
                $validPrices = array_values(config('subscription.plans', []));

                if (! empty($validPrices) && ! in_array($priceId, $validPrices)) {
                    throw new \Exception('Plano inválido selecionado');
                }

                Log::info('Criando assinatura', [
                    'price_id' => $priceId,
                    'stripe_id' => $user->stripe_id,
                    'promotion_code_id' => $request->promotion_code_id,
                ]);

                // Cria a assinatura usando o Cashier (sem trial)
                $subscriptionBuilder = $user
                    ->newSubscription('default', $priceId)
                    ->skipTrial();

                // Adiciona o código promocional se fornecido
                if ($request->promotion_code_id) {
                    $subscriptionBuilder->withPromotionCode($request->promotion_code_id);
                }

                $subscriptionBuilder->create($request->payment_method);

                Log::info('Assinatura criada com sucesso!', ['user_id' => $user->id]);

                return redirect()->route('subscription.index')
                    ->with('success', 'Assinatura realizada com sucesso! Você agora tem vidas infinitas.');
            } catch (\Exception $e) {
                Log::error('Erro ao criar assinatura', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return redirect()->route('subscription.index')
                    ->with('error', 'Erro: '.$e->getMessage());
            }
        } catch (IncompletePayment $exception) {
            try {
                // Verifica se a propriedade payment existe
                if (property_exists($exception, 'payment') && $exception->payment) {
                    $paymentId = $exception->payment->id ?? null;

                    if ($paymentId) {
                        return redirect()->route('cashier.payment', [$paymentId])
                            ->with('error', 'Precisamos de informações adicionais para completar o pagamento.');
                    }
                }
            } catch (\Exception $e) {
                // Silenciosamente falha e continua
            }

            // Fallback se não conseguir acessar o payment_id
            return redirect()->route('subscription.index')
                ->with('error', 'Ocorreu um erro ao processar seu pagamento. Por favor, tente novamente.');
        } catch (\Exception $e) {
            return redirect()->route('subscription.index')
                ->with('error', 'Ocorreu um erro ao processar sua assinatura. Por favor, tente novamente.');
        }
    }

    /**
     * Cancela a assinatura
     */
    public function cancel()
    {
        $user = Auth::user();

        try {
            // Verifica se o usuário tem uma assinatura ativa
            if (! $user->hasActiveSubscription()) {
                return redirect()->route('subscription.index')
                    ->with('error', 'Você não possui uma assinatura ativa para cancelar.');
            }

            // Cancela a assinatura no Stripe (no final do período atual)
            $subscription = $user->subscription('default');
            $subscription->cancel();

            // Obter a data de término efetivo da assinatura
            $endsAt = $subscription->ends_at;
            $formattedDate = $endsAt ? $endsAt->format('d/m/Y') : 'data desconhecida';

            return redirect()->route('subscription.index')
                ->with('success', "Sua assinatura foi cancelada com sucesso. Você continuará tendo acesso até $formattedDate.");
        } catch (\Exception $e) {
            return redirect()->route('subscription.index')
                ->with('error', 'Ocorreu um erro ao cancelar sua assinatura. Por favor, tente novamente.');
        }
    }

    /**
     * Reativa uma assinatura cancelada
     */
    public function resume()
    {
        $user = Auth::user();

        try {
            // Verifica se o usuário já tem uma assinatura ativa
            if ($user->hasActiveSubscription()) {
                return redirect()->route('subscription.index')
                    ->with('info', 'Você já possui uma assinatura ativa com vidas infinitas.');
            }

            // Verifica se a assinatura está em período de graça
            if ($user->subscription('default') && $user->subscription('default')->onGracePeriod()) {
                // Reativa a assinatura no Stripe
                $user->subscription('default')->resume();

                return redirect()->route('subscription.index')
                    ->with('success', 'Sua assinatura foi reativada com sucesso!');
            }

            // Se não está em período de graça, precisa criar uma nova assinatura
            return redirect()->route('subscription.index')
                ->with('info', 'Sua assinatura já expirou. Por favor, assine novamente.');
        } catch (\Exception $e) {
            return redirect()->route('subscription.index')
                ->with('error', 'Ocorreu um erro ao reativar sua assinatura. Por favor, tente novamente.');
        }
    }

    /**
     * Webhook para processar eventos do Stripe
     */
    public function webhook(Request $request)
    {
        // Verificar a assinatura do webhook
        $stripeSignature = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        try {
            // Obter o payload e verificar a assinatura
            $payload = $request->getContent();
            $event = Webhook::constructEvent(
                $payload, $stripeSignature, $webhookSecret
            );

            // Processar o evento
            $method = 'handle'.str_replace('.', '', ucwords($event->type, '.'));

            if (method_exists($this, $method)) {
                return $this->{$method}($event->data->object);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            // Retornar erro 400 para o Stripe
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Manipula o evento de assinatura cancelada
     */
    protected function handleCustomerSubscriptionDeleted($object)
    {
        Log::info('Processando evento de assinatura cancelada', [
            'customer_id' => $object->customer,
            'subscription_id' => $object->id,
        ]);

        // Deleta a assinatura do banco de dados
        $subscription = \Laravel\Cashier\Subscription::where('stripe_id', $object->id)->first();

        if ($subscription) {
            Log::info('Assinatura encontrada, deletando do banco', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
            $subscription->delete();
            Log::info('Assinatura deletada com sucesso');
        } else {
            Log::warning('Assinatura não encontrada no banco', ['stripe_id' => $object->id]);
        }

        return response()->json(['status' => 'success']);
    }
}
