<template>
    <!-- Container com renderização condicional -->
    <div
        v-if="shouldRenderAd"
        ref="adContainer"
        class="adsense-wrapper"
        :style="containerStyle"
    >
        <div class="ad-label">Anúncio</div>

        <!-- Componente AdSense da biblioteca -->
        <Adsense
            v-if="isAdVisible"
            :adStyle="adStyle"
            :clientId="clientId"
            :slotId="slotId"
            :format="format"
            :fullWidthResponsive="fullWidthResponsive"
            class="adsense-unit"
        />

        <!-- Loading state -->
        <div v-else class="ad-loading">
            <div class="loading-spinner"></div>
            <p>Carregando anúncio...</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { Adsense } from 'vue3-google-adsense'

// Props
interface Props {
    clientId?: string
    slotId?: string
    format?: string
    adStyle?: string
    fullWidthResponsive?: boolean
    minWidth?: number
    minHeight?: number
}

const props = withDefaults(defineProps<Props>(), {
    clientId: 'ca-pub-2585274176504938',
    slotId: '3465272448',
    format: 'auto',
    adStyle: 'display:block',
    fullWidthResponsive: true,
    minWidth: 320,
    minHeight: 250
})

// Refs
const adContainer = ref<HTMLElement | null>(null)
const shouldRenderAd = ref(false)
const isAdVisible = ref(false)
const containerWidth = ref(0)

// Estados para modal
let resizeObserver: ResizeObserver | null = null
let visibilityCheckInterval: ReturnType<typeof setInterval> | null = null

// Computed
const containerStyle = computed(() => ({
    minWidth: `${props.minWidth}px`,
    minHeight: `${props.minHeight}px`,
    width: '100%',
    maxWidth: '100%'
}))

// Função para verificar se o container tem largura válida
const checkContainerWidth = (): Promise<boolean> => {
    return new Promise((resolve) => {
        if (!adContainer.value) {
            resolve(false)
            return
        }

        const checkWidth = () => {
            const width = adContainer.value?.offsetWidth || 0
            containerWidth.value = width

            console.log('🔍 Container width check:', width)

            if (width >= props.minWidth) {
                resolve(true)
            } else if (width === 0) {
                // Container ainda não tem largura, aguarda mais um pouco
                setTimeout(checkWidth, 100)
            } else {
                console.warn(`⚠️ Container width (${width}px) below minimum (${props.minWidth}px)`)
                resolve(false)
            }
        }

        checkWidth()
    })
}

// Função para verificar visibilidade do container
const checkContainerVisibility = (): boolean => {
    if (!adContainer.value) return false

    const rect = adContainer.value.getBoundingClientRect()
    const isVisible = rect.width > 0 && rect.height > 0

    console.log('👁️ Container visibility check:', isVisible, rect)
    return isVisible
}

// Inicializa o anúncio de forma segura
const initializeAd = async () => {
    console.log('🚀 Initializing AdSense...')

    try {
        // Aguarda DOM estar pronto
        await nextTick()
        await new Promise(resolve => setTimeout(resolve, 500))

        // Verifica se container existe e é visível
        if (!checkContainerVisibility()) {
            console.log('❌ Container not visible, retrying...')
            setTimeout(initializeAd, 500)
            return
        }

        // Verifica largura
        const hasValidWidth = await checkContainerWidth()
        if (!hasValidWidth) {
            console.log('❌ Container width invalid, retrying...')
            setTimeout(initializeAd, 500)
            return
        }

        console.log('✅ Container ready, showing ad')
        isAdVisible.value = true

    } catch (error) {
        console.error('❌ Error initializing ad:', error)
        // Em caso de erro, ainda mostra o ad após um tempo
        setTimeout(() => {
            isAdVisible.value = true
        }, 2000)
    }
}

// Observa mudanças de tamanho
const setupResizeObserver = () => {
    if (!adContainer.value || !window.ResizeObserver) return

    resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            const width = entry.contentRect.width
            console.log('📐 Resize observed:', width)

            if (width >= props.minWidth && !isAdVisible.value) {
                console.log('✅ Width sufficient after resize, initializing ad')
                initializeAd()
            }
        }
    })

    resizeObserver.observe(adContainer.value)
}

// Monitora visibilidade periódicamente (para modais)
const startVisibilityCheck = () => {
    visibilityCheckInterval = setInterval(() => {
        if (!isAdVisible.value && shouldRenderAd.value) {
            if (checkContainerVisibility()) {
                const width = adContainer.value?.offsetWidth || 0
                if (width >= props.minWidth) {
                    console.log('✅ Container became visible with valid width')
                    clearInterval(visibilityCheckInterval!)
                    visibilityCheckInterval = null
                    initializeAd()
                }
            }
        }
    }, 1000)
}

// Lifecycle
onMounted(async () => {
    console.log('🎯 AdSense component mounted')

    // Permite renderização do container
    shouldRenderAd.value = true

    await nextTick()

    // Aguarda um pouco para garantir que o modal esteja totalmente renderizado
    setTimeout(() => {
        setupResizeObserver()
        startVisibilityCheck()
        initializeAd()
    }, 1000)
})

onUnmounted(() => {
    console.log('🧹 AdSense component unmounted')

    if (resizeObserver) {
        resizeObserver.disconnect()
        resizeObserver = null
    }

    if (visibilityCheckInterval) {
        clearInterval(visibilityCheckInterval)
        visibilityCheckInterval = null
    }
})

// Método público para forçar inicialização (pode ser chamado pelo componente pai)
const forceInitialize = () => {
    console.log('🔄 Force initialize called')
    isAdVisible.value = false
    setTimeout(initializeAd, 100)
}

// Expõe métodos para o componente pai
defineExpose({
    forceInitialize,
    containerWidth: containerWidth.value
})
</script>

<style scoped>
.adsense-wrapper {
    position: relative;
    margin: 16px auto;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    overflow: hidden;
}

.ad-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.adsense-unit {
    min-height: 250px;
    width: 100%;
}

.ad-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 250px;
    color: #6c757d;
}

.loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .adsense-wrapper {
        background: #2d3748;
        border-color: #4a5568;
    }

    .ad-label,
    .ad-loading {
        color: #a0aec0;
    }
}
</style>