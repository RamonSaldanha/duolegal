<template>
    <Head title="Assinatura" />

    <!-- Quem ainda não assina: o mesmo desenho do convite Premium do fim de fase. A pessoa
         sendo nomeada, a frase com marca-texto, as vantagens em etiquetas, os planos, o cartão e
         o botão azul. Uma coluna só, direta, do celular ao computador. -->
    <template v-if="!hasActiveSubscription">
        <div class="min-h-dvh bg-background">
            <div class="mx-auto w-full max-w-md px-4 pb-10 pt-3">
                <a href="/" class="close -ml-2" aria-label="Fechar"><X class="h-6 w-6" /></a>

                <div class="flex flex-col items-center text-center">
                    <div class="hero-gif">
                        <img src="/img/premium-nomeado.gif" alt="" class="w-full" />
                    </div>
                    <h1 class="text-[1.625rem] font-bold leading-tight text-foreground">Seja <HighlightMark :delay="250">Plus</HighlightMark></h1>
                    <PerkList class="mt-4" :delay="300" />
                </div>

                <!-- Oferta de boas-vindas do onboarding -->
                <Notice v-if="offerAvailable" tone="offer" :title="`${welcomeOffer?.percent_off}% off no plano anual`" class="mt-6">
                    <template v-if="welcomeActive"
                        >Já aplicado. Termina em <span class="font-bold tabular-nums">{{ offerCountdown }}</span
                        >.</template
                    >
                    <template v-else
                        >Escolha o anual para usar. Termina em <span class="font-bold tabular-nums">{{ offerCountdown }}</span
                        >.</template
                    >
                </Notice>

                <!-- Planos: o escolhido fica azul; o desconto de cada um, suspenso na borda -->
                <div class="mt-6 grid grid-cols-3 gap-2" role="radiogroup" aria-label="Plano">
                    <button
                        v-for="plan in PLANS"
                        :key="plan"
                        type="button"
                        role="radio"
                        :aria-checked="selectedPlan === plan"
                        class="mz-choice plan"
                        :class="{ 'is-selected': selectedPlan === plan }"
                        @click="selectPlan(plan)"
                    >
                        <span v-if="getDiscount(plan)" class="tag">{{ getDiscount(plan) }}</span>
                        <span class="text-sm font-bold">{{ planNames[plan] }}</span>
                        <span v-if="plan === 'yearly' && offerAvailable" class="text-xs text-muted-foreground line-through">{{
                            formatCurrency(getPlanPrice('yearly'))
                        }}</span>
                        <span class="text-lg font-bold tabular-nums leading-tight">{{ formatCurrency(shownPrice(plan)) }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            plan === 'monthly' ? 'por mês' : `${formatCurrency(shownMonthly(plan))}/mês`
                        }}</span>
                    </button>
                </div>

                <!-- Cartão (campos do Stripe) -->
                <div class="mt-6 flex flex-col gap-3">
                    <Notice v-if="flash.error" tone="error">{{ flash.error }}</Notice>
                    <Notice v-if="cardError" tone="error">{{ cardError }}</Notice>

                    <div>
                        <p class="mb-1.5 text-sm font-bold text-foreground">Número do cartão</p>
                        <div class="relative">
                            <div id="card-number-element" class="field pr-12"></div>
                            <img
                                v-if="cardBrand"
                                :src="getCardBrandIcon(cardBrand)"
                                :alt="cardBrand"
                                class="absolute right-3 top-1/2 h-6 w-auto -translate-y-1/2"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="mb-1.5 text-sm font-bold text-foreground">Validade</p>
                            <div id="card-expiry-element" class="field"></div>
                        </div>
                        <div>
                            <p class="mb-1.5 text-sm font-bold text-foreground">CVV</p>
                            <div id="card-cvc-element" class="field"></div>
                        </div>
                    </div>
                </div>

                <!-- Cupom (a oferta de boas-vindas não soma com cupom): fechado até a pessoa pedir -->
                <div v-if="!welcomeActive" class="mt-3">
                    <button v-if="!couponOpen && !appliedCoupon" type="button" class="mz-btn mz-xs mz-ghost -ml-2" @click="couponOpen = true">
                        Tenho um cupom
                    </button>
                    <template v-else>
                        <div class="flex gap-2">
                            <Input
                                v-model="couponCode"
                                type="text"
                                placeholder="Código do cupom"
                                class="h-10 flex-1"
                                :disabled="couponLoading || !!appliedCoupon"
                            />
                            <button
                                v-if="!appliedCoupon"
                                type="button"
                                class="mz-btn mz-sm mz-outline"
                                :disabled="couponLoading || !couponCode.trim()"
                                @click="applyCoupon"
                            >
                                <Loader2 v-if="couponLoading" class="h-4 w-4 animate-spin" />
                                <span v-else>Aplicar</span>
                            </button>
                            <button v-else type="button" class="mz-btn mz-sm mz-tonal-danger" @click="removeCoupon">Remover</button>
                        </div>
                        <p v-if="couponError" class="mt-1 text-xs text-red-500">{{ couponError }}</p>
                        <p v-if="appliedCoupon" class="mt-1 text-sm font-bold text-ok-ink">Cupom "{{ appliedCoupon.code }}" aplicado.</p>
                    </template>
                </div>

                <!-- Total -->
                <div class="mt-5 flex items-end justify-between gap-3 border-t-2 border-border pt-4">
                    <div class="min-w-0 text-sm text-muted-foreground">
                        {{ planLabels[selectedPlan] }}
                        <span v-if="discountAmount" class="block font-bold text-ok-ink">
                            {{ welcomeActive ? 'Presente de boas-vindas' : 'Desconto' }}: -{{ formatCurrency(discountAmount) }}
                        </span>
                    </div>
                    <span class="text-2xl font-bold tabular-nums text-foreground">{{ formatCurrency(finalTotal) }}</span>
                </div>

                <PremiumButton class="mt-4" :disabled="isProcessing" @click="subscribe">
                    <template v-if="isProcessing"><Loader2 class="h-5 w-5 animate-spin" /> Processando...</template>
                    <template v-else>Assinar por {{ formatCurrency(finalTotal) }}</template>
                </PremiumButton>

                <p class="mt-4 text-center text-xs text-muted-foreground">
                    Pagamento seguro pelo Stripe. Ao assinar, você concorda com os Termos de Uso.
                </p>
            </div>
        </div>
    </template>

    <!-- Quem já assina: o mesmo desenho, com o plano, a próxima cobrança e o cancelamento -->
    <template v-else>
        <div class="min-h-dvh bg-background">
            <div class="mx-auto w-full max-w-md px-4 pb-10 pt-3">
                <a href="/" class="close -ml-2" aria-label="Fechar"><X class="h-6 w-6" /></a>

                <div class="flex flex-col items-center text-center">
                    <div class="hero-gif">
                        <img src="/img/premium-nomeado.gif" alt="" class="w-full" />
                    </div>
                    <h1 class="text-[1.625rem] font-bold leading-tight text-foreground">Você é <HighlightMark :delay="250">Plus!</HighlightMark></h1>
                    <PerkList class="mt-4" :delay="300" />
                </div>

                <div class="mz-choice is-selected plan-active mt-6">
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block text-base font-bold">Memorize Plus</span>
                        <span class="block text-sm text-muted-foreground">{{ props.subscriptionCancelled ? 'Cancelamento agendado' : 'Ativo' }}</span>
                    </span>
                    <span class="text-right leading-tight">
                        <span class="block text-xl font-bold tabular-nums">{{ formatCurrency(props.price || 1199) }}</span>
                        <span class="block text-xs text-muted-foreground">por {{ props.planInterval || 'mês' }}</span>
                    </span>
                </div>

                <template v-if="props.subscriptionCancelled">
                    <Notice tone="warning" title="Cancelamento agendado" class="mt-4">
                        Você ainda tem acesso até <strong>{{ props.subscriptionEndsAt }}</strong
                        >.
                    </Notice>
                    <GameButton variant="main" size="lg" class="mt-4 w-full" @click="resumeSubscription">Reativar assinatura</GameButton>
                </template>
                <template v-else>
                    <div class="mt-4 flex items-center justify-between rounded-2xl border-2 border-border px-4 py-3 text-sm">
                        <span class="text-muted-foreground">Próxima cobrança</span>
                        <span class="font-bold text-foreground">{{ props.nextBillingDate || '—' }}</span>
                    </div>
                    <GameButton variant="tonal-danger" class="mt-4 w-full" @click="cancelSubscription">Cancelar assinatura</GameButton>
                </template>

                <GameButton variant="ghost" size="sm" class="mt-2 w-full" href="/">Voltar para o app</GameButton>
            </div>
        </div>
    </template>
</template>

<script setup lang="ts">
import HighlightMark from '@/components/premium/HighlightMark.vue';
import PerkList from '@/components/premium/PerkList.vue';
import PremiumButton from '@/components/premium/PremiumButton.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { Input } from '@/components/ui/input';
import Notice from '@/components/ui/Notice.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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
    /** Oferta de boas-vindas do onboarding (só no anual), enquanto as 24h valem. */
    welcomeOffer?: { percent_off: number; expires_at: string } | null;
}

const props = defineProps<Props>();
const page = usePage();

// Planos disponíveis. Abre no anual (o que as telas do jogo oferecem e onde vale a oferta de
// boas-vindas), a não ser que o link peça outro (?plan=monthly|quarterly|yearly).
type Plan = 'monthly' | 'quarterly' | 'yearly';
const PLANS: Plan[] = ['monthly', 'quarterly', 'yearly'];
const planNames: Record<Plan, string> = { monthly: 'Mensal', quarterly: 'Trimestral', yearly: 'Anual' };
const askedPlan = new URLSearchParams(window.location.search).get('plan') as Plan | null;
const selectedPlan = ref<Plan>(askedPlan && PLANS.includes(askedPlan) && !props.welcomeOffer ? askedPlan : 'yearly');
const couponOpen = ref(false);

// Oferta de boas-vindas: relógio das 24h e desconto no anual
const now = ref(Date.now());
let offerClock: ReturnType<typeof setInterval> | undefined;
const offerMsLeft = computed(() => (props.welcomeOffer ? new Date(props.welcomeOffer.expires_at).getTime() - now.value : 0));
const offerAvailable = computed(() => offerMsLeft.value > 0);
const welcomeActive = computed(() => offerAvailable.value && selectedPlan.value === 'yearly');
const offerCountdown = computed(() => {
    const ms = Math.max(0, offerMsLeft.value);
    return [Math.floor(ms / 3_600_000), Math.floor((ms % 3_600_000) / 60_000), Math.floor((ms % 60_000) / 1000)]
        .map((n) => String(n).padStart(2, '0'))
        .join(':');
});
const welcomeDiscount = (plan: string) => Math.round(getPlanPrice(plan) * ((props.welcomeOffer?.percent_off ?? 0) / 100));

onMounted(() => {
    if (props.welcomeOffer) offerClock = setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => clearInterval(offerClock));

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

// O preço que o cartão do plano mostra: o anual já com a oferta de boas-vindas, se houver
const shownPrice = (plan: Plan) => getPlanPrice(plan) - (plan === 'yearly' && offerAvailable.value ? welcomeDiscount('yearly') : 0);
const shownMonthly = (plan: Plan) => (plan === 'yearly' && offerAvailable.value ? Math.round(shownPrice('yearly') / 12) : getPlanMonthlyPrice(plan));

const getDiscount = (plan: string): string | null => {
    const monthlyPrice = getPlanMonthlyPrice('monthly');
    const planMonthlyPrice = getPlanMonthlyPrice(plan);

    if (monthlyPrice > 0 && planMonthlyPrice > 0 && planMonthlyPrice < monthlyPrice) {
        const discount = Math.round((1 - planMonthlyPrice / monthlyPrice) * 100);
        return `-${discount}%`;
    }
    return null;
};

const selectPlan = (plan: Plan) => {
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
    if (welcomeActive.value) return welcomeDiscount('yearly');
    if (!appliedCoupon.value) return 0;
    return appliedCoupon.value.discount_amount;
});

const finalTotal = computed(() => {
    return Math.max(0, getPlanPrice(selectedPlan.value) - discountAmount.value);
});

const formatCurrency = (valueInCents: number) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(valueInCents / 100);
};

const applyCoupon = async () => {
    if (!couponCode.value.trim()) return;

    couponLoading.value = true;
    couponError.value = null;

    try {
        const response = await axios.post(route('subscription.validate-coupon'), {
            coupon_code: couponCode.value.trim(),
            price_id: getPlanPriceId(selectedPlan.value),
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
            // Garante o desconto correto sobre o preço do plano selecionado,
            // sem depender do valor calculado no backend.
            recalculateDiscount();
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
            },
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
        form.welcome_offer = welcomeActive.value;
        form.promotion_code_id = welcomeActive.value ? '' : appliedCoupon.value?.promotion_code_id || '';
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
            },
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
    welcome_offer: false,
});

const cancelSubscription = () => {
    if (props.subscriptionCancelled) return;

    if (confirm('Cancelar assinatura?\n\nVocê manterá acesso até o fim do período pago.')) {
        const cancelForm = useForm({});
        cancelForm.post(route('subscription.cancel'), {
            preserveScroll: true,
            onError: () => {
                alert('Erro ao cancelar. Tente novamente.');
            },
        });
    }
};

const resumeSubscription = () => {
    const resumeForm = useForm({});
    resumeForm.post(route('subscription.resume'), {
        preserveScroll: true,
        onError: () => {
            alert('Erro ao reativar. Tente novamente.');
        },
    });
};
</script>

<style scoped>
.close {
    display: inline-grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    color: hsl(var(--muted-foreground));

    @media (hover: hover) {
        &:hover {
            background: hsl(var(--muted));
        }
    }
}
/* A pessoa sendo nomeada, na mesma moldura do convite do fim de fase */
.hero-gif {
    width: min(13rem, 30vh);
    aspect-ratio: 1;
    overflow: hidden;
}
/* Planos: cartões de escolha em coluna; o escolhido em azul, como o anual do convite */
.plan {
    position: relative;
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
    padding: 14px 12px 12px;

    &.is-selected {
        --c: #3b82f6;
        background: color-mix(in srgb, #3b82f6 10%, hsl(var(--card)));
    }
}
.plan-active {
    --c: #3b82f6;
    background: color-mix(in srgb, #3b82f6 10%, hsl(var(--card)));
}
.tag {
    position: absolute;
    top: -10px;
    right: 10px;
    border-radius: 999px;
    padding: 1px 8px;
    background: #3b82f6;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}
/* Campos do Stripe, no contorno da família */
.field {
    height: 48px;
    border-radius: 12px;
    border: 2px solid hsl(var(--border));
    background: hsl(var(--card));
    padding: 13px 14px;
}
</style>
