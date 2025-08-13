<template>
    <Head title="Meus Desafios" />
    
    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-7xl mx-auto">
                
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold">Meus Desafios</h1>
                            <p class="text-muted-foreground mt-2">
                                Gerencie os desafios que você criou
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <GameButton 
                                :href="route('challenges.index')"
                                variant="white"
                                class="flex items-center gap-2"
                            >
                                <Trophy class="w-4 h-4" />
                                Explorar Desafios
                            </GameButton>
                            
                            <GameButton 
                                :href="route('challenges.create')"
                                variant="green"
                                class="flex items-center gap-2"
                            >
                                <Plus class="w-4 h-4" />
                                Criar Novo Desafio
                            </GameButton>
                        </div>
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
                                <FileText class="w-3 h-3 mr-1" />
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
                            <div class="flex items-center gap-1">
                                <Eye class="w-4 h-4" />
                                {{ challenge.is_public ? 'Público' : 'Privado' }}
                            </div>
                        </div>
                        
                        <!-- Created Date -->
                        <div class="text-xs text-muted-foreground mb-4">
                            Criado em {{ formatDate(challenge.created_at) }}
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <GameButton 
                                :href="route('challenges.show', challenge.uuid)"
                                variant="blue"
                                class="flex-1 text-sm"
                            >
                                Ver Detalhes
                            </GameButton>
                            <GameButton 
                                :href="route('challenges.edit', challenge.uuid)"
                                variant="white"
                                size="sm"
                            >
                                <Edit class="w-4 h-4" />
                            </GameButton>
                            <GameButton 
                                @click="deleteChallenge(challenge)"
                                variant="red"
                                size="sm"
                                :disabled="deleting === challenge.uuid"
                            >
                                <Loader2 v-if="deleting === challenge.uuid" class="w-4 h-4 animate-spin" />
                                <Trash2 v-else class="w-4 h-4" />
                            </GameButton>
                        </div>
                    </div>
                </div>
                
                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <Trophy class="w-16 h-16 text-muted-foreground mx-auto mb-4" />
                    <h3 class="text-xl font-semibold mb-2">Você ainda não criou nenhum desafio</h3>
                    <p class="text-muted-foreground mb-6">
                        Crie seu primeiro desafio personalizado e compartilhe com outros usuários!
                    </p>
                    <GameButton 
                        :href="route('challenges.create')"
                        variant="green"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="w-4 h-4" />
                        Criar Meu Primeiro Desafio
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
import { 
    Trophy, Users, CheckCircle, Plus, Edit, Trash2, 
    FileText, Eye, Loader2
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Challenge {
    uuid: string;
    title: string;
    description?: string;
    total_articles: number;
    created_at: string;
    is_public: boolean;
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

defineProps<{
    challenges: PaginatedChallenges;
}>();

const deleting = ref<string | null>(null);

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('pt-BR');
};

const deleteChallenge = (challenge: Challenge) => {
    if (!confirm(`Tem certeza que deseja excluir o desafio "${challenge.title}"? Esta ação não pode ser desfeita.`)) {
        return;
    }
    
    deleting.value = challenge.uuid;
    router.delete(route('challenges.destroy', challenge.uuid), {
        onFinish: () => {
            deleting.value = null;
        }
    });
};
</script>