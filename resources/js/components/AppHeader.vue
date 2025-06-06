<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ref } from 'vue';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger,  } from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Heart, Play, Menu, Lock, Settings2, Gem, Infinity, Bug, Users } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_admin: boolean;
            lives: number;
            avatar?: string;
            has_infinite_lives?: boolean;
            debug_info?: {
                has_active_subscription: boolean;
                on_trial: boolean;
                subscribed: boolean;
                trial_ends_at: string | null;
            };
        } | null;
    };
}>();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => page.props.auth.user?.is_admin);

// Verifica se o usuário tem uma assinatura ativa (vidas infinitas)
const hasSubscription = computed(() => {
    // Verifica se o usuário está logado e tem uma assinatura ativa
    return !!page.props.auth.user?.has_infinite_lives;
});

// Variável para controlar a exibição das informações de depuração
const showDebugInfo = ref(false);

const isCurrentRoute = computed(() => (url: string) => page.url === url);

const activeItemStyles = computed(
    () => (url: string) => (isCurrentRoute.value(url) ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' : ''),
);

const mainNavItems: NavItem[] = [
    {
        title: 'Jogar',
        href: '/play',
        icon: Play,
    },
    {
        title: 'Preferências',
        href: '/user/legal-references',
        icon: Settings2,
    },
    ...(isAdmin.value
        ? [
            {
                title: 'Criar artigo',
                href: '/admin/create-lawarticle',
                icon: Lock,
            },
        ]
        : []),
    ...(isAdmin.value
        ? [
            {
                title: 'Legislações',
                href: '/admin/legislations',
                icon: BookOpen,
            },
        ]
        : []),
    ...(isAdmin.value
        ? [
            {
                title: 'Usuários',
                href: '/admin/users',
                icon: Users,
            },
        ]
        : []),
];

const userLives = computed(() => page.props.auth.user?.lives ?? 0);

// Observa mudanças nas vidas para recriar a animação
watch(userLives, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        // Remove e readiciona a classe para recriar a animação
        const heartElement = document.querySelector('.animate-pulse-once') as HTMLElement;
        if (heartElement) {
            heartElement.classList.remove('animate-pulse-once');
            // Force reflow usando uma maneira type-safe
            heartElement.style.animation = 'none';
            heartElement.getBoundingClientRect();
            heartElement.style.animation = '';
            heartElement.classList.add('animate-pulse-once');
        }
    }
});

const rightNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon class="size-6 fill-current text-black dark:text-white" />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                        :class="activeItemStyles(item.href)"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        {{ item.title }}
                                    </Link>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="route('play.map')" class="flex items-center gap-x-2">
                    <AppLogo  />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList class="flex h-full items-stretch space-x-2">
                            <NavigationMenuItem v-for="(item, index) in mainNavItems" :key="index" class="relative flex h-full items-center">
                                <Link :href="item.href">
                                    <NavigationMenuLink
                                        :class="[navigationMenuTriggerStyle(), activeItemStyles(item.href), 'h-9 cursor-pointer px-3']"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="mr-2 h-4 w-4" />
                                        {{ item.title }}
                                    </NavigationMenuLink>
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                    <div class="ml-auto flex items-center space-x-4">
                        <!-- Lives Counter -->
                        <div v-if="auth.user?.lives !== undefined" class="flex items-center gap-1">
                            <div class="flex items-center gap-1">
                                <!-- Mostrar ícone de coração para usuários normais ou coroa para premium -->
                                <template v-if="!hasSubscription">
                                    <Heart
                                        class="w-5 h-5 transition-transform"
                                        :class="[
                                            userLives > 0 ? 'text-red-500' : 'text-gray-400',
                                            'animate-pulse-once'
                                        ]"
                                        fill="currentColor"
                                    />
                                </template>
                                <template v-else>
                                    <Link :href="route('subscription.index')" class="inline-flex">
                                        <Heart
                                            class="w-5 h-5 text-blue-500 animate-pulse-once cursor-pointer hover:text-blue-600 transition-colors"
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

                            <!-- Badge Premium para usuários premium -->
                            <!-- <Link v-if="hasSubscription" :href="route('subscription.index')" class="ml-2">
                                <div class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs font-medium rounded-full hover:bg-blue-200 dark:hover:bg-blue-800/30 transition-colors cursor-pointer">
                                    Premium
                                </div>
                            </Link> -->

                            <!-- Informações de depuração (apenas para administradores) -->
                            <div v-if="isAdmin" class="ml-2 font-medium text-gray-500 cursor-pointer" @click="showDebugInfo = !showDebugInfo">
                                <Bug class="w-5 h-5" />
                            </div>

                            <div v-if="isAdmin && showDebugInfo" class="absolute top-full right-0 mt-2 p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-50 text-xs">
                                <div><strong>has_infinite_lives:</strong> {{ auth.user?.has_infinite_lives ? 'true' : 'false' }}</div>
                                <div v-if="auth.user?.debug_info"><strong>has_active_subscription:</strong> {{ auth.user?.debug_info.has_active_subscription ? 'true' : 'false' }}</div>
                                <div v-if="auth.user?.debug_info"><strong>on_trial:</strong> {{ auth.user?.debug_info.on_trial ? 'true' : 'false' }}</div>
                                <div v-if="auth.user?.debug_info"><strong>subscribed:</strong> {{ auth.user?.debug_info.subscribed ? 'true' : 'false' }}</div>
                                <div v-if="auth.user?.debug_info"><strong>trial_ends_at:</strong> {{ auth.user?.debug_info.trial_ends_at }}</div>
                            </div>

                            <!-- Botão de assinatura para usuários normais -->
                            <Link
                                v-if="!hasSubscription"
                                :href="route('subscription.index')"
                                class="ml-2 flex items-center gap-1 text-sm text-blue-500 hover:text-blue-600 font-medium"
                            >
                                <Gem class="w-5" />
                                <span class="hidden sm:inline">Premium</span>
                            </Link>
                        </div>

                        <div class="relative flex items-center space-x-1">
                        <!-- <Button variant="ghost" size="icon" class="group h-9 w-9 cursor-pointer">
                            <Search class="size-5 opacity-80 group-hover:opacity-100" />
                        </Button> -->

                        <div class="hidden space-x-1 lg:flex">
                            <template v-for="item in rightNavItems" :key="item.title">
                                <!-- Substituindo o Tooltip por um botão simples -->
                                <Button variant="ghost" size="icon" as-child class="group h-9 w-9 cursor-pointer">
                                    <a :href="item.href" target="_blank" rel="noopener noreferrer">
                                        <span class="sr-only">{{ item.title }}</span>
                                        <component :is="item.icon" class="size-5 opacity-80 group-hover:opacity-100" />
                                    </a>
                                </Button>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu v-if="auth.user">
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                        {{ getInitials(auth.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>

        </div>
    </div>
</template>

<style scoped>
@keyframes pulse-once {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

.animate-pulse-once {
    animation: pulse-once 0.6s cubic-bezier(0.4, 0, 0.6, 1);
}

.animate-pulse-once {
    animation: none;
}

:deep(.animate-pulse-once:not(:hover)) {
    animation: pulse-once 0.6s cubic-bezier(0.4, 0, 0.6, 1);
}
</style>
