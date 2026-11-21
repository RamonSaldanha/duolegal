<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        class="mz-choice"
        :class="stateClass"
        :aria-pressed="interactive ? selected : undefined"
        :disabled="interactive ? disabled : undefined"
    >
        <span v-if="keyLabel" class="mz-choice-key">{{ keyLabel }}</span>
        <span class="min-w-0 flex-1">
            <slot />
        </span>
        <slot name="end" />
    </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';

// Cartão de escolha da família (docs/design/componentes.html, regra 4): alternativa da questão,
// opção do onboarding, plano da assinatura. O desenho é a classe .mz-choice de app.css; aqui só
// se escolhe o estado. Telas com arrasto próprio (o desafio risca alternativas) usam a classe direto.
const props = withDefaults(
    defineProps<{
        selected?: boolean;
        state?: 'right' | 'wrong' | 'dim' | null;
        tone?: 'choice' | 'premium';
        keyLabel?: string;
        disabled?: boolean;
        interactive?: boolean;
    }>(),
    { selected: false, state: null, tone: 'choice', keyLabel: undefined, disabled: false, interactive: true },
);

const stateClass = computed(() => {
    if (props.state) return `is-${props.state}`;
    if (props.selected) return props.tone === 'premium' ? 'is-premium' : 'is-selected';
    return '';
});
</script>
