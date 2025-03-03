<template>
    <Head :title="`Fase ${phase.phase_number}: ${phase.reference_name}`" />

    <AppLayout>
        <div class="container py-4 md:py-8 px-3 md:px-4">
            <div class="max-w-4xl mx-auto">
                <!-- Cabeçalho da fase - versão responsiva -->
                <div class="mb-4 md:mb-8">
                    <!-- Mostrar apenas no desktop -->
                    <Link :href="route('play.map')" class="hidden md:flex items-center text-primary hover:underline mb-4">
                        <ChevronLeft class="h-5 w-5 mr-1" />
                        Voltar ao mapa
                    </Link>

                    <div class="flex flex-row justify-between items-center gap-4">
                        <!-- Botão X para mobile -->
                        <Link :href="route('play.map')" class="md:hidden flex items-center justify-center h-8 w-8 rounded-full bg-muted/30 hover:bg-muted/50">
                            <X class="h-4 w-4" />
                        </Link>
                        
                        <!-- Título e nível apenas no desktop -->
                        <div class="hidden md:block">
                            <h1 class="text-3xl font-bold">{{ phase.title }}</h1>
                            <p class="text-muted-foreground mt-1">
                                Nível:
                                <span
                                    :class="`inline-flex items-center px-2 py-1 rounded-full text-xs text-white ${getDifficultyColor(phase.difficulty)}`"
                                >
                                    {{ getDifficultyText(phase.difficulty) }}
                                </span>
                            </p>
                        </div>

                        <!-- Barra de progresso - sempre visível -->
                        <div class="w-full max-w-md">
                            <div class="relative h-2 bg-muted rounded-full overflow-hidden">
                                <div
                                    class="absolute left-0 top-0 h-full bg-green-500 transition-all duration-300"
                                    :style="`width: ${(completedArticles.length / articlesArray.length) * 100}%`"
                                ></div>
                                <div
                                    class="absolute h-full bg-yellow-500 transition-all duration-300"
                                    :style="`
                                        left: ${(currentArticleIndex / articlesArray.length) * 100}%;
                                        width: ${(1 / articlesArray.length) * 100}%;
                                    `"
                                ></div>
                            </div>
                            <!-- Texto de progresso apenas no desktop -->
                            <div class="mt-2 text-sm text-center text-muted-foreground hidden md:block">
                                {{ completedArticles.length }} de {{ articlesArray.length }} artigos completados
                            </div>
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
                <Card v-if="currentArticle" class="mb-6">
                    <CardHeader>
                        <CardTitle>Art. {{ currentArticle.article_reference }}</CardTitle>
                        <CardDescription>
                            Complete as lacunas com as opções corretas
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <!-- Texto com lacunas - aumentado no mobile -->
                        <div
                            ref="textContainerRef"
                            class="p-4 md:p-6 bg-primary/5 border border-primary/20 rounded-md mb-6 text-xl md:text-lg leading-relaxed whitespace-pre-line"
                            v-html="processedText"
                        ></div>
                        
                        <!-- Palavras selecionadas que podem ser removidas -->
                        <div v-if="Object.keys(userAnswers[currentArticleIndex] || {}).length > 0 && !answered" class="mb-6">
                            <h3 class="text-sm font-medium mb-2 text-muted-foreground">Palavras selecionadas:</h3>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="(word, lacunaIndex) in userAnswers[currentArticleIndex]"
                                    :key="`selected-${lacunaIndex}`"
                                    variant="outline"
                                    size="sm"
                                    class="bg-primary/10 border-primary/20 flex items-center gap-1"
                                    @click="removeWordFromLacuna(Number(lacunaIndex))"
                                >
                                    <span>{{ word }}</span>
                                    <X class="h-3 w-3 ml-1" />
                                </Button>
                            </div>
                        </div>
                        
                        <!-- Offcanvas - mantém igual -->
                        <div 
                        v-if="answered" 
                        class="offcanvas-container"
                        :class="{ 'offcanvas-open': answered }"
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
                                    
                                    <Button variant="ghost" size="sm" @click="answered = false">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div v-if="articleScore">
                                    <div class="text-lg font-medium mb-2">
                                        Você acertou {{ articleScore.correct }} de {{ articleScore.total }} lacunas ({{ articleScore.percentage }}%)
                                    </div>

                                    <div class="mt-6">
                                        <h3 class="font-medium mb-2">Texto original:</h3>
                                        <div class="p-4 bg-muted/30 dark:bg-muted/10 border border-muted/30 rounded-md max-h-[150px] overflow-y-auto">
                                            <p class="whitespace-pre-line text-lg">{{ currentArticle?.original_content }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex justify-between gap-4">
                                        <Button variant="outline" class="w-full" @click="resetAnswers">
                                            <RefreshCw class="mr-2 h-4 w-4" />
                                            <!-- Texto diferente para mobile e desktop -->
                                            <span class="hidden md:inline">Tentar novamente</span>
                                            <span class="md:hidden">Novamente</span>
                                        </Button>
                                        <Button 
                                            v-if="currentArticleIndex < articlesArray.length - 1"
                                            class="w-full" 
                                            @click="nextArticle"
                                        >
                                            Próximo
                                            <ChevronRight class="ml-2 h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resto do conteúdo sem alteração -->
                    </CardContent>

                    <CardFooter>
                        <!-- Botões na parte inferior do card -->
                        <div v-if="!answered" class="w-full">
                            <div v-if="availableOptions.length > 0">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <Button
                                        v-for="(word, index) in availableOptions"
                                        :key="`word-${index}`"
                                        variant="outline"
                                        size="sm"
                                        @click="selectWord(word)"
                                        class="text-base md:text-sm"
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
                                    Responder
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

                            <Button variant="default" :disabled="currentArticleIndex === articlesArray.length - 1" @click="nextArticle">
                                Próximo
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script lang="ts" setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, Check, X, RefreshCw } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Article {
    article_reference: string;
    practice_content: string;
    original_content: string;
    options: {
        word: string;
        is_correct: boolean;
        gap_order: number; // gap_order agora é obrigatório
    }[];
}

const props = defineProps<{
    phase: {
        phase_number: number;
        reference_name: string;
        title: string;
        difficulty: number;
    };
    articles: Record<string, Article>;
}>();

// Controle do artigo atual
const currentArticleIndex = ref(0);
const articlesArray = computed(() => {
    const articles = Object.values(props.articles);
    console.log('Converted articles array:', articles);
    return articles;
});
console.log('Received articles:', props.articles);
const currentArticle = computed(() => {
    console.log('Current article index:', currentArticleIndex.value);
    console.log('Current article:', articlesArray.value[currentArticleIndex.value]);
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
const completedArticles = ref<number[]>([]);

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

        // Embaralha as palavras e remove duplicatas
        const shuffledWords = [...new Set(allWords)].sort(() => Math.random() - 0.5);

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
            
            const replacement = answered.value 
                ? (selectedWord
                    ? `<span class="lacuna ${selectedWord === correctAnswer ? 'correct' : 'incorrect'}">${selectedWord}</span>`
                    : '<span class="lacuna empty">(...)</span>')
                : (selectedWord
                    ? `<span class="lacuna filled" data-lacuna-index="${index}" role="button" tabindex="0">${selectedWord}</span>`
                    : '<span class="lacuna empty">(...)</span>');
                    
            text = text.replace(lacuna, replacement);
        });

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
}

// Permite remover uma palavra de uma lacuna específica
function removeWordFromLacuna(index: number) {
    if (answered.value) return;
    
    if (userAnswers.value[currentArticleIndex.value] && 
        userAnswers.value[currentArticleIndex.value].hasOwnProperty(index)) {
        delete userAnswers.value[currentArticleIndex.value][index];
        userAnswers.value = { ...userAnswers.value }; // Força atualização reativa
    }
}

// Verifica as respostas preenchidas
const checkAnswers = () => {
    answered.value = true;
    if (articleScore.value && articleScore.value.percentage >= 70) {
        if (!completedArticles.value.includes(currentArticleIndex.value)) {
            completedArticles.value.push(currentArticleIndex.value);
        }
    }
};

// Reinicia as respostas para o artigo atual
const resetAnswers = () => {
    if (userAnswers.value[currentArticleIndex.value]) {
        userAnswers.value[currentArticleIndex.value] = {};
    }
    answered.value = false;
};

// Navega para o próximo artigo
const nextArticle = () => {
    if (currentArticleIndex.value < articlesArray.value.length - 1) {
        currentArticleIndex.value++;
        answered.value = false;
    }
};

// Navega para o artigo anterior
const previousArticle = () => {
    if (currentArticleIndex.value > 0) {
        currentArticleIndex.value--;
        answered.value = false;
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
const handleLacunaClick = (event) => {
    if (answered.value) return;
    
    // Verificar se o clique foi em uma lacuna preenchida
    const target = event.target;
    if (target.classList.contains('filled') && target.hasAttribute('data-lacuna-index')) {
        const lacunaIndex = Number(target.getAttribute('data-lacuna-index'));
        removeWordFromLacuna(lacunaIndex);
    }
};

// Adicione isto após as declarações de variáveis
const textContainerRef = ref(null);

// Adicione isto usando onMounted
onMounted(() => {
    if (textContainerRef.value) {
        textContainerRef.value.addEventListener('click', handleLacunaClick);
    }
});

// Adicione isto usando onUnmounted
onUnmounted(() => {
    if (textContainerRef.value) {
        textContainerRef.value.removeEventListener('click', handleLacunaClick);
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
    transform: translateY(100%);
    transition: transform 0.3s ease-in-out;
    width: 100%; /* Limitar ao tamanho da viewport */
    overflow-x: hidden; /* Impedir rolagem horizontal */
}
  
.offcanvas-open {
    transform: translateY(0);
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
.lacuna {

}

.lacuna.empty {
    background: rgba(255, 250, 119, 0.812);
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
}

.lacuna.filled {
    background-color: rgba(0, 0, 0, 0.1);
    border-bottom: 2px solid rgb(59, 130, 246);
    padding: 2px 4px;
    border-radius: 2px;
}

.lacuna.correct {
    background-color: rgba(34, 197, 94, 0.2);
    border-bottom: 2px solid rgb(34, 197, 94);
    padding: 2px 4px;
    border-radius: 2px;
}

.lacuna.incorrect {
    background-color: rgba(239, 68, 68, 0.2);
    border-bottom: 2px solid rgb(239, 68, 68);
    padding: 2px 4px;
    border-radius: 2px;
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
</style>
