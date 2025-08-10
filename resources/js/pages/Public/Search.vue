<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="keywords" :content="meta.keywords" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <link rel="canonical" :href="route('public.search', { q: query })" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100">
        <!-- Navbar -->
        <PublicNavbar />

        <!-- Search Section -->
        <section class="py-8 bg-white border-b border-gray-200">
            <div class="container mx-auto px-4">
                <div class="max-w-4xl mx-auto">
                    <!-- Back button -->
                    <div class="mb-6">
                        <Link :href="route('public.laws')" class="inline-flex items-center text-gray-600 hover:text-green-600 font-medium transition-colors">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Voltar para todas as leis
                        </Link>
                    </div>
                    
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">
                            Encontre Artigos Específicos
                        </h2>
                        <p class="text-lg text-gray-600">
                            Digite o número do artigo ou palavras-chave para encontrar rapidamente o que você procura
                        </p>
                    </div>
                    
                    <!-- Enhanced Search Bar -->
                    <div class="relative mb-6">
                        <div class="flex">
                            <input
                                type="search"
                                v-model="searchQuery"
                                @keyup.enter="performSearch"
                                placeholder="Ex: artigo 5º, propriedade, código civil..."
                                class="flex-1 pl-6 pr-4 py-4 text-lg border border-gray-300 rounded-l-xl focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white"
                                autofocus
                            />
                            <button 
                                @click="performSearch"
                                class="game-button bg-green-500 hover:bg-green-600 text-white px-8 py-4 rounded-r-xl border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold hover:transform hover:translate-y-1 hover:shadow-[0_2px_0_theme(colors.green.700)] transition-all"
                            >
                                <Search class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Quick Search Suggestions -->
                    <div class="flex flex-wrap justify-center gap-2 mb-8">
                        <span class="text-sm text-gray-500 dark:text-gray-400 mr-2">Sugestões:</span>
                        <button 
                            v-for="suggestion in quickSuggestions" 
                            :key="suggestion"
                            @click="searchQuery = suggestion; performSearch()"
                            class="game-button text-sm bg-white text-gray-600 hover:bg-green-50 hover:text-green-600 px-3 py-1 rounded-full border-2 border-gray-200 hover:border-green-300 font-medium transition-all hover:transform hover:translate-y-1"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Results Section -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <div class="max-w-4xl mx-auto">
                    <!-- Results Header -->
                    <div v-if="query" class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-900">
                                Resultados para "{{ query }}"
                            </h3>
                            <span class="text-sm text-gray-500">
                                {{ results.length }} resultado{{ results.length !== 1 ? 's' : '' }} encontrado{{ results.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>

                    <!-- No Results -->
                    <div v-if="query && results.length === 0" class="text-center py-12">
                        <div class="text-gray-400 mb-6">
                            <SearchX class="w-16 h-16 mx-auto mb-4" />
                            <h3 class="text-xl font-semibold mb-2">Nenhum resultado encontrado</h3>
                            <p class="text-gray-500 mb-4">
                                Não encontramos artigos com o termo "{{ query }}"
                            </p>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-md mx-auto">
                            <h4 class="font-medium text-yellow-800 mb-2">Dicas de busca:</h4>
                            <ul class="text-sm text-yellow-700 text-left space-y-1">
                                <li>• Tente termos mais genéricos</li>
                                <li>• Use apenas números para artigos (ex: "121")</li>
                                <li>• Verifique a ortografia</li>
                                <li>• Experimente sinônimos</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Results List -->
                    <div v-else-if="results.length > 0" class="space-y-4">
                        <div 
                            v-for="article in results" 
                            :key="article.uuid"
                            class="bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 border border-gray-200"
                        >
                            <div class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-3">
                                            <span class="text-lg font-bold text-green-600 mr-3">
                                                Art. {{ article.article_reference }}
                                            </span>
                                            <span class="text-sm text-gray-500 mr-3">
                                                {{ article.law_name }}
                                            </span>
                                            <span 
                                                :class="getDifficultyClass(article.difficulty_level)"
                                                class="text-xs px-2 py-1 rounded-full font-medium"
                                            >
                                                {{ getDifficultyText(article.difficulty_level) }}
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-600 text-sm mb-4 leading-relaxed" 
                                           v-html="highlightSearchTerm(article.preview_content, query)">
                                        </p>
                                        
                                        <div class="flex items-center text-xs text-gray-500 space-x-4">
                                            <span>{{ article.law_name }}</span>
                                            <span>•</span>
                                            <span>{{ getDifficultyText(article.difficulty_level) }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-4">
                                        <Link 
                                            :href="article.url"
                                            class="game-button bg-white text-gray-900 text-sm px-4 py-2 rounded-lg border-4 border-gray-300 font-bold hover:transform hover:translate-y-1 transition-all inline-flex items-center gap-1"
                                            style="box-shadow: 0 3px 0 rgb(209, 213, 219);"
                                            @mouseover="$event.target.style.boxShadow = '0 1px 0 rgb(209, 213, 219)'"
                                            @mouseout="$event.target.style.boxShadow = '0 3px 0 rgb(209, 213, 219)'"
                                        >
                                            Praticar
                                            <ExternalLink class="w-3 h-3" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State (no search yet) -->
                    <div v-else class="text-center py-12">
                        <div class="text-gray-400 mb-6">
                            <Search class="w-16 h-16 mx-auto mb-4" />
                            <h3 class="text-xl font-semibold mb-2">Comece sua busca</h3>
                            <p class="text-gray-500">
                                Digite acima para encontrar artigos específicos
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-gray-800 py-12">
            <div class="container mx-auto px-4 text-center">
                <h3 class="text-2xl font-bold text-white mb-4">
                    Encontrou o que procurava?
                </h3>
                <p class="text-gray-300 text-lg mb-6 max-w-2xl mx-auto">
                    Cadastre-se para salvar seu progresso e ter acesso a ferramentas avançadas de estudo!
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link 
                        :href="route('register')"
                        class="game-button bg-green-500 hover:bg-green-600 text-white px-8 py-4 rounded-lg border-4 border-green-700 shadow-[0_6px_0_theme(colors.green.700)] font-bold hover:transform hover:translate-y-1 hover:shadow-[0_4px_0_theme(colors.green.700)] transition-all inline-flex items-center justify-center"
                    >
                        Cadastrar-se Gratuitamente
                    </Link>
                    <Link 
                        :href="route('public.laws')"
                        class="game-button bg-white text-gray-900 px-8 py-4 rounded-lg border-4 border-gray-300 font-bold hover:transform hover:translate-y-1 transition-all inline-flex items-center justify-center"
                        style="box-shadow: 0 6px 0 rgb(209, 213, 219);"
                        @mouseover="$event.target.style.boxShadow = '0 4px 0 rgb(209, 213, 219)'"
                        @mouseout="$event.target.style.boxShadow = '0 6px 0 rgb(209, 213, 219)'"
                    >
                        Explorar Todas as Leis
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowLeft, Search, SearchX, ExternalLink } from 'lucide-vue-next'
import PublicNavbar from '@/components/PublicNavbar.vue'

interface SearchResult {
    uuid: string
    article_reference: string
    law_name: string
    law_uuid: string
    preview_content: string
    difficulty_level: number
    url: string
}

interface Props {
    query: string
    results: SearchResult[]
    meta: {
        title: string
        description: string
        keywords: string
    }
}

const props = defineProps<Props>()

const searchQuery = ref(props.query || '')

const quickSuggestions = [
    'artigo 5',
    'propriedade',
    'contrato',
    'posse',
    'família',
    'casamento',
    'herança',
    'direitos',
    'obrigações'
]

const performSearch = () => {
    if (searchQuery.value.trim()) {
        router.get(route('public.search', { q: searchQuery.value.trim() }))
    }
}

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

const highlightSearchTerm = (text: string, searchTerm: string): string => {
    if (!searchTerm || !text) return text
    
    const regex = new RegExp(`(${searchTerm})`, 'gi')
    return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>')
}
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