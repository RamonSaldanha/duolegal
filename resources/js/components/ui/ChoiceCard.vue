<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        class="choice-card"
        :class="[stateClass, { 'is-static': !interactive }]"
        :aria-pressed="interactive ? selected : undefined"
        :disabled="interactive ? disabled : undefined"
    >
        <span v-if="keyLabel" class="choice-key">{{ keyLabel }}</span>
        <span class="min-w-0 flex-1">
            <slot />
        </span>
        <slot name="end" />
    </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';

// Cartão de escolha da família (docs/design/componentes.html, regra 4): alternativa da questão,
// opção do onboarding, plano da assinatura. Canto de 16, contorno e base de 4 px da mesma cor.
// O estado só troca a cor --c: neutro, azul quando escolhido, verde ou vermelho quando corrigido,
// roxo no Premium. Assim apertar afunda igual em qualquer estado.
const props = withDefaults(
    defineProps<{
        selected?: boolean;
        state?: 'right' | 'wrong' | null;
        tone?: 'choice' | 'premium';
        keyLabel?: string;
        disabled?: boolean;
        interactive?: boolean;
    }>(),
    { selected: false, state: null, tone: 'choice', keyLabel: undefined, disabled: false, interactive: true },
);

const stateClass = computed(() => {
    if (props.state === 'right') return 'is-right';
    if (props.state === 'wrong') return 'is-wrong';
    if (props.selected) return props.tone === 'premium' ? 'is-premium' : 'is-selected';
    return '';
});
</script>

<style scoped>
.choice-card {
    --c: hsl(var(--border));
    --key: hsl(var(--muted-foreground));
    display: flex;
    width: 100%;
    align-items: center;
    gap: 12px;
    border: 0;
    border-radius: 16px;
    background: hsl(var(--card));
    padding: 14px 16px;
    text-align: left;
    color: hsl(var(--foreground));
    box-shadow:
        inset 0 0 0 2px var(--c),
        0 4px 0 var(--c);
    -webkit-tap-highlight-color: transparent;
    transition:
        transform 0.08s ease,
        box-shadow 0.08s ease,
        background-color 0.15s ease;
}
button.choice-card {
    cursor: pointer;
}
@media (hover: hover) {
    button.choice-card:not(:disabled):not(.is-selected):not(.is-right):not(.is-wrong):not(.is-premium):hover {
        background: hsl(var(--secondary));
    }
}
button.choice-card:not(:disabled):active {
    transform: translateY(4px);
    box-shadow:
        inset 0 0 0 2px var(--c),
        0 0 0 var(--c);
}
.choice-card:focus-visible {
    outline: 3px solid hsl(var(--ring));
    outline-offset: 2px;
}
button.choice-card:disabled {
    cursor: default;
}
.choice-card.is-selected {
    --c: var(--mz-choice);
    --key: var(--mz-choice-ink);
    background: var(--mz-choice-soft);
}
.choice-card.is-premium {
    --c: var(--mz-premium);
    --key: var(--mz-premium);
    background: color-mix(in srgb, var(--mz-premium) 10%, hsl(var(--card)));
}
.choice-card.is-right {
    --c: var(--mz-ok);
    --key: var(--mz-ok-ink);
    background: var(--mz-ok-soft);
}
.choice-card.is-wrong {
    --c: var(--mz-bad);
    --key: var(--mz-bad-ink);
    background: var(--mz-bad-soft);
}
.choice-key {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    flex: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--key);
    box-shadow: inset 0 0 0 2px var(--c);
}
</style>
