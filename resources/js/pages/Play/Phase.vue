<!-- filepath: /c:/Users/ramon/Desktop/study/resources/js/Pages/Play/Phase.vue -->
<script lang="ts" setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { ChevronLeft, ChevronRight, Check, X, RefreshCw } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Alert, AlertTitle, AlertDescription } from '@/Components/ui/alert';

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
    articles: Article[];
}>();

// Controle do artigo atual
const currentArticleIndex = ref(0);
const currentArticle = computed(() => props.articles[currentArticleIndex.value]);

interface UserAnswers {
    [articleIndex: number]: {
        [lacunaIndex: number]: string;
    };
}

// Estado para armazenar as respostas do usuário para cada artigo
const userAnswers = ref<UserAnswers>({});
const answered = ref(false);

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
                    ? `<span class="lacuna filled">${selectedWord}</span>`
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
    if (currentArticleIndex.value < props.articles.length - 1) {
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
</script>

<template>
    <Head :title="`Fase ${phase.phase_number}: ${phase.reference_name}`" />

    <AppLayout>
        <div class="container py-8 px-4">
            <div class="max-w-4xl mx-auto">
                <!-- Cabeçalho da fase -->
                <div class="mb-8">
                    <Link :href="route('play.map')" class="flex items-center text-primary hover:underline mb-4">
                        <ChevronLeft class="h-5 w-5 mr-1" />
                        Voltar ao mapa
                    </Link>

                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
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

                        <div class="flex">
                            <div
                                v-for="i in 5"
                                :key="`diff-${i}`"
                                class="w-3 h-12 mx-0.5 rounded-t-md"
                                :class="i <= phase.difficulty ? getDifficultyColor(phase.difficulty) : 'bg-muted'"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Navegação entre artigos -->
                <div class="flex justify-between items-center mb-4">
                    <Button
                        variant="outline"
                        :disabled="currentArticleIndex === 0"
                        @click="previousArticle"
                    >
                        <ChevronLeft class="mr-2 h-4 w-4" />
                        Anterior
                    </Button>

                    <span class="text-sm font-medium">
                        Artigo {{ currentArticleIndex + 1 }} de {{ articles.length }}
                    </span>

                    <Button
                        variant="outline"
                        :disabled="currentArticleIndex === articles.length - 1"
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
                        <!-- Texto com lacunas -->
                        <div
                            class="p-4 bg-primary/5 border border-primary/20 rounded-md mb-6"
                            v-html="processedText"
                        ></div>

                        <!-- Feedback após responder -->
                        <Alert
                            v-if="answered && articleScore"
                            :class="articleScore.percentage >= 70 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                            class="mb-6"
                        >
                            <div class="flex items-center gap-2">
                                <Check v-if="articleScore.percentage >= 70" class="h-5 w-5 text-green-500" />
                                <X v-else class="h-5 w-5 text-red-500" />
                                <AlertTitle>
                                    {{ articleScore.percentage >= 70 ? 'Bom trabalho!' : 'Continue tentando!' }}
                                </AlertTitle>
                            </div>
                            <AlertDescription>
                                Você acertou {{ articleScore.correct }} de {{ articleScore.total }} lacunas ({{ articleScore.percentage }}%)
                            </AlertDescription>
                        </Alert>

                        <!-- Texto original (mostrado após responder) -->
                        <div v-if="answered">
                            <h3 class="font-medium mb-2">Texto original:</h3>
                            <div class="p-4 bg-muted rounded-md">
                                <p class="whitespace-pre-wrap">{{ currentArticle.original_content }}</p>
                            </div>
                        </div>

                        <!-- Palavras já selecionadas (apenas para visualização em desenvolvimento) -->
                        <div v-if="!answered && userAnswers[currentArticleIndex] && Object.keys(userAnswers[currentArticleIndex]).length > 0" class="mb-4 border-t pt-4">
                            <p class="text-sm text-muted-foreground mb-2">Palavras selecionadas (clique para remover):</p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="(word, index) in userAnswers[currentArticleIndex]"
                                    :key="`selected-${index}`"
                                    variant="secondary"
                                    size="sm"
                                    @click="removeWordFromLacuna(Number(index))"
                                    class="selected-word"
                                >
                                    {{ word }}
                                    <X class="ml-1 h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter>
                        <!-- Opções para preencher lacunas -->
                        <div v-if="!answered" class="w-full">
                            <div v-if="availableOptions.length > 0">
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <Button
                                        v-for="(word, index) in availableOptions"
                                        :key="`word-${index}`"
                                        variant="outline"
                                        size="sm"
                                        @click="selectWord(word)"
                                    >
                                        {{ word }}
                                    </Button>
                                </div>
                            </div>
                            <div v-else-if="allLacunasFilled" class="text-center text-muted-foreground p-4">
                                Todas as lacunas foram preenchidas! Você pode verificar suas respostas ou limpar para tentar novamente.
                            </div>
                            <div v-else class="text-center text-muted-foreground p-4">
                                Não foram encontradas lacunas para preencher neste artigo.
                            </div>

                            <div class="flex justify-between mt-6 pt-4 border-t">
                                <Button variant="outline" @click="resetAnswers">
                                    <RefreshCw class="mr-2 h-4 w-4" />
                                    Limpar
                                </Button>

                                <Button variant="default" :disabled="!allLacunasFilled" @click="checkAnswers">
                                    Responder
                                </Button>
                            </div>
                        </div>

                        <!-- Botões exibidos após a verificação das respostas -->
                        <div v-else class="w-full flex justify-between">
                            <Button variant="outline" @click="resetAnswers">
                                <RefreshCw class="mr-2 h-4 w-4" />
                                Tentar novamente
                            </Button>

                            <Button variant="default" :disabled="currentArticleIndex === articles.length - 1" @click="nextArticle">
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

<style>
.lacuna {

}

.lacuna.empty {
    background: rgba(255, 250, 119, 0.812);
    font-weight: bold;
    padding: 2px;
}

.lacuna.filled {
    background-color: rgba(0, 0, 0, 0.1);
    border-bottom: 2px solid rgb(59, 130, 246);
}

.lacuna.correct {
    background-color: rgba(34, 197, 94, 0.2);
    border-bottom: 2px solid rgb(34, 197, 94);
}

.lacuna.incorrect {
    background-color: rgba(239, 68, 68, 0.2);
    border-bottom: 2px solid rgb(239, 68, 68);
}

button {
    opacity: 1 !important;
    visibility: visible !important;
}

.selected-word {
    display: inline-flex;
    align-items: center;
    background-color: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
}
</style>
