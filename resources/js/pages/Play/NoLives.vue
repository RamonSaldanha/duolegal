<template>
    <Head title="Sem vidas" />

    <AppLayout>
        <div class="container py-8">
            <!-- Mensagem para usuários premium -->
            <div v-if="isPremium" class="max-w-lg mx-auto text-center">
                <div class="mb-6 flex justify-center">
                    <div class="w-60 h-60 flex items-center justify-center">
                        <Crown class="w-32 h-32 text-amber-500" />
                    </div>
                </div>
                <h1 class="text-4xl font-bold mb-4">Você é um assinante Premium!</h1>
                <div class="bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 p-4 rounded-lg mb-8">
                    <p class="text-lg font-medium">
                        Você tem vidas infinitas! Você não deveria estar vendo esta página.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link :href="route('play.map')" class="inline-flex items-center">
                        <Button class="bg-amber-500 hover:bg-amber-600">
                            <Play class="mr-2 h-4 w-4" />
                            Voltar para o mapa
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Mensagem para usuários normais -->
            <div v-else class="max-w-lg mx-auto text-center">
                <div class="mb-6 flex justify-center">
                    <img src="/img/nolives.png" alt="Coração vazio" class="w-60" />
                </div>
                <h1 class="text-4xl font-bold mb-4">Você ficou sem vidas!</h1>
                <p class="text-lg text-muted-foreground mb-8">
                    Aguarde até que suas vidas sejam recarregadas para continuar jogando.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Button @click="handleShowAd" class="inline-flex items-center">
                        <Play class="mr-2 h-4 w-4" />
                        Assistir anúncio para ganhar uma vida
                    </Button>

                    <Link :href="route('subscription.index')" class="inline-flex items-center">
                        <Button variant="outline" class="border-amber-500 text-amber-600 hover:bg-amber-50">
                            <Crown class="mr-2 h-4 w-4" />
                            Assinar Premium para vidas infinitas
                        </Button>
                    </Link>
                </div>
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
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Play, Crown } from 'lucide-vue-next'
import AdComponent from '@/components/AdComponent.vue'
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Obter informações do usuário da página
const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            lives: number;
            has_infinite_lives?: boolean;
        } | null;
    };
}>();

// Verificar se o usuário é premium
const isPremium = computed(() => {
    return !!page.props.auth.user?.has_infinite_lives;
});

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
