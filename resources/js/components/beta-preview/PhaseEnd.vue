<script setup lang="ts">
import AchievementScene from '@/components/beta-preview/AchievementScene.vue';
import PhaseCompleteScene from '@/components/beta-preview/PhaseCompleteScene.vue';
import PremiumInviteScene from '@/components/beta-preview/PremiumInviteScene.vue';
import type { DisciplineLevelUp } from '@/types/legislation-play';
import { onMounted, onUnmounted, ref } from 'vue';

// Fim de fase do mapa beta. As duas passagens são o marca-texto: quatro riscos amarelos atravessam
// a tela na diagonal, um depois do outro, até cobri-la; por baixo a tela troca, e os riscos saem
// pelo outro lado. É o mesmo amarelo do traço do título.
// - Ao montar: de uma folha lisa (o lugar da tela do jogo) para o resultado.
// - Em "Continuar": se a fase deu uma estrela, vai para a tela da conquista; depois, quem não
//   assina vai para o convite Premium. Quem assina, ou pula o convite, volta ao mapa.
const props = defineProps<{
    title: string;
    mark: string;
    count: number;
    countLabel: string;
    xp: number;
    // A parte do XP que veio do combo de quem assina, mostrada à parte
    comboXp?: number;
    accuracy: number;
    premium: boolean;
    // A estrela que a fase deu (chega com o fim da fase), se deu
    levelUp?: DisciplineLevelUp | null;
}>();
const emit = defineEmits<{ done: [] }>();

type Stage = 'blank' | 'complete' | 'achievement' | 'invite';
const stage = ref<Stage>('complete');
const highlight = ref<'in' | 'out' | null>(null);
const BANDS = 4;
const STAGGER = 90;
const SWEEP = 260;
const COVERED_AT = (BANDS - 1) * STAGGER + SWEEP + 20; // quando o último risco termina de cobrir
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const timers: ReturnType<typeof setTimeout>[] = [];
const later = (fn: () => void, ms: number) => timers.push(setTimeout(fn, ms));

// Cobre com os riscos, troca a tela por baixo e descobre
function highlightTo(to: Stage) {
    if (highlight.value) return;
    if (reduced()) {
        stage.value = to;
        return;
    }
    highlight.value = 'in';
    later(() => {
        stage.value = to;
        highlight.value = 'out';
    }, COVERED_AT);
    later(() => (highlight.value = null), COVERED_AT * 2);
}

onMounted(() => {
    if (reduced()) return;
    stage.value = 'blank';
    highlightTo('complete');
});
onUnmounted(() => timers.forEach(clearTimeout));

function next() {
    if (props.levelUp) return highlightTo('achievement');
    afterAchievement();
}

function afterAchievement() {
    if (props.premium) return emit('done');
    highlightTo('invite');
}
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
        <div v-if="stage === 'blank'" class="sheet" />
        <div v-else-if="stage === 'complete'" class="sheet">
            <PhaseCompleteScene
                :title="title"
                :mark="mark"
                :count="count"
                :count-label="countLabel"
                :xp="xp"
                :combo-xp="comboXp ?? 0"
                :accuracy="accuracy"
                @continue="next"
            />
        </div>
        <div v-else-if="stage === 'achievement' && levelUp" class="sheet">
            <AchievementScene :level-up="levelUp" @continue="afterAchievement" />
        </div>
        <div v-else class="sheet">
            <PremiumInviteScene @skip="emit('done')" />
        </div>

        <div v-if="highlight" class="bands" :class="`is-${highlight}`" aria-hidden="true">
            <span v-for="i in BANDS" :key="i" class="band" :style="{ top: -10 + (i - 1) * 28 + '%', animationDelay: (i - 1) * STAGGER + 'ms' }" />
        </div>
    </div>
</template>

<style scoped>
.sheet {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    background: hsl(var(--background));
}

/* Marca-texto: riscos da cor do traço do título, inclinados, que crescem da esquerda e
   depois somem para a direita */
.bands {
    position: absolute;
    inset: 0;
    z-index: 5;
    pointer-events: none;
}
.band {
    position: absolute;
    left: -20%;
    width: 140%;
    height: 37%;
    border-radius: 999px;
    background: var(--mz-brand-mark);
    rotate: -8deg;
}
.is-in .band {
    transform-origin: 0 50%;
    animation: band-in 0.26s cubic-bezier(0.65, 0, 0.35, 1) both;
}
.is-out .band {
    transform-origin: 100% 50%;
    animation: band-out 0.26s cubic-bezier(0.65, 0, 0.35, 1) both;
}

@keyframes band-in {
    from {
        scale: 0 1;
    }
    to {
        scale: 1 1;
    }
}
@keyframes band-out {
    from {
        scale: 1 1;
    }
    to {
        scale: 0 1;
    }
}
</style>
