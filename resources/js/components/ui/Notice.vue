<template>
    <p v-if="short" class="flex items-center gap-1.5 text-sm font-semibold text-red-700 dark:text-red-300" role="alert">
        <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7.5v5.5M12 16.5v.01" />
        </svg>
        <span><slot /></span>
    </p>
    <div
        v-else
        class="flex w-full gap-3 rounded-2xl border-2 px-4 py-3.5 text-left"
        :class="[TONES[tone].box, $slots.action || dismissible ? 'items-center' : 'items-start']"
        :role="tone === 'error' ? 'alert' : 'status'"
    >
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-white" :class="TONES[tone].dot" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path v-if="tone === 'success'" d="M20 6 9 17l-5-5" />
                <path v-else-if="tone === 'error'" d="M18 6 6 18M6 6l12 12" />
                <path v-else-if="tone === 'warning'" d="M12 6v8M12 18.5v.01" />
                <path v-else-if="tone === 'info'" d="M12 11v7M12 6.5v.01" />
                <template v-else>
                    <rect x="3" y="8" width="18" height="4" rx="1" stroke-width="2" />
                    <path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7" stroke-width="2" />
                </template>
            </svg>
        </span>
        <div class="min-w-0 flex-1">
            <p v-if="title" class="text-base font-bold leading-snug" :class="TONES[tone].title">{{ title }}</p>
            <div v-if="$slots.default" class="text-sm leading-relaxed" :class="[TONES[tone].text, title ? 'mt-0.5' : '']">
                <slot />
            </div>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
        <IconButton v-if="dismissible" label="Fechar" size="xs" class="!text-current opacity-70" @click="emit('close')">
            <X class="h-4 w-4" />
        </IconButton>
    </div>
</template>

<script setup lang="ts">
import IconButton from '@/components/ui/IconButton.vue';
import { X } from 'lucide-vue-next';

// Aviso da família (docs/design/componentes.html, avisos): chapado, sem base nem sombra, com a cor
// pelo tipo. Sucesso verde, erro vermelho, atenção âmbar, informação azul, oferta rosa. Fundo no tom
// 100 (950 no escuro), contorno no 200 (900), círculo no 500, título no 700 (300) e texto no 800
// (200). Quando pede uma ação, ela é um botão de verdade no slot "action". "short" é o erro de uma
// linha embaixo de um botão. A barra depois da resposta continua sendo o ResultBar.
withDefaults(
    defineProps<{
        tone?: 'success' | 'error' | 'warning' | 'info' | 'offer';
        title?: string;
        dismissible?: boolean;
        short?: boolean;
    }>(),
    { tone: 'info', title: undefined, dismissible: false, short: false },
);
const emit = defineEmits<{ close: [] }>();

const TONES = {
    success: {
        box: 'border-green-200 bg-green-100 dark:border-green-900 dark:bg-green-950',
        dot: 'bg-green-500',
        title: 'text-green-700 dark:text-green-300',
        text: 'text-green-800 dark:text-green-200',
    },
    error: {
        box: 'border-red-200 bg-red-100 dark:border-red-900 dark:bg-red-950',
        dot: 'bg-red-500',
        title: 'text-red-700 dark:text-red-300',
        text: 'text-red-800 dark:text-red-200',
    },
    warning: {
        box: 'border-amber-200 bg-amber-100 dark:border-amber-900 dark:bg-amber-950',
        dot: 'bg-amber-500',
        title: 'text-amber-700 dark:text-amber-300',
        text: 'text-amber-800 dark:text-amber-200',
    },
    info: {
        box: 'border-blue-200 bg-blue-100 dark:border-blue-900 dark:bg-blue-950',
        dot: 'bg-blue-500',
        title: 'text-blue-700 dark:text-blue-300',
        text: 'text-blue-800 dark:text-blue-200',
    },
    offer: {
        box: 'border-pink-200 bg-pink-100 dark:border-pink-900 dark:bg-pink-950',
        dot: 'bg-pink-500',
        title: 'text-pink-700 dark:text-pink-300',
        text: 'text-pink-800 dark:text-pink-200',
    },
} as const;
</script>
