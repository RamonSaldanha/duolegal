<template>
    <!-- Container limpo apenas para AdSense -->
    <div
        v-if="shouldRenderAd"
        ref="adContainer"
        :style="containerStyle"
    >
        <!-- Componente AdSense da biblioteca -->
        <Adsense
            v-if="isAdVisible"
            :adStyle="adStyle"
            :clientId="clientId"
            :slotId="slotId"
            :format="format"
            :fullWidthResponsive="fullWidthResponsive ? 'true' : 'false'"
        />

        <!-- Loading discreto -->
        <div v-else class="ad-loading">
            <div class="loading-spinner"></div>
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

    return isVisible
}

// Inicializa o anúncio de forma segura
const initializeAd = async () => {
    try {
        await nextTick()
        await new Promise(resolve => setTimeout(resolve, 500))

        if (!checkContainerVisibility()) {
            setTimeout(initializeAd, 500)
            return
        }

        const hasValidWidth = await checkContainerWidth()
        if (!hasValidWidth) {
            setTimeout(initializeAd, 500)
            return
        }

        isAdVisible.value = true

    } catch (error) {
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
            if (width >= props.minWidth && !isAdVisible.value) {
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
    shouldRenderAd.value = true
    await nextTick()

    setTimeout(() => {
        setupResizeObserver()
        startVisibilityCheck()
        initializeAd()
    }, 1000)
})

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect()
        resizeObserver = null
    }

    if (visibilityCheckInterval) {
        clearInterval(visibilityCheckInterval)
        visibilityCheckInterval = null
    }
})

// Método público para forçar inicialização
const forceInitialize = () => {
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
.ad-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 250px;
    opacity: 0.6;
}

.loading-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>