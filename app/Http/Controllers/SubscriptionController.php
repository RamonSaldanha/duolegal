<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;
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
                $stripeCustomer = $user->createOrGetStripeCustomer();
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

        try {
            // Verifica se o usuário tem uma assinatura
            if ($user->subscribed('default')) {
                $subscription = $user->subscription('default');

                // Verifica se a assinatura foi cancelada
                $onGracePeriod = $subscription->onGracePeriod();
                $cancelAtPeriodEnd = $subscription->cancel_at_period_end;

                // Uma assinatura é considerada cancelada se estiver no período de graça ou se for cancelada no final do período
                $subscriptionCancelled = $onGracePeriod || $cancelAtPeriodEnd;

                // Se a assinatura foi cancelada, obter a data de término
                if ($subscriptionCancelled) {
                    // Se a assinatura tem uma data de término definida
                    if ($subscription->ends_at) {
                        $subscriptionEndsAt = $subscription->ends_at->format('d/m/Y');
                    }
                    // Se a assinatura será cancelada no final do período atual
                    else if ($cancelAtPeriodEnd && isset($subscription->asStripeSubscription()->current_period_end)) {
                        $endTimestamp = $subscription->asStripeSubscription()->current_period_end;
                        $endDate = \Carbon\Carbon::createFromTimestamp($endTimestamp);
                        $subscriptionEndsAt = $endDate->format('d/m/Y');
                    }
                }
            }
        } catch (\Exception $e) {
            // Silenciosamente falha e continua
        }

        return Inertia::render('Subscription/Index', [
            'hasActiveSubscription' => $user->hasActiveSubscription(),
            'intent' => $intent,
            'subscriptionEndsAt' => $subscriptionEndsAt,
            'subscriptionCancelled' => $subscriptionCancelled,
        ]);
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
            ]);

            // Verifica se o usuário já tem uma assinatura ativa
            if ($user->hasActiveSubscription()) {
                return redirect()->route('subscription.index')
                    ->with('success', 'Você já possui uma assinatura ativa com vidas infinitas.');
            }

            try {
                // Verifica se o usuário já tem um ID do Stripe
                if (empty($user->stripe_id)) {
                    // Cria um cliente no Stripe para o usuário
                    $stripeCustomer = $user->createOrGetStripeCustomer();

                    // Recarrega o usuário para garantir que o stripe_id esteja atualizado
                    $user = $user->fresh();
                }

                // Verifica novamente se o stripe_id foi criado
                if (empty($user->stripe_id)) {
                    throw new \Exception('Não foi possível criar um cliente Stripe para o usuário');
                }

                // Tenta criar a assinatura usando o Cashier (sem trial)
                $subscription = $user
                    ->newSubscription('default', 'price_1R9xghJhFrAxy23kFqF5gDrD')
                    ->skipTrial() // Garante que não haja período de teste
                    ->create($request->payment_method);

                return redirect()->route('subscription.index')
                    ->with('success', 'Assinatura realizada com sucesso! Você agora tem vidas infinitas.');
            } catch (\Exception $e) {
                return redirect()->route('subscription.index')
                    ->with('error', 'Não foi possível processar sua assinatura. Por favor, tente novamente ou entre em contato com o suporte.');
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
            if (!$user->hasActiveSubscription()) {
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
            $method = 'handle' . str_replace('.', '', ucwords($event->type, '.'));

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
            'subscription_id' => $object->id
        ]);

        $user = User::where('stripe_id', $object->customer)->first();

        if ($user) {
            Log::info('Usuário encontrado, desativando vidas infinitas', ['user_id' => $user->id]);
            // As vidas infinitas são automaticamente desativadas quando a assinatura é cancelada
            // pois agora elas são baseadas diretamente no status da assinatura
            Log::info('Assinatura cancelada, vidas infinitas desativadas automaticamente');
            Log::info('Vidas infinitas desativadas com sucesso');
        } else {
            Log::warning('Usuário não encontrado para o customer_id', ['customer_id' => $object->customer]);
        }

        return response()->json(['status' => 'success']);
    }
}
