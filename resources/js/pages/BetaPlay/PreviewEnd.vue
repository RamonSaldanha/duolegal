<script setup lang="ts">
import PhaseEnd from '@/components/beta-preview/PhaseEnd.vue';
import { playPhaseComplete } from '@/lib/sounds';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import { useReward } from 'vue-rewards';

// Prévia do fim de fase e do convite Premium, fora do jogo, para testar. Usa o mesmo PhaseEnd do
// jogo (BetaPlay/Phase.vue e Checkpoint.vue), então o que se vê aqui é o que vai para o jogo.
// A barra de cima só existe aqui: troca o tipo de fase, finge ser assinante e repete.
type Stage = 'game' | 'end' | 'map';
const kind = ref<'phase' | 'challenge'>('phase');
const premium = ref(false);
const stage = ref<Stage>('game');
const run = ref(0);
const dark = ref(document.documentElement.classList.contains('dark'));

const data = computed(() =>
    kind.value === 'phase'
        ? { title: 'Fase', mark: 'concluída!', count: 4, countLabel: 'Blocos', xp: 40, accuracy: 92 }
        : { title: 'Desafio', mark: 'concluído!', count: 6, countLabel: 'Questões', xp: 60, accuracy: 83 },
);

// O confete de sempre do fim de fase (mesma configuração do BetaPlay/Phase.vue)
const { reward: confettiReward } = useReward('preview-confetti', 'confetti', {
    lifetime: 300,
    elementCount: 100,
    spread: 90,
});

function finishPhase() {
    run.value++;
    stage.value = 'end';
    playPhaseComplete();
    setTimeout(() => confettiReward(), 250);
}
function replay() {
    stage.value = 'game';
}
function toggleDark() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
}
</script>

<template>
    <Head title="Prévia do fim de fase" />
    <div class="flex h-dvh flex-col bg-background">
        <div class="relative z-10 flex flex-wrap items-center gap-1.5 border-b-2 border-border bg-card px-3 py-2 text-xs">
            <span class="mr-1 font-bold text-gray-500">Prévia</span>
            <button class="mz-btn mz-xs" :class="kind === 'phase' ? 'mz-tonal-brand' : 'mz-ghost'" @click="kind = 'phase'">Fase</button>
            <button class="mz-btn mz-xs" :class="kind === 'challenge' ? 'mz-tonal-brand' : 'mz-ghost'" @click="kind = 'challenge'">Desafio</button>
            <button class="mz-btn mz-xs" :class="premium ? 'mz-tonal-brand' : 'mz-ghost'" @click="premium = !premium">
                {{ premium ? 'Assinante' : 'Não assinante' }}
            </button>
            <button class="mz-btn mz-xs mz-ghost" @click="toggleDark">{{ dark ? 'Claro' : 'Escuro' }}</button>
            <button class="mz-btn mz-xs mz-outline ml-auto" @click="replay">Repetir</button>
        </div>

        <span id="preview-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]"></span>

        <div v-if="stage === 'game'" class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <p class="text-muted-foreground">Última pergunta respondida.</p>
            <button class="mz-btn mz-lg mz-main w-full max-w-xs" @click="finishPhase">Terminar a fase</button>
        </div>
        <PhaseEnd v-else-if="stage === 'end'" :key="run" v-bind="data" :premium="premium" @done="stage = 'map'" />
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center text-muted-foreground">
            <p>Aqui o jogo voltaria para o mapa.</p>
            <button class="mz-btn mz-md mz-second" @click="replay">Ver de novo</button>
        </div>
    </div>
</template>
