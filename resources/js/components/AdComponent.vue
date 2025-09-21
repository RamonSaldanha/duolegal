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
                <div
                    id="ad-container"
                    class="w-full max-w-4xl mb-8 overflow-hidden min-h-[300px] flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-lg"
                >
                    <div v-if="!adLoaded" class="text-center p-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                        <p class="text-sm text-muted-foreground">Carregando anúncio...</p>
                    </div>

                    <div
                        v-show="adLoaded"
                        id="adsense-ad"
                        class="w-full h-full flex items-center justify-center"
                    ></div>
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
                            {{ shouldUseTestAds() ? '🧪 Modo Teste ATIVO' : '🔧 Ativar Modo Teste' }}
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
import { ref, onUnmounted } from 'vue'
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
const adLoaded = ref(false)
let countdownInterval: ReturnType<typeof setInterval> | null = null
let adSenseScriptLoaded = false

// Declaração para TypeScript reconhecer adsbygoogle
declare global {
    interface Window {
        adsbygoogle: any[];
    }
}

// Carrega script do AdSense com melhor handling de erros
const ensureAdSenseScript = (): Promise<void> => {
    return new Promise((resolve) => {
        // Se já carregou, resolve imediatamente
        if (adSenseScriptLoaded || document.querySelector('script[src*="adsbygoogle.js"]')) {
            adSenseScriptLoaded = true
            resolve()
            return
        }

        // Em localhost, simula script carregado para evitar erros de rede
        if (isLocalhost() && !shouldUseTestAds()) {
            console.log('Localhost detected: skipping AdSense script load')
            adSenseScriptLoaded = true
            // Simula window.adsbygoogle para desenvolvimento
            if (!window.adsbygoogle) {
                window.adsbygoogle = []
            }
            resolve()
            return
        }

        // Cria e adiciona o script
        const script = document.createElement('script')
        script.async = true
        script.crossOrigin = 'anonymous'

        // Usa publisher ID de teste se estiver em modo de teste
        const clientId = shouldUseTestAds() ? 'ca-pub-3940256099942544' : 'ca-pub-2585274176504938'
        script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${clientId}`

        script.onload = () => {
            adSenseScriptLoaded = true
            console.log(`AdSense script loaded successfully (${shouldUseTestAds() ? 'TEST MODE' : 'PRODUCTION'})`)
            resolve()
        }

        script.onerror = (error) => {
            console.warn('AdSense script failed to load:', error)
            adSenseScriptLoaded = true
            // Simula window.adsbygoogle para continuar funcionamento
            if (!window.adsbygoogle) {
                window.adsbygoogle = []
            }
            resolve() // Não rejeita para não quebrar a aplicação
        }

        document.head.appendChild(script)
    })
}

// Verifica se está em desenvolvimento local
const isLocalhost = () => {
    return window.location.hostname === 'localhost' ||
           window.location.hostname === '127.0.0.1' ||
           window.location.hostname.includes('.local')
}

// Verifica se deve usar modo de teste do AdSense
const shouldUseTestAds = () => {
    // Você pode controlar isso via:
    // 1. Variável de ambiente
    // 2. Query parameter (?test_ads=true)
    // 3. localStorage
    // 4. Forçar teste em localhost apenas se explicitamente ativado
    return new URLSearchParams(window.location.search).has('test_ads') ||
           localStorage.getItem('adsense_test_mode') === 'true'
}

// Verifica se deve mostrar anúncio simulado
const shouldShowMockAd = () => {
    return isLocalhost() && !shouldUseTestAds()
}

// Cria anúncio de desenvolvimento (simulação)
const createDevAd = () => {
    const adContainer = document.getElementById('adsense-ad')
    if (!adContainer) return

    adContainer.innerHTML = `
        <div class="w-full h-64 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white">
            <div class="text-center">
                <h3 class="text-lg font-bold mb-2">🎯 Anúncio de Desenvolvimento</h3>
                <p class="text-sm opacity-90">Este é um anúncio simulado para localhost</p>
                <p class="text-xs opacity-75 mt-2">Para testar anúncios reais, clique em "Ativar Modo Teste"</p>
                <p class="text-xs opacity-60 mt-1">Em produção, aparecerá o Google AdSense</p>
            </div>
        </div>
    `
}

// Cria anúncio de teste do Google AdSense
const createTestAd = () => {
    const adContainer = document.getElementById('adsense-ad')
    if (!adContainer) return null

    // Cria elemento ins do AdSense com configuração de teste
    const adElement = document.createElement('ins')
    adElement.className = 'adsbygoogle'
    adElement.style.display = 'block'
    adElement.setAttribute('data-ad-client', 'ca-pub-3940256099942544') // Test Publisher ID do Google
    adElement.setAttribute('data-ad-slot', '6300978111') // Test Ad Unit ID
    adElement.setAttribute('data-ad-format', 'auto')
    adElement.setAttribute('data-full-width-responsive', 'true')
    adElement.setAttribute('data-adtest', 'on') // Força modo de teste

    adContainer.appendChild(adElement)
    return adElement
}

// Cria o anúncio dinamicamente
const createAdElement = () => {
    const adContainer = document.getElementById('adsense-ad')
    if (!adContainer) {
        console.error('Ad container not found')
        return
    }

    // Limpa container anterior
    adContainer.innerHTML = ''

    // Se deve mostrar anúncio simulado (localhost sem modo teste)
    if (shouldShowMockAd()) {
        console.log('Development mode: showing mock ad')
        createDevAd()
        return null
    }

    // Se deve usar anúncios de teste
    if (shouldUseTestAds()) {
        console.log('Test mode: using Google test ads')
        return createTestAd()
    }

    // Cria elemento ins do AdSense para produção
    const adElement = document.createElement('ins')
    adElement.className = 'adsbygoogle'
    adElement.style.display = 'block'
    adElement.setAttribute('data-ad-client', 'ca-pub-2585274176504938')
    adElement.setAttribute('data-ad-slot', '3465272448')
    adElement.setAttribute('data-ad-format', 'auto')
    adElement.setAttribute('data-full-width-responsive', 'true')

    adContainer.appendChild(adElement)

    return adElement
}

// Inicializa o anúncio
const initializeAd = async () => {
    try {
        console.log('Initializing AdSense ad...')

        // Aguarda um momento para o DOM estar pronto
        await new Promise(resolve => setTimeout(resolve, 500))

        const adElement = createAdElement()

        // Se for anúncio simulado (localhost), apenas marca como carregado
        if (shouldShowMockAd()) {
            adLoaded.value = true
            console.log('Mock ad loaded for development')
            return
        }

        if (!adElement) {
            console.error('Failed to create ad element')
            adLoaded.value = true
            return
        }

        // Inicializa o AdSense (funciona tanto para teste quanto produção)
        if (window.adsbygoogle) {
            try {
                window.adsbygoogle.push({})
                console.log(`AdSense ad pushed to queue (${shouldUseTestAds() ? 'TEST MODE' : 'PRODUCTION'})`)
                adLoaded.value = true

                // Em modo de teste, monitora se o anúncio carregou
                if (shouldUseTestAds()) {
                    setTimeout(() => {
                        const hasAdContent = adElement.querySelector('iframe') || adElement.offsetHeight > 0
                        if (!hasAdContent) {
                            console.warn('Test ad may not have loaded. This is normal on localhost.')
                        }
                    }, 2000)
                }
            } catch (error) {
                console.error('Error pushing ad to AdSense queue:', error)
                adLoaded.value = true
            }
        } else {
            console.warn('window.adsbygoogle not available')
            adLoaded.value = true
        }
    } catch (error) {
        console.error('Error initializing ad:', error)
        adLoaded.value = true
    }
}

// Inicia a experiência de anúncio
const startAdExperience = async () => {
    console.log('Starting ad experience...')

    // Reset states
    adLoaded.value = false
    countdown.value = 15

    // Mostra o modal
    showAdDialog.value = true
    emit('adStarted')

    // Inicia countdown
    startCountdown()

    try {
        // Carrega script se necessário
        await ensureAdSenseScript()

        // Inicializa o anúncio
        await initializeAd()
    } catch (error) {
        console.error('Error in ad experience:', error)
        adLoaded.value = true
    }
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

// Alterna modo de teste
const toggleTestMode = () => {
    const currentMode = localStorage.getItem('adsense_test_mode') === 'true'
    localStorage.setItem('adsense_test_mode', (!currentMode).toString())
    console.log(`AdSense test mode ${!currentMode ? 'ENABLED' : 'DISABLED'}`)
    // Recarrega a página para aplicar mudanças
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
