<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { X } from 'lucide-vue-next';
import AdSenseComponent from '@/components/AdSenseComponent.vue';

interface Props {
    /**
     * Se o modal está visível
     */
    show: boolean;

    /**
     * Tempo em segundos antes de permitir fechar o modal
     * Default: 5 segundos
     */
    closeDelay?: number;

    /**
     * ID do cliente AdSense (opcional, usa o padrão se não fornecido)
     */
    clientId?: string;

    /**
     * ID do slot AdSense (opcional, usa o padrão se não fornecido)
     */
    slotId?: string;
}

const props = withDefaults(defineProps<Props>(), {
    closeDelay: 5,
    clientId: 'ca-pub-2585274176504938',
    slotId: '3465272448',
});

const emit = defineEmits<{
    close: [];
}>();

// Contador regressivo até poder fechar
const secondsRemaining = ref(props.closeDelay);

// Se pode fechar agora
const canClose = computed(() => secondsRemaining.value <= 0);

// Interval para countdown
let countdownInterval: number | null = null;

/**
 * Inicia o countdown quando o modal é mostrado
 */
const startCountdown = () => {
    secondsRemaining.value = props.closeDelay;

    countdownInterval = window.setInterval(() => {
        if (secondsRemaining.value > 0) {
            secondsRemaining.value--;
        } else {
            stopCountdown();
        }
    }, 1000);
};

/**
 * Para o countdown
 */
const stopCountdown = () => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
};

/**
 * Tenta fechar o modal
 */
const handleClose = () => {
    if (canClose.value) {
        emit('close');
    }
};

/**
 * Fecha ao pressionar ESC
 */
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && canClose.value) {
        handleClose();
    }
};

// Watch para quando o modal é aberto
onMounted(() => {
    if (props.show) {
        startCountdown();
    }

    // Listener para ESC
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    stopCountdown();
    window.removeEventListener('keydown', handleKeydown);
});

// Reinicia countdown quando modal é reaberto
const handleModalOpened = () => {
    if (props.show) {
        startCountdown();
    }
};

// Observa mudanças no prop show
import { watch } from 'vue';
watch(
    () => props.show,
    (newValue) => {
        if (newValue) {
            handleModalOpened();
        } else {
            stopCountdown();
        }
    },
);
</script>

<template>
    <!-- Modal Backdrop -->
    <Transition name="fade">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex h-screen min-h-screen flex-col bg-black/95 backdrop-blur-sm"
            @click.self="handleClose"
        >
            <!-- Modal Container Full Screen -->
            <div
                class="relative flex h-screen min-h-screen w-full flex-col bg-white dark:bg-gray-900"
                @click.stop
            >
                <!-- Header com botão de fechar -->
                <div
                    class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900"
                        >
                            <span class="text-xl">📢</span>
                        </div>
                        <div>
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Anúncio
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Apoie o Memorize Direito
                            </p>
                        </div>
                    </div>

                    <!-- Botão de fechar com countdown -->
                    <button
                        type="button"
                        :disabled="!canClose"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all"
                        :class="
                            canClose
                                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                : 'cursor-not-allowed bg-gray-50 text-gray-400 dark:bg-gray-800/50 dark:text-gray-600'
                        "
                        @click="handleClose"
                    >
                        <X :size="16" />
                        <span v-if="!canClose">Fechar ({{ secondsRemaining }}s)</span>
                        <span v-else>Fechar</span>
                    </button>
                </div>

                <!-- Conteúdo: AdSense - Ocupa todo o espaço disponível -->
                <div
                    class="flex min-h-0 flex-1 items-center justify-center overflow-auto p-6"
                >
                    <div class="w-full max-w-4xl">
                        <AdSenseComponent
                            :client-id="clientId"
                            :slot-id="slotId"
                            format="auto"
                            full-width-responsive="true"
                            :min-width="300"
                            :min-height="250"
                        />
                    </div>
                </div>

                <!-- Footer com mensagem -->
                <div
                    class="border-t border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-800/50"
                >
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        💡 Os anúncios nos ajudam a manter o Memorize Direito gratuito
                        para todos
                    </p>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Animações de fade */
.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.3s ease,
        backdrop-filter 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    backdrop-filter: blur(0px);
}

.fade-enter-to,
.fade-leave-from {
    opacity: 1;
    backdrop-filter: blur(8px);
}

/* Previne scroll do body quando modal está aberto */
body:has(.fixed.z-50) {
    overflow: hidden;
}

/* Garante que o modal sempre ocupe 100% da altura da viewport */
.fixed.inset-0 {
    height: 100vh !important;
    min-height: 100vh !important;
    max-height: 100vh !important;
}
</style>
