<script setup lang="ts">
import GameButton from '@/components/ui/GameButton.vue';
import { useCountUp } from '@/composables/useCountUp';
import { FileCheck, Target, Zap } from 'lucide-vue-next';
import { toRef } from 'vue';

// Tela de fim de fase do mapa beta (fases normais e desafio da etapa): a ilustração, o
// título, os números da fase e um único botão. O XP recebido sobe contando.
const props = defineProps<{
    title: string;
    count: number;
    countLabel: string;
    xp: number;
    accuracy: number;
}>();

const emit = defineEmits<{ continue: [] }>();

const { shown: shownXp } = useCountUp(toRef(props, 'xp'), { from: 0, delay: 300 });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-4 pb-6">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col">
            <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                <img src="/img/vencedora.gif" alt="" class="h-56 w-auto sm:h-64" />
                <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
                <p class="mt-2 text-base text-gray-500 dark:text-gray-400">Você está um passo mais perto da aprovação.</p>
            </div>

            <div class="grid grid-cols-3 divide-x-2 divide-gray-200 rounded-2xl border-2 border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <div class="px-2 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5 text-xl font-bold text-green-600 dark:text-green-400">
                        <FileCheck class="h-5 w-5" />
                        {{ count }}
                    </div>
                    <div class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ countLabel }}</div>
                </div>
                <div class="px-2 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5 text-xl font-bold tabular-nums text-brand-soft-ink dark:text-brand">
                        <Zap class="h-5 w-5" />
                        {{ shownXp }}
                    </div>
                    <div class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">XP recebido</div>
                </div>
                <div class="px-2 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5 text-xl font-bold text-blue-500">
                        <Target class="h-5 w-5" />
                        {{ accuracy }}%
                    </div>
                    <div class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Precisão</div>
                </div>
            </div>

            <GameButton variant="purple" size="lg" class="mt-6 w-full" @click="emit('continue')">Continuar</GameButton>
        </div>
    </div>
</template>
