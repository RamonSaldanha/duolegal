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
                <Button @click="handleShowAd" class="inline-flex items-center">
                    <Play class="mr-2 h-4 w-4" />
                    Assistir anúncio para ganhar uma vida
                </Button>
            </div>
        </div>

        <AdComponent
            ref="adComponentRef"
            :reward-route="route('play.reward-life')"
            :redirect-route="route('play.map')"
            @ad-started="handleAdStarted"
            @ad-completed="handleAdCompleted"
            @ad-closed="handleAdClosed"
        />
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Heart, Play } from 'lucide-vue-next'
import AdComponent from '@/components/AdComponent.vue'
import { ref } from 'vue'

// Referência para o componente de anúncio
const adComponentRef = ref<InstanceType<typeof AdComponent> | null>(null)

// Mostra o anúncio quando o botão é clicado
const handleShowAd = () => {
    adComponentRef.value?.startAdExperience()
}

// Handlers para eventos do anúncio
const handleAdStarted = () => {
    console.log('Ad started')
}

const handleAdCompleted = () => {
    console.log('Ad completed successfully')
}

const handleAdClosed = () => {
    console.log('Ad closed')
}
</script>
