<script setup lang="ts">
import GameButton from '@/components/ui/GameButton.vue';
import { playCorrect } from '@/lib/sounds';
import { Check, X } from 'lucide-vue-next';
import { onMounted } from 'vue';

// Barra de resultado no pé da fase: sobe depois da resposta, na cor do acerto ou do erro,
// com o aviso, os detalhes (slot) e o botão de seguir. A mesma nas fases de lacunas e no
// desafio da etapa, para o aviso de acerto ser igual nos dois.
const props = withDefaults(
    defineProps<{
        correct: boolean;
        title?: string;
        buttonLabel?: string;
    }>(),
    { title: undefined, buttonLabel: 'Continuar' },
);

defineEmits<{ continue: [] }>();

// Todo acerto tem som; o erro fica em silêncio
onMounted(() => {
    if (props.correct) playCorrect();
});
</script>

<template>
    <div
        class="result-bar shrink-0 border-t-2 px-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-4 md:px-4"
        :class="
            correct ? 'border-green-200 bg-green-100 dark:border-green-900 dark:bg-green-950' : 'border-red-200 bg-red-100 dark:border-red-900 dark:bg-red-950'
        "
        role="status"
    >
        <div class="mx-auto w-full sm:w-[95%] lg:w-[50rem]">
            <div class="flex items-center gap-2.5">
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white"
                    :class="correct ? 'bg-green-500' : 'bg-red-500'"
                >
                    <component :is="correct ? Check : X" class="h-4 w-4" :stroke-width="3.5" />
                </span>
                <p class="text-lg font-bold" :class="correct ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                    {{ title ?? (correct ? 'Você acertou!' : 'Não foi dessa vez') }}
                </p>
            </div>

            <div v-if="$slots.default" class="mt-2 text-sm" :class="correct ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'">
                <slot />
            </div>

            <GameButton :variant="correct ? 'success' : 'danger'" size="lg" class="mt-4 w-full" @click="$emit('continue')">
                {{ buttonLabel }}
            </GameButton>
        </div>
    </div>
</template>

<style scoped>
.result-bar {
    animation: result-bar-in 0.2s ease-out;
}
@keyframes result-bar-in {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .result-bar {
        animation: none;
    }
}
</style>
