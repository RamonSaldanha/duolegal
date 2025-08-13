<template>
    <Head title="Editar Desafio" />
    
    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-4xl mx-auto">
                
                <!-- Header -->
                <div class="mb-8">
                    <Link :href="route('challenges.show', challenge.uuid)" class="text-sm text-primary hover:underline mb-4 inline-flex items-center">
                        <ChevronLeft class="w-4 h-4 mr-1" />
                        Voltar ao Desafio
                    </Link>
                    <h1 class="text-3xl font-bold">Editar Desafio</h1>
                    <p class="text-muted-foreground mt-2">
                        Modifique as informações e artigos do seu desafio
                    </p>
                </div>
                
                <form @submit.prevent="submitForm" class="space-y-8">
                    
                    <!-- Basic Info -->
                    <div class="bg-card border border-border rounded-lg p-6">
                        <h2 class="text-xl font-semibold mb-4">Informações Básicas</h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-sm font-medium mb-2">
                                    Título do Desafio *
                                </label>
                                <input
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    required
                                    placeholder="Ex: Intensivo Processo Civil e Direito Civil"
                                    class="w-full px-3 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-foreground placeholder:text-muted-foreground"
                                    :class="{ 'border-red-500': errors.title }"
                                />
                                <p v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</p>
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium mb-2">
                                    Descrição (opcional)
                                </label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    placeholder="Descreva o objetivo do seu desafio..."
                                    class="w-full px-3 py-2 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-foreground placeholder:text-muted-foreground"
                                    :class="{ 'border-red-500': errors.description }"
                                />
                                <p v-if="errors.description" class="text-red-500 text-sm mt-1">{{ errors.description }}</p>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_public"
                                    v-model="form.is_public"
                                    type="checkbox"
                                    class="rounded border-border focus:ring-2 focus:ring-primary bg-background text-primary"
                                />
                                <label for="is_public" class="text-sm">
                                    Tornar desafio público (outros usuários poderão encontrar e participar)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Article Selection -->
                    <div class="bg-card border border-border rounded-lg p-6">
                        <h2 class="text-xl font-semibold mb-4">Seleção de Artigos</h2>
                        <p class="text-sm text-muted-foreground mb-6">
                            Selecione as leis e os artigos que farão parte do seu desafio. 
                            Os artigos serão apresentados na ordem que você selecionar.
                        </p>
                        
                        <!-- Search Articles -->
                        <div class="mb-6">
                            <div class="relative">
                                <input
                                    v-model="articleSearch"
                                    type="text"
                                    placeholder="Buscar por lei ou número do artigo..."
                                    class="w-full px-3 py-2 pr-10 border border-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-foreground placeholder:text-muted-foreground"
                                />
                                <Search class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                            </div>
                        </div>
                        
                        <!-- Selected Articles Counter -->
                        <div v-if="form.selected_articles.length > 0" class="mb-4 p-3 bg-primary/10 border border-primary/20 rounded-lg">
                            <p class="text-sm font-medium text-primary">
                                {{ form.selected_articles.length }} artigo(s) selecionado(s)
                            </p>
                        </div>
                        
                        <!-- Laws and Articles List -->
                        <div class="space-y-6">
                            <div
                                v-for="legalRef in filteredLegalReferences"
                                :key="legalRef.id"
                                class="border border-border rounded-lg"
                            >
                                <div class="p-4 border-b border-border bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold">{{ legalRef.name }}</h3>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                @click="toggleAllArticlesInLaw(legalRef)"
                                                class="text-xs text-primary hover:underline"
                                            >
                                                {{ areAllArticlesSelected(legalRef) ? 'Desmarcar Todos' : 'Selecionar Todos' }}
                                            </button>
                                            <button
                                                type="button"
                                                @click="toggleLawExpanded(legalRef.id)"
                                                class="p-1 hover:bg-background rounded"
                                            >
                                                <ChevronDown 
                                                    class="w-4 h-4 transition-transform"
                                                    :class="{ 'rotate-180': expandedLaws.includes(legalRef.id) }"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="expandedLaws.includes(legalRef.id)" class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <div
                                            v-for="article in legalRef.articles"
                                            :key="article.id"
                                            class="flex items-center gap-2 p-2 border border-border rounded hover:bg-accent/50"
                                        >
                                            <input
                                                :id="`article-${article.id}`"
                                                v-model="form.selected_articles"
                                                :value="article.id"
                                                type="checkbox"
                                                class="rounded border-border focus:ring-2 focus:ring-primary bg-background text-primary"
                                            />
                                            <label
                                                :for="`article-${article.id}`"
                                                class="flex-1 text-sm cursor-pointer"
                                            >
                                                Art. {{ article.article_reference }}
                                                <span class="text-muted-foreground ml-1">
                                                    ({{ getDifficultyText(article.difficulty_level) }})
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p v-if="errors.selected_articles" class="text-red-500 text-sm mt-4">{{ errors.selected_articles }}</p>
                    </div>
                    
                    <!-- Selected Articles Order -->
                    <div v-if="form.selected_articles.length > 0" class="bg-card border border-border rounded-lg p-6">
                        <h2 class="text-xl font-semibold mb-4">Ordem dos Artigos</h2>
                        <p class="text-sm text-muted-foreground mb-4">
                            Arraste para reordenar como os artigos serão apresentados no desafio
                        </p>
                        
                        <div class="space-y-2">
                            <div
                                v-for="(articleId, index) in form.selected_articles"
                                :key="articleId"
                                class="flex items-center gap-3 p-3 border border-border rounded-lg bg-background"
                            >
                                <div class="text-sm font-medium text-muted-foreground w-8">
                                    {{ index + 1 }}.
                                </div>
                                <div class="flex-1">
                                    <span class="font-medium">
                                        Art. {{ getArticleReference(articleId) }}
                                    </span>
                                    <span class="text-muted-foreground ml-2">
                                        - {{ getLegalReferenceName(articleId) }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="removeArticle(articleId)"
                                    class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-between gap-4">
                        <GameButton 
                            :href="route('challenges.show', challenge.uuid)"
                            variant="white"
                        >
                            Cancelar
                        </GameButton>
                        
                        <GameButton 
                            type="submit"
                            variant="green"
                            :disabled="processing || form.selected_articles.length === 0"
                            class="flex items-center gap-2"
                        >
                            <Loader2 v-if="processing" class="w-4 h-4 animate-spin" />
                            <Edit v-else class="w-4 h-4" />
                            {{ processing ? 'Salvando...' : 'Salvar Alterações' }}
                        </GameButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { ChevronLeft, ChevronDown, Search, Edit, X, Loader2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface LawArticle {
    id: number;
    article_reference: string;
    difficulty_level: number;
}

interface LegalReference {
    id: number;
    name: string;
    articles: LawArticle[];
}

interface Challenge {
    uuid: string;
    title: string;
    description?: string;
    selected_articles: number[];
    is_public: boolean;
}

const props = defineProps<{
    challenge: Challenge;
    legalReferences: LegalReference[];
    errors?: Record<string, string>;
}>();

const form = ref({
    title: props.challenge.title,
    description: props.challenge.description || '',
    selected_articles: [...props.challenge.selected_articles],
    is_public: props.challenge.is_public
});

const processing = ref(false);
const articleSearch = ref('');
const expandedLaws = ref<number[]>([]);

// Expand first law by default
if (props.legalReferences.length > 0) {
    expandedLaws.value = [props.legalReferences[0].id];
}

const errors = computed(() => props.errors || {});

const filteredLegalReferences = computed(() => {
    if (!articleSearch.value) return props.legalReferences;
    
    const search = articleSearch.value.toLowerCase();
    return props.legalReferences.filter(legalRef => {
        const nameMatches = legalRef.name.toLowerCase().includes(search);
        const hasMatchingArticles = legalRef.articles.some(article => 
            article.article_reference.toLowerCase().includes(search)
        );
        return nameMatches || hasMatchingArticles;
    });
});

const toggleLawExpanded = (lawId: number) => {
    const index = expandedLaws.value.indexOf(lawId);
    if (index > -1) {
        expandedLaws.value.splice(index, 1);
    } else {
        expandedLaws.value.push(lawId);
    }
};

const areAllArticlesSelected = (legalRef: LegalReference) => {
    return legalRef.articles.every(article => 
        form.value.selected_articles.includes(article.id)
    );
};

const toggleAllArticlesInLaw = (legalRef: LegalReference) => {
    if (areAllArticlesSelected(legalRef)) {
        // Remove all articles from this law
        legalRef.articles.forEach(article => {
            const index = form.value.selected_articles.indexOf(article.id);
            if (index > -1) {
                form.value.selected_articles.splice(index, 1);
            }
        });
    } else {
        // Add all articles from this law
        legalRef.articles.forEach(article => {
            if (!form.value.selected_articles.includes(article.id)) {
                form.value.selected_articles.push(article.id);
            }
        });
    }
};

const removeArticle = (articleId: number) => {
    const index = form.value.selected_articles.indexOf(articleId);
    if (index > -1) {
        form.value.selected_articles.splice(index, 1);
    }
};

const getArticleReference = (articleId: number): string => {
    for (const legalRef of props.legalReferences) {
        const article = legalRef.articles.find(a => a.id === articleId);
        if (article) return article.article_reference;
    }
    return '';
};

const getLegalReferenceName = (articleId: number): string => {
    for (const legalRef of props.legalReferences) {
        const article = legalRef.articles.find(a => a.id === articleId);
        if (article) return legalRef.name;
    }
    return '';
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

const submitForm = () => {
    processing.value = true;
    
    router.put(route('challenges.update', props.challenge.uuid), form.value, {
        onFinish: () => {
            processing.value = false;
        }
    });
};
</script>