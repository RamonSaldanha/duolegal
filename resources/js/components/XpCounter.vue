<script setup lang="ts">
import { useCountUp } from '@/composables/useCountUp';
import { onMounted, ref, toRef, watch } from 'vue';

// Contador de XP do topo. Guarda no navegador o último XP mostrado: quando a pessoa volta de
// uma fase com mais XP, o número sobe contando a partir do anterior e mostra o "+N" ganho.
const props = defineProps<{ xp: number; userId: number }>();

const storageKey = `memorize:xp-seen:${props.userId}`;

function readSeen(): number | null {
    try {
        const value = Number(window.localStorage.getItem(storageKey));
        return Number.isFinite(value) && value > 0 ? value : null;
    } catch {
        return null;
    }
}

function saveSeen(value: number) {
    try {
        window.localStorage.setItem(storageKey, String(value));
    } catch {
        // Sem armazenamento, só não anima da próxima vez
    }
}

const seen = typeof window !== 'undefined' ? readSeen() : null;
const start = seen !== null && seen < props.xp ? seen : props.xp;

const { shown, animating } = useCountUp(toRef(props, 'xp'), { from: start, delay: 400, duration: 1200 });
const gained = ref(0);

onMounted(() => {
    if (start < props.xp) gained.value = props.xp - start;
    saveSeen(props.xp);
});

watch(
    () => props.xp,
    (value, old) => {
        if (value > old) gained.value = value - old;
        saveSeen(value);
    },
);
</script>

<template>
    <div class="relative flex items-center gap-1" :class="{ 'xp-counting': animating }">
        <span class="rounded bg-purple-100 px-2 py-1 text-xs font-bold text-purple-500 dark:bg-purple-900/30">XP</span>
        <span class="xp-value whitespace-nowrap text-sm font-bold tabular-nums text-purple-500 sm:text-base">{{ shown }}</span>
        <span
            v-if="gained > 0"
            :key="gained"
            class="xp-gain pointer-events-none absolute -bottom-5 right-0 whitespace-nowrap text-xs font-bold text-purple-500"
            aria-hidden="true"
            @animationend="gained = 0"
            >+{{ gained }}</span
        >
    </div>
</template>

<style scoped>
.xp-value {
    display: inline-block;
    transition: transform 0.2s ease;
}
.xp-counting .xp-value {
    transform: scale(1.15);
}
.xp-gain {
    animation: xp-gain 2s ease-out 0.4s both;
}
@keyframes xp-gain {
    0% {
        opacity: 0;
        transform: translateY(-4px);
    }
    15%,
    70% {
        opacity: 1;
        transform: translateY(0);
    }
    100% {
        opacity: 0;
        transform: translateY(6px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .xp-gain {
        animation: none;
        display: none;
    }
    .xp-counting .xp-value {
        transform: none;
    }
}
</style>
