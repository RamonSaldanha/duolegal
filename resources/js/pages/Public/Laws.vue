<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="keywords" :content="meta.keywords" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <meta property="og:type" content="website" />
        <link rel="canonical" :href="route('public.laws')" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 dark:from-gray-900 dark:to-black">
        <!-- Navbar -->
        <PublicNavbar />

        <!-- Hero Section -->
        <section class="py-12 text-center">
            <div class="container mx-auto px-4">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                    Pratique <span class="text-green-600">Gratuitamente</span><br>
                    Todos os Artigos de Direito
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-3xl mx-auto">
                    Explore e pratique artigos de diversas leis brasileiras. Sistema gamificado para memorizar 
                    efetivamente cada artigo. Experimente gratuitamente antes de se cadastrar!
                </p>
                
                <!-- Barra de busca -->
                <div class="max-w-md mx-auto mb-8">
                    <div class="relative">
                        <input
                            type="search"
                            v-model="searchQuery"
                            @keyup.enter="performSearch"
                            placeholder="Buscar artigos..."
                            class="w-full pl-4 pr-12 py-3 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white dark:bg-gray-900 dark:text-white shadow-sm"
                        />
                        <button 
                            @click="performSearch"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-green-600 dark:hover:text-green-400 p-2"
                        >
                            <Search class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                
                <div class="flex flex-wrap justify-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center">
                        <CheckCircle class="w-4 h-4 text-green-500 mr-1" />
                        <span>{{ totalArticles }}+ artigos</span>
                    </div>
                    <div class="flex items-center">
                        <CheckCircle class="w-4 h-4 text-green-500 mr-1" />
                        <span>{{ legalReferences.length }}+ leis diferentes</span>
                    </div>
                    <div class="flex items-center">
                        <CheckCircle class="w-4 h-4 text-green-500 mr-1" />
                        <span>Exercícios interativos</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Laws Grid -->
        <section class="pb-16">
            <div class="container mx-auto px-4">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-8 text-center">
                    Escolha uma Lei para Praticar
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div 
                        v-for="law in legalReferences" 
                        :key="law.uuid"
                        class="bg-white dark:bg-gray-900 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-gray-200 dark:border-gray-800"
                    >
                        <div class="p-4 sm:p-6">
                            <div class="mb-4">
                                <div class="flex items-start justify-between mb-3">
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white line-clamp-2 flex-1 pr-2">
                                        {{ law.name }}
                                    </h4>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-1 rounded-full whitespace-nowrap flex-shrink-0">
                                        {{ law.type || 'Lei' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                    {{ law.description || 'Pratique os artigos desta importante lei brasileira.' }}
                                </p>
                            </div>
                            
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <BookOpen class="w-4 h-4 inline mr-1" />
                                    {{ law.total_articles }} artigos
                                </div>
                                <div class="flex items-center text-sm text-blue-600">
                                    <Zap class="w-4 h-4 mr-1" />
                                    Exercícios
                                </div>
                            </div>

                            <!-- Sample Articles Preview -->
                            <div v-if="law.sample_articles && law.sample_articles.length > 0" class="mb-4">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Artigos em destaque:</div>
                                <div class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="article in law.sample_articles.slice(0, 5)" 
                                        :key="article.uuid"
                                        class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-1 rounded"
                                    >
                                        Art. {{ article.article_reference }}
                                    </span>
                                    <span v-if="law.total_articles > 5" class="text-xs text-gray-400">
                                        +{{ law.total_articles - 5 }} mais
                                    </span>
                                </div>
                            </div>
                            
                            <Link
                                :href="route('public.law', { legalReference: law.slug || law.uuid })"
                                class="block w-full"
                            >
                                <GameButton variant="white" class="w-full flex items-center justify-center gap-2 min-w-0">
                                    <span class="truncate">Explorar Artigos</span>
                                    <ChevronRight class="w-4 h-4 flex-shrink-0" />
                                </GameButton>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-gray-800 dark:bg-gray-950 py-16">
            <div class="container mx-auto px-4 text-center">
                <h3 class="text-3xl font-bold text-white mb-4">
                    Pronto para Acelerar seus Estudos?
                </h3>
                <p class="text-gray-300 dark:text-gray-400 text-lg mb-8 max-w-2xl mx-auto">
                    Cadastre-se gratuitamente e tenha acesso ao sistema completo de progresso, 
                    XP, fases organizadas e muito mais!
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link 
                        :href="route('register')"
                        class="flex items-center justify-center"
                    >
                        <GameButton variant="green" size="lg" class="flex items-center gap-2">
                            Cadastrar-se Gratuitamente
                        </GameButton>
                    </Link>
                    <Link 
                        :href="route('login')"
                        class="flex items-center justify-center"
                    >
                        <GameButton variant="white" size="lg" class="flex items-center gap-2">
                            Já tenho conta
                        </GameButton>
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { BookOpen, Zap, ChevronRight, Search, CheckCircle } from 'lucide-vue-next'
import PublicNavbar from '@/components/PublicNavbar.vue'
import GameButton from '@/components/ui/GameButton.vue'

interface SampleArticle {
    uuid: string
    article_reference: string
    difficulty_level: number
}

interface LegalReference {
    uuid: string
    slug?: string
    name: string
    description: string
    type: string
    total_articles: number
    sample_articles: SampleArticle[]
}

interface Props {
    legalReferences: LegalReference[]
    meta: {
        title: string
        description: string
        keywords: string
    }
}

const props = defineProps<Props>()
const searchQuery = ref('')

const totalArticles = computed(() => {
    return props.legalReferences.reduce((sum, law) => sum + law.total_articles, 0)
})

const performSearch = () => {
    if (searchQuery.value.trim()) {
        router.get(route('public.search', { q: searchQuery.value.trim() }))
    }
}
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

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