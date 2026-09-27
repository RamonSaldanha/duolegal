<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import BottomNavigation from '@/components/BottomNavigation.vue';
import type { BreadcrumbItemType } from '@/types';
import { onBeforeUnmount, onMounted, ref } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

/**
 * Altura do cabeçalho, publicada em `--app-header-h` para quem também gruda no
 * topo ao rolar — hoje, o palco do editor de avatar. Com `top-0`, ele grudava
 * na mesma altura do cabeçalho e ficava por baixo dele (o cabeçalho é z-50),
 * com a cabeça do boneco cortada. Medida, e não fixa, para continuar certa se
 * o cabeçalho mudar de altura.
 */
const header = ref<HTMLElement | null>(null);
let observer: ResizeObserver | null = null;

const publishHeight = () => {
    document.documentElement.style.setProperty('--app-header-h', `${header.value?.offsetHeight ?? 0}px`);
};

onMounted(() => {
    // Já na montagem, sem esperar o primeiro aviso do observer (que só chega no
    // próximo quadro); o observer cuida das mudanças depois.
    publishHeight();
    observer = new ResizeObserver(publishHeight);

    if (header.value) {
        observer.observe(header.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.documentElement.style.removeProperty('--app-header-h');
});
</script>

<template>
    <AppShell class="flex-col">
        <div ref="header" class="sticky top-0 z-50 bg-white dark:bg-gray-900">
            <AppHeader :breadcrumbs="breadcrumbs" />
        </div>
        <AppContent class="pb-20">
            <slot />
        </AppContent>
        <BottomNavigation />
    </AppShell>
</template>
