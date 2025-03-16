<template>
    <Dialog 
        :open="showAdDialog" 
        @update:open="handleDialogUpdate"
    >
        <DialogContent class="sm:max-w-[425px] min-h-[400px] flex flex-col bg-white">
            <DialogHeader>
                <DialogTitle>Assistindo anúncio...</DialogTitle>
                <DialogDescription>
                    Aguarde a contagem regressiva para ganhar uma vida.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div v-if="countdown > 0" class="text-xl font-bold mb-4">
                        {{ countdown }}s
                    </div>
                    <Button 
                        v-if="countdown <= 0"
                        @click="handleAdComplete"
                        class="bg-primary text-white hover:bg-primary/90"
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
let countdownInterval: ReturnType<typeof setInterval> | null = null

// Inicia a experiência de anúncio
const startAdExperience = () => {
    showAdDialog.value = true
    startCountdown()
    emit('adStarted')
}

// Inicia o contador regressivo
const startCountdown = () => {
    countdown.value = 15
    
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

// Limpa o intervalo quando o componente for desmontado
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
