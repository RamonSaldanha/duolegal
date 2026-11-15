<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { BookOpenCheck, Heart, Scale, Shirt, X, Zap } from 'lucide-vue-next';
import { onUnmounted, watch } from 'vue';

// Convite para assinar, em tela cheia: aparece quando quem não assina toca num recurso do
// Premium. Mostra as vantagens e leva à página de assinatura (não direto ao checkout).
const props = defineProps<{ show: boolean }>();
const emit = defineEmits<{ close: [] }>();

const benefits = [
    {
        icon: BookOpenCheck,
        title: 'Resumos e questões de concurso',
        text: 'Ao fim de cada etapa, resumos do que você estudou nela e questões no estilo Cebraspe, FGV, FCC e Vunesp.',
    },
    { icon: Heart, title: 'Vidas infinitas', text: 'Erre à vontade e estude sem pausa.' },
    { icon: Scale, title: 'Todas as legislações', text: 'Acesso completo ao conteúdo.' },
    { icon: Zap, title: 'Sem anúncios', text: 'Uma experiência limpa e focada.' },
    { icon: Shirt, title: 'Fantasias exclusivas', text: 'Toga e astronauta para o seu avatar.' },
];

function onKey(event: KeyboardEvent) {
    if (event.key === 'Escape') emit('close');
}

watch(
    () => props.show,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) window.addEventListener('keydown', onKey);
        else window.removeEventListener('keydown', onKey);
    },
);

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[60] overflow-y-auto bg-background"
            role="dialog"
            aria-modal="true"
            aria-labelledby="premium-upsell-title"
        >
            <div class="mx-auto flex min-h-full max-w-md flex-col px-6 pb-8 pt-4">
                <button
                    type="button"
                    class="-ml-2 self-start rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    aria-label="Fechar"
                    @click="emit('close')"
                >
                    <X class="h-6 w-6" />
                </button>

                <div class="mt-2 flex justify-center">
                    <AppLogo height="64px" width="120px" class="justify-items-center" />
                </div>

                <p class="mt-4 text-center text-xs font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400">Memorize Premium</p>
                <h2 id="premium-upsell-title" class="mt-2 text-center text-2xl font-bold leading-tight text-gray-900 dark:text-white">
                    Libere resumos e questões de cada etapa
                </h2>
                <p class="mt-2 text-center text-gray-500 dark:text-gray-400">Revise o que você leu do jeito que a banca cobra.</p>

                <ul class="mt-8 space-y-5">
                    <li v-for="benefit in benefits" :key="benefit.title" class="flex items-start gap-4">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-300"
                        >
                            <component :is="benefit.icon" class="h-5 w-5" :stroke-width="2.5" />
                        </span>
                        <span>
                            <span class="block font-bold text-gray-900 dark:text-white">{{ benefit.title }}</span>
                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ benefit.text }}</span>
                        </span>
                    </li>
                </ul>

                <div class="mt-auto pt-10">
                    <GameButton variant="purple" size="lg" class="w-full" :href="route('subscription.index')">Conhecer o Premium</GameButton>
                    <button
                        type="button"
                        class="mt-3 w-full py-2 text-sm font-bold uppercase tracking-wide text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click="emit('close')"
                    >
                        Agora não
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
