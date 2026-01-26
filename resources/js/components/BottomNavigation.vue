<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gamepad2, Flag, Trophy, BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const isCurrentRoute = computed(() => (url: string) => page.url.startsWith(url));

const navItems = [
    {
        title: 'Jogar',
        href: '/play',
        icon: Gamepad2,
    },
    {
        title: 'Desafios',
        href: '/challenges',
        icon: Flag,
    },
    {
        title: 'Ranking',
        href: '/ranking',
        icon: Trophy,
    },
    {
        title: 'Leis',
        href: '/user/legal-references',
        icon: BookOpen,
    },
];
</script>

<template>
    <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 safe-area-bottom">
        <div class="mx-auto flex h-16 max-w-lg items-center justify-around px-4">
            <Link
                v-for="item in navItems"
                :key="item.title"
                :href="item.href"
                class="flex flex-1 flex-col items-center justify-center py-2 transition-colors"
                :class="[
                    isCurrentRoute(item.href)
                        ? 'text-purple-600 dark:text-purple-400'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                ]"
            >
                <component :is="item.icon" class="h-6 w-6" />
                <span class="mt-1 text-xs font-medium">{{ item.title }}</span>
            </Link>
        </div>
    </nav>
</template>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
