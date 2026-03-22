<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Trophy, Crown, Medal, Flame, TrendingUp, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    xp: number;
    position: number;
}

interface Props {
    topUsers: User[];
    currentUserPosition: number | null;
    currentUserData: User | null;
    period: string;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Home', href: '/' },
    { title: 'Ranking', href: '/ranking' },
];

const remainingUsers = computed(() => {
    const users = [...props.topUsers];
    if (props.currentUserData && props.currentUserPosition && props.currentUserPosition > 20) {
        return [...users, props.currentUserData];
    }
    return users;
});

const pageTitle = computed(() => {
    if (props.currentUserPosition) {
        return `Ranking (#${props.currentUserPosition})`;
    }
    return 'Ranking';
});

const isCurrentUser = (userId: number) => props.currentUserData?.id === userId;

const changePeriod = (period: string) => {
    router.get(route('ranking.index'), { period }, { preserveScroll: true });
};

const tabs = [
    { value: 'daily', label: 'Hoje', icon: Flame },
    { value: 'weekly', label: 'Semana', icon: TrendingUp },
    { value: 'all', label: 'Geral', icon: Trophy },
];

const activePeriod = computed(() => props.period || 'all');

const getInitials = (firstName: string, lastName: string) => {
    return (firstName[0] + (lastName?.[0] || '')).toUpperCase();
};

const getPositionStyle = (position: number) => {
    if (position === 1) return {
        badge: 'bg-yellow-500 text-white',
        ring: 'ring-2 ring-yellow-400/40',
        avatar: 'bg-yellow-100 text-yellow-700 ring-2 ring-yellow-400',
        text: 'text-yellow-600',
    };
    if (position === 2) return {
        badge: 'bg-blue-500 text-white',
        ring: 'ring-2 ring-blue-400/30',
        avatar: 'bg-blue-100 text-blue-700 ring-2 ring-blue-400',
        text: 'text-blue-600',
    };
    if (position === 3) return {
        badge: 'bg-purple-500 text-white',
        ring: 'ring-2 ring-purple-400/30',
        avatar: 'bg-purple-100 text-purple-700 ring-2 ring-purple-400',
        text: 'text-purple-600',
    };
    return {
        badge: 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
        ring: '',
        avatar: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
        text: 'text-gray-500',
    };
};

const getPositionIcon = (position: number) => {
    if (position === 1) return Crown;
    if (position <= 3) return Medal;
    return null;
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl space-y-5 px-4 pb-8">

            <!-- Header -->
            <div class="text-center pt-5">
                <h1 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Ranking</h1>
            </div>

            <!-- Period tabs -->
            <div class="flex justify-center">
                <div class="inline-flex bg-gray-100 dark:bg-gray-800/80 rounded-full p-1 gap-0.5">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        @click="changePeriod(tab.value)"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-full transition-all duration-200"
                        :class="[
                            activePeriod === tab.value
                                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                        ]"
                    >
                        <component :is="tab.icon" class="h-3.5 w-3.5" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Users list -->
            <div v-if="remainingUsers.length > 0" class="space-y-2">
                <template v-for="user in remainingUsers" :key="user.id">
                    <!-- Gap separator for current user outside top 20 -->
                    <div
                        v-if="isCurrentUser(user.id) && user.position > 20"
                        class="flex items-center gap-3 py-1.5"
                    >
                        <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                        <span class="text-xs text-gray-400 font-medium">...</span>
                        <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                    </div>

                    <div
                        class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all"
                        :class="[
                            isCurrentUser(user.id)
                                ? 'bg-blue-50 dark:bg-blue-950/30 ring-1 ring-blue-400/50'
                                : user.position <= 3
                                    ? 'bg-white dark:bg-gray-800/80 border border-gray-100 dark:border-gray-700/50 ' + getPositionStyle(user.position).ring
                                    : 'bg-white dark:bg-gray-800/60 hover:bg-gray-50 dark:hover:bg-gray-800'
                        ]"
                    >
                        <!-- Position -->
                        <div class="flex-shrink-0 w-9 flex justify-center">
                            <div
                                v-if="user.position <= 3"
                                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black"
                                :class="getPositionStyle(user.position).badge"
                            >
                                {{ user.position }}
                            </div>
                            <span v-else class="text-sm font-bold text-gray-400 dark:text-gray-500 tabular-nums">
                                {{ user.position }}
                            </span>
                        </div>

                        <!-- Avatar -->
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                            :class="getPositionStyle(user.position).avatar"
                        >
                            {{ getInitials(user.first_name, user.last_name) }}
                        </div>

                        <!-- Name + position icon -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <component
                                    v-if="getPositionIcon(user.position)"
                                    :is="getPositionIcon(user.position)"
                                    class="h-4 w-4 flex-shrink-0"
                                    :class="getPositionStyle(user.position).text"
                                />
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                    {{ user.first_name }}
                                    <span v-if="user.last_name" class="font-normal text-gray-500 dark:text-gray-400">{{ user.last_name }}</span>
                                </p>
                                <span
                                    v-if="isCurrentUser(user.id)"
                                    class="text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/50 px-1.5 py-0.5 rounded-full flex-shrink-0"
                                >Você</span>
                            </div>
                        </div>

                        <!-- XP -->
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <Zap class="h-3.5 w-3.5 text-yellow-500" />
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300 tabular-nums">
                                {{ user.xp.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty state -->
            <div v-if="topUsers.length === 0" class="text-center py-16">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                    <Trophy class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="text-base font-bold text-gray-600 dark:text-gray-400 mb-1">
                    Nenhum ranking disponivel
                </h3>
                <p class="text-sm text-gray-500 max-w-[260px] mx-auto">
                    {{ activePeriod === 'daily' ? 'Ninguem ganhou XP hoje ainda. Seja o primeiro!' :
                       activePeriod === 'weekly' ? 'Ninguem ganhou XP esta semana. Seja o primeiro!' :
                       'Comece a estudar para aparecer no ranking!' }}
                </p>
            </div>
        </div>
    </AppHeaderLayout>
</template>
