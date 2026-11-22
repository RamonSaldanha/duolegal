<script setup lang="ts">
import type { EnergyState } from '@/types/beta-play';
import { BatteryCharging, Infinity as InfinityIcon } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

// Energia do mapa novo: a bateria com raio (o raio sozinho é o XP) e o número. Quem assina vê
// o infinito; acima da barra cheia (supercarga do Dia da prova) fica dourado. O título diz
// quando vem o próximo +1. Pulsa quando o valor muda.
const props = defineProps<{ energy: EnergyState; pill?: boolean }>();

const now = ref(Date.now());
const ticker = setInterval(() => (now.value = Date.now()), 30_000);
onUnmounted(() => clearInterval(ticker));

const isSuper = computed(() => !props.energy.infinite && props.energy.value > props.energy.max);
const isEmpty = computed(() => !props.energy.infinite && props.energy.value === 0);

const hint = computed(() => {
    if (props.energy.infinite) return 'Energia infinita';
    if (isSuper.value) return `Supercarga: ${props.energy.value} de energia`;
    if (!props.energy.next_at) return 'Energia cheia';

    const minutes = Math.ceil((Date.parse(props.energy.next_at) - now.value) / 60_000);
    return minutes > 0 ? `${props.energy.value} de energia · +1 em ${minutes} min` : `${props.energy.value} de energia · +1 chegando`;
});

const bump = ref(false);
watch(
    () => props.energy.value,
    (value, old) => {
        if (value === old) return;
        bump.value = false;
        requestAnimationFrame(() => (bump.value = true));
    },
);
</script>

<template>
    <div
        class="energy"
        :class="{ 'is-pill': pill, 'is-super': isSuper, 'is-empty': isEmpty, 'is-bump': bump }"
        :title="hint"
        :aria-label="hint"
        role="img"
        @animationend="bump = false"
    >
        <BatteryCharging class="icon h-5 w-5" />
        <InfinityIcon v-if="energy.infinite" class="h-5 w-5" />
        <span v-else class="tabular-nums">{{ energy.value }}</span>
    </div>
</template>

<style scoped>
.energy {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 700;
    color: hsl(var(--foreground));
}
.icon {
    color: var(--mz-brand-edge);
}
.is-pill {
    border-radius: 999px;
    padding: 4px 10px;
    background: var(--mz-brand-soft);
    color: var(--mz-brand-soft-ink);
}
.is-pill .icon {
    color: var(--mz-brand-soft-ink);
}
.is-super,
.is-super .icon {
    color: #d97706;
}
.is-empty,
.is-empty .icon {
    color: hsl(var(--muted-foreground));
}
.is-bump {
    animation: bump 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes bump {
    40% {
        scale: 1.18;
    }
}
@media (prefers-reduced-motion: reduce) {
    .is-bump {
        animation: none;
    }
}
</style>
