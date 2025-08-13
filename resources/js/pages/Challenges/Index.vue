<template>
    <Head title="Desafios" />
    
    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold">Desafios Públicos</h1>
                            <p class="text-muted-foreground mt-2">
                                Participe de desafios criados pela comunidade ou 
                                <Link :href="route('challenges.create')" class="text-primary hover:underline">
                                    crie o seu próprio
                                </Link>
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <GameButton 
                                :href="route('challenges.my-index')"
                                variant="white"
                                class="flex items-center gap-2"
                            >
                                <User class="w-4 h-4" />
                                Meus Desafios
                            </GameButton>
                            
                            <GameButton 
                                :href="route('challenges.create')"
                                variant="green"
                                class="flex items-center gap-2"
                            >
                                <Plus class="w-4 h-4" />
                                Criar Desafio
                            </GameButton>
                        </div>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="mt-6">
                        <form @submit.prevent="searchChallenges" class="relative max-w-md">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar desafios..."
                                class="w-full px-4 py-2 pr-10 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            />
                            <button
                                type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground"
                            >
                                <Search class="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Challenges Grid -->
                <div v-if="challenges.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div
                        v-for="challenge in challenges.data"
                        :key="challenge.uuid"
                        class="bg-card border border-border rounded-lg p-6 hover:shadow-lg transition-shadow"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">{{ challenge.title }}</h3>
                                <p v-if="challenge.description" class="text-muted-foreground text-sm line-clamp-3">
                                    {{ challenge.description }}
                                </p>
                            </div>
                            <div class="flex items-center text-xs text-muted-foreground ml-4">
                                <Trophy class="w-3 h-3 mr-1" />
                                {{ challenge.total_articles }}
                            </div>
                        </div>
                        
                        <!-- Challenge Stats -->
                        <div class="flex items-center gap-4 mb-4 text-sm text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <Users class="w-4 h-4" />
                                {{ challenge.stats.total_participants }} participantes
                            </div>
                            <div class="flex items-center gap-1">
                                <CheckCircle class="w-4 h-4" />
                                {{ challenge.stats.completion_rate }}% concluído
                            </div>
                        </div>
                        
                        <!-- Creator -->
                        <div class="flex items-center gap-2 mb-4 text-sm">
                            <Avatar class="w-6 h-6">
                                <AvatarFallback class="text-xs">
                                    {{ getInitials(challenge.creator.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="text-muted-foreground">por {{ challenge.creator.name }}</span>
                        </div>
                        
                        <!-- Action Button -->
                        <GameButton 
                            :href="route('challenges.show', challenge.uuid)"
                            variant="purple"
                            class="w-full"
                        >
                            Ver Desafio
                        </GameButton>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <Trophy class="w-16 h-16 text-muted-foreground mx-auto mb-4" />
                    <h3 class="text-xl font-semibold mb-2">Nenhum desafio encontrado</h3>
                    <p class="text-muted-foreground mb-6">
                        {{ search ? 'Tente ajustar sua busca ou' : 'Seja o primeiro a' }}
                        criar um desafio para a comunidade!
                    </p>
                    <GameButton 
                        :href="route('challenges.create')"
                        variant="green"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="w-4 h-4" />
                        Criar Primeiro Desafio
                    </GameButton>
                </div>
                
                <!-- Pagination -->
                <div v-if="challenges.data.length > 0 && challenges.links.length > 3" class="mt-8">
                    <nav class="flex justify-center">
                        <div class="flex items-center gap-2">
                            <template v-for="link in challenges.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="[
                                        link.active 
                                            ? 'bg-primary text-primary-foreground' 
                                            : 'bg-card border border-border hover:bg-accent'
                                    ]"
                                >
                                    {{ link.label }}
                                </Link>
                                <span
                                    v-else
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    {{ link.label }}
                                </span>
                            </template>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { Trophy, Users, CheckCircle, Plus, Search, User } from 'lucide-vue-next';
import { ref } from 'vue';

interface Challenge {
    uuid: string;
    title: string;
    description?: string;
    total_articles: number;
    creator: {
        name: string;
    };
    stats: {
        total_participants: number;
        completion_rate: number;
    };
}

interface PaginatedChallenges {
    data: Challenge[];
    links: Array<{
        url?: string;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    challenges: PaginatedChallenges;
    search?: string;
}>();

const searchQuery = ref(props.search || '');

const searchChallenges = () => {
    router.get(route('challenges.index'), {
        search: searchQuery.value || undefined
    }, {
        preserveState: true
    });
};
</script>