<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Gem, Infinity } from 'lucide-vue-next';
import { computed, watch } from 'vue';

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
const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);

const hasSubscription = computed(() => {
    return !!page.props.auth.user?.has_infinite_lives;
});

const userLives = computed(() => page.props.auth.user?.lives ?? 0);

watch(userLives, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        const heartElement = document.querySelector('.animate-pulse-once') as HTMLElement;
        if (heartElement) {
            heartElement.classList.remove('animate-pulse-once');
            heartElement.style.animation = 'none';
            heartElement.getBoundingClientRect();
            heartElement.style.animation = '';
            heartElement.classList.add('animate-pulse-once');
        }
    }
});
</script>

<template>
    <div class="border-b border-sidebar-border/80">
        <div class="mx-auto flex h-16 items-center justify-between px-4 max-w-4xl">
            <!-- Logo (Desktop only) -->
            <Link :href="route('play.map')" class="hidden lg:flex items-center">
                <AppLogo />
            </Link>

            <!-- Stats Container - Centered on mobile, right on desktop -->
            <div class="flex flex-1 items-center justify-center gap-6 lg:flex-none lg:justify-end lg:gap-8">
                <!-- Lives Counter -->
                <div v-if="auth.user?.lives !== undefined" class="flex items-center">
                    <div class="flex items-center gap-1.5 rounded-full bg-pink-50 px-3 py-1.5 dark:bg-pink-900/20">
                        <template v-if="!hasSubscription">
                            <Heart
                                class="h-5 w-5 transition-transform animate-pulse-once"
                                :class="userLives > 0 ? 'text-red-500' : 'text-gray-400'"
                                fill="currentColor"
                            />
                            <span class="font-bold" :class="userLives > 0 ? 'text-red-500' : 'text-gray-400'">
                                {{ userLives }}
                            </span>
                        </template>
                        <template v-else>
                            <Heart
                                class="h-5 w-5 text-blue-500 animate-pulse-once"
                                fill="currentColor"
                            />
                            <Infinity class="h-5 w-5 text-blue-500" />
                        </template>
                    </div>
                </div>

                <!-- XP Counter -->
                <div v-if="auth.user?.xp !== undefined" class="flex items-center gap-1">
                    <span class="rounded bg-purple-100 px-2 py-1 text-xs font-bold text-purple-500 dark:bg-purple-900/30">XP</span>
                    <span class="font-bold text-purple-500">{{ auth.user.xp }}</span>
                </div>

                <!-- Diamond / Premium -->
                <Link
                    :href="route('subscription.index')"
                    class="flex items-center gap-2 text-blue-500 transition-colors hover:text-blue-600"
                >
                    <Gem class="h-6 w-6" />
                    <span class="hidden lg:inline font-medium text-sm">Assine Premium</span>
                </Link>

                <!-- User Avatar with Dropdown -->
                <DropdownMenu v-if="auth.user">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                        >
                            <Avatar class="h-10 w-10 overflow-hidden rounded-full border-2 border-gray-200 dark:border-gray-700">
                                <AvatarImage v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                <AvatarFallback class="rounded-full bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                    {{ getInitials(auth.user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="auth.user" :is-admin="isAdmin" />
                    </DropdownMenuContent>
                </DropdownMenu>
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
</style>
