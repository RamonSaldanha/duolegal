<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="keywords" :content="meta.keywords" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <meta property="og:type" content="website" />
        <link rel="canonical" :href="route('public.law', { uuid: law.uuid })" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 dark:from-gray-900 dark:to-black">
        <!-- Navbar -->
        <PublicNavbar />

        <!-- Law Info Section -->
        <section class="py-8 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800">
            <div class="container mx-auto px-4">
                <div class="max-w-4xl mx-auto">
                    <!-- Back button -->
                    <div class="mb-6">
                        <Link :href="route('public.laws')" class="inline-flex items-center text-gray-600 dark:text-gray-300 hover:text-green-600 dark:hover:text-green-400 font-medium transition-colors">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Voltar para todas as leis
                        </Link>
                    </div>
                    
                    <div class="flex flex-col md:flex-row items-start justify-between mb-6">
                        <div class="flex-1">
                            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
                                {{ law.name }}
                            </h2>
                            <p v-if="law.description" class="text-lg text-gray-600 dark:text-gray-300 mb-4">
                                {{ law.description }}
                            </p>
                            <div class="flex items-center space-x-6 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center">
                                    <BookOpen class="w-4 h-4 mr-2" />
                                    {{ articles.length }} artigos disponíveis
                                </div>
                                <div class="flex items-center">
                                    <Zap class="w-4 h-4 mr-2" />
                                    {{ exerciseCount }} com exercícios
                                </div>
                                <div class="flex items-center">
                                    <Target class="w-4 h-4 mr-2" />
                                    {{ law.type || 'Lei' }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 md:mt-0 md:ml-8">
                            <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ articles.length }}
                                </div>
                                <div class="text-sm text-green-600 dark:text-green-300">
                                    Artigos para Praticar
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter and Search -->
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <div class="relative">
                                <input
                                    type="search"
                                    v-model="searchFilter"
                                    placeholder="Buscar por número do artigo..."
                                    class="w-full pl-4 pr-10 py-3 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white dark:bg-gray-900 dark:text-white"
                                />
                                <Search class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" />
                            </div>
                        </div>
                        <select 
                            v-model="difficultyFilter"
                            class="px-4 py-3 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Todos os níveis</option>
                            <option value="1">Iniciante</option>
                            <option value="2">Básico</option>
                            <option value="3">Intermediário</option>
                            <option value="4">Avançado</option>
                            <option value="5">Especialista</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <!-- Articles List -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <div class="max-w-4xl mx-auto">
                    <div v-if="filteredArticles.length === 0" class="text-center py-12">
                        <div class="text-gray-400 dark:text-gray-500 mb-4">
                            <Search class="w-12 h-12 mx-auto mb-4" />
                            <p class="text-lg">Nenhum artigo encontrado</p>
                            <p class="text-sm">Tente ajustar os filtros de busca</p>
                        </div>
                    </div>
                    
                    <div v-else class="grid gap-4">
                        <div 
                            v-for="article in paginatedArticles" 
                            :key="article.uuid"
                            class="bg-white dark:bg-gray-900 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 border border-gray-200 dark:border-gray-800"
                        >
                            <div class="p-4 sm:p-6">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center mb-3 gap-2">
                                            <span class="text-lg font-bold text-green-600 dark:text-green-400">
                                                Art. {{ article.article_reference }}
                                            </span>
                                            <span 
                                                :class="getDifficultyClass(article.difficulty_level)"
                                                class="text-xs px-2 py-1 rounded-full font-medium"
                                            >
                                                {{ getDifficultyText(article.difficulty_level) }}
                                            </span>
                                            <div v-if="article.has_exercise" class="flex items-center text-green-600 dark:text-green-400">
                                                <Zap class="w-4 h-4 mr-1" />
                                                <span class="text-xs">Exercício</span>
                                            </div>
                                        </div>
                                        
                                        <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                                            {{ article.preview_content }}
                                        </p>
                                    </div>
                                    
                                    <div class="flex justify-end sm:justify-start mt-4 sm:mt-0">
                                        <Link 
                                            :href="route('public.article', { lawUuid: law.uuid, articleUuid: article.uuid })"
                                            class="inline-flex items-center justify-center"
                                        >
                                            <GameButton variant="white" size="sm" class="inline-flex items-center gap-1">
                                                <span v-if="article.has_exercise">Praticar</span>
                                                <span v-else>Ver Artigo</span>
                                                <Play class="w-3 h-3" />
                                            </GameButton>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="filteredArticles.length > articlesPerPage" class="mt-8 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <button
                                @click="currentPage = Math.max(1, currentPage - 1)"
                                :disabled="currentPage === 1"
                                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-white"
                            >
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            
                            <span class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
                                Página {{ currentPage }} de {{ totalPages }}
                            </span>
                            
                            <button
                                @click="currentPage = Math.min(totalPages, currentPage + 1)"
                                :disabled="currentPage === totalPages"
                                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-white"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-gray-800 dark:bg-gray-950 py-12">
            <div class="container mx-auto px-4 text-center">
                <h3 class="text-2xl font-bold text-white mb-4">
                    Pratique de Forma Organizada
                </h3>
                <p class="text-gray-300 dark:text-gray-400 text-lg mb-6 max-w-2xl mx-auto">
                    Cadastre-se para ter acesso às fases organizadas, sistema de progresso, XP e muito mais!
                </p>
                <Link 
                    :href="route('register')"
                    class="inline-flex items-center justify-center"
                >
                    <GameButton variant="green" size="lg" class="px-8 py-4 inline-flex items-center gap-2">
                        Começar Agora Gratuitamente
                    </GameButton>
                </Link>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { ArrowLeft, BookOpen, Zap, Target, Search, Play, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import PublicNavbar from '@/components/PublicNavbar.vue'
import GameButton from '@/components/ui/GameButton.vue'

interface Article {
    uuid: string
    article_reference: string
    difficulty_level: number
    preview_content: string
    law_name: string
    has_exercise: boolean
}

interface Law {
    uuid: string
    name: string
    description: string
    type: string
}

interface Props {
    law: Law
    articles: Article[]
    meta: {
        title: string
        description: string
        keywords: string
    }
}

const props = defineProps<Props>()

const searchFilter = ref('')
const difficultyFilter = ref('')
const currentPage = ref(1)
const articlesPerPage = 20

const exerciseCount = computed(() => {
    return props.articles.filter(article => article.has_exercise).length
})

const filteredArticles = computed(() => {
    let filtered = props.articles

    if (searchFilter.value) {
        filtered = filtered.filter(article =>
            article.article_reference.includes(searchFilter.value) ||
            article.preview_content.toLowerCase().includes(searchFilter.value.toLowerCase())
        )
    }

    if (difficultyFilter.value) {
        filtered = filtered.filter(article =>
            article.difficulty_level.toString() === difficultyFilter.value
        )
    }

    return filtered
})

const totalPages = computed(() => {
    return Math.ceil(filteredArticles.value.length / articlesPerPage)
})

const paginatedArticles = computed(() => {
    const start = (currentPage.value - 1) * articlesPerPage
    const end = start + articlesPerPage
    return filteredArticles.value.slice(start, end)
})

const getDifficultyText = (level: number): string => {
    const texts = {
        1: 'Iniciante',
        2: 'Básico',
        3: 'Intermediário',
        4: 'Avançado',
        5: 'Especialista'
    }
    return texts[level] || 'Intermediário'
}

const getDifficultyClass = (level: number): string => {
    const classes = {
        1: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        2: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        3: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        4: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
        5: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
    }
    return classes[level] || classes[3]
}

// Reset page when filters change
computed(() => {
    currentPage.value = 1
    return [searchFilter.value, difficultyFilter.value]
})
</script>

<style scoped>
.game-button {
    transition: all 0.2s ease;
}

.game-button:hover {
    transform: translateY(2px);
}

.game-button:active {
    transform: translateY(4px);
    box-shadow: none !important;
}
</style>