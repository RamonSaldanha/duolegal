<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge'; 
import { LoaderCircle, X } from 'lucide-vue-next';
import { ref, onMounted, computed, watch } from 'vue';
import SearchCreateSelect from '@/components/SearchCreateSelect.vue';
import axios from 'axios';
import { useToast } from '@/components/ui/toast/use-toast'
import Toaster from '@/components/ui/toast/Toaster.vue'


const { toast } = useToast()

const props = defineProps<{
    legalReferences?: LegalReference[]
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Criar Artigo de Lei',
        href: '/admin/create-lawarticle',
    },
];

type FormFields = {
    legal_reference_id: string | number;
    original_content: string;
    article_reference: string;
    difficulty_level: number;
    position: number;
    selected_words: Array<{
        word: string;
        position: number;
        gap_order: number;
    }>;
    custom_options: Array<{
        word: string;
        is_correct: boolean;
    }>;
    practice_content: string;
    [key: string]: unknown;
}

const form = useForm<FormFields>({
    legal_reference_id: '',
    original_content: '',       // Conteúdo original completo
    article_reference: '',      // Alterado de 0 para string vazia
    difficulty_level: 1,        // Nível de dificuldade
    position: 10,               // Posição inicial definida como 10
    selected_words: [],         // Palavras selecionadas para remover do texto
    custom_options: [],         // Opções personalizadas adicionadas pelo administrador
    practice_content: '',       // Inicializar o campo practice_content
});

interface LawArticleOption {
    id: number;
    word: string;
    frequency: number;
}

interface Word {
    text: string;
    space: string;
    index: number;
}

interface LegalReference {
    id: number;
    name: string;
    description?: string;
}

// Usar as referências legais fornecidas pela prop ou começar com um array vazio
const legalReferences = ref<LegalReference[]>(props.legalReferences || []);

// Estado para gerenciar palavras selecionadas e opções personalizadas
const words = ref<Word[]>([]);
const textLines = ref<Word[][]>([]);
const selectedWordIndices = ref<Set<number>>(new Set());
const customWordInput = ref('');
const lawArticleOptions = ref<LawArticleOption[]>([]); 

// Função para adicionar uma nova referência legal à lista
const handleReferenceCreated = (newReference: LegalReference) => {
    legalReferences.value.unshift(newReference);
};

// Carregar referências legais do backend (caso não tenham sido fornecidas via props)
if (legalReferences.value.length === 0) {
    onMounted(async () => {
        try {
            const response = await axios.get('/admin/legal-references');
            legalReferences.value = response.data;
        } catch (error) {
            console.error('Erro ao carregar referências legais:', error);
        }
    });
}

// Carregar opções de palavras existentes no banco de dados
onMounted(async () => {
    try {
        const response = await axios.get('/admin/law-article-options');
        // Agrupar opções com o mesmo texto
        const groupedOptions: Record<string, LawArticleOption> = {};
        
        response.data.forEach((option: { id: number; word: string }) => {
            if (!groupedOptions[option.word]) {
                groupedOptions[option.word] = {
                    id: option.id,
                    word: option.word,
                    frequency: 1
                };
            } else {
                groupedOptions[option.word].frequency++;
            }
        });
        
        // Converter para array e ordenar por frequência (mais usados primeiro)
        lawArticleOptions.value = Object.values(groupedOptions).sort((a, b) => 
            b.frequency - a.frequency
        );
    } catch (error) {
        console.error('Erro ao carregar opções de palavras:', error);
    }
});

// Função melhorada para detectar automaticamente o número do artigo no texto
const detectArticleNumber = (text: string) => {
    console.log('Detectando número do artigo...');
    
    // Regex melhorada para capturar mais formatos de artigos
    const regex = /Art(?:igo)?\.?\s*(\d+(?:\.\d+)*)\s*\.?º?/i;
    const match = text.match(regex);
    
    console.log('Match:', match); // Para debug
    
    if (match && match[1]) {
        // Encontrou um número de artigo
        const articleNumber = match[1].replace(/\./g, ''); // Remove pontos
        console.log('Número do artigo encontrado:', articleNumber);
        
        // Atualiza como string, não como número
        form.article_reference = articleNumber;
    } else {
        console.log('Nenhum número de artigo encontrado no texto');
    }
};

// Usa input event para garantir que a detecção seja feita imediatamente
const handleContentInput = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;
    const newText = target.value;
    detectArticleNumber(newText);
};

// Código corrigido para detectar mudanças no texto e processar corretamente
watch(() => form.original_content, (newText: string) => {
    console.log('Watcher de original_content acionado');
    console.log('Texto novo:', newText);
    
    // Limpa seleções anteriores
    selectedWordIndices.value.clear();
    
    if (!newText) {
        words.value = [];
        textLines.value = [];
        return;
    }
    
    // Verifica se há quebras de linha no texto
    console.log('Número de quebras de linha:', (newText.match(/\n/g) || []).length);
    
    // Divide o texto em linhas para preservar quebras de linha
    const lines = newText.split(/\r?\n/); // Compatível com quebras de linha Windows e Unix
    console.log('Número de linhas após split:', lines.length);
    console.log('Linhas:', lines);
    
    let wordIndex = 0;
    words.value = [];
    textLines.value = [];
    
lines.forEach((line, lineIdx) => {
        const lineWords: Word[] = [];
        
        // Verifica se a linha está vazia e adiciona um espaço para manter quebras de linha
        if (line.trim() === '') {
            console.log(`Linha ${lineIdx} está vazia`);
            const emptyLineObj = {
                text: '',
                space: '\n',
                index: wordIndex,
            };
            words.value.push(emptyLineObj);
            lineWords.push(emptyLineObj);
            wordIndex++;
            textLines.value.push(lineWords);
            return;
        }
        
        // Divide a linha em palavras, preservando pontuação
        const regex = /([^\s]+)(\s*)/g;
        const matches = [...line.matchAll(regex)];
        console.log(`Linha ${lineIdx} tem ${matches.length} palavras`);
        
        matches.forEach(match => {
            const wordObj = {
                text: match[1],       // A palavra
                space: match[2],      // O espaço após a palavra
                index: wordIndex,     // Índice global da palavra
            };
            
            words.value.push(wordObj);
            lineWords.push(wordObj);
            wordIndex++;
        });
        
        // Se não for a última linha, adiciona quebra de linha ao final
        if (lineIdx < lines.length - 1) {
            const lastWord = lineWords[lineWords.length - 1];
            if (lastWord) {
                lastWord.space += '\n';
            }
        }
        
        textLines.value.push(lineWords);
    });
    
    console.log('Processamento concluído:');
    console.log('Total de palavras:', words.value.length);
    console.log('Total de linhas:', textLines.value.length);
}, { immediate: true });

// Processa a seleção de palavra
const toggleWordSelection = (wordIndex: number) => {
    // Se a palavra já está selecionada, apenas remove
    if (selectedWordIndices.value.has(wordIndex)) {
        selectedWordIndices.value.delete(wordIndex);
    } else {
        // Verifica se a palavra já existe nas seleções
        const newWord = words.value[wordIndex].text;
        const isDuplicate = form.selected_words.some(item => item.word === newWord);
        
        if (isDuplicate) {
            toast({
                variant: "destructive",
                title: "Palavra duplicada",
                description: "Esta palavra já foi selecionada como lacuna neste texto.",
            });
            return;
        }
        
        selectedWordIndices.value.add(wordIndex);
    }
    
    // Atualiza o formulário com as palavras selecionadas
    // A ordem da lacuna (gap_order) é baseada na posição real no texto, não na ordem de seleção
    const selectedIndices = Array.from(selectedWordIndices.value);
    selectedIndices.sort((a, b) => a - b); // Ordena por posição no texto
    
    form.selected_words = selectedIndices.map((index, i) => {
        return {
            word: words.value[index].text,
            position: index,
            gap_order: i + 1 // A ordem da lacuna começa em 1 e segue a ordem no texto
        };
    });
};

// Remove uma opção personalizada
const removeCustomOption = (index: number) => {
    form.custom_options.splice(index, 1);
};

// Gera o texto com lacunas baseado nas palavras selecionadas
const practiceContent = computed(() => {
    if (!words.value.length) return '';
    
    return words.value.map((word, index) => {
        if (selectedWordIndices.value.has(index)) {
            return '_'.repeat(Math.max(5, word.text.length)) + word.space;
        } else {
            return word.text + word.space;
        }
    }).join('');
});

// Todas as palavras que serão opções para o usuário
const allOptions = computed(() => {
    const selectedWords = form.selected_words.map(item => item.word);
    const customWords = form.custom_options.map(item => item.word);
    
    return [...selectedWords, ...customWords];
});

// Função para lidar com opção criada
const handleOptionCreated = (newOption: { word: string }) => {
    // Adicionar a nova opção ao início da lista
    lawArticleOptions.value.unshift({
        id: Date.now(), // ID temporário para nova opção
        word: newOption.word,
        frequency: 1
    });
    
    // Adicionar ao formulário
    form.custom_options.push({
        word: newOption.word,
        is_correct: false
    });
};

// Função para lidar com opção selecionada
const handleOptionSelected = (option: LawArticleOption | null) => {
    if (!option) return;
    
    // Verificar se a opção já foi adicionada
    const exists = form.custom_options.some(item => item.word === option.word);
    
    if (!exists) {
        // Adicionar ao formulário
        form.custom_options.push({
            word: option.word,
            is_correct: false
        });
    }
    
    // Limpar a seleção para permitir novas escolhas
    customWordInput.value = '';
};

const submit = () => {
    // Adicionar o texto com lacunas gerado ao formulário
    form.practice_content = practiceContent.value;
    
    // Garantir que todas as palavras selecionadas tenham gap_order
    form.selected_words.forEach((word, index) => {
        if (!word.gap_order) {
            word.gap_order = index + 1;
        }
    });
    
    console.log('Enviando formulário:', form);
    
    form.post(route('admin.form.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: "Sucesso!",
                description: "Artigo de lei adicionado com sucesso.",
            })
            form.reset();
            selectedWordIndices.value.clear();
            words.value = [];
            textLines.value = [];
        },
        onError: (errors) => {
            console.error('Erros detalhados:', errors);
            
            // Criar uma mensagem de erro mais descritiva
            const errorMessages = Object.values(errors).flat().join(", ");
            
            toast({
                variant: "destructive",
                title: "Erro!",
                description: errorMessages || "Ocorreu um erro ao adicionar o artigo de lei.",
            })
        },
    });
};
</script>

<template>
    <Head title="Criar Artigo de Lei" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container p-8">
            <Card>
                <CardHeader>
                    <CardTitle>Criar Novo Artigo de Lei</CardTitle>
                    <CardDescription>
                        Preencha o texto do artigo de lei e selecione palavras para se tornarem lacunas.
                    </CardDescription>
                </CardHeader>
                
                <CardContent>
                    <form @submit.prevent="submit" class="flex flex-col gap-6">
                        <div class="grid gap-2">
                            <Label for="original_content">Conteúdo Original</Label>
                            <Textarea 
                                id="original_content" 
                                v-model="form.original_content" 
                                placeholder="Digite o texto completo do artigo de lei..." 
                                required 
                                class="min-h-28"
                                rows="8"
                                @input="handleContentInput"
                            />
                            <p class="text-sm text-muted-foreground">
                                Insira o texto completo do artigo, conforme publicado oficialmente.
                                As quebras de linha serão preservadas.
                            </p>
                            <InputError :message="form.errors.original_content" />
                        </div>
                        
                        <!-- Componente de busca e criação de referência legal -->
                        <SearchCreateSelect
                            label="Referência Legal"
                            placeholder="Buscar ou criar nova referência legal..."
                            :items="legalReferences"
                            v-model:value="form.legal_reference_id"
                            :error-message="form.errors.legal_reference_id"
                            item-label-field="name"
                            item-description-field="description"
                            required
                            create-entity-name="referência legal"
                            create-route="admin.legal-references.store"
                            @item-created="handleReferenceCreated"
                            show-edit-button
                        >
                            <template #item-description="{ item }">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-muted-foreground">{{ item.description }}</span>
                                    <span v-if="item.difficulty_level" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        Nível {{ item.difficulty_level }}
                                    </span>
                                </div>
                            </template>
                        </SearchCreateSelect>
                        
                        <!-- Referência específica do artigo com Input normal -->
                        <div class="grid gap-2">
                            <div class="max-w-[200px]">
                                <Label for="article_reference">Referência do Artigo</Label>
                                <Input
                                    id="article_reference"
                                    v-model="form.article_reference"
                                    placeholder="Ex: 5"
                                    type="text" 
                                />
                                <p class="text-sm text-muted-foreground mt-1">
                                    Número do artigo (ex: para "Art. 5º", digite 5).
                                </p>
                                <InputError :message="form.errors.article_reference" />
                            </div>
                        </div>
                        
                        <!-- Área de visualização do texto para seleção de palavras (melhorada) -->
                        <div v-if="textLines.length > 0" class="grid gap-2">
                            <Label>Selecione as Palavras para Lacunas</Label>
                            <div class="p-4 border rounded-md bg-gray-50 dark:bg-gray-900 min-h-[100px] cursor-pointer overflow-auto whitespace-pre-wrap">
                                <!-- Renderiza o texto respeitando quebras de linha -->
                                <template v-for="(line, lineIndex) in textLines" :key="`line-${lineIndex}`">
                                    <div :class="{'mb-1': lineIndex < textLines.length - 1}">
                                        <template v-if="line.length === 0">
                                            <!-- Linha vazia -->
                                            <br>
                                        </template>
                                        <template v-else>
                                            <span
                                                v-for="word in line"
                                                :key="`word-${word.index}`"
                                                @click="toggleWordSelection(word.index)"
                                                class="inline-block"
                                                :class="{
                                                    'bg-primary text-primary-foreground px-1 py-0.5 rounded': selectedWordIndices.has(word.index),
                                                    'hover:bg-gray-200 dark:hover:bg-gray-800': !selectedWordIndices.has(word.index)
                                                }"
                                            >
                                                {{ word.text }}<span class="whitespace-pre">{{ word.space }}</span>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Clique nas palavras do texto para transformá-las em lacunas. As palavras selecionadas serão opções de resposta.
                            </p>
                        </div>
                        
                        <!-- Área para adicionar alternativas personalizadas com SearchCreateSelect -->
                        <div class="grid gap-2">
                            <Label>Adicionar Alternativas Personalizadas</Label>
                            <div class="flex flex-col gap-2">
                                <SearchCreateSelect
                                    placeholder="Buscar ou adicionar uma nova alternativa..."
                                    :items="lawArticleOptions"
                                    v-model:value="customWordInput"
                                    item-label-field="word"
                                    item-description-field="frequency"
                                    item-description-prefix="Frequência: "
                                    create-entity-name="alternativa"
                                    create-route="admin.law-article-options.store"
                                    @item-created="handleOptionCreated"
                                    @item-selected="handleOptionSelected"
                                    not-found-text="Nenhuma alternativa encontrada"
                                    create-button-text="Criar nova alternativa"
                                >
                                    <template #item-description="{ item }">
                                        <span class="text-sm text-muted-foreground">
                                            {{ item.frequency > 1 ? `Usado ${item.frequency} vezes` : 'Usado 1 vez' }}
                                        </span>
                                    </template>
                                </SearchCreateSelect>
                                
                                <p class="text-sm text-muted-foreground">
                                    Adicione palavras que não existem no texto original como alternativas falsas.
                                    Você pode selecionar alternativas já utilizadas em outros artigos ou criar novas.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Lista de alternativas disponíveis -->
                        <div v-if="allOptions.length > 0" class="grid gap-2">
                            <Label>Alternativas Disponíveis</Label>
                            <div class="flex flex-wrap gap-2">
                                <!-- Palavras selecionadas do texto -->
                                <Badge 
                                    v-for="(item, index) in form.selected_words" 
                                    :key="`selected-${index}`" 
                                    variant="default"
                                    class="flex items-center gap-1"
                                >
                                    {{ item.gap_order }}- {{ item.word }}
                                    <button type="button" @click="toggleWordSelection(item.position)" class="hover:text-red-500">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                                
                                <!-- Alternativas personalizadas -->
                                <Badge 
                                    v-for="(item, index) in form.custom_options" 
                                    :key="`custom-${index}`" 
                                    variant="outline"
                                    class="flex items-center gap-1"
                                >
                                    0- {{ item.word }}
                                    <button type="button" @click="removeCustomOption(index)" class="hover:text-red-500">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>
                        
                        <!-- Visualização do texto com lacunas (melhorada) -->
                        <div v-if="practiceContent" class="grid gap-2">
                            <Label>Prévia do Texto com Lacunas</Label>
                            <div class="p-4 border rounded-md bg-white dark:bg-gray-800">
                                <p class="whitespace-pre-wrap">{{ practiceContent }}</p>
                            </div>
                        </div>
                        
                        <!-- Nível de dificuldade -->
                        <div class="grid gap-2">
                            <Label for="difficulty_level">Nível de Dificuldade</Label>
                            <select 
                                id="difficulty_level" 
                                v-model="form.difficulty_level" 
                                class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="1">1 - Iniciante</option>
                                <option value="2">2 - Básico</option>
                                <option value="3">3 - Intermediário</option>
                                <option value="4">4 - Avançado</option>
                                <option value="5">5 - Especialista</option>
                            </select>
                        </div>
                        
                        <!-- Campo de posição oculto - será definido automaticamente -->
                        <input type="hidden" v-model="form.position" />
                        
                        <CardFooter class="flex justify-between px-0 pt-6">
                            <Button 
                                type="button" 
                                variant="outline" 
            @click="() => router.visit(route('play.map'))"
                            >
                                Cancelar
                            </Button>
                            <Button 
                                type="submit" 
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                Salvar Artigo de Lei
                            </Button>
                        </CardFooter>
                    </form>
                </CardContent>
            </Card>
        </div>
        <Toaster />
    </AppLayout>
</template>
