<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta name="keywords" :content="meta.keywords" />
        <meta property="og:title" :content="meta.title" />
        <meta property="og:description" :content="meta.description" />
        <meta property="og:type" content="article" />
        <link rel="canonical" :href="route('public.article', { lawUuid: article.law_uuid, articleUuid: article.uuid })" />
    </Head>

    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
        <!-- Navbar -->
        <PublicNavbar />
        
        <Toaster
          position="top-right"
          class="z-[200]"
        />

        <!-- Elementos para os efeitos de recompensa -->
        <span id="confetti-canvas" class="fixed top-1/2 left-1/2 z-[100] pointer-events-none"></span>
        <span id="emoji-canvas" class="fixed top-1/2 left-1/2 z-[100] pointer-events-none"></span>
        
        <div class="container py-4 md:py-8 px-3 md:px-4">

            <div class="w-full sm:w-[95%] lg:w-[50rem] mx-auto">
                <!-- Cabeçalho da fase - versão responsiva -->
                <div class="mb-4 md:mb-4">
                    <!-- Mostrar apenas no desktop -->
                    <Link :href="route('public.law', { uuid: article.law_uuid })" class="hidden md:flex text-sm items-center text-primary hover:underline mb-4">
                        <ChevronLeft class="h-3 w-3 mr-1" />
                        Voltar para {{ article.law_name }}
                    </Link>

                    <!-- Título e nível apenas no desktop - tamanho reduzido -->
                    <div class="hidden md:block md:mb-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Art. {{ article.article_reference }} - {{ article.law_name }}</h1>
                        <p class="text-muted-foreground mt-1">
                            Nível:
                            <span
                                :class="`inline-flex items-center px-2 py-1 rounded-full text-xs text-white ${getDifficultyColor(article.difficulty_level)}`"
                            >
                                {{ getDifficultyText(article.difficulty_level) }}
                            </span>
                            <span class="ml-3 text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 px-2 py-1 rounded-full">
                                Exercício Público - Sem Login
                            </span>
                        </p>
                    </div>

                    <!-- Barra de progresso - sempre visível -->
                    <div class="w-full">
                        <div class="flex items-center justify-between w-full mb-2">
                            <!-- Container da barra de progresso -->
                            <div class="relative h-2 bg-muted rounded-full overflow-hidden grow">
                                <!-- Indicador do artigo atual -->
                                <div class="absolute h-full bg-blue-500 transition-all duration-300 w-full"></div>
                            </div>
                            
                            <!-- Botão de voltar - apenas no mobile -->
                            <Link 
                                :href="route('public.law', { uuid: article.law_uuid })" 
                                class="md:hidden ml-3 rounded-full p-1.5 bg-muted hover:bg-muted/80 transition-colors"
                            >
                                <X class="h-4 w-4" />
                            </Link>
                        </div>
                        
                        <!-- Texto de progresso apenas no desktop -->
                        <div class="mt-2 text-sm text-center text-muted-foreground hidden md:block">
                            Exercício público - Cadastre-se para acompanhar seu progresso
                        </div>
                    </div>
                </div>

                <!-- Artigo atual -->
                <Card class="mb-6 border-0 p-0 bg-gradient-to-br from-slate-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
                    <CardHeader class="">
                        <CardTitle>Art. {{ article.article_reference }} - Leia e responda:</CardTitle>
                    </CardHeader>

                    <CardContent class="pb-0">
                        <!-- Texto com lacunas - aumentado no mobile -->
                        <div
                            ref="textContainerRef"
                            class="rounded-md mb-7 text-xl md:text-lg leading-relaxed whitespace-pre-line overflow-hidden transition-all duration-300 font-medium"
                            :class="{ 'mobile-collapsed': isMobile && !allLacunasFilled }"
                            :style="isMobile ? { maxHeight: textContainerHeight + 'px' } : {}"
                            v-html="processedText"
                        ></div>
                        
                        <button 
                            v-if="isMobile && hasHiddenLacunas" 
                            @click="toggleTextContainer"
                            class="md:hidden w-full py-2 text-sm text-primary flex items-center justify-center border-t border-primary/10 -mt-2 mb-4"
                        >
                            <span v-if="isTextExpanded">Ver menos</span>
                            <span v-else>Expandir texto</span>
                            <ChevronDown v-if="!isTextExpanded" class="ml-1 h-4 w-4" />
                            <ChevronUp v-else class="ml-1 h-4 w-4" />
                        </button>
                        
                        <!-- Offcanvas - alterado para garantir visibilidade e minimize correto -->
                        <div 
                        class="offcanvas-container"
                        :class="{ 
                            'offcanvas-open': !offcanvasMinimize,
                            'offcanvas-closed': !offcanvasMinimize && !answered
                        }"
                        >
                        <div class="offcanvas-content bg-background border-t border-border">
                            <!-- Conteúdo do offcanvas -->
                            <div class="flex items-center justify-between border-b border-border pb-4 mb-4">
                                <div class="flex items-center gap-3 text-xl">
                                        <div 
                                            :class="[
                                                'flex items-center justify-center w-12 h-12 rounded-full',
                                                (articleScore && articleScore.percentage >= 70) ? 'bg-green-100 dark:bg-green-950' : 'bg-red-100 dark:bg-red-950'
                                            ]"
                                        >
                                            <Check 
                                                v-if="articleScore && articleScore.percentage >= 70" 
                                                class="w-6 h-6 text-green-600"
                                            />
                                            <X 
                                                v-else 
                                                class="w-6 h-6 text-red-600"
                                            />
                                        </div>
                                        <span class="font-semibold">
                                            {{ articleScore && articleScore.percentage >= 70 ? 'Parabéns!' : 'Continue tentando!' }}
                                        </span>
                                    </div>
                                    
                                    <button 
                                        @click="closeOffcanvas"
                                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>

                                <div v-if="articleScore">
                                    <!-- Mensagem especial para exercício público -->
                                    <div class="bg-blue-100 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 p-3 rounded-lg text-center mb-4">
                                        <p class="text-sm font-medium mb-1">✨ Exercício Público Concluído!</p>
                                        <p class="text-xs">
                                            Cadastre-se gratuitamente para salvar seu progresso, ganhar XP e acessar o sistema completo!
                                        </p>
                                    </div>

                                    <div class="text-lg font-medium mb-2">
                                        Você acertou {{ articleScore.correct }} de {{ articleScore.total }} lacunas ({{ articleScore.percentage }}%)
                                    </div>

                                    <div class="mt-6">
                                        <h3 class="font-medium mb-2">Texto original:</h3>
                                        <div class="p-1 bg-muted/70 dark:bg-muted/10 border border-muted/80 rounded-md max-h-[150px] overflow-y-auto">
                                            <p class="whitespace-pre-line text-md" v-html="highlightedOriginalText">
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Nova seção para mostrar as respostas do usuário -->
                                    <div class="mt-4">
                                        <h3 class="font-medium mb-2">Suas respostas:</h3>
                                        <div class="p-2 bg-muted/70 dark:bg-muted/10 border border-muted/80 rounded-md max-h-[150px] overflow-y-auto">
                                            <p class="whitespace-pre-line text-md" v-html="highlightedUserAnswers"></p>
                                        </div>
                                        
                                    </div>

                                    <!-- CTA Buttons -->
                                    <div class="mt-6 flex flex-col gap-3">
                                        <Link 
                                            :href="route('register')"
                                            class="game-button w-full bg-green-500 hover:bg-green-600 text-white text-center py-3 px-4 rounded-lg border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold hover:transform hover:translate-y-1 hover:shadow-[0_2px_0_theme(colors.green.700)] transition-all"
                                        >
                                            <UserPlus class="w-4 h-4 inline mr-2" />
                                            Cadastrar-se Gratuitamente
                                        </Link>
                                        
                                        <div class="flex gap-3">
                                            <GameButton 
                                                @click="resetAnswers"
                                                variant="white"
                                                size="sm"
                                                class="flex-1 flex items-center justify-center gap-2"
                                            >
                                                <RefreshCw class="h-4 w-4" />
                                                Tentar novamente
                                            </GameButton>
                                            
                                            <GameButton 
                                                @click="router.visit(route('login'))"
                                                variant="white"
                                                size="sm"
                                                class="flex-1 flex items-center justify-center"
                                            >
                                                Já tenho conta
                                            </GameButton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resto do conteúdo sem alteração -->
                    </CardContent>

                    <CardFooter class="px-4">
                        <!-- Botões na parte inferior do card -->
                        <div v-if="!answered" class="w-full">
                            <div v-if="availableOptions.length > 0">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <GameButton
                                        v-for="(word, index) in availableOptions"
                                        :key="`word-${index}`"
                                        @click="selectWord(word)"
                                        variant="white"
                                        size="sm"
                                    >
                                        {{ word }}
                                    </GameButton>
                                </div>
                            </div>
                            
                            <!-- Botões de ações -->
                            <div class="flex justify-between mt-6 pt-4 border-t">
                                <GameButton 
                                    @click="resetAnswers"
                                    variant="white"
                                    class="flex items-center gap-2"
                                >
                                    <RefreshCw class="h-4 w-4" />
                                    <span class="hidden md:inline">Limpar</span>
                                    <span class="md:hidden">Limpar</span>
                                </GameButton>

                                <GameButton 
                                    :disabled="!allLacunasFilled" 
                                    @click="checkAnswers"
                                    variant="purple"
                                >
                                    Verificar
                                </GameButton>
                            </div>
                        </div>

                        <!-- Botões após verificação -->
                        <div v-else class="w-full flex justify-between">
                            <GameButton 
                                @click="resetAnswers"
                                variant="white"
                                class="flex items-center gap-2"
                            >
                                <RefreshCw class="h-4 w-4" />
                                <span class="hidden md:inline">Tentar novamente</span>
                                <span class="md:hidden">Novamente</span>
                            </GameButton>
                        </div>
                    </CardFooter>
                </Card>

                <!-- Related Articles -->
                <div v-if="relatedArticles.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            Outros artigos do {{ article.law_name }}
                        </h3>
                        <div class="space-y-3">
                            <Link 
                                v-for="relatedArticle in relatedArticles"
                                :key="relatedArticle.uuid"
                                :href="relatedArticle.url"
                                class="block p-4 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors border border-gray-200 dark:border-gray-600"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-600 dark:text-blue-400 mb-1">
                                            Art. {{ relatedArticle.article_reference }}
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                            {{ relatedArticle.preview_content }}
                                        </p>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-gray-400 ml-2 flex-shrink-0" />
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Dialog -->
        <transition 
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="showResetConfirmDialog" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
                <div class="max-w-md w-full bg-background rounded-lg shadow-lg p-6 border border-border">
                    <div class="text-center">
                        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900/40 dark:text-yellow-300">
                            <AlertTriangle class="h-8 w-8" />
                        </div>
                        <h2 class="text-xl font-bold mb-4">Limpar respostas?</h2>
                        <p class="mb-6">Você perderá todas as respostas que já preencheu neste artigo. Tem certeza?</p>
                        
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <GameButton 
                                @click="showResetConfirmDialog = false"
                                variant="white"
                            >
                                Cancelar
                            </GameButton>
                            <GameButton 
                                @click="() => { showResetConfirmDialog = false; resetAnswersConfirmed(); }"
                                variant="red"
                            >
                                Sim, limpar tudo
                            </GameButton>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { 
    ChevronLeft, ChevronRight, Check, X, RefreshCw, ChevronDown, ChevronUp, 
    AlertTriangle, UserPlus
} from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card'
import GameButton from '@/components/ui/GameButton.vue'
import { useWindowSize } from '@vueuse/core'
import { useToast } from '@/components/ui/toast/use-toast'
import Toaster from '@/components/ui/toast/Toaster.vue'
import PublicNavbar from '@/components/PublicNavbar.vue'
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import { useReward } from 'vue-rewards'

const { toast } = useToast()

interface ArticleOption {
    id: number
    word: string
    is_correct: boolean
    gap_order: number
    position: number
}

interface Article {
    uuid: string
    article_reference: string
    original_content: string
    practice_content: string
    difficulty_level: number
    law_name: string
    law_uuid: string
    options: ArticleOption[]
}

interface RelatedArticle {
    uuid: string
    article_reference: string
    preview_content: string
    url: string
}

interface Props {
    article: Article
    relatedArticles: RelatedArticle[]
    meta: {
        title: string
        description: string
        keywords: string
    }
}

const props = defineProps<Props>()

// State
const textContainerRef = ref<HTMLElement | null>(null)
const userAnswers = ref<{ [lacunaIndex: number]: string }>({})
const answered = ref(false)
const offcanvasMinimize = ref(false)
const showResetConfirmDialog = ref(false)

// Mobile handling
const isTextExpanded = ref(false)
const textContainerHeight = ref(200) 
const hasHiddenLacunas = ref(false)
const { width } = useWindowSize()
const isMobile = computed(() => width.value < 768)

// Configure as recompensas
const { reward: confettiReward } = useReward('confetti-canvas', 'confetti', {
    startVelocity: 30, 
    spread: 360,
    elementCount: 100,
    decay: 0.94,
    colors: ['#26ccff', '#a25afd', '#ff5e7e', '#88ff5a', '#fcff42', '#ffa62d'],
    zIndex: 100
});

const { reward: emojiReward } = useReward('emoji-canvas', 'emoji', {
    emoji: ['🎓', '✨', '👏', '🏆'],
    elementCount: 20,
    spread: 50,
    zIndex: 100
});

// Computed properties
const articleOptions = computed(() => {
    if (!props.article.options || props.article.options.length === 0) {
        return {
            allOptions: [],
            correctAnswers: new Map<number, string>()
        }
    }

    const allWords: string[] = []
    const correctAnswers = new Map<number, string>()

    props.article.options.forEach(option => {
        allWords.push(option.word)
        if (option.is_correct) {
            correctAnswers.set(option.gap_order, option.word)
        }
    })

    // Shuffle words
    const shuffledWords = [...allWords].sort(() => Math.random() - 0.5)

    return {
        allOptions: shuffledWords,
        correctAnswers: correctAnswers
    }
})

const availableOptions = computed(() => {
    const usedWords = Object.values(userAnswers.value)
    return articleOptions.value.allOptions.filter(word => !usedWords.includes(word))
})

const totalLacunas = computed(() => {
    if (!props.article.practice_content) return 0
    const matches = props.article.practice_content.match(/_{5,}/g)
    return matches ? matches.length : 0
})

const allLacunasFilled = computed(() => {
    return Object.keys(userAnswers.value).length === totalLacunas.value
})

const processedText = computed(() => {
    if (!props.article.practice_content) return ''
    
    let text = props.article.practice_content
    const lacunas = text.match(/_{5,}/g) || []
    
    lacunas.forEach((lacuna, index) => {
        const selectedWord = userAnswers.value[index]
        const correctAnswer = articleOptions.value.correctAnswers.get(index + 1)
        
        const replacement = answered.value 
            ? (selectedWord
                ? `<span class="lacuna ${selectedWord === correctAnswer ? 'correct' : 'incorrect'}">${selectedWord}</span>`
                : `<span class="lacuna empty lacuna-empty-${document.documentElement.classList.contains('dark') ? 'dark' : 'light'}">(...)</span>`)
            : (selectedWord
                ? `<span class="lacuna filled" data-lacuna-index="${index}">${selectedWord}<span class="lacuna-remove-indicator">×</span></span>`
                : `<span class="lacuna empty lacuna-empty-${document.documentElement.classList.contains('dark') ? 'dark' : 'light'}">(...)</span>`)
        
        text = text.replace(lacuna, replacement)
    })

    // Mobile text limiting
    if (isMobile.value && !isTextExpanded.value && !allLacunasFilled.value) {
        const firstEmptyIndex = text.indexOf('<span class="lacuna empty')
        if(firstEmptyIndex !== -1) {
            const nextLineBreak = text.indexOf('\n', firstEmptyIndex)
            if(nextLineBreak !== -1) {
                text = text.substring(0, nextLineBreak)
            }
        }
    }
    return text
})

const articleScore = computed(() => {
    if (!answered.value) return null
    
    let correctCount = 0
    Object.entries(userAnswers.value).forEach(([lacunaIndex, userAnswer]) => {
        const gapNumber = Number(lacunaIndex) + 1
        const correctAnswer = articleOptions.value.correctAnswers.get(gapNumber)
        if (correctAnswer && userAnswer === correctAnswer) {
            correctCount++
        }
    })

    return {
        correct: correctCount,
        total: totalLacunas.value,
        percentage: Math.round((correctCount / totalLacunas.value) * 100)
    }
})

const highlightedOriginalText = computed(() => {
    if (!props.article.original_content) return ''
    
    let result = props.article.original_content
    
    // Highlight correct words
    articleOptions.value.correctAnswers.forEach((word) => {
        const wordIndex = result.indexOf(word)
        if (wordIndex >= 0) {
            result = result.substring(0, wordIndex) +
                    `<strong class="text-primary font-bold underline decoration-2 underline-offset-2">${word}</strong>` +
                    result.substring(wordIndex + word.length)
        }
    })
    
    return result
})

const highlightedUserAnswers = computed(() => {
    if (!answered.value) return ''

    let text = props.article.practice_content
    const lacunas = text.match(/_{5,}/g) || []
    
    lacunas.forEach((lacuna, index) => {
        const userAnswer = userAnswers.value[index]
        const correctAnswer = articleOptions.value.correctAnswers.get(index + 1)
        
        let replacement
        if (userAnswer) {
            const isCorrect = userAnswer === correctAnswer
            replacement = isCorrect 
                ? `<span class="px-1 py-0.5 rounded font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">${userAnswer}</span>`
                : `<span class="px-1 py-0.5 rounded font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">${userAnswer}</span>`
        } else {
            replacement = `<span class="px-1 py-0.5 rounded font-medium bg-gray-100 text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">(...)</span>`
        }
        
        text = text.replace(lacuna, replacement)
    })
    
    return text
})

// Methods
const selectWord = (word: string) => {
    if (answered.value) return

    // Find next empty lacuna
    for (let i = 0; i < totalLacunas.value; i++) {
        if (!userAnswers.value.hasOwnProperty(i)) {
            userAnswers.value[i] = word
            break
        }
    }

    // Mobile scroll handling
    if (isMobile.value) {
        setTimeout(() => {
            scrollToNextEmptyLacuna()
        }, 200)
    }
}

const checkAnswers = () => {
    if (!allLacunasFilled.value) return
    answered.value = true
    
    // Calcular pontuação e disparar efeitos visuais
    const score = articleScore.value
    if (score) {
        // Efeitos visuais para celebrar o acerto
        if (score.percentage >= 70) {
            // Dispara o confetti para acertos acima de 70%
            setTimeout(() => {
                confettiReward();
            }, 300);
            
            // Para 100% de acerto, mostre emojis também
            if (score.percentage === 100) {
                setTimeout(() => {
                    emojiReward();
                }, 600);
            }
            
            // Toast de celebração
            setTimeout(() => {
                toast({
                    title: score.percentage === 100 ? "🎉 Perfeito!" : "🎊 Parabéns!",
                    description: score.percentage === 100 
                        ? "Você acertou todas as lacunas! Excelente trabalho!" 
                        : `Ótimo desempenho! ${score.percentage}% de acertos.`,
                    duration: 4000,
                });
            }, 800);
        } else {
            // Toast de encorajamento para pontuações baixas
            setTimeout(() => {
                toast({
                    title: "Continue tentando! 💪",
                    description: `${score.percentage}% de acertos. Tente novamente para melhorar!`,
                    duration: 3000,
                });
            }, 500);
        }
    }
}

const resetAnswers = () => {
    if (!userAnswers.value || Object.keys(userAnswers.value).length === 0) {
        resetAnswersConfirmed()
        return
    }
    showResetConfirmDialog.value = true
}

const resetAnswersConfirmed = () => {
    userAnswers.value = {}
    answered.value = false
    resetTextState()
}

const closeOffcanvas = () => {
    offcanvasMinimize.value = true
}

// Mobile text handling
const toggleTextContainer = () => {
    isTextExpanded.value = !isTextExpanded.value
    if (isTextExpanded.value) {
        textContainerHeight.value = 1000
    } else {
        textContainerHeight.value = 200
        scrollToNextEmptyLacuna()
    }
}

const resetTextState = () => {
    isTextExpanded.value = false
    textContainerHeight.value = 200
    hasHiddenLacunas.value = false
    
    setTimeout(() => {
        if (isMobile.value && textContainerRef.value) {
            scrollToNextEmptyLacuna()
        }
    }, 100)
}

const scrollToNextEmptyLacuna = () => {
    if (!textContainerRef.value) return

    setTimeout(() => {
        const emptyLacunas = textContainerRef.value!.querySelectorAll('.lacuna.empty, .lacuna.lacuna-empty-light, .lacuna.lacuna-empty-dark')
        const allLacunas = props.article?.practice_content.match(/_{5,}/g) || []
        const filledLacunasCount = Object.keys(userAnswers.value).length
        
        const isLastLacuna = emptyLacunas.length === 1 && filledLacunasCount === allLacunas.length - 1
        
        if (emptyLacunas.length > 0) {
            if (isLastLacuna) {
                isTextExpanded.value = true
                textContainerHeight.value = 3000
                hasHiddenLacunas.value = false
                
                setTimeout(() => {
                    if (emptyLacunas[0]) {
                        emptyLacunas[0].scrollIntoView({ behavior: 'smooth', block: 'center' })
                    }
                }, 50)
            } else {
                textContainerHeight.value = textContainerRef.value?.scrollHeight || 5000
                hasHiddenLacunas.value = true
            }
        } else {
            if (filledLacunasCount < allLacunas.length) {
                textContainerHeight.value += 500
                hasHiddenLacunas.value = true
            } else {
                isTextExpanded.value = true
                textContainerHeight.value = 3000
                hasHiddenLacunas.value = false
            }
        }
    }, 150)
}

const handleLacunaClick = (event: MouseEvent) => {
    if (answered.value) return
    
    const target = event.target as HTMLElement | null
    if (target && target.classList.contains('lacuna-remove-indicator')) {
        const lacunaElement = target.parentElement
        if (lacunaElement && lacunaElement.hasAttribute('data-lacuna-index')) {
            const lacunaIndex = Number(lacunaElement.getAttribute('data-lacuna-index'))
            delete userAnswers.value[lacunaIndex]
        }
        return
    }
    
    if (target && target.classList.contains('filled') && target.hasAttribute('data-lacuna-index')) {
        const lacunaIndex = Number(target.getAttribute('data-lacuna-index'))
        delete userAnswers.value[lacunaIndex]
    }
}

// Utility functions
const getDifficultyText = (level: number): string => {
    const texts: Record<number, string> = {
        1: 'Iniciante',
        2: 'Básico', 
        3: 'Intermediário',
        4: 'Avançado',
        5: 'Especialista'
    }
    return texts[level] || 'Intermediário'
}

const getDifficultyColor = (level: number): string => {
    const classes: Record<number, string> = {
        1: 'bg-green-500',
        2: 'bg-emerald-500', 
        3: 'bg-yellow-500',
        4: 'bg-orange-500',
        5: 'bg-red-500'
    }
    return classes[level] || 'bg-blue-500'
}

// Watch for answered state
import { watch } from 'vue'
watch(answered, (newVal) => {
    if (newVal === true) {
        offcanvasMinimize.value = false
    }
})

// Lifecycle
onMounted(() => {
    if (textContainerRef.value) {
        textContainerRef.value.addEventListener('click', handleLacunaClick)
        
        if (isMobile.value) {
            scrollToNextEmptyLacuna()
        }
    }
})

onUnmounted(() => {
    if (textContainerRef.value) {
        textContainerRef.value.removeEventListener('click', handleLacunaClick)
    }
})
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.offcanvas-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 50;
    transform: translateY(100%);
    transition: transform 0.3s ease-in-out;
    width: 100%;
    overflow-x: hidden;
    display: block; 
}
  
.offcanvas-open {
    transform: translateY(0);
}

.offcanvas-closed {
    display: none;
}
  
.offcanvas-content {
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
    box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1), 0 -2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    max-height: 80vh;
    overflow-y: auto;
    box-sizing: border-box;
    width: 100%;
}
</style>

<style>
.lacuna.filled {
    background-color: rgb(240, 249, 255);
    border-bottom: 2px solid rgb(56, 189, 248);
    padding: 2px 6px;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    display: inline-flex;
    align-items: center;
    line-height: 1.2;
    vertical-align: baseline;
    font-size: inherit;
    height: auto;
    color: rgb(30, 41, 59);
}

@media (prefers-color-scheme: dark) {
    .lacuna.filled {
        background-color: rgb(30, 59, 138);
        border-bottom-color: rgb(5, 36, 121);
        color: rgb(226, 232, 240);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }
}

.lacuna-remove-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 3px;
    font-size: 12px;
    font-weight: bold;
    color: rgba(239, 239, 239, 0.7);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    line-height: 1;
}

@media (max-width: 767px) {
    .lacuna.filled {
        padding-right: 6px;
    }
    
    .lacuna-remove-indicator {
        background-color: rgba(248, 248, 248, 0.407);
    }
    
    .lacuna.filled:hover .lacuna-remove-indicator,
    .lacuna.filled:active .lacuna-remove-indicator {
        background-color: rgba(239, 68, 68, 0.15);
        color: rgb(239, 68, 68);
    }
}

.lacuna.empty.lacuna-empty-light {
    background: #fffbcd !important;
    font-weight: semibold;
    padding: 4px;
    border-radius: 6px;
    color: #8e7e26;
}

.lacuna.empty.lacuna-empty-dark {
    background: #242424 !important; 
    color: #fffbcd !important;
    font-weight: semibold;
    padding: 4px;
    border-radius: 6px;
}

.lacuna.correct {
    background-color: rgba(38, 184, 89, 0.802);
    border-bottom: 2px solid rgb(17, 133, 58);
    padding: 2px 6px;
    border-radius: 4px;
    color: rgb(2, 51, 19);
}

.lacuna.incorrect {
    background-color: rgb(254, 226, 226);
    border-bottom: 2px solid rgb(239, 68, 68);
    padding: 2px 6px;
    border-radius: 4px;
    color: rgb(185, 28, 28);
}

.lacuna.empty {
    background: #fffbcd !important;
    font-weight: semibold;
    padding: 4px;
    border-radius: 6px;
    color: #8e7e26 !important;
}

:global(.dark) .lacuna.correct {
    background-color: rgba(34, 197, 94, 0.2);
    border-bottom-color: rgb(22, 163, 74);
    color: rgb(134, 239, 172);
}

:global(.dark) .lacuna.incorrect {
    background-color: rgba(239, 68, 68, 0.2);
    border-bottom-color: rgb(220, 38, 38);
    color: rgb(252, 165, 165);
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