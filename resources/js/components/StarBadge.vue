<script setup lang="ts">
import DisciplineBadge from '@/components/DisciplineBadge.vue';

interface Props {
    badgeUrl?: string | null;
    name: string;
    stars: number;
    icon: string;
    color: string;
    size?: 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    badgeUrl: null,
    size: 'md',
});

const starsLabel = () => {
    if (props.stars === 0) return `${props.name}, conquista bloqueada`;
    return `${props.name}, ${props.stars} ${props.stars === 1 ? 'estrela' : 'estrelas'}`;
};
</script>

<template>
    <img v-if="badgeUrl" :src="badgeUrl" :alt="starsLabel()" :class="size === 'lg' ? 'h-52 w-52' : 'h-32 w-32'" draggable="false" />
    <!-- Disciplina ainda sem arte nova usa o badge antigo. -->
    <DisciplineBadge v-else :icon="icon" :color="color" :level="stars" :locked="stars === 0" :size="size" />
</template>
