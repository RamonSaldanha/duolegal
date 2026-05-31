<script setup lang="ts">
import DisciplineBadge from '@/components/DisciplineBadge.vue';
import type { DisciplineLevelUp } from '@/types/legislation-play';
import { watch } from 'vue';

const props = defineProps<{
    levelUp: DisciplineLevelUp | null;
}>();

const emit = defineEmits<{
    (e: 'done'): void;
}>();

let timer: ReturnType<typeof setTimeout> | null = null;

// Auto-dismiss: o overlay aparece, segura e some sozinho em ~5.5s (não exige clique).
watch(
    () => props.levelUp,
    (value) => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        if (value) {
            timer = setTimeout(() => emit('done'), 10000);
        }
    },
);
</script>

<template>
    <div
        v-if="levelUp"
        class="level-up-overlay fixed inset-0 z-[90] flex items-center justify-center bg-black/55 pointer-events-none"
    >
        <div
            class="level-up-card flex w-[82%] max-w-[360px] flex-col items-center rounded-3xl px-6 pt-6 pb-7"
            :style="{ borderColor: levelUp.color }"
        >
            <p class="text-center text-xl font-bold leading-snug text-gray-800 dark:text-gray-100">
                Você subiu de nível!
            </p>

            <div class="mt-5 mb-4 flex items-center justify-center">
                <DisciplineBadge
                    :icon="levelUp.icon"
                    :color="levelUp.color"
                    :level="levelUp.new_level"
                    size="lg"
                />
            </div>

            <p class="text-center text-md font-bold leading-snug text-gray-800 dark:text-gray-100">
                Especialista nível {{ levelUp.new_level }} em {{ levelUp.discipline_name }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.level-up-overlay {
    animation: lvOverlay 3s ease forwards;
}

.level-up-card {
    animation: lvCard 3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes lvOverlay {
    0% { opacity: 0; }
    8% { opacity: 1; }
    88% { opacity: 1; }
    100% { opacity: 0; }
}

@keyframes lvCard {
    0% { opacity: 0; transform: scale(0.7) translateY(12px); }
    10% { opacity: 1; transform: scale(1.05) translateY(0); }
    16% { transform: scale(1); }
    86% { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(0.92); }
}
</style>
