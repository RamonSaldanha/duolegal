<script setup lang="ts">
import ActiveChallenge from '@/components/legislation-play/ActiveChallenge.vue';
import CompletedSegment from '@/components/legislation-play/CompletedSegment.vue';
import type { ActiveSegmentData, CompletedSegmentData } from '@/types/legislation-play';
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import { computed, nextTick, ref } from 'vue';
import { useReward } from 'vue-rewards';
import type { ChallengeArticle } from './quiz';

/**
 * O desafio do onboarding é um bloco do jogo de verdade: mesmo componente da
 * tela de fase (`ActiveChallenge`), com o mesmo texto, lacunas, banco de
 * palavras, "Limpar" e "Verificar". Muda só o que acontece depois: aqui não
 * se perde vida, e o acerto dispara a mesma comemoração da fase.
 */

const props = defineProps<{
    article: ChallengeArticle;
    /** Já resolvido antes (recarregou a página): mostra o bloco concluído. */
    solvedBefore?: boolean;
    xpReward: number;
    /** Onde os controles (palavras + Limpar/Verificar) aparecem, como no jogo. */
    controlsTarget?: string;
}>();

const emit = defineEmits<{ solved: [] }>();

const challengeRef = ref<InstanceType<typeof ActiveChallenge> | null>(null);
const solvedNow = ref(false);

const { reward: confettiReward } = useReward('onboarding-confetti', 'confetti', {
    lifetime: 300,
    elementCount: 80,
    spread: 80,
});

/** O artigo no formato que o jogo usa: texto + posição de cada lacuna. */
const segment = computed<ActiveSegmentData>(() => {
    const a = props.article;
    const original = `${a.ref} ${a.text}`;
    const words = original.split(/\s+/).filter(Boolean);
    let from = a.ref.split(/\s+/).filter(Boolean).length;

    const lacunas = a.gaps.map((word, i) => {
        const position = words.indexOf(word, from);
        from = position + 1;
        return { gap_order: i + 1, word, word_position: position };
    });

    return {
        uuid: 'onboarding',
        original_text: original,
        position: 0,
        segment_type: 'article',
        article_reference: a.ref,
        structural_marker: a.ref,
        lacunas,
        options: a.bank,
        total_gaps: lacunas.length,
    };
});

const completedSegment = computed<CompletedSegmentData>(() => ({
    ...segment.value,
    answers: segment.value.lacunas.map((l) => ({
        gap_order: l.gap_order,
        word_position: l.word_position,
        correct_word: l.word,
        user_word: l.word,
        is_correct: true,
    })),
    is_completed: true,
    best_score: 100,
}));

function playSuccessAudio() {
    try {
        const audio = new Audio('/bell-win.wav');
        audio.volume = 0.7;
        audio.play().catch(() => {});
    } catch {
        // Sem áudio, segue a comemoração visual.
    }
}

/** Mesma notificação de XP da tela de fase. */
function showXpGained(amount: number) {
    const el = document.createElement('div');
    el.textContent = `+${amount} XP`;
    el.className = 'onboarding-xp-gain fixed left-1/2 top-20 z-50 rounded-lg bg-purple-500 px-4 py-2 text-lg font-bold text-white shadow-lg';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2000);
}

async function handleSubmit(data: { correct_count: number; total_count: number }) {
    if (data.correct_count === data.total_count) {
        solvedNow.value = true;
        confettiReward();
        playSuccessAudio();
        showXpGained(props.xpReward);
        emit('solved');
        return;
    }

    // Errou: deixa ver o que estava errado e libera para tentar de novo.
    await new Promise((resolve) => setTimeout(resolve, 1600));
    await nextTick();
    challengeRef.value?.reset();
}
</script>

<template>
    <div class="relative">
        <span id="onboarding-confetti" class="pointer-events-none fixed left-1/2 top-1/2 z-[100]" />

        <!-- Nome da lei, como os separadores do mapa -->
        <div class="flex items-center gap-3 text-sm font-medium text-gray-500 dark:text-gray-400">
            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
            {{ article.law }}
            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
        </div>

        <div v-if="solvedBefore && !solvedNow" class="py-6">
            <CompletedSegment :segment="completedSegment" />
        </div>
        <ActiveChallenge v-else ref="challengeRef" :segment="segment" :controls-target="controlsTarget" @submit="handleSubmit" />
    </div>
</template>

<style>
.onboarding-xp-gain {
    animation: onboarding-xp-gain 2s ease-out forwards;
}

@keyframes onboarding-xp-gain {
    0% {
        opacity: 0;
        transform: translate(-50%, 20px) scale(0.8);
    }
    30% {
        opacity: 1;
        transform: translate(-50%, -10px) scale(1.2);
    }
    70% {
        opacity: 1;
        transform: translate(-50%, -20px) scale(1);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -40px) scale(0.8);
    }
}
</style>
