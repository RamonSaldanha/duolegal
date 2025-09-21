<template>
    <Dialog
        :open="showAdDialog"
        @update:open="handleDialogUpdate"
    >
        <DialogContent
            :hideClose="false"
            :square="true"
            :borderless="true"
            class="w-screen h-screen max-w-none max-h-none m-0 p-6 flex flex-col bg-background"
        >
            <DialogHeader class="relative">
                <DialogTitle>Assistindo anúncio...</DialogTitle>
                <DialogDescription>
                    Aguarde a contagem regressiva para ganhar uma vida.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 flex items-center justify-center flex-col py-8">
                <!-- Container para o anúncio do AdSense -->
                <div class="w-full max-w-4xl mb-8">
                    <div v-if="!adLoaded" class="text-center p-8 bg-gray-100 dark:bg-gray-800 rounded-lg min-h-[300px] flex items-center justify-center">
                        <div>
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                            <p class="text-sm text-muted-foreground">Carregando anúncio...</p>
                        </div>
                    </div>

                    <!-- Novo componente AdSense -->
                    <div
                        v-show="adLoaded"
                        class="w-full"
                    >
                        <AdSenseComponent
                            ref="adSenseRef"
                            :clientId="'ca-pub-2585274176504938'"
                            :slotId="'3465272448'"
                            :minWidth="320"
                            :minHeight="250"
                        />
                    </div>
                </div>

                <div class="text-center">
                    <div v-if="countdown > 0" class="text-3xl font-bold mb-6">
                        {{ countdown }}s
                    </div>
                    <Button
                        v-if="countdown <= 0"
                        @click="handleAdComplete"
                        class="bg-primary hover:bg-primary/90 text-primary-foreground text-lg px-8 py-3"
                    >
                        Ganhar vida agora
                    </Button>

                    <!-- Botão de teste apenas em desenvolvimento -->
                    <div v-if="isLocalhost()" class="mt-4 text-xs text-muted-foreground">
                        <button
                            @click="toggleTestMode"
                            class="underline hover:no-underline"
                        >
                            🔧 Toggle Test Mode
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { ref, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import AdSenseComponent from '@/components/AdSenseComponent.vue'

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
const adLoaded = ref(false)
let countdownInterval: ReturnType<typeof setInterval> | null = null
const adSenseRef = ref<InstanceType<typeof AdSenseComponent> | null>(null)

// Inicializa o anúncio usando o novo componente
const initializeAd = async () => {
    try {
        console.log('🚀 Initializing AdSense with new component...')

        // Aguarda o modal estar totalmente renderizado
        await nextTick()
        await new Promise(resolve => setTimeout(resolve, 1500))

        // Marca como carregado para mostrar o componente
        adLoaded.value = true

        // Aguarda mais um pouco e força inicialização se necessário
        setTimeout(() => {
            if (adSenseRef.value) {
                console.log('🔄 Force initializing AdSense component')
                adSenseRef.value.forceInitialize()
            }
        }, 1000)

    } catch (error) {
        console.error('❌ Error initializing ad:', error)
        adLoaded.value = true
    }
}

// Inicia a experiência de anúncio
const startAdExperience = async () => {
    console.log('🎬 Starting ad experience...')

    // Reset states
    adLoaded.value = false
    countdown.value = 15

    // Mostra o modal
    showAdDialog.value = true
    emit('adStarted')

    // Inicia countdown
    startCountdown()

    // Inicializa o anúncio
    await initializeAd()
}

// Inicia o contador regressivo
const startCountdown = () => {
    if (countdownInterval) {
        clearInterval(countdownInterval)
    }

    countdownInterval = setInterval(() => {
        countdown.value--

        if (countdown.value <= 0 && countdownInterval) {
            clearInterval(countdownInterval)
            countdownInterval = null
        }
    }, 1000)
}

// Trata a abertura/fechamento do diálogo
const handleDialogUpdate = (isOpen: boolean) => {
    if (!isOpen) {
        // Limpa countdown
        if (countdownInterval) {
            clearInterval(countdownInterval)
            countdownInterval = null
        }

        // Reset states
        showAdDialog.value = false
        countdown.value = 15
        adLoaded.value = false

        emit('adClosed')
    }
}

// Trata a conclusão do anúncio
const handleAdComplete = async () => {
    try {
        console.log('Completing ad experience...')
        const response = await axios.post(props.rewardRoute)

        if (response.data.success) {
            emit('adCompleted')
            window.location.href = props.redirectRoute
        } else {
            console.error('Failed to reward life:', response.data)
        }
    } catch (error) {
        console.error('Error rewarding life:', error)
    } finally {
        showAdDialog.value = false
    }
}

// Verifica se está em desenvolvimento local
const isLocalhost = () => {
    return window.location.hostname === 'localhost' ||
           window.location.hostname === '127.0.0.1' ||
           window.location.hostname.includes('.local')
}

// Alterna modo de teste (apenas para debug)
const toggleTestMode = () => {
    const currentMode = localStorage.getItem('adsense_test_mode') === 'true'
    localStorage.setItem('adsense_test_mode', (!currentMode).toString())
    console.log(`AdSense test mode ${!currentMode ? 'ENABLED' : 'DISABLED'}`)
    window.location.reload()
}

// Cleanup
onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval)
        countdownInterval = null
    }
})

// Expor métodos para o componente pai
defineExpose({
    startAdExperience
})
</script>
