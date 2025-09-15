<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Trophy, Medal, Award, Crown, Star } from 'lucide-vue-next';
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
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Home', href: '/' },
    { title: 'Ranking', href: '/ranking' },
];

const top3Users = computed(() => props.topUsers.slice(0, 3));
const remainingUsers = computed(() => props.topUsers.slice(3));

const getPositionIcon = (position: number) => {
    switch (position) {
        case 1:
            return Crown;
        case 2:
            return Medal;
        case 3:
            return Award;
        default:
            return Star;
    }
};

</script>

<template>
    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Cabeçalho -->
            <div class="text-center space-y-2 mt-5">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ranking de XP</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Os melhores estudantes de direito do Memorize</p>
            </div>

            <!-- Top 3 em destaque -->
            <div v-if="top3Users.length > 0" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div
                    v-for="user in top3Users"
                    :key="user.id"
                    class="relative"
                >
                    <div
                        class="rounded-lg p-4 text-center space-y-3 border-2"
                        :class="[
                            user.position === 1 ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-900/10' :
                            user.position === 2 ? 'border-gray-400 bg-gray-50 dark:bg-gray-800/10' :
                            'border-orange-400 bg-orange-50 dark:bg-orange-900/10'
                        ]"
                    >
                        <!-- Posição -->
                        <div class="flex justify-center">
                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg"
                                :class="[
                                    user.position === 1 ? 'bg-yellow-500' :
                                    user.position === 2 ? 'bg-gray-500' :
                                    'bg-orange-500'
                                ]"
                            >
                                {{ user.position }}
                            </div>
                        </div>

                        <!-- Ícone da posição -->
                        <div class="flex justify-center">
                            <component
                                :is="getPositionIcon(user.position)"
                                class="h-8 w-8"
                                :class="[
                                    user.position === 1 ? 'text-yellow-500' :
                                    user.position === 2 ? 'text-gray-400' :
                                    'text-orange-600'
                                ]"
                            />
                        </div>

                        <!-- Nome -->
                        <div class="space-y-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                                {{ user.first_name }}
                            </h3>
                            <p v-if="user.last_name" class="text-base font-medium text-gray-700 dark:text-gray-300 leading-tight">
                                {{ user.last_name }}
                            </p>
                        </div>

                        <!-- XP -->
                        <div>
                            <div
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full font-bold text-sm"
                                :class="[
                                    user.position === 1 ? 'bg-yellow-500 text-white' :
                                    user.position === 2 ? 'bg-gray-500 text-white' :
                                    'bg-orange-500 text-white'
                                ]"
                            >
                                <Star class="h-4 w-4" />
                                {{ user.xp.toLocaleString() }} XP
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resto do ranking (4º ao 20º) -->
            <div v-if="remainingUsers.length > 0" class="space-y-3 pt-4 pb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white text-center">
                    Demais Posições
                </h2>

                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div
                        v-for="user in remainingUsers"
                        :key="user.id"
                        class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    >
                        <!-- Posição e Nome -->
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-sm"
                                :class="[
                                    'bg-purple-500'
                                ]"
                            >
                                {{ user.position }}
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight">
                                    {{ user.first_name }}
                                    <span v-if="user.last_name">{{ user.last_name }}</span>
                                </h4>
                            </div>
                        </div>

                        <!-- XP -->
                        <div class="flex items-center gap-1">
                            <Star class="h-4 w-4 text-purple-500" />
                            <span class="font-bold text-purple-600 dark:text-purple-400 text-sm">
                                {{ user.xp.toLocaleString() }} XP
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mensagem caso não haja usuários -->
            <div v-if="topUsers.length === 0" class="text-center py-8">
                <Trophy class="h-12 w-12 text-gray-400 mx-auto mb-3" />
                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-1">
                    Nenhum ranking disponível
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Seja o primeiro a aparecer no ranking estudando direito!
                </p>
            </div>
        </div>
    </AppHeaderLayout>
</template>