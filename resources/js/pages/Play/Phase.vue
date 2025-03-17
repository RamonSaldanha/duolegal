<template>
    <Head :title="`Fase ${phase.phase_number}: ${phase.reference_name}`" />

    <AppLayout>
        <!-- Elementos para os efeitos de recompensa -->
        <span id="confetti-canvas" class="fixed top-1/2 left-1/2 z-[100] pointer-events-none"></span>
        <span id="emoji-canvas" class="fixed top-1/2 left-1/2 z-[100] pointer-events-none"></span>
        
        <div class="container py-4 md:py-8 px-3 md:px-4">
            <div class="max-w-4xl mx-auto">
                <!-- Cabeçalho da fase - versão responsiva -->
                <div class="mb-4 md:mb-4">
                    <!-- Mostrar apenas no desktop -->
                    <Link :href="route('play.map')" class="hidden md:flex text-sm items-center text-primary hover:underline mb-4">
                        <ChevronLeft class="h-3 w-3 mr-1" />
                        Voltar ao mapa
                    </Link>

                    <!-- Título e nível apenas no desktop - tamanho reduzido -->
                    <div class="hidden md:block md:mb-4">
                        <h1 class="text-2xl font-bold">{{ phase.title }}</h1>
                        <p class="text-muted-foreground mt-1">
                            Nível:
                            <span
                                :class="`inline-flex items-center px-2 py-1 rounded-full text-xs text-white ${getDifficultyColor(phase.difficulty)}`"
                            >
                                {{ getDifficultyText(phase.difficulty) }}
                            </span>
                        </p>
                    </div>

                    <!-- Barra de progresso - sempre visível (ajustada para width 100%) -->
                    <div class="w-full">
                        <div class="flex items-center justify-between w-full mb-2">
                            <!-- Container da barra de progresso (largura reduzida para acomodar o botão) -->
                            <div class="relative h-2 bg-muted rounded-full overflow-hidden grow">
                                <!-- Segmentos de progresso para cada artigo -->
                                <template v-for="(article, index) in articlesArray" :key="`progress-${index}`">
                                    <div
                                        v-if="article.progress"
                                        class="absolute h-full transition-all duration-300"
                                        :class="article.progress.percentage >= 70 ? 'bg-green-500' : 'bg-red-500'"
                                        :style="`
                                            left: ${(index / articlesArray.length) * 100}%;
                                            width: ${(1 / articlesArray.length) * 100}%;
                                        `"
                                    ></div>
                                </template>
                                
                                <!-- Indicador do artigo atual -->
                                <div
                                    class="absolute h-full bg-blue-500 transition-all duration-300"
                                    :style="`
                                        left: ${(currentArticleIndex / articlesArray.length) * 100}%;
                                        width: ${(1 / articlesArray.length) * 100}%;
                                    `"
                                ></div>
                            </div>
                            
                            <!-- Botão de voltar para o mapa - apenas no mobile -->
                            <Link 
                                :href="route('play.map')" 
                                class="md:hidden ml-3 rounded-full p-1.5 bg-muted hover:bg-muted/80 transition-colors"
                            >
                                <X class="h-4 w-4" />
                            </Link>
                        </div>
                        
                        <!-- Texto de progresso apenas no desktop -->
                        <div class="mt-2 text-sm text-center text-muted-foreground hidden md:block">
                            {{ attemptedArticles.length }} de {{ articlesArray.length }} artigos tentados
                            <!-- <span class="text-xs text-muted-foreground ml-1">({{ completedArticles.length }} com sucesso)</span> -->
                        </div>
                    </div>
                </div>

                <!-- Navegação entre artigos - apenas desktop -->
                <div class="hidden md:flex justify-between items-center mb-4">
                    <Button
                        variant="outline"
                        :disabled="currentArticleIndex === 0"
                        @click="previousArticle"
                    >
                        <ChevronLeft class="mr-2 h-4 w-4" />
                        Anterior
                    </Button>

                    <span class="text-sm font-medium">
                        Artigo {{ currentArticleIndex + 1 }} de {{ articlesArray.length }}
                    </span>

                    <Button
                        variant="outline"
                        :disabled="currentArticleIndex === articlesArray.length - 1"
                        @click="nextArticle"
                    >
                        Próximo
                        <ChevronRight class="ml-2 h-4 w-4" />
                    </Button>
                </div>

                <!-- Artigo atual -->
                <Card v-if="currentArticle" class="mb-6 border-0 p-0">
                    <CardHeader class="px-0">
                        <CardTitle>Art. {{ currentArticle.article_reference }} - Leia e responda:</CardTitle>
                    </CardHeader>

                    <CardContent class="px-0 pb-0">
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
                                    
                                    <Button variant="ghost" size="sm" @click="closeOffcanvas">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div v-if="articleScore">
                                    <!-- Notificação de vida perdida -->
                                    <div v-if="articleScore.percentage < 70" class="bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 p-2 rounded-lg text-center text-sm mb-4">
                                        <span class="flex items-center justify-center gap-1">
                                            <Heart class="w-4 h-4" fill="currentColor" />
                                            Você perdeu 1 vida!
                                        </span>
                                    </div>
                                    <div class="text-lg font-medium mb-2">
                                        Você acertou {{ articleScore.correct }} de {{ articleScore.total }} lacunas ({{ articleScore.percentage }}%)
                                    </div>
                                    
                                    <!-- Removido a exibição de estatísticas adicionais conforme solicitado -->

                                    <div class="mt-6">
                                        <h3 class="font-medium mb-2">Texto original:</h3>
                                        <div class="p-1 bg-muted/70 dark:bg-muted/10 border border-muted/80 rounded-md max-h-[150px] overflow-y-auto">
                                            <p class="whitespace-pre-line text-md">
                                                {{ currentArticle?.original_content }}
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

                                    <div class="mt-6 flex justify-between gap-4">
                                        <Button variant="outline" class="w-full" @click="resetAnswers">
                                            <RefreshCw class="mr-2 h-4 w-4" />
                                            <!-- Texto diferente para mobile e desktop -->
                                            <span class="hidden md:inline">Tentar novamente</span>
                                            <span class="md:hidden">Novamente</span>
                                        </Button>
                                        
                                        <!-- Botão condicional baseado em se é o último artigo e se a fase está completa -->
                                        <Button 
                                            v-if="currentArticleIndex < articlesArray.length - 1"
                                            class="w-full" 
                                            @click="nextArticle"
                                        >
                                            Próximo
                                            <ChevronRight class="ml-2 h-4 w-4" />
                                        </Button>
                                        
                                        <!-- Botão especial para o último artigo quando a fase estiver completa -->
                                        <Button 
                                            v-else-if="isPhaseComplete"
                                            class="w-full" 
                                            @click="showPhaseCompletionModal"
                                        >
                                            {{ props.phase.has_next_phase ? 'Próxima Fase' : 'Concluir' }}
                                            <ChevronRight class="ml-2 h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resto do conteúdo sem alteração -->
                    </CardContent>

                    <CardFooter class="px-0">
                        <!-- Botões na parte inferior do card -->
                        <div v-if="!answered" class="w-full">
                            <div v-if="availableOptions.length > 0">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <Button
                                        v-for="(word, index) in availableOptions"
                                        :key="`word-${index}`"
                                        @click="selectWord(word)"
                                        size="sm"
                                        variant="outline"
                                        class="text-base px-3.5 p-5 rounded-xl hover:bg-primary/5 hover:border-primary/30 transition-all duration-200"
                                    >
                                        {{ word }}
                                    </Button>
                                </div>
                            </div>
                            
                            <!-- Botões de ações -->
                            <div class="flex justify-between mt-6 pt-4 border-t">
                                <Button variant="outline" @click="resetAnswers">
                                    <RefreshCw class="mr-2 h-4 w-4" />
                                    <span class="hidden md:inline">Limpar</span>
                                    <span class="md:hidden">Limpar</span>
                                </Button>

                                <Button variant="default" :disabled="!allLacunasFilled" @click="checkAnswers">
                                    Verificar
                                </Button>
                            </div>
                        </div>

                        <!-- Botões após verificação -->
                        <div v-else class="w-full flex justify-between">
                            <Button variant="outline" @click="resetAnswers">
                                <RefreshCw class="mr-2 h-4 w-4" />
                                <span class="hidden md:inline">Tentar novamente</span>
                                <span class="md:hidden">Novamente</span>
                            </Button>
                        
                            <!-- Botão condicional baseado em se é o último artigo e se a fase está completa -->
                            <Button 
                                v-if="currentArticleIndex < articlesArray.length - 1" 
                                variant="default" 
                                @click="nextArticle"
                            >
                                Próximo
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                            
                            <!-- Botão especial para o último artigo quando a fase estiver completa -->
                            <Button 
                                v-else-if="isPhaseComplete"
                                variant="default"
                                @click="showPhaseCompletionModal"
                            >
                                {{ props.phase.has_next_phase ? 'Próxima Fase' : 'Concluir' }}
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>

    <!-- Adicione isto ao final do template, antes do fechamento da tag AppLayout -->
    <transition 
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div v-if="showCompletionModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <!-- Aumentei o z-index para 60, acima do 50 usado pelo offcanvas -->
            <div class="max-w-md w-full bg-background rounded-lg shadow-lg p-6 border border-border">
                <div class="text-center">
                    <div class="mb-4 inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300">
                        <Check class="h-8 w-8" />
                    </div>
                    <h2 class="text-2xl font-bold mb-4">Fase Concluída!</h2>
                    <p class="mb-6">Parabéns! Você completou todos os artigos desta fase.</p>
                    
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <Button variant="outline" @click="showCompletionModal = false">
                            Continuar praticando
                        </Button>
                        <Button 
                            @click="advanceToNextPhase" 
                        >
                            {{ props.phase.has_next_phase ? 'Avançar para próxima fase' : 'Voltar ao mapa' }}
                            <ChevronRight class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script lang="ts" setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Heart } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, Check, X, RefreshCw, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
// @ts-expect-error: vue-rewards package does not provide proper type definitions
import { useReward } from 'vue-rewards';
import { useWindowSize } from '@vueuse/core';
import axios from 'axios';

// Atualizar a interface Article para incluir progresso
interface Article {
    uuid: string;
    article_reference: string;
    practice_content: string;
    original_content: string;
    options: {
        word: string;
        is_correct: boolean;
        gap_order: number;
    }[];
    progress?: {
        percentage: number;
        is_completed: boolean;
        best_score: number;
        attempts: number;
        wrong_answers?: number;
        revisions?: number;
    } | null;
}

// PRIMEIRO: Definir props antes de usá-la
const props = defineProps<{
    phase: {
        phase_number: number;
        reference_name: string;
        title: string;
        difficulty: number;
        has_next_phase: boolean;
        reference_uuid: string;
    };
    articles: Record<string, Article>;
}>();

// Obtém página e tipos
const page = usePage<{
    auth: {
        user: {
            lives: number;
        }
    }
}>();

// Usar props para inicializar articlesArray
const articlesArray = ref(Object.values(props.articles));

// Determina o primeiro artigo não tentado (prioridade 1) ou não completado (prioridade 2)
const firstArticleToShowIndex = computed(() => {
    // Primeiro, procura por artigos que ainda não foram tentados (progress === null)
    const notAttemptedIndex = articlesArray.value.findIndex(article => article.progress === null);
    if (notAttemptedIndex !== -1) {
        return notAttemptedIndex;
    }
    
    // Se não encontrar nenhum não tentado, procura por artigos não completados
    const uncompletedIndex = articlesArray.value.findIndex(article => article.progress && !article.progress.is_completed);
    if (uncompletedIndex !== -1) {
        return uncompletedIndex;
    }
    
    // Se todos os artigos foram completados, retorna o primeiro
    return 0;
});

// Controle do artigo atual - inicia no primeiro não tentado ou não completado
const currentArticleIndex = ref(firstArticleToShowIndex.value);
const currentArticle = computed(() => {
    return articlesArray.value[currentArticleIndex.value];
});

interface UserAnswers {
    [articleIndex: number]: {
        [lacunaIndex: number]: string;
    };
}

// Estado para armazenar as respostas do usuário para cada artigo
const userAnswers = ref<UserAnswers>({});
const answered = ref(false);
const offcanvasMinimize = ref(false);

// Inicializa attemptedArticles com os índices dos artigos já tentados (com progresso)
const attemptedArticles = ref<number[]>(
    articlesArray.value
        .map((article, index) => article.progress ? index : -1)
        .filter(index => index !== -1)
);

// Inicializa completedArticles com os índices dos artigos completados com sucesso
const completedArticles = ref<number[]>(
    articlesArray.value
        .map((article, index) => article.progress?.is_completed ? index : -1)
        .filter(index => index !== -1)
);

// Adicione esta variável perto das outras variáveis de estado
const isPhaseComplete = ref(false);

// Extrai todas as opções para o artigo atual e monta as respostas corretas
    const articleOptions = computed(() => {
        if (!currentArticle.value || !currentArticle.value.options || currentArticle.value.options.length === 0) {
            return {
                allOptions: [],
                correctAnswers: new Map<number, string>(),
                gapOrderMapping: new Map<number, number>() // Mapeia gap_order para índice da lacuna
            };
        }

        const allWords: string[] = [];
        const correctAnswers = new Map<number, string>();
        const gapOrderMapping = new Map<number, number>();

        // Agrupa as palavras e define as respostas corretas conforme o gap_order
        currentArticle.value.options.forEach(option => {
            allWords.push(option.word);
            if (option.is_correct && option.gap_order !== undefined) {
                correctAnswers.set(option.gap_order, option.word);
                // Mapeia gap_order para índice da lacuna (0-based)
                gapOrderMapping.set(option.gap_order, option.gap_order - 1);
            }
        });

        // Embaralha as palavras (mantendo duplicatas)
        const shuffledWords = [...allWords].sort(() => Math.random() - 0.5);

        return {
            allOptions: shuffledWords,
            correctAnswers: correctAnswers,
            gapOrderMapping: gapOrderMapping
        };
    });

    // Computa as opções disponíveis (remove as palavras já utilizadas)
    const availableOptions = computed(() => {
        const answers = userAnswers.value[currentArticleIndex.value] || {};
        const usedWords = Object.values(answers);
        return articleOptions.value.allOptions.filter(word => !usedWords.includes(word));
    });

    // Obtém o número total de lacunas no texto (baseado nos marcadores ______)
    const totalLacunas = computed(() => {
        if (!currentArticle.value || !currentArticle.value.practice_content) return 0;
        const matches = currentArticle.value.practice_content.match(/_{5,}/g);
        return matches ? matches.length : 0;
    });

    // Processa o texto do artigo substituindo cada lacuna pela palavra selecionada
    const processedText = computed(() => {
        if (!currentArticle.value || !currentArticle.value.practice_content) return '';
        
        let text = currentArticle.value.practice_content;
        const lacunas = text.match(/_{5,}/g) || [];
        
        lacunas.forEach((lacuna, index) => {
            const selectedWord = userAnswers.value[currentArticleIndex.value]?.[index];
            
            // Mapeia todas as respostas corretas por gap_order
            const correctAnswersMap: Map<number, string> = new Map();
            currentArticle.value.options.forEach(option => {
                if (option.is_correct) {
                    correctAnswersMap.set(option.gap_order, option.word);
                }
            });
            
            // Obtém a resposta correta para esta lacuna
            const correctAnswer = correctAnswersMap.get(index + 1); // gap_order é 1-based
            
            // No computed property processedText, modifique a parte que cria o replacement:

            const replacement = answered.value 
                ? (selectedWord
                    ? `<span class="lacuna ${selectedWord === correctAnswer ? 'correct' : 'incorrect'}">${selectedWord}</span>`
                    : `<span class="lacuna empty lacuna-empty-${document.documentElement.classList.contains('dark') ? 'dark' : 'light'}">(...)</span>`)
                : (selectedWord
                    ? `<span class="lacuna filled" data-lacuna-index="${index}">${selectedWord}<span class="lacuna-remove-indicator">×</span></span>`
                    : `<span class="lacuna empty lacuna-empty-${document.documentElement.classList.contains('dark') ? 'dark' : 'light'}">(...)</span>`);
                                
            text = text.replace(lacuna, replacement);
        });

        // Limita o texto até a quebra de linha que contém a primeira lacuna não preenchida para dispositivos móveis
        if (isMobile.value && !isTextExpanded.value && !allLacunasFilled.value) {
            // Procura a ocorrência de lacuna, se não existir retorna -1
            const firstEmptyIndex = text.indexOf('<span class="lacuna empty">');
            if(firstEmptyIndex !== -1) {
                // Encontra a primeira quebra de linha após a primeira lacuna vazia
                const nextLineBreak = text.indexOf('\n', firstEmptyIndex);
                
                // Se encontrou uma quebra de linha
                if(nextLineBreak !== -1) {
                    // Corta o texto até essa quebra de linha
                    text = text.substring(0, nextLineBreak);
                }
            }
        }
        return text;
    });


    // Verifica se todas as lacunas foram preenchidas
    const allLacunasFilled = computed(() => {
        if (!userAnswers.value[currentArticleIndex.value]) return false;
        return Object.keys(userAnswers.value[currentArticleIndex.value]).length === totalLacunas.value;
    });

    // Calcula a pontuação do usuário para o artigo atual
    const articleScore = computed(() => {
        if (!answered.value) return null;
        let correctCount = 0;
        const answers = userAnswers.value[currentArticleIndex.value] || {};

        Object.entries(answers).forEach(([lacunaIndex, userAnswer]) => {
            const gapNumber = Number(lacunaIndex) + 1; // gap_order é 1-based
            // Obtém a resposta correta para esta lacuna
            const correctAnswer = articleOptions.value.correctAnswers.get(gapNumber);
            
            if (correctAnswer && userAnswer === correctAnswer) {
                correctCount++;
            }
        });

        return {
            correct: correctCount,
            total: totalLacunas.value,
            percentage: Math.round((correctCount / totalLacunas.value) * 100)
        };
    });

    // Configure as recompensas
    // Recompensas
const { reward: confettiReward } = useReward('confetti-canvas', 'confetti', {
        startVelocity: 30, 
        spread: 360,
        elementCount: 100,
        decay: 0.94,
        colors: ['#26ccff', '#a25afd', '#ff5e7e', '#88ff5a', '#fcff42', '#ffa62d'],
        zIndex: 100 // Garantindo que o z-index também está definido na configuração
    });

    const { reward: emojiReward } = useReward('emoji-canvas', 'emoji', {
        emoji: ['🎓', '✨', '👏', '🏆'],
        elementCount: 20,
        spread: 50,
        zIndex: 100 // Garantindo que o z-index também está definido na configuração
    });

    // Determina a próxima lacuna vazia para preenchimento
    const nextEmptyLacunaIndex = computed(() => {
        const answers = userAnswers.value[currentArticleIndex.value] || {};
        for (let i = 0; i < totalLacunas.value; i++) {
            if (!answers.hasOwnProperty(i)) {
                return i;
            }
        }
        return null; // Todas as lacunas estão preenchidas
    });

    // Ao clicar em uma palavra, ela é selecionada para preencher a próxima lacuna disponível
    function selectWord(word: string) {
        if (answered.value) return;

        const nextEmptyIndex = nextEmptyLacunaIndex.value;
        
        if (nextEmptyIndex === null) return;
        
        if (!userAnswers.value[currentArticleIndex.value]) {
            userAnswers.value[currentArticleIndex.value] = {};
        }
        userAnswers.value[currentArticleIndex.value][nextEmptyIndex] = word;
        userAnswers.value = { ...userAnswers.value }; // Força a atualização reativa
        
        // Se estiver no mobile, ajusta a altura do container após preenchimento
        if (isMobile.value) {
            // Dá tempo para o DOM atualizar
            setTimeout(() => {
                scrollToNextEmptyLacuna();
            }, 200);
        }
    }

    // Permite remover uma palavra de uma lacuna específica
    function removeWordFromLacuna(index: number) {
        if (answered.value) return;
        
        if (userAnswers.value[currentArticleIndex.value] && 
            userAnswers.value[currentArticleIndex.value].hasOwnProperty(index)) {
            delete userAnswers.value[currentArticleIndex.value][index];
            userAnswers.value = { ...userAnswers.value }; // Força atualização reativa
        }
        const score = articleScore.value;
        
        if (score) {
            // Salvar progresso no servidor
            axios.post(route('play.progress'), {
                article_uuid: currentArticle.value.uuid,
                correct_answers: score.correct,
                total_answers: score.total,
            })
            .then(response => {
                // Atualizar o progresso local com os dados do servidor
                if (response.data.success) {
                    console.log('Progresso atualizado:', response.data.progress);
                    
                    // Atualiza o estado de redirecionamento de sem vidas
                    noLivesState.value = {
                        shouldRedirect: response.data.should_redirect,
                        redirectUrl: response.data.redirect_url
                    };

                    // Atualiza o objeto do artigo atual com o progresso atualizado
                    const currentIdx = currentArticleIndex.value;
                    const articlesCopy = [...articlesArray.value];
                    articlesCopy[currentIdx] = {
                        ...articlesCopy[currentIdx],
                        progress: response.data.progress
                    };
                    
                    // Atualizar o array reativo
                    articlesArray.value = articlesCopy;

                    // Atualizar as vidas do usuário no estado da página
                    if (response.data.user?.lives !== undefined) {
                        page.props.auth.user.lives = response.data.user.lives;
                    }
                    
                    // Verificar se todos os artigos foram respondidos, mas NÃO exibe o modal
                    // Se for o último artigo, só marca como pronto para exibir o botão especial
                    if (currentArticleIndex.value === articlesArray.value.length - 1) {
                        const allDone = articlesCopy.every(article => article.progress !== null);
                        if (allDone) {
                            // Ao invés de mostrar o modal, marcamos que a fase está completa
                            isPhaseComplete.value = true;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao salvar progresso:', error);
            });
        
            // Código para exibir recompensas visuais
            if (score.percentage >= 70) {
                if (!completedArticles.value.includes(currentArticleIndex.value)) {
                    completedArticles.value.push(currentArticleIndex.value);
                }
                
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
            }
        }

        // Expande o texto para mostrar o próximo trecho após verificar as respostas
        if (isMobile.value) {
            isTextExpanded.value = false;
            scrollToNextEmptyLacuna();
        }
    };
    
    const showPhaseCompletionModal = () => {
        showCompletionModal.value = true;
        offcanvasMinimize.value = true; // Minimiza o offcanvas quando o modal aparecer
    };

    watch(answered, (newVal) => {
        if (newVal === true) {
            offcanvasMinimize.value = false;
        }
    })

    // Função auxiliar para reiniciar o estado de exibição do texto
    const resetTextState = () => {
        isTextExpanded.value = false;
        textContainerHeight.value = 200; // Altura inicial em pixels
        hasHiddenLacunas.value = false;
        
        // Importante dar tempo para o DOM atualizar
        setTimeout(() => {
            if (isMobile.value && textContainerRef.value) {
                scrollToNextEmptyLacuna();
            }
        }, 100);
    };

    // Estado para controlar o redirecionamento quando sem vidas
    const noLivesState = ref<{shouldRedirect: boolean; redirectUrl: string | null}>({
        shouldRedirect: false,
        redirectUrl: null
    });

    // Função para verificar se deve redirecionar
    const checkNoLivesRedirect = () => {
        if (noLivesState.value.shouldRedirect && noLivesState.value.redirectUrl) {
            router.visit(noLivesState.value.redirectUrl);
        }
    };

    // Modifique a função resetAnswers
    const resetAnswers = () => {
        checkNoLivesRedirect(); // Verifica redirecionamento antes de qualquer ação
        
        if (userAnswers.value[currentArticleIndex.value]) {
            userAnswers.value[currentArticleIndex.value] = {};
        }
        answered.value = false;
        resetTextState();
    };

    // Modifique as funções nextArticle e previousArticle
    const nextArticle = () => {
        checkNoLivesRedirect(); // Verifica redirecionamento antes de qualquer ação
        
        if (currentArticleIndex.value < articlesArray.value.length - 1) {
            currentArticleIndex.value++;
            answered.value = false;
            resetTextState();
        }
    };

    const previousArticle = () => {
        checkNoLivesRedirect(); // Verifica redirecionamento antes de qualquer ação
        
        if (currentArticleIndex.value > 0) {
            currentArticleIndex.value--;
            answered.value = false;
            resetTextState();
        }
    };

    // Retorna o texto referente ao nível de dificuldade
    const getDifficultyText = (level: number): string => {
        switch (level) {
            case 1: return 'Iniciante';
            case 2: return 'Básico';
            case 3: return 'Intermediário';
            case 4: return 'Avançado';
            case 5: return 'Especialista';
            default: return 'Intermediário';
        }
    };

    // Retorna a classe CSS correspondente à dificuldade
    const getDifficultyColor = (level: number): string => {
        switch (level) {
            case 1: return 'bg-green-500';
            case 2: return 'bg-emerald-500';
            case 3: return 'bg-yellow-500';
            case 4: return 'bg-orange-500';
            case 5: return 'bg-red-500';
            default: return 'bg-blue-500';
        }
    };

    // Adicione esta função em setup
    const handleLacunaClick = (event: MouseEvent) => {
        if (answered.value) return;
        
        const target = event.target as HTMLElement | null;
        // Verificar se o clique foi no indicador de remoção (X)
        if (target && target.classList.contains('lacuna-remove-indicator')) {
            const lacunaElement = target.parentElement;
            if (lacunaElement && lacunaElement.hasAttribute('data-lacuna-index')) {
                const lacunaIndex = Number(lacunaElement.getAttribute('data-lacuna-index'));
                removeWordFromLacuna(lacunaIndex);
            }
            return;
        }
        
        if (target && target.classList.contains('filled') && target.hasAttribute('data-lacuna-index')) {
            const lacunaIndex = Number(target.getAttribute('data-lacuna-index'));
            removeWordFromLacuna(lacunaIndex);
        }
    };

    // Adicione isto após as declarações de variáveis
    const textContainerRef = ref<HTMLElement | null>(null);

    // Adicione isto usando onMounted
    onMounted(() => {
        if (textContainerRef.value) {
            textContainerRef.value.addEventListener('click', handleLacunaClick);
            
            // Inicializa o ajuste de altura para dispositivos móveis
            if (isMobile.value) {
                scrollToNextEmptyLacuna();
            }
        }
    });

    // Adicione isto usando onUnmounted
    onUnmounted(() => {
        if (textContainerRef.value) {
            textContainerRef.value.removeEventListener('click', handleLacunaClick);
        }
    });

    // Adicione estas variáveis ao componente
    const isTextExpanded = ref(false);
    const textContainerHeight = ref(200); // Altura inicial em pixels
    const hasHiddenLacunas = ref(false);
    const { width } = useWindowSize();
    const isMobile = computed(() => width.value < 768); // Considerar dispositivos com menos de 768px como móveis

    // Função para controlar manualmente a expansão/colapso do texto
    function toggleTextContainer() {
        isTextExpanded.value = !isTextExpanded.value;
        if (isTextExpanded.value) {
            textContainerHeight.value = 1000; // Altura expandida
        } else {
            textContainerHeight.value = 200; // Altura colapsada
            scrollToNextEmptyLacuna();
        }
    }

    // Função para encontrar e rolar para a próxima lacuna vazia
    function scrollToNextEmptyLacuna() {
        if (!textContainerRef.value) return;

        // Usar setTimeout para garantir que o DOM está atualizado
        setTimeout(() => {
            const emptyLacunas = textContainerRef.value!.querySelectorAll('.lacuna.empty');
            const allLacunas = currentArticle.value?.practice_content.match(/_{5,}/g) || [];
            const filledLacunasCount = Object.keys(userAnswers.value[currentArticleIndex.value] || {}).length;
            
            // Verifica se estamos na última lacuna (apenas uma lacuna vazia restante)
            const isLastLacuna = emptyLacunas.length === 1 && filledLacunasCount === allLacunas.length - 1;
            
            if (emptyLacunas.length > 0) {
                // Força a exibição completa se for a última lacuna
                if (isLastLacuna) {
                    isTextExpanded.value = true;
                    textContainerHeight.value = 3000; // Altura suficientemente grande para qualquer texto
                    hasHiddenLacunas.value = false;
                    
                    // Forçar atualização do DOM
                    setTimeout(() => {
                        if (emptyLacunas[0]) {
                            emptyLacunas[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 50);
                } else {
                    
                    textContainerHeight.value = textContainerRef.value?.scrollHeight || 5000;
                    hasHiddenLacunas.value = true;
                }
            } else {
                // Se não houver lacunas vazias visíveis, expanda mais o texto
                if (filledLacunasCount < allLacunas.length) {
                    textContainerHeight.value += 500; // Expande significativamente
                    hasHiddenLacunas.value = true;
                } else {
                    // Todas as lacunas foram preenchidas
                    isTextExpanded.value = true;
                    textContainerHeight.value = 3000; // Mostrar tudo
                    hasHiddenLacunas.value = false;
                }
            }
        }, 150); // Aumentei o timeout para dar mais tempo ao DOM para atualizar
    }

    // Adicione este computed property ao seu script
    const highlightedUserAnswers = computed(() => {
        if (!currentArticle.value || !answered.value) return '';

        let text = currentArticle.value.practice_content;
        const lacunas = text.match(/_{5,}/g) || [];
        const answers = userAnswers.value[currentArticleIndex.value] || {};
        
        // Para cada lacuna, substitua pelo texto do usuário com destaque adequado
        lacunas.forEach((lacuna, index) => {
            // Obtém a resposta do usuário para esta lacuna
            const userAnswer = answers[index];
            
            // Obtém a resposta correta para esta lacuna
            const correctAnswer = getCorrectAnswerForGap(index + 1); // gap_order é 1-based
            
            let replacement;
            if (userAnswer) {
                // Usuário respondeu, verificar se está correto
                const isCorrect = userAnswer === correctAnswer;
                
                if (isCorrect) {
                    // Resposta correta - verde
                    replacement = `<span class="px-1 py-0.5 rounded font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">${userAnswer}</span>`;
                } else {
                    // Resposta incorreta - vermelho, mostrando a resposta correta
                    replacement = `<span class="px-1 py-0.5 rounded font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">${userAnswer}</span>`;
                }
            } else {
                // Usuário não respondeu - usar cinza
                replacement = `<span class="px-1 py-0.5 rounded font-medium bg-gray-100 text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">(...)</span>`;
            }
            
            text = text.replace(lacuna, replacement);
        });
        
        return text;
    });

    // Adicione esta função de ajuda para obter a resposta correta para uma lacuna específica
    const getCorrectAnswerForGap = (gapOrder: number): string => {
        if (!currentArticle.value || !currentArticle.value.options) return '';
        
        const correctOption = currentArticle.value.options.find(
            option => option.is_correct && option.gap_order === gapOrder
        );
        
        return correctOption ? correctOption.word : '';
    };

    const closeOffcanvas = () => {
        // Mantenha answered como true, apenas minimize o offcanvas
        offcanvasMinimize.value = true;
    };

    // Função para verificar as respostas do usuário
    const checkAnswers = () => {
        if (!allLacunasFilled.value) return;
        
        answered.value = true;
        
        // Se não estiver no array de artigos tentados, adicione
        if (!attemptedArticles.value.includes(currentArticleIndex.value)) {
            attemptedArticles.value.push(currentArticleIndex.value);
        }
        
        const score = articleScore.value;
        
        if (score) {
            // Salvar progresso no servidor
            axios.post(route('play.progress'), {
                article_uuid: currentArticle.value.uuid,
                correct_answers: score.correct,
                total_answers: score.total,
            })
            .then(response => {
                // Atualizar o progresso local com os dados do servidor
                if (response.data.success) {
                    console.log('Progresso atualizado:', response.data.progress);
                    
                    // Atualiza o estado de redirecionamento de sem vidas
                    noLivesState.value = {
                        shouldRedirect: response.data.should_redirect,
                        redirectUrl: response.data.redirect_url
                    };

                    // Atualiza o objeto do artigo atual com o progresso atualizado
                    const currentIdx = currentArticleIndex.value;
                    const articlesCopy = [...articlesArray.value];
                    articlesCopy[currentIdx] = {
                        ...articlesCopy[currentIdx],
                        progress: response.data.progress
                    };
                    
                    // Atualizar o array reativo
                    articlesArray.value = articlesCopy;

                    // Atualizar as vidas do usuário no estado da página
                    if (response.data.user?.lives !== undefined) {
                        page.props.auth.user.lives = response.data.user.lives;
                    }
                    
                    // Verificar se todos os artigos foram respondidos, mas NÃO exibe o modal
                    // Se for o último artigo, só marca como pronto para exibir o botão especial
                    if (currentArticleIndex.value === articlesArray.value.length - 1) {
                        const allDone = articlesCopy.every(article => article.progress !== null);
                        if (allDone) {
                            // Ao invés de mostrar o modal, marcamos que a fase está completa
                            isPhaseComplete.value = true;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao salvar progresso:', error);
            });
        
            // Código para exibir recompensas visuais
            if (score.percentage >= 70) {
                if (!completedArticles.value.includes(currentArticleIndex.value)) {
                    completedArticles.value.push(currentArticleIndex.value);
                }
                
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
            }
        }
    };


    // Substituir a função advanceToNextPhase existente por esta:
    const advanceToNextPhase = () => {
        if (props.phase.has_next_phase) {
            const nextPhaseNumber = props.phase.phase_number + 1;
            
            // Usar o reference_uuid que foi passado do controller
            router.visit(route('play.phase', {
                reference: props.phase.reference_uuid,
                phase: nextPhaseNumber
            }));
        } else {
            router.visit(route('play.map'));
        }
    };

    // Estado para controlar a exibição do modal de conclusão
    const showCompletionModal = ref(false);

    // Adicionar este watch para fechar o offcanvas quando o modal aparecer
    watch(showCompletionModal, (isShowing) => {
        if (isShowing) {
            offcanvasMinimize.value = true; // Minimiza o offcanvas quando o modal de conclusão aparecer
        }
    });
</script>

<style scoped>
.offcanvas-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 50;
    transform: translateY(100%); /* Inicialmente escondido */
    transition: transform 0.3s ease-in-out;
    width: 100%;
    overflow-x: hidden;
    /* Tornamos visível por padrão, controlando apenas com transform */
    display: block; 
}
  
.offcanvas-open {
    transform: translateY(0); /* Mostra o offcanvas */
}

.offcanvas-closed {
    display: none; /* Esconde completamente quando minimizado e não respondido */
}
  
.offcanvas-content {
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
    box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1), 0 -2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    max-height: 80vh;
    overflow-y: auto;
    box-sizing: border-box; /* Garantir que padding não aumente largura */
    width: 100%;
}
</style>
<style>
/* Atualizações para o estilo do X nas lacunas */
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
    color: rgb(30, 41, 59); /* Cor escura para tema claro */
}

/* Tema escuro para lacuna preenchida */
@media (prefers-color-scheme: dark) {
    .lacuna.filled {
        background-color: rgb(30, 59, 138); /* Azul escuro */
        border-bottom-color: rgb(5, 36, 121);
        color: rgb(226, 232, 240); /* Texto claro para melhor contraste */
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

/* Mostra um fundo mais evidente no hover */
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

/* Remove overlay and adjust dialog positioning */
:global(.dialog-overlay) {
    display: none !important;
}

button {
    opacity: 1 !important;
    visibility: visible !important;
}

/* Dialog animation */
:global(.dialog-content-enter-active),
:global(.dialog-content-leave-active) {
    transition: transform 0.3s ease-in-out;
}

:global(.dialog-content-enter-from),
:global(.dialog-content-leave-to) {
    transform: translateY(100%);
}

:global(.dialog-content-enter-to),
:global(.dialog-content-leave-from) {
    transform: translateY(0);
}

.selected-word {
    display: inline-flex;
    align-items: center;
    background-color: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
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

/* Estilos para quando o modal é fechado mas ainda mostrando correções */
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

/* Modo escuro para as lacunas - usando a classe dark do Tailwind */
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
</style>
