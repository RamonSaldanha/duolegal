<template>
    <Dialog
        :open="showAdDialog"
        @update:open="handleDialogUpdate"
    >
        <DialogContent class="sm:max-w-[425px] min-h-[400px] flex flex-col bg-background">
            <DialogHeader>
                <DialogTitle>Assistindo anúncio...</DialogTitle>
                <DialogDescription>
                    Aguarde a contagem regressiva para ganhar uma vida.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 flex items-center justify-center flex-col">
                <!-- Container para o anúncio do AdSense -->
                <div ref="adContainer" class="w-full mb-4 overflow-hidden min-h-[200px] flex items-center justify-center">
                    <!-- O anúncio será inserido aqui dinamicamente -->
                    <ins class="adsbygoogle"
                         style="display:block; width:100%; height:200px"
                         data-ad-client="ca-pub-2585274176504938"
                         data-ad-slot="5997106715"
                         data-ad-format="auto"
                         data-full-width-responsive="true"></ins>
                </div>

                <div class="text-center">
                    <div v-if="countdown > 0" class="text-xl font-bold mb-4">
                        {{ countdown }}s
                    </div>
                    <Button
                        v-if="countdown <= 0"
                        @click="handleAdComplete"
                        class="bg-primary hover:bg-primary/90 text-primary-foreground"
                    >
                        Pular anúncio e ganhar vida
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

// Props
const props = defineProps<{
    rewardRoute: string;
    redirectRoute: string;
}>()

// Emits
const emit = defineEmits<{
    (e: 'adStarted'): void;
    (e: 'adCompleted'): void;
    (e: 'adClosed'): void;
}>()

// Component state
const showAdDialog = ref(false)
const countdown = ref(15)
let countdownInterval: ReturnType<typeof setInterval> | null = null
const adContainer = ref<HTMLElement | null>(null)
let adLoaded = false

// Carrega o script do AdSense
const loadAdSenseScript = () => {
    // Verifica se o script já foi carregado
    if (document.querySelector('script[src*="adsbygoogle.js"]')) {
        return Promise.resolve();
    }

    return new Promise<void>((resolve) => {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2585274176504938';
        script.crossOrigin = 'anonymous';
        script.onload = () => resolve();
        document.head.appendChild(script);
    });
};

// Inicializa o anúncio
const initializeAd = async () => {
    if (!adLoaded) {
        try {
            await nextTick();
            // Verifica se o objeto adsbygoogle está disponível
            if (window.adsbygoogle) {
                (window.adsbygoogle = window.adsbygoogle || []).push({});
                adLoaded = true;
                console.log('AdSense ad initialized');
            } else {
                console.warn('AdSense not available, continuing without ads');
                // Marca como carregado mesmo sem o AdSense para não bloquear a funcionalidade
                adLoaded = true;
            }
        } catch (error) {
            console.error('Error initializing AdSense ad:', error);
            // Marca como carregado mesmo com erro para não bloquear a funcionalidade
            adLoaded = true;
        }
    }
};

// Inicia a experiência de anúncio
const startAdExperience = async () => {
    showAdDialog.value = true
    startCountdown()
    emit('adStarted')

    // Carrega o anúncio após o diálogo ser aberto
    await loadAdSenseScript();
    await nextTick();
    initializeAd();
}

// Inicia o contador regressivo
const startCountdown = () => {
    countdown.value = 30

    if (countdownInterval) {
        clearInterval(countdownInterval)
    }

    countdownInterval = setInterval(() => {
        countdown.value--

        if (countdown.value <= 0 && countdownInterval) {
            clearInterval(countdownInterval)
        }
    }, 1000)
}

// Trata a abertura/fechamento do diálogo
const handleDialogUpdate = (isOpen: boolean) => {
    if (!isOpen && countdown.value <= 0) {
        // Só permitir fechar se a contagem terminou
        if (countdownInterval) {
            clearInterval(countdownInterval)
            countdownInterval = null
        }

        showAdDialog.value = false
        countdown.value = 15
        adLoaded = false // Reseta o estado do anúncio para carregar um novo na próxima vez
        emit('adClosed')
    } else if (!isOpen && countdown.value > 0) {
        // Se tentar fechar antes da contagem acabar, mantenha aberto
        return true
    }
}

// Trata a conclusão do anúncio
const handleAdComplete = async () => {
    try {
        const response = await axios.post(props.rewardRoute)
        if (response.data.success) {
            emit('adCompleted')
            window.location.href = props.redirectRoute
        }
    } catch (error) {
        console.error('Error rewarding life:', error)
    } finally {
        showAdDialog.value = false
    }
}

// Carrega o script do AdSense quando o componente for montado
onMounted(() => {
    // Pré-carrega o script do AdSense para estar pronto quando o usuário abrir o diálogo
    loadAdSenseScript().catch(error => {
        console.error('Failed to load AdSense script:', error)
    })
})

// Limpa o intervalo quando o componente for desmontado
onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval)
        countdownInterval = null
    }
})

// Declaração para TypeScript reconhecer adsbygoogle
declare global {
    interface Window {
        adsbygoogle: any[];
    }
}

// Expor métodos para o componente pai
defineExpose({
    startAdExperience
})
</script>
