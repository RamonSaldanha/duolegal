<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import EnergyMeter from '@/components/EnergyMeter.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserAvatar from '@/components/UserAvatar.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import XpCounter from '@/components/XpCounter.vue';
import type { AvatarConfig } from '@/lib/avatar';
import type { EnergyState } from '@/types/beta-play';
import { Link, usePage } from '@inertiajs/vue3';
import { Flame, Heart, Infinity, Plus } from 'lucide-vue-next';
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
            current_streak?: number;
            longest_streak?: number;
            avatar_config?: AvatarConfig | null;
            has_infinite_lives?: boolean;
            // Só para administradores (o mapa beta, que usa energia no lugar das vidas)
            energy?: EnergyState | null;
        } | null;
    };
}>();

const currentStreak = computed(() => page.props.auth.user?.current_streak ?? 0);

const auth = computed(() => page.props.auth);
const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);

const hasSubscription = computed(() => {
    return !!page.props.auth.user?.has_infinite_lives;
});

const userLives = computed(() => page.props.auth.user?.lives ?? 0);

// A energia do mapa no lugar das vidas (as vidas só aparecem se o servidor não mandar energia)
const energy = computed(() => page.props.auth.user?.energy ?? null);

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
        <div class="mx-auto flex h-16 max-w-4xl justify-between px-3 sm:px-4">
            <!-- Logo (Desktop only) -->
            <Link :href="route('play.map')" class="hidden items-center lg:flex">
                <AppLogo />
            </Link>

            <!--
                Sem caixas (docs/design/plus-barra-combo.html, B4): só ícone e número, espalhados
                de ponta a ponta no celular e à direita no computador. O avatar fica porque é por
                ele que se chega às configurações e ao sair.
            -->
            <div class="flex flex-1 items-center justify-between gap-2 sm:gap-4 lg:flex-none lg:justify-end lg:gap-8">
                <!-- Energia -->
                <EnergyMeter v-if="energy" :energy="energy" />

                <!-- Lives Counter -->
                <div v-else-if="auth.user?.lives !== undefined" class="flex items-center">
                    <div class="flex items-center gap-1 sm:gap-1.5">
                        <template v-if="!hasSubscription">
                            <Heart
                                class="animate-pulse-once h-5 w-5 transition-transform"
                                :class="userLives > 0 ? 'text-red-500' : 'text-gray-400'"
                                fill="currentColor"
                            />
                            <span class="font-bold" :class="userLives > 0 ? 'text-red-500' : 'text-gray-400'">
                                {{ userLives }}
                            </span>
                        </template>
                        <template v-else>
                            <Heart class="animate-pulse-once h-5 w-5 text-blue-500" fill="currentColor" />
                            <Infinity class="h-5 w-5 text-blue-500" />
                        </template>
                    </div>
                </div>

                <!-- Ofensiva (streak) -->
                <Link
                    v-if="auth.user"
                    :href="route('ofensiva.index')"
                    class="flex items-center gap-1 sm:gap-1.5"
                    :title="`Ofensiva: ${currentStreak} ${currentStreak === 1 ? 'dia' : 'dias'}`"
                >
                    <Flame class="h-5 w-5" :class="currentStreak > 0 ? 'text-orange-500' : 'text-gray-400'" fill="currentColor" />
                    <span class="font-bold" :class="currentStreak > 0 ? 'text-orange-500' : 'text-gray-400'">
                        {{ currentStreak }}
                    </span>
                </Link>

                <!-- XP Counter -->
                <XpCounter v-if="auth.user?.xp !== undefined" :xp="auth.user.xp" :user-id="auth.user.id" />

                <!-- Plus: o convite para quem não assina, o selo para quem assina -->
                <Link
                    :href="route('subscription.index')"
                    class="flex items-center gap-0.5 whitespace-nowrap text-sm font-extrabold text-plus-ink transition-opacity hover:opacity-80 sm:text-base"
                >
                    <Plus class="h-4 w-4 sm:h-5 sm:w-5" :stroke-width="3.2" />
                    {{ hasSubscription ? 'Plus' : 'Seja Plus' }}
                </Link>

                <!-- User Avatar with Dropdown -->
                <DropdownMenu v-if="auth.user">
                    <DropdownMenuTrigger :as-child="true">
                        <!--
                            `p-0`: o avatar ocupa o botão inteiro. Com o `p-1` que havia
                            aqui a caixa de conteúdo ficava 8px menor que o avatar, que
                            então transbordava o botão pelos quatro lados.
                        -->
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-[38px] shrink-0 rounded-full p-0 focus-within:ring-2 focus-within:ring-primary sm:size-[42px]"
                        >
                            <UserAvatar :config="auth.user.avatar_config" :name="auth.user.name" class="size-full rounded-full text-sm" />
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
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.3);
    }
}

.animate-pulse-once {
    animation: pulse-once 0.6s cubic-bezier(0.4, 0, 0.6, 1);
}
</style>
