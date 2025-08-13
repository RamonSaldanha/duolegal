<template>
    <Head :title="challenge.title" />
    
    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-4xl mx-auto">
                
                <!-- Breadcrumb -->
                <Link :href="route('challenges.index')" class="text-sm text-primary hover:underline mb-6 inline-flex items-center">
                    <ChevronLeft class="w-4 h-4 mr-1" />
                    Voltar aos Desafios
                </Link>
                
                <!-- Challenge Header -->
                <div class="bg-card border border-border rounded-lg p-6 mb-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-3">
                                <Trophy class="w-6 h-6 text-primary" />
                                <h1 class="text-3xl font-bold">{{ challenge.title }}</h1>
                            </div>
                            
                            <p v-if="challenge.description" class="text-muted-foreground mb-4">
                                {{ challenge.description }}
                            </p>
                            
                            <!-- Challenge Info -->
                            <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <FileText class="w-4 h-4" />
                                    {{ challenge.total_articles }} artigos
                                </div>
                                <div class="flex items-center gap-1">
                                    <Users class="w-4 h-4" />
                                    {{ stats.total_participants }} participantes
                                </div>
                                <div class="flex items-center gap-1">
                                    <CheckCircle class="w-4 h-4" />
                                    {{ stats.completion_rate }}% de conclusão
                                </div>
                                <div class="flex items-center gap-1">
                                    <Calendar class="w-4 h-4" />
                                    Criado em {{ formatDate(challenge.created_at) }}
                                </div>
                            </div>
                            
                            <!-- Creator -->
                            <div class="flex items-center gap-2 mt-4">
                                <Avatar class="w-8 h-8">
                                    <AvatarFallback>
                                        {{ getInitials(challenge.creator.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="text-sm">
                                    <span class="text-muted-foreground">Criado por</span>
                                    <span class="font-medium ml-1">{{ challenge.creator.name }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col gap-3">
                            <GameButton 
                                v-if="!isParticipating"
                                @click="joinChallenge"
                                variant="green"
                                class="flex items-center gap-2"
                                :disabled="joining"
                            >
                                <Loader2 v-if="joining" class="w-4 h-4 animate-spin" />
                                <Play v-else class="w-4 h-4" />
                                {{ joining ? 'Entrando...' : 'Participar' }}
                            </GameButton>
                            
                            <GameButton 
                                v-else
                                :href="route('challenges.map', challenge.uuid)"
                                variant="blue"
                                class="flex items-center gap-2"
                            >
                                <Map class="w-4 h-4" />
                                Continuar
                            </GameButton>
                            
                            <!-- Share Button -->
                            <GameButton 
                                @click="shareChallenge"
                                variant="white"
                                class="flex items-center gap-2"
                            >
                                <Share2 class="w-4 h-4" />
                                Compartilhar
                            </GameButton>
                            
                            <!-- Edit/Delete for owner -->
                            <div v-if="canEdit" class="flex gap-2">
                                <GameButton 
                                    :href="route('challenges.edit', challenge.uuid)"
                                    variant="white"
                                    size="sm"
                                >
                                    <Edit class="w-4 h-4" />
                                </GameButton>
                                <GameButton 
                                    @click="deleteChallenge"
                                    variant="red"
                                    size="sm"
                                    :disabled="deleting"
                                >
                                    <Loader2 v-if="deleting" class="w-4 h-4 animate-spin" />
                                    <Trash2 v-else class="w-4 h-4" />
                                </GameButton>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Section for Participants -->
                <div v-if="isParticipating && userProgress" class="bg-card border border-border rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">Seu Progresso</h2>
                    
                    <div class="space-y-4">
                        <!-- Progress Bar -->
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Progresso Geral</span>
                                <span>{{ completedArticles }} / {{ challenge.total_articles }}</span>
                            </div>
                            <div class="w-full bg-muted rounded-full h-2">
                                <div 
                                    class="bg-primary rounded-full h-2 transition-all duration-300"
                                    :style="{ width: `${(completedArticles / challenge.total_articles) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-muted/50 rounded-lg">
                                <div class="text-2xl font-bold text-primary">{{ completedArticles }}</div>
                                <div class="text-xs text-muted-foreground">Completados</div>
                            </div>
                            <div class="text-center p-3 bg-muted/50 rounded-lg">
                                <div class="text-2xl font-bold text-green-500">{{ averageScore }}%</div>
                                <div class="text-xs text-muted-foreground">Média</div>
                            </div>
                            <div class="text-center p-3 bg-muted/50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-500">{{ totalAttempts }}</div>
                                <div class="text-xs text-muted-foreground">Tentativas</div>
                            </div>
                            <div class="text-center p-3 bg-muted/50 rounded-lg">
                                <div class="text-2xl font-bold text-purple-500">{{ bestStreak }}</div>
                                <div class="text-xs text-muted-foreground">Melhor Sequência</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Challenge Preview -->
                <div class="bg-card border border-border rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Artigos do Desafio</h2>
                    <p class="text-sm text-muted-foreground mb-6">
                        Este desafio contém {{ challenge.total_articles }} artigos de diferentes leis. 
                        Complete-os em sequência para conquistar o desafio!
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="(article, index) in challengeArticles"
                            :key="article.id"
                            class="p-4 border border-border rounded-lg"
                            :class="{
                                'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800': isParticipating && isArticleCompleted(article.id),
                                'bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800': isParticipating && !isArticleCompleted(article.id) && canAccessArticle(index),
                                'bg-muted/50': isParticipating && !canAccessArticle(index)
                            }"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-medium">Art. {{ article.article_reference }}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">
                                        {{ getDifficultyText(article.difficulty_level) }}
                                    </span>
                                    <div v-if="isParticipating" class="flex items-center">
                                        <CheckCircle 
                                            v-if="isArticleCompleted(article.id)"
                                            class="w-4 h-4 text-green-500"
                                        />
                                        <Clock 
                                            v-else-if="canAccessArticle(index)"
                                            class="w-4 h-4 text-blue-500"
                                        />
                                        <Lock 
                                            v-else
                                            class="w-4 h-4 text-muted-foreground"
                                        />
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ article.legal_reference_name }}</p>
                            
                            <!-- Article Progress -->
                            <div v-if="isParticipating && getArticleProgress(article.id)" class="mt-2">
                                <div class="text-xs text-muted-foreground">
                                    Melhor: {{ getArticleProgress(article.id).best_score }}%
                                    ({{ getArticleProgress(article.id).attempts }} tentativas)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { 
    Trophy, Users, CheckCircle, Calendar, FileText, ChevronLeft, 
    Play, Map, Share2, Edit, Trash2, Clock, Lock, Loader2 
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface ChallengeArticle {
    id: number;
    article_reference: string;
    difficulty_level: number;
    legal_reference_name: string;
}

interface Challenge {
    uuid: string;
    title: string;
    description?: string;
    total_articles: number;
    created_at: string;
    created_by: number;
    creator: {
        name: string;
    };
}

interface UserProgressItem {
    best_score: number;
    attempts: number;
    is_completed: boolean;
}

interface UserProgress {
    [articleId: string]: UserProgressItem;
}

const props = defineProps<{
    challenge: Challenge;
    stats: {
        total_participants: number;
        completion_rate: number;
    };
    userProgress?: UserProgress;
    isParticipating: boolean;
    challengeArticles?: ChallengeArticle[];
}>();

const page = usePage();
const joining = ref(false);
const deleting = ref(false);

const canEdit = computed(() => {
    const authUser = page.props.auth.user as any;
    return authUser?.id === props.challenge.created_by;
});

const completedArticles = computed(() => {
    if (!props.userProgress) return 0;
    return Object.values(props.userProgress).filter(progress => progress.is_completed).length;
});

const averageScore = computed(() => {
    if (!props.userProgress) return 0;
    const scores = Object.values(props.userProgress)
        .filter(progress => progress.best_score > 0)
        .map(progress => progress.best_score);
    
    return scores.length > 0 ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length) : 0;
});

const totalAttempts = computed(() => {
    if (!props.userProgress) return 0;
    return Object.values(props.userProgress).reduce((total, progress) => total + progress.attempts, 0);
});

const bestStreak = computed(() => {
    if (!props.userProgress || !props.challengeArticles) return 0;
    
    let maxStreak = 0;
    let currentStreak = 0;
    
    for (const article of props.challengeArticles) {
        if (isArticleCompleted(article.id)) {
            currentStreak++;
            maxStreak = Math.max(maxStreak, currentStreak);
        } else {
            currentStreak = 0;
        }
    }
    
    return maxStreak;
});

const isArticleCompleted = (articleId: number): boolean => {
    return props.userProgress?.[articleId]?.is_completed || false;
};

const canAccessArticle = (index: number): boolean => {
    if (!props.isParticipating || !props.challengeArticles) return true;
    if (index === 0) return true;
    
    const previousArticle = props.challengeArticles[index - 1];
    return isArticleCompleted(previousArticle.id);
};

const getArticleProgress = (articleId: number): UserProgressItem | null => {
    return props.userProgress?.[articleId] || null;
};

const getDifficultyText = (level: number): string => {
    const difficulties = {
        1: 'Iniciante',
        2: 'Básico', 
        3: 'Intermediário',
        4: 'Avançado',
        5: 'Especialista'
    };
    return difficulties[level as keyof typeof difficulties] || 'Intermediário';
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('pt-BR');
};

const joinChallenge = () => {
    joining.value = true;
    router.post(route('challenges.join', props.challenge.uuid), {}, {
        onFinish: () => {
            joining.value = false;
        }
    });
};

const shareChallenge = async () => {
    const url = window.location.href;
    
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.challenge.title,
                text: `Participe do desafio: ${props.challenge.title}`,
                url: url
            });
        } catch {
            // Fallback to clipboard
            await navigator.clipboard.writeText(url);
            alert('Link copiado para a área de transferência!');
        }
    } else {
        // Fallback to clipboard
        await navigator.clipboard.writeText(url);
        alert('Link copiado para a área de transferência!');
    }
};

const deleteChallenge = () => {
    if (!confirm('Tem certeza que deseja excluir este desafio? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    deleting.value = true;
    router.delete(route('challenges.destroy', props.challenge.uuid), {
        onFinish: () => {
            deleting.value = false;
        }
    });
};
</script>