<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Gem, Infinity } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
            lives: number;
            xp: number;
            avatar?: string;
            has_infinite_lives?: boolean;
        } | null;
    };
}>();

const auth = computed(() => page.props.auth);

const hasSubscription = computed(() => {
    return !!page.props.auth.user?.has_infinite_lives;
});

const userLives = computed(() => page.props.auth.user?.lives ?? 0);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- XP e Vidas -->
        <div class="flex items-center gap-3">
            <!-- XP Counter -->
            <div v-if="auth.user?.xp !== undefined" class="flex items-center gap-1">
                <span class="text-xs font-bold text-purple-500 bg-purple-100 dark:bg-purple-900/30 px-2 py-1 rounded">XP</span>
                <span class="font-bold text-purple-500">{{ auth.user.xp }}</span>
            </div>

            <!-- Lives Counter -->
            <div v-if="auth.user?.lives !== undefined" class="flex items-center gap-1">
                <div class="flex items-center gap-1">
                    <!-- Mostrar ícone de coração para usuários normais ou coroa para premium -->
                    <template v-if="!hasSubscription">
                        <Heart
                            class="w-5 h-5 transition-transform"
                            :class="[
                                userLives > 0 ? 'text-red-500' : 'text-gray-400'
                            ]"
                            fill="currentColor"
                        />
                    </template>
                    <template v-else>
                        <Link :href="route('subscription.index')" class="inline-flex">
                            <Heart
                                class="w-5 h-5 text-blue-500 cursor-pointer hover:text-blue-600 transition-colors"
                                fill="currentColor"
                            />
                        </Link>
                    </template>

                    <!-- Mostrar número de vidas para usuários normais -->
                    <span v-if="!hasSubscription" class="font-medium" :class="userLives > 0 ? 'text-red-500' : 'text-gray-400'">
                        {{ userLives }}
                    </span>
                    <!-- Mostrar símbolo de infinito para usuários premium -->
                    <span v-else class="font-medium text-blue-500">
                        <Infinity class="w-5 h-5" />
                    </span>
                </div>

                <!-- Botão de assinatura para usuários normais -->
                <Link
                    v-if="!hasSubscription"
                    :href="route('subscription.index')"
                    class="flex items-center gap-1 text-sm text-blue-500 hover:text-blue-600 font-medium"
                >
                    <Gem class="w-5" />
                    <span class="hidden sm:inline">Premium</span>
                </Link>
            </div>
        </div>
    </header>
</template>
