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
    <!-- A largura acompanha o card e a altura segue a proporção, para o badge nunca deformar. -->
    <img
        v-if="badgeUrl"
        :src="badgeUrl"
        :alt="starsLabel()"
        class="aspect-square w-full object-contain"
        :class="size === 'lg' ? 'max-w-52' : 'max-w-40'"
        draggable="false"
    />
    <!-- Disciplina ainda sem arte nova usa o badge antigo. -->
    <DisciplineBadge v-else :icon="icon" :color="color" :level="stars" :locked="stars === 0" :size="size" />
</template>
