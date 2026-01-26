<template>
    <Head title="Assinatura" />

    <!-- Layout Checkout -->
    <template v-if="!hasActiveSubscription">
        <div class="min-h-screen bg-white dark:bg-gray-900">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="max-w-6xl mx-auto px-4 h-18 flex items-center justify-between">
                    <a href="/" class="flex items-center">
                        <img src="/img/logomemorizeblack.svg" class="mt-[-16px] block dark:hidden" alt="Logo" style="height: 90px; width: 110px;">
                    </a>
                    <a href="/" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </a>
                </div>
            </div>

            <!-- Conteúdo -->
            <div class="max-w-6xl mx-auto px-4 py-6 lg:py-8">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
                    <!-- Coluna Esquerda: Benefícios -->
                    <div class="lg:col-span-2">
                        <div class="p-5 lg:p-6">
                            <h2 class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white mb-1">
                                Seja Premium e estude sem limites
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-5">
                                Desbloqueie todos os recursos e acelere seus estudos
                            </p>

                            <ul class="space-y-4">
                                <li class="flex items-start gap-3">
                                    <Heart class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Vidas infinitas</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Estude sem interrupções</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <Zap class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Sem anúncios</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Experiência limpa e focada</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <BookOpen class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Todas as legislações</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Acesso completo ao conteúdo</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <Shield class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Garantia de 7 dias</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Reembolso total se não gostar</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Coluna Direita: Checkout -->
                    <div class="lg:col-span-3">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <!-- Escolher Plano -->
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Escolher plano</h3>

                                <div class="grid grid-cols-3 gap-2 lg:gap-3">
                                    <!-- Plano Mensal -->
                                    <button
                                        @click="selectPlan('monthly')"
                                        :class="[
                                            'relative p-3 lg:p-4 rounded-xl border-2 text-left transition-all',
                                            selectedPlan === 'monthly'
                                                ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                        ]"
                                    >
                                        <div class="font-semibold text-gray-900 dark:text-white text-sm">Mensal</div>
                                        <div class="mt-1">
                                            <span class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(getPlanPrice('monthly')) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">/mês</div>
                                        <div v-if="selectedPlan === 'monthly'" class="absolute top-2 right-2">
                                            <CheckCircle class="w-4 h-4 text-purple-500" />
                                        </div>
                                    </button>

                                    <!-- Plano Trimestral -->
                                    <button
                                        @click="selectPlan('quarterly')"
                                        :class="[
                                            'relative p-3 lg:p-4 rounded-xl border-2 text-left transition-all',
                                            selectedPlan === 'quarterly'
                                                ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                        ]"
                                    >
                                        <div v-if="getDiscount('quarterly')" class="absolute -top-2 left-1/2 -translate-x-1/2">
                                            <span class="bg-green-500 text-white text-[10px] font-medium px-1.5 py-0.5 rounded-full">
                                                {{ getDiscount('quarterly') }}
                                            </span>
                                        </div>
                                        <div class="font-semibold text-gray-900 dark:text-white text-sm">Trimestral</div>
                                        <div class="mt-1">
                                            <span class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(getPlanPrice('quarterly')) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(getPlanMonthlyPrice('quarterly')) }}/mês</div>
                                        <div v-if="selectedPlan === 'quarterly'" class="absolute top-2 right-2">
                                            <CheckCircle class="w-4 h-4 text-purple-500" />
                                        </div>
                                    </button>

                                    <!-- Plano Anual -->
                                    <button
                                        @click="selectPlan('yearly')"
                                        :class="[
                                            'relative p-3 lg:p-4 rounded-xl border-2 text-left transition-all',
                                            selectedPlan === 'yearly'
                                                ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                        ]"
                                    >
                                        <div v-if="getDiscount('yearly')" class="absolute -top-2 left-1/2 -translate-x-1/2">
                                            <span class="bg-green-500 text-white text-[10px] font-medium px-1.5 py-0.5 rounded-full">
                                                {{ getDiscount('yearly') }}
                                            </span>
                                        </div>
                                        <div class="font-semibold text-gray-900 dark:text-white text-sm">Anual</div>
                                        <div class="mt-1">
                                            <span class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(getPlanPrice('yearly')) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(getPlanMonthlyPrice('yearly')) }}/mês</div>
                                        <div v-if="selectedPlan === 'yearly'" class="absolute top-2 right-2">
                                            <CheckCircle class="w-4 h-4 text-purple-500" />
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Informações de Pagamento -->
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Informações de pagamento</h3>

                                <!-- Flash/Error Messages -->
                                <div v-if="flash.error" class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-4">
                                    <div class="flex items-center">
                                        <AlertCircle class="text-red-500 w-4 h-4 mr-2 flex-shrink-0" />
                                        <p class="text-red-700 dark:text-red-300 text-sm">{{ flash.error }}</p>
                                    </div>
                                </div>

                                <div v-if="cardError" class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-lg mb-4 text-sm">
                                    {{ cardError }}
                                </div>

                                <!-- Campos do Cartão -->
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Número do Cartão <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div id="card-number-element" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-3 bg-white dark:bg-gray-900 h-[46px] pr-12"></div>
                                            <div v-if="cardBrand" class="absolute right-3 top-1/2 -translate-y-1/2">
                                                <img :src="getCardBrandIcon(cardBrand)" :alt="cardBrand" class="h-6 w-auto" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                Data de Expiração <span class="text-red-500">*</span>
                                            </label>
                                            <div id="card-expiry-element" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-3 bg-white dark:bg-gray-900 h-[46px]"></div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                                CVV <span class="text-red-500">*</span>
                                            </label>
                                            <div id="card-cvc-element" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-3 bg-white dark:bg-gray-900 h-[46px]"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumo do Faturamento -->
                            <div class="p-5 lg:p-6 bg-gray-50 dark:bg-gray-900/50">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Resumo do faturamento</h3>

                                <!-- Cupom -->
                                <div class="mb-4">
                                    <div class="flex gap-2">
                                        <Input
                                            v-model="couponCode"
                                            type="text"
                                            placeholder="Código de desconto"
                                            class="flex-1 bg-white dark:bg-gray-800 h-10"
                                            :disabled="couponLoading || !!appliedCoupon"
                                        />
                                        <Button
                                            v-if="!appliedCoupon"
                                            variant="outline"
                                            @click="applyCoupon"
                                            :disabled="couponLoading || !couponCode.trim()"
                                            class="h-10"
                                        >
                                            <Loader2 v-if="couponLoading" class="h-4 w-4 animate-spin" />
                                            <span v-else>Aplicar</span>
                                        </Button>
                                        <Button
                                            v-else
                                            variant="outline"
                                            @click="removeCoupon"
                                            class="h-10 text-red-500 hover:text-red-600"
                                        >
                                            Remover
                                        </Button>
                                    </div>
                                    <p v-if="couponError" class="text-red-500 text-xs mt-1">{{ couponError }}</p>
                                    <div v-if="appliedCoupon" class="mt-2 flex items-center gap-2 text-green-600 dark:text-green-400 text-sm">
                                        <CheckCircle class="w-4 h-4" />
                                        <span>Cupom "{{ appliedCoupon.code }}" aplicado!</span>
                                    </div>
                                </div>

                                <!-- Valores -->
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">{{ planLabels[selectedPlan] }}</span>
                                        <span class="text-gray-900 dark:text-white">{{ formatCurrency(getPlanPrice(selectedPlan)) }}</span>
                                    </div>
                                    <div v-if="appliedCoupon" class="flex justify-between text-green-600 dark:text-green-400">
                                        <span>Desconto</span>
                                        <span>-{{ formatCurrency(discountAmount) }}</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                        <span class="font-semibold text-gray-900 dark:text-white">Total</span>
                                        <span class="font-bold text-xl text-gray-900 dark:text-white">{{ formatCurrency(finalTotal) }}</span>
                                    </div>
                                </div>

                                <!-- Botão de Assinar -->
                                <GameButton
                                    variant="purple"
                                    size="lg"
                                    @click="subscribe"
                                    :disabled="isProcessing"
                                    class="w-full mt-6"
                                >
                                    <span v-if="isProcessing" class="flex items-center justify-center">
                                        <Loader2 class="h-5 w-5 animate-spin mr-2" />
                                        Processando...
                                    </span>
                                    <span v-else>Assinar agora</span>
                                </GameButton>

                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-4">
                                    Ao assinar, você concorda com nossos Termos de Uso.
                                    Pagamento seguro pelo Stripe.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Layout para usuários com assinatura ativa -->
    <template v-else>
        <div class="min-h-screen bg-white dark:bg-gray-900">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="max-w-6xl mx-auto px-4 h-18 flex items-center justify-between">
                    <a href="/" class="flex items-center">
                        <img src="/img/logomemorizeblack.svg" class="mt-[-16px] block dark:hidden" alt="Logo" style="height: 90px; width: 110px;">
                    </a>
                    <a href="/" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </a>
                </div>
            </div>

            <!-- Conteúdo -->
            <div class="max-w-6xl mx-auto px-4 py-6 lg:py-8">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
                    <!-- Coluna Esquerda: Benefícios -->
                    <div class="lg:col-span-2">
                        <div class="p-5 lg:p-6">
                            <h2 class="text-lg lg:text-xl font-bold text-gray-900 dark:text-white mb-1">
                                Você é Premium!
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-5">
                                Aproveite todos os recursos desbloqueados
                            </p>

                            <ul class="space-y-4">
                                <li class="flex items-start gap-3">
                                    <Heart class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Vidas infinitas</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Estude sem interrupções</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <Zap class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Sem anúncios</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Experiência limpa e focada</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <BookOpen class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Todas as legislações</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Acesso completo ao conteúdo</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <Shield class="w-5 h-5 text-gray-700 dark:text-gray-300 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Garantia de 7 dias</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Reembolso total se não gostar</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Coluna Direita: Gerenciar Assinatura -->
                    <div class="lg:col-span-3">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <!-- Seu Plano -->
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Seu plano</h3>

                                <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                                            <CheckCircle class="w-5 h-5 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white">Memorize Premium</div>
                                            <div class="text-sm text-green-600 dark:text-green-400">Ativo</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(props.price || 1199) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">/{{ props.planInterval || 'mês' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status da Assinatura -->
                            <div class="p-5 lg:p-6">
                                <!-- Assinatura cancelada mas ainda ativa -->
                                <div v-if="props.subscriptionCancelled">
                                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-4">
                                        <div class="flex items-start gap-3">
                                            <AlertCircle class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                                            <div>
                                                <p class="font-medium text-amber-800 dark:text-amber-200">Cancelamento agendado</p>
                                                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                                                    Você ainda tem acesso até <strong>{{ props.subscriptionEndsAt }}</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <GameButton
                                        @click="resumeSubscription"
                                        class="w-full"
                                        variant="green"
                                    >
                                        Reativar assinatura
                                    </GameButton>
                                </div>

                                <!-- Assinatura ativa normal -->
                                <div v-else>
                                    <div class="flex items-center justify-between text-sm p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl mb-4">
                                        <span class="text-gray-600 dark:text-gray-400">Próxima cobrança</span>
                                        <span class="text-gray-900 dark:text-white font-medium">{{ props.nextBillingDate || '—' }}</span>
                                    </div>
                                    <GameButton
                                        @click="cancelSubscription"
                                        class="w-full"
                                        variant="red"
                                    >
                                        Cancelar assinatura
                                    </GameButton>
                                </div>
                            </div>
                        </div>

                        <!-- Link para voltar -->
                        <div class="text-center mt-6">
                            <a href="/" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium">
                                ← Voltar para o app
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import GameButton from '@/components/ui/GameButton.vue';
import { CheckCircle, Loader2, AlertCircle, X, Heart, Zap, BookOpen, Shield } from 'lucide-vue-next';
import { onMounted, ref, computed } from 'vue';
import axios from 'axios';

declare global {
    interface Window {
        Stripe?: any;
    }
}

interface AppliedCoupon {
    code: string;
    description: string;
    discount_amount: number;
    percent_off: number | null;
    amount_off: number | null;
    promotion_code_id: string;
}

interface PlanInfo {
    price_id: string;
    price: number;
    monthly_price: number;
    interval: string;
    interval_count: number;
    currency: string;
}

interface Props {
    hasActiveSubscription: boolean;
    intent: {
        client_secret: string;
    };
    subscriptionEndsAt: string | null;
    subscriptionCancelled: boolean;
    nextBillingDate: string | null;
    price: number;
    planInterval: string;
    plans?: Record<string, PlanInfo>;
}

const props = defineProps<Props>();
const page = usePage();

// Planos disponíveis
const selectedPlan = ref<'monthly' | 'quarterly' | 'yearly'>('monthly');

// Valores padrão caso o backend não retorne os planos
const defaultPrices: Record<string, number> = {
    monthly: 1199,
    quarterly: 2999,
    yearly: 8999,
};

const defaultMonthlyPrices: Record<string, number> = {
    monthly: 1199,
    quarterly: 1000,
    yearly: 750,
};

const planLabels: Record<string, string> = {
    monthly: 'Plano Mensal',
    quarterly: 'Plano Trimestral',
    yearly: 'Plano Anual',
};

// Funções para obter preços (do backend ou valores padrão)
const getPlanPrice = (plan: string): number => {
    if (props.plans && props.plans[plan]) {
        return props.plans[plan].price;
    }
    return defaultPrices[plan] || 0;
};

const getPlanMonthlyPrice = (plan: string): number => {
    if (props.plans && props.plans[plan]) {
        return props.plans[plan].monthly_price;
    }
    return defaultMonthlyPrices[plan] || 0;
};

const getPlanPriceId = (plan: string): string => {
    if (props.plans && props.plans[plan]) {
        return props.plans[plan].price_id;
    }
    // Fallback para IDs padrão
    const defaultIds: Record<string, string> = {
        monthly: 'price_1StCFJGjTEIZborGaTlExEMF',
        quarterly: 'price_1StIQJGjTEIZborGfWvfFumD',
        yearly: 'price_1StIR1GjTEIZborGzAc3WC2U',
    };
    return defaultIds[plan] || '';
};

const getDiscount = (plan: string): string | null => {
    const monthlyPrice = getPlanMonthlyPrice('monthly');
    const planMonthlyPrice = getPlanMonthlyPrice(plan);

    if (monthlyPrice > 0 && planMonthlyPrice > 0 && planMonthlyPrice < monthlyPrice) {
        const discount = Math.round((1 - planMonthlyPrice / monthlyPrice) * 100);
        return `-${discount}%`;
    }
    return null;
};

const selectPlan = (plan: 'monthly' | 'quarterly' | 'yearly') => {
    selectedPlan.value = plan;
    if (appliedCoupon.value) {
        recalculateDiscount();
    }
};

const recalculateDiscount = () => {
    if (!appliedCoupon.value) return;
    const price = getPlanPrice(selectedPlan.value);
    if (appliedCoupon.value.percent_off) {
        appliedCoupon.value.discount_amount = Math.round(price * (appliedCoupon.value.percent_off / 100));
    }
};

const flash = computed(() => {
    const pageProps = page.props as any;
    return {
        success: pageProps?.flash?.success || null,
        error: pageProps?.flash?.error || null,
        info: pageProps?.flash?.info || null,
    };
});

const stripe = ref<any>(null);
const elements = ref<any>(null);
const cardNumberElement = ref<any>(null);
const cardExpiryElement = ref<any>(null);
const cardCvcElement = ref<any>(null);
const cardError = ref<string | null>(null);
const isProcessing = ref(false);
const cardBrand = ref<string | null>(null);

const getCardBrandIcon = (brand: string): string => {
    const brandIcons: Record<string, string> = {
        visa: 'https://js.stripe.com/v3/fingerprinted/img/visa-729c05c240c4bdb47b03ac81d9945bfe.svg',
        mastercard: 'https://js.stripe.com/v3/fingerprinted/img/mastercard-4d8844094130711885b5e41b28c9848f.svg',
        amex: 'https://js.stripe.com/v3/fingerprinted/img/amex-a49b82f46c5cd6a96a6e418a6ca1717c.svg',
        discover: 'https://js.stripe.com/v3/fingerprinted/img/discover-ac52cd46f89fa40a29a0bfb954e33173.svg',
        diners: 'https://js.stripe.com/v3/fingerprinted/img/diners-fbcbd3360f8e3f629cdaa80e93abdb8b.svg',
        jcb: 'https://js.stripe.com/v3/fingerprinted/img/jcb-271fd06e6e7a2c52692f000e6b0e7ce1.svg',
        unionpay: 'https://js.stripe.com/v3/fingerprinted/img/unionpay-8a10aefc7295216c338ba4e1224627a1.svg',
        elo: 'https://js.stripe.com/v3/fingerprinted/img/elo-3c5c28c4be8e3f1f79c8a1f5b31ffcd7.svg',
        hipercard: 'https://js.stripe.com/v3/fingerprinted/img/hipercard-d42d8c59d889e5f7c6378e3e9b0c8b99.svg',
    };
    return brandIcons[brand] || '';
};

// Cupom
const couponCode = ref('');
const couponLoading = ref(false);
const couponError = ref<string | null>(null);
const appliedCoupon = ref<AppliedCoupon | null>(null);

const discountAmount = computed(() => {
    if (!appliedCoupon.value) return 0;
    return appliedCoupon.value.discount_amount;
});

const finalTotal = computed(() => {
    return Math.max(0, getPlanPrice(selectedPlan.value) - discountAmount.value);
});

const formatCurrency = (valueInCents: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valueInCents / 100);
};

const applyCoupon = async () => {
    if (!couponCode.value.trim()) return;

    couponLoading.value = true;
    couponError.value = null;

    try {
        const response = await axios.post(route('subscription.validate-coupon'), {
            coupon_code: couponCode.value.trim(),
            price: getPlanPrice(selectedPlan.value),
        });

        if (response.data.valid) {
            appliedCoupon.value = {
                code: couponCode.value.trim().toUpperCase(),
                description: response.data.description,
                discount_amount: response.data.discount_amount,
                percent_off: response.data.percent_off,
                amount_off: response.data.amount_off,
                promotion_code_id: response.data.promotion_code_id,
            };
        } else {
            couponError.value = response.data.message || 'Cupom inválido';
        }
    } catch (error: any) {
        couponError.value = error.response?.data?.message || 'Erro ao validar cupom';
    } finally {
        couponLoading.value = false;
    }
};

const removeCoupon = () => {
    appliedCoupon.value = null;
    couponCode.value = '';
    couponError.value = null;
};

onMounted(() => {
    if (!props.hasActiveSubscription) {
        if (typeof window.Stripe !== 'undefined') {
            loadStripe();
        } else {
            const checkStripe = setInterval(() => {
                if (typeof window.Stripe !== 'undefined') {
                    clearInterval(checkStripe);
                    loadStripe();
                }
            }, 100);

            setTimeout(() => {
                clearInterval(checkStripe);
                if (typeof window.Stripe === 'undefined') {
                    cardError.value = 'Erro ao carregar Stripe. Recarregue a página.';
                }
            }, 5000);
        }
    }
});

const loadStripe = async () => {
    try {
        const stripeKey = import.meta.env.VITE_STRIPE_KEY;

        if (!stripeKey) {
            throw new Error('Chave do Stripe não configurada.');
        }

        if (typeof window.Stripe === 'undefined') {
            throw new Error('Stripe.js não carregado.');
        }

        stripe.value = window.Stripe(stripeKey);

        const isDarkMode = document.documentElement.classList.contains('dark');

        const elementStyles = {
            base: {
                fontSize: '16px',
                color: isDarkMode ? '#e2e8f0' : '#334155',
                fontFamily: 'system-ui, -apple-system, sans-serif',
                '::placeholder': {
                    color: isDarkMode ? '#64748b' : '#94a3b8',
                },
            },
            invalid: {
                color: '#ef4444',
            },
        };

        elements.value = stripe.value.elements({
            appearance: {
                theme: isDarkMode ? 'night' : 'stripe',
            }
        });

        cardNumberElement.value = elements.value.create('cardNumber', {
            style: elementStyles,
            placeholder: '1234 5678 9012 3456',
        });

        cardExpiryElement.value = elements.value.create('cardExpiry', {
            style: elementStyles,
            placeholder: 'MM/AA',
        });

        cardCvcElement.value = elements.value.create('cardCvc', {
            style: elementStyles,
            placeholder: '123',
        });

        cardNumberElement.value.mount('#card-number-element');
        cardExpiryElement.value.mount('#card-expiry-element');
        cardCvcElement.value.mount('#card-cvc-element');

        const handleChange = (event: any) => {
            cardError.value = event.error ? event.error.message : null;
        };

        cardNumberElement.value.on('change', (event: any) => {
            handleChange(event);
            if (event.brand && event.brand !== 'unknown') {
                cardBrand.value = event.brand;
            } else {
                cardBrand.value = null;
            }
        });
        cardExpiryElement.value.on('change', handleChange);
        cardCvcElement.value.on('change', handleChange);

    } catch {
        cardError.value = 'Erro ao carregar formulário. Recarregue a página.';
    }
};

const subscribe = async () => {
    if (isProcessing.value) return;

    isProcessing.value = true;
    cardError.value = null;

    try {
        if (!stripe.value || !cardNumberElement.value) {
            cardError.value = 'Formulário não carregado. Recarregue a página.';
            isProcessing.value = false;
            return;
        }

        const { paymentMethod, error } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: cardNumberElement.value,
        });

        if (error) {
            cardError.value = error.message;
            isProcessing.value = false;
            return;
        }

        if (!paymentMethod || !paymentMethod.id) {
            cardError.value = 'Erro ao processar cartão. Tente novamente.';
            isProcessing.value = false;
            return;
        }

        form.payment_method = paymentMethod.id;
        form.promotion_code_id = appliedCoupon.value?.promotion_code_id || '';
        form.price_id = getPlanPriceId(selectedPlan.value);

        form.post(route('subscription.subscribe'), {
            preserveScroll: true,
            onSuccess: () => {
                isProcessing.value = false;
            },
            onError: (errors) => {
                isProcessing.value = false;
                if (errors.message) {
                    cardError.value = errors.message;
                } else {
                    const errorKeys = Object.keys(errors);
                    if (errorKeys.length > 0) {
                        cardError.value = errors[errorKeys[0]];
                    } else {
                        cardError.value = 'Erro ao processar assinatura.';
                    }
                }
            },
            onFinish: () => {
                isProcessing.value = false;
            }
        });
    } catch (error: any) {
        isProcessing.value = false;
        cardError.value = error.message || 'Erro ao processar pagamento.';
    }
};

const form = useForm({
    payment_method: '',
    promotion_code_id: '',
    price_id: '',
});

const cancelSubscription = () => {
    if (props.subscriptionCancelled) return;

    if (confirm('Cancelar assinatura?\n\nVocê manterá acesso até o fim do período pago.')) {
        const cancelForm = useForm({});
        cancelForm.post(route('subscription.cancel'), {
            preserveScroll: true,
            onError: () => {
                alert('Erro ao cancelar. Tente novamente.');
            }
        });
    }
};

const resumeSubscription = () => {
    const resumeForm = useForm({});
    resumeForm.post(route('subscription.resume'), {
        preserveScroll: true,
        onError: () => {
            alert('Erro ao reativar. Tente novamente.');
        }
    });
};
</script>
