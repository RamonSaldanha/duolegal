<template>
    <Head title="Assinatura" />

    <AppLayout>
        <div class="container py-8">
            <div class="max-w-3xl mx-auto mt-8">
                <!-- <h1 class="text-3xl font-bold mb-6 dark:text-white">Assinatura Premium</h1> -->

                <div v-if="hasActiveSubscription" class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-6 mb-8">
                    <div class="flex items-start">
                        <CheckCircle class="text-green-500 dark:text-green-400 w-6 h-6 mr-3 mt-1" />
                        <div>
                            <h2 class="text-xl font-semibold text-green-700 dark:text-green-300">Assinatura Ativa</h2>
                            <p class="text-green-600 dark:text-green-400 mt-1">
                                Você tem acesso a vidas infinitas! Aproveite para estudar sem limitações.
                            </p>

                            <!-- Mostrar data de término se a assinatura foi cancelada -->
                            <div v-if="props.subscriptionCancelled" class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-md">
                                <p class="text-amber-700 dark:text-amber-300 font-medium flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Sua assinatura foi cancelada
                                </p>
                                <p v-if="props.subscriptionEndsAt" class="text-amber-600 dark:text-amber-400 mt-1 ml-7">
                                    Você continuará tendo acesso Premium até <span class="font-bold">{{ props.subscriptionEndsAt }}</span>
                                </p>
                                <p v-else class="text-amber-600 dark:text-amber-400 mt-1 ml-7">
                                    Você continuará tendo acesso Premium até o final do período já pago.
                                </p>
                            </div>

                            <!-- Debug info (apenas para administradores) -->
                            <div v-if="props.subscriptionCancelled && isAdmin" class="mt-2 text-xs text-dark-500 dark:text-dark-400 border-l-2 border-dark-300 dark:border-dark-600 pl-2">
                                <div class="font-semibold">[Debug]</div>
                                Status: {{ props.subscriptionCancelled ? 'Cancelada' : 'Ativa' }} |
                                Data de término: {{ props.subscriptionEndsAt || 'Não definida' }}
                            </div>

                            <div class="mt-4">
                                <!-- Botão de cancelar assinatura (desativado se já estiver cancelada) -->
                                <Button
                                    type="button"
                                    variant="outline"
                                    :class="{
                                        'text-red-500 dark:text-red-400 border-red-300 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/30': !props.subscriptionCancelled,
                                        'text-dark-400 dark:text-dark-500 border-dark-300 dark:border-dark-700 cursor-not-allowed': props.subscriptionCancelled
                                    }"
                                    @click="cancelSubscription"
                                    :disabled="props.subscriptionCancelled"
                                >
                                    {{ props.subscriptionCancelled ? 'Assinatura já cancelada' : 'Cancelar assinatura' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class=" rounded-lg shadow-sm p-6 mb-8 container mx-auto max-w-4xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-9">
                        <div class="flex-1">
                            <div class="relative inline-block">
                                <div class="absolute -top-[45px] md:-top-[45px] md:-right-10 bg-card dark:bg-gray-800 rounded-lg px-3 py-1 shadow-md text-sm font-medium speech-bubble text-foreground">
                                    Não fique mais travado ✅
                                </div>
                                <div class="absolute -top-[10px] -left-[0px] md:-top-[10px] md:-left-[95px] bg-card dark:bg-gray-800 rounded-lg px-3 py-1 shadow-md text-sm font-medium speech-bubble text-foreground">
                                    Com o Premium, você estuda sem limites! ✅
                                </div>
                                <img src="/img/superararaazul.png" class="w-56 mt-6 md:mt-1" />
                            </div>
                        </div>

                        <div class="flex-1">
                            <div class="mb-4">
                                <h2 class="text-2xl font-bold dark:text-white">Plano premium</h2>
                                <div class="text-lg font-bold dark:text-white">R$ 9,90/mês</div>
                            </div>
                            <div v-if="cardError" class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-md mb-4">
                                <div class="flex flex-col">
                                    <p>{{ cardError }}</p>
                                </div>
                            </div>

                            <div id="card-element" class="border dark:border-dark-700 rounded-md p-3 mb-4 dark:bg-dark-700"></div>

                            <Button
                                type="button"
                                @click="subscribe"
                                :disabled="isProcessing"
                                class="w-full"
                            >
                                <span v-if="isProcessing">
                                    <Loader2 class="mr-2 h-4 w-4 animate-spin" />
                                    Processando...
                                </span>
                                <span v-else>
                                    Assinar agora
                                </span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { CheckCircle, Loader2 } from 'lucide-vue-next';
import { onMounted, ref, computed } from 'vue';

// Declaração do tipo Stripe para o TypeScript
declare global {
    interface Window {
        Stripe?: any;
    }
}

interface Props {
    hasActiveSubscription: boolean;
    intent: {
        client_secret: string;
    };
    subscriptionEndsAt: string | null;
    subscriptionCancelled: boolean;
}

const props = defineProps<Props>();
const page = usePage();

// Verifica se o usuário é administrador
// Acessa as propriedades de forma segura com verificações
const isAdmin = computed(() => {
    try {
        // Acessa as propriedades de forma segura
        const pageProps = page.props as any;
        if (pageProps && pageProps.auth && pageProps.auth.user) {
            return !!pageProps.auth.user.is_admin;
        }
        return false;
    } catch (error) {
        console.error('Erro ao acessar propriedades de auth:', error);
        return false;
    }
});

const stripe = ref<any>(null);
const elements = ref<any>(null);
const cardElement = ref<any>(null);
const cardError = ref<string | null>(null);
const isProcessing = ref(false);

onMounted(() => {
    // Carrega o Stripe.js
    if (!props.hasActiveSubscription) {
        // Verifica se o Stripe já está disponível
        if (typeof window.Stripe !== 'undefined') {
            console.log('Stripe já está disponível, carregando...');
            loadStripe();
        } else {
            console.log('Stripe ainda não está disponível, aguardando carregamento...');
            // Aguarda o carregamento do Stripe
            const checkStripe = setInterval(() => {
                if (typeof window.Stripe !== 'undefined') {
                    console.log('Stripe carregado, inicializando...');
                    clearInterval(checkStripe);
                    loadStripe();
                }
            }, 100);

            // Timeout após 5 segundos
            setTimeout(() => {
                clearInterval(checkStripe);
                if (typeof window.Stripe === 'undefined') {
                    console.error('Timeout ao aguardar carregamento do Stripe');
                    cardError.value = 'Timeout ao carregar o Stripe. Por favor, recarregue a página.';
                }
            }, 5000);
        }
    }
});

const loadStripe = async () => {
    try {
        console.log('Carregando Stripe.js...');
        // Obter a chave do Stripe diretamente da variável de ambiente ou usar a chave diretamente
        const stripeKey = import.meta.env.VITE_STRIPE_KEY || 'pk_test_51MbXdpJhFrAxy23koT5CfvObNzBhbm8MzV8Fdm3iFlKkY5spSgCS8M3L2LgKLN9CD2B562DQ1Ubu5iylvzC22Zvf007tmLk05K';
        console.log('Chave do Stripe:', stripeKey.substring(0, 10) + '...');

        // @ts-ignore - Stripe é carregado globalmente
        if (typeof Stripe === 'undefined') {
            throw new Error('Stripe.js não foi carregado. Verifique se o script está sendo carregado corretamente.');
        }

        // Inicializa o Stripe com a chave pública
        stripe.value = Stripe(stripeKey);
        console.log('Stripe.js carregado com sucesso');

        // Verificar se está no modo dark
        const isDarkMode = document.documentElement.classList.contains('dark');
        console.log('Modo dark detectado:', isDarkMode);

        // Cria os elementos do Stripe com estilo customizado para o modo dark
        elements.value = stripe.value.elements({
            appearance: {
                theme: isDarkMode ? 'night' : 'stripe',
                variables: {
                    colorText: isDarkMode ? '#e2e8f0' : '#334155', // slate-200 no dark mode, slate-700 no light mode
                },
                rules: {
                    '.Input': {
                        color: isDarkMode ? '#e2e8f0' : '#334155', // slate-200 no dark mode, slate-700 no light mode
                    },
                    '.Label': {
                        color: isDarkMode ? '#e2e8f0' : '#334155', // slate-200 no dark mode, slate-700 no light mode
                    },
                    '.Error': {
                        color: '#ef4444', // red-500
                    }
                }
            }
        });

        // Cria o elemento de cartão com configurações mínimas
        cardElement.value = elements.value.create('card');

        // Monta o elemento no DOM
        const cardElementContainer = document.getElementById('card-element');
        if (!cardElementContainer) {
            throw new Error('Elemento #card-element não encontrado no DOM');
        }

        // Aplicar estilos diretamente ao container para garantir consistência visual


        cardElement.value.mount('#card-element');
        console.log('Elemento de cartão montado com sucesso');

        // Adiciona listener para erros
        cardElement.value.on('change', (event: any) => {
            console.log('Evento de mudança do cartão:', event);
            cardError.value = event.error ? event.error.message : null;
        });

        // Adicionar um observer para mudanças no tema (dark/light)
        const darkModeObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class' && document.documentElement.classList.contains('dark') !== isDarkMode) {
                    // O modo dark mudou, recarregar o elemento Stripe
                    console.log('Tema mudou, recarregando elemento do Stripe...');
                    setTimeout(() => {
                        window.location.reload();
                    }, 100);
                }
            });
        });
        
        darkModeObserver.observe(document.documentElement, { attributes: true });
        
    } catch (error) {
        console.error('Erro ao carregar Stripe:', error);
        cardError.value = 'Erro ao carregar o formulário de pagamento: ' + (error instanceof Error ? error.message : 'Erro desconhecido');

        // Adiciona um botão para recarregar a página
        setTimeout(() => {
            const cardElementContainer = document.getElementById('card-element');
            if (cardElementContainer) {
                cardElementContainer.innerHTML = `
                    <div class="p-4 text-center">
                        <p class="text-red-500 dark:text-red-400 mb-4">${cardError.value}</p>
                        <button
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                            onclick="window.location.reload()"
                        >
                            Recarregar Página
                        </button>
                    </div>
                `;
            }
        }, 100);
    }
};

const subscribe = async () => {
    if (isProcessing.value) return;

    isProcessing.value = true;
    cardError.value = null;

    try {
        // Verifica se o Stripe e o elemento do cartão foram inicializados corretamente
        if (!stripe.value || !cardElement.value) {
            cardError.value = 'Erro ao carregar o formulário de pagamento. Por favor, recarregue a página.';
            isProcessing.value = false;
            return;
        }

        console.log('Criando payment method...'); // Log para depuração

        // Cria um payment method com o cartão
        const { paymentMethod, error } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: cardElement.value,
        });

        // Se houver um erro, exibe a mensagem e retorna
        if (error) {
            console.error('Erro ao criar payment method:', error);
            cardError.value = error.message;
            isProcessing.value = false;
            return;
        }

        // Se não houver payment method, exibe um erro genérico
        if (!paymentMethod || !paymentMethod.id) {
            console.error('Payment method não foi criado corretamente');
            cardError.value = 'Ocorreu um erro ao processar o cartão. Por favor, tente novamente.';
            isProcessing.value = false;
            return;
        }

        // Define o payment_method no formulário
        form.payment_method = paymentMethod.id;

        console.log('Payment Method ID:', paymentMethod.id); // Log para depuração

        // Envia o payment method para o servidor
        console.log('Enviando payment method para o servidor:', form.payment_method);

        try {
            await form.post(route('subscription.subscribe'), {
                preserveScroll: true,
                onSuccess: (response) => {
                    console.log('Assinatura realizada com sucesso:', response);
                    isProcessing.value = false;
                    // Redireciona para a página de sucesso ou atualiza a página
                    window.location.reload();
                },
                onError: (errors) => {
                    isProcessing.value = false;
                    console.error('Erro na assinatura:', errors); // Log para depuração

                    if (errors.message) {
                        cardError.value = errors.message;
                    } else if (errors.payment_method) {
                        cardError.value = errors.payment_method;
                    } else {
                        cardError.value = 'Ocorreu um erro ao processar sua assinatura. Por favor, tente novamente.';
                    }
                }
            });
        } catch (postError) {
            console.error('Erro ao enviar formulário:', postError);
            isProcessing.value = false;
            cardError.value = 'Erro ao enviar dados para o servidor. Por favor, tente novamente.';
        }
    } catch (error: any) {
        console.error('Erro inesperado:', error);
        isProcessing.value = false;
        cardError.value = error.message || 'Ocorreu um erro ao processar o pagamento.';
    }
};

const form = useForm({
    payment_method: '',
});

const cancelSubscription = () => {
    // Verifica se a assinatura já está cancelada
    if (props.subscriptionCancelled) {
        console.log('Assinatura já está cancelada');
        return;
    }

    console.log('Iniciando cancelamento da assinatura...');

    if (confirm('Tem certeza que deseja cancelar sua assinatura?\n\nVocê continuará tendo acesso até o final do período já pago.')) {
        console.log('Usuário confirmou o cancelamento');

        try {
            // Criar um novo formulário para o cancelamento
            const cancelForm = useForm({});
            console.log('Formulário criado:', cancelForm);

            // Verificar se a rota existe
            const cancelRoute = route('subscription.cancel');
            console.log('Rota de cancelamento:', cancelRoute);

            // Enviar a solicitação de cancelamento
            console.log('Enviando solicitação de cancelamento...');
            cancelForm.post(cancelRoute, {
                preserveScroll: true,
                onSuccess: (response) => {
                    console.log('Cancelamento bem-sucedido:', response);
                    // Recarregar a página após o cancelamento bem-sucedido
                    window.location.reload();
                },
                onError: (errors) => {
                    console.error('Erro ao cancelar assinatura:', errors);
                    alert('Ocorreu um erro ao cancelar sua assinatura. Por favor, tente novamente.');
                }
            });
        } catch (error) {
            console.error('Erro inesperado ao cancelar assinatura:', error);
            alert('Ocorreu um erro inesperado ao cancelar sua assinatura. Por favor, tente novamente.');
        }
    } else {
        console.log('Usuário cancelou a operação');
    }
};
</script>

<!-- Adicione estilos específicos para o modo escuro do Stripe -->
<style>
/* Estilo global para ajudar a personalizar o iframe do Stripe no modo dark */
:root.dark .StripeElement iframe {
  filter: invert(0.85) hue-rotate(180deg) !important;
}

/* Evitar que os cartões mostrados no iframe fiquem invertidos no modo dark */
:root.dark .StripeElement [data-stripe-type="card-preview"] iframe {
  filter: invert(0) hue-rotate(0deg) !important;
}
</style>
