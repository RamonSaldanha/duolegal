<script setup lang="ts">
import HighlightMark from '@/components/premium/HighlightMark.vue';
import StarBadge from '@/components/StarBadge.vue';
import GameButton from '@/components/ui/GameButton.vue';
import type { DisciplineLevelUp } from '@/types/legislation-play';

// Conquista do fim de fase: a estrela nova da disciplina, numa tela própria entre o resultado e
// o convite Premium (antes era uma sobreposição por cima do resultado). Mesmo desenho das
// outras telas do fim: fundo sólido, o selo no lugar da ilustração, a frase com marca-texto.
defineProps<{ levelUp: DisciplineLevelUp }>();
const emit = defineEmits<{ continue: [] }>();
</script>

<template>
    <div class="relative flex min-h-0 flex-1 flex-col overflow-y-auto bg-background">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col px-4 pb-6">
            <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                <p class="text-sm font-bold text-muted-foreground">Parabéns!</p>
                <div class="badge mt-4">
                    <StarBadge
                        :badge-url="levelUp.badge_url"
                        :name="levelUp.discipline_name"
                        :stars="levelUp.stars"
                        :icon="levelUp.icon"
                        :color="levelUp.color"
                        size="lg"
                    />
                </div>
                <h1 class="mt-5 text-[1.75rem] font-bold leading-tight text-foreground">
                    Você ganhou
                    <HighlightMark :delay="750">{{ levelUp.stars === 1 ? 'a primeira estrela!' : 'uma estrela!' }}</HighlightMark>
                </h1>
                <p class="mt-2 text-base text-muted-foreground">
                    {{ levelUp.stars }} {{ levelUp.stars === 1 ? 'estrela' : 'estrelas' }} em {{ levelUp.discipline_name }}
                </p>
            </div>

            <GameButton variant="main" size="lg" class="w-full" @click="emit('continue')">Continuar</GameButton>
        </div>
    </div>
</template>

<style scoped>
/* O selo entra crescendo, com um leve passo além do tamanho final */
.badge {
    width: min(13rem, 30vh);

    @media (prefers-reduced-motion: no-preference) {
        animation: badge-in 0.55s 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
}

@keyframes badge-in {
    from {
        opacity: 0;
        scale: 0.6;
    }
}
</style>
