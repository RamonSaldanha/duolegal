<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import BottomNavigation from '@/components/BottomNavigation.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <div class="sticky top-0 z-50 bg-white dark:bg-gray-900">
            <AppHeader :breadcrumbs="breadcrumbs" />
        </div>
        <AppContent class="pb-20">
            <slot />
        </AppContent>
        <BottomNavigation />
    </AppShell>
</template>
