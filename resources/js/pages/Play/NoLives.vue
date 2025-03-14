<template>
    <Head title="Sem vidas" />

    <AppLayout>
        <div class="container py-8">
            <div class="max-w-lg mx-auto text-center">
                <div class="mb-6 flex justify-center">
                    <Heart class="w-20 h-20 text-red-500" />
                </div>
                <h1 class="text-4xl font-bold mb-4">Você ficou sem vidas!</h1>
                <p class="text-lg text-muted-foreground mb-8">
                    Aguarde até que suas vidas sejam recarregadas para continuar jogando.
                </p>
                <Button @click="startAdExperience" class="inline-flex items-center">
                    <Play class="mr-2 h-4 w-4" />
                    Assistir anúncio para ganhar uma vida
                </Button>
            </div>
        </div>

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
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Heart, Play } from 'lucide-vue-next'
import { ref, onUnmounted } from 'vue'
import axios from 'axios'

// Component state
const showAdDialog = ref(false)
const countdown = ref(15)
let countdownInterval: ReturnType<typeof setInterval> | null = null

// Inicia a experiência de anúncio
const startAdExperience = () => {
    showAdDialog.value = true
    startCountdown()
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
    } else if (!isOpen && countdown.value > 0) {
        // Se tentar fechar antes da contagem acabar, mantenha aberto
        return true
    }
}

// Trata a conclusão do anúncio
const handleAdComplete = async () => {
    try {
        const response = await axios.post(route('play.reward-life'))
        if (response.data.success) {
            window.location.href = route('play.map')
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
</script>
