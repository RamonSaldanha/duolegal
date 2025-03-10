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
                <Link :href="route('play.map')" class="inline-flex items-center">
                    <Button>
                        <ChevronLeft class="mr-2 h-4 w-4" />
                        Voltar para o início
                    </Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Heart, ChevronLeft } from 'lucide-vue-next';
</script>
