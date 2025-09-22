<!-- filepath: /C:/Users/ramon/Desktop/study/resources/js/components/SearchCreateSelect.vue -->
<!-- <SearchCreateSelect
label="Tags"
placeholder="Buscar ou adicionar nova tag..."
:items="tagsDisponiveis"
v-model:value="form.tag_id"
:error-message="form.errors.tag_id"
item-label-field="nome"
item-description-field="categoria"
create-entity-name="tag"
@item-created="handleTagCreated"
/> -->
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';;
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { LoaderCircle, Plus, Edit2 } from 'lucide-vue-next';
import axios from 'axios'; // Adicionando a importação do axios

const props = defineProps({
    label: {
        type: String,
        default: 'Selecione um item'
    },
    placeholder: {
        type: String,
        default: 'Buscar ou criar novo item...'
    },
    items: {
        type: Array,
        required: true
    },
    required: {
        type: Boolean,
        default: false
    },
    itemIdField: {
        type: String,
        default: 'id'
    },
    itemLabelField: {
        type: String,
        default: 'nome'
    },
    itemDescriptionField: {
        type: String,
        default: 'descricao'
    },
    errorMessage: {
        type: String,
        default: ''
    },
    value: {
        type: [String, Number],
        default: null
    },
    createRoute: {
        type: String,
        default: ''
    },
    createEntityName: {
        type: String,
        default: 'item'
    },
    showEditButton: {
        type: Boolean,
        default: false
    },
    enableDifficultyLevel: {
        type: Boolean,
        default: false
    },
    itemDescriptionPrefix: {
        type: String,
        default: ''
    },
    notFoundText: {
        type: String,
        default: ''
    },
    createButtonText: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:value', 'item-selected', 'item-created']);

// Estado para busca de itens
const searchTerm = ref('');
const showResults = ref(false);
const isCreatingNew = ref(false);
const isEditing = ref(false);
const isLoading = ref(false);
const selectedItem = ref(null);
const editingItem = ref(null);

// Estado para o formulário de novo item
const newItemForm = useForm({
    [props.itemLabelField]: '',
    [props.itemDescriptionField]: '',
    difficulty_level: 1,
});

// Estado para o formulário de edição
const editItemForm = useForm({
    [props.itemLabelField]: '',
    [props.itemDescriptionField]: '',
    difficulty_level: 1,
});

// Filtra itens com base no termo de busca
const filteredItems = computed(() => {
    if (!searchTerm.value) return props.items;
    return props.items.filter(item => 
        String(item[props.itemLabelField]).toLowerCase().includes(searchTerm.value.toLowerCase()) || 
        (item[props.itemDescriptionField] && 
         String(item[props.itemDescriptionField]).toLowerCase().includes(searchTerm.value.toLowerCase()))
    );
});

// Verifica se não encontrou resultados na busca
const noResults = computed(() => {
    return searchTerm.value.length > 0 && filteredItems.value.length === 0;
});

// Atualiza o formulário do novo item quando o termo de busca muda
watch(searchTerm, (newValue) => {
    if (isCreatingNew.value) {
        newItemForm[props.itemLabelField] = newValue;
    }
});

// Watch para inicializar o valor selecionado
watch(() => props.value, (newValue) => {
    if (newValue !== null && props.items.length > 0) {
        // Procura o item com o ID correspondente
        const matchedItem = props.items.find(item => item[props.itemIdField] == newValue);
        if (matchedItem) {
            selectedItem.value = matchedItem;
            searchTerm.value = matchedItem[props.itemLabelField];
        }
    }
}, { immediate: true });

const selectItem = (item) => {
    selectedItem.value = item;
    searchTerm.value = item[props.itemLabelField];
    emit('update:value', item[props.itemIdField]);
    emit('item-selected', item);
    showResults.value = false;
};

const handleFocus = () => {
    showResults.value = true;
    isCreatingNew.value = false;
};

const handleBlur = () => {
    // Pequeno atraso para permitir clicks nos resultados
    setTimeout(() => {
        if (!isCreatingNew.value) {
            showResults.value = false;
        }
    }, 200);
};

// Inicia o processo de criação de novo item
const startCreatingItem = () => {
    isCreatingNew.value = true;
    newItemForm[props.itemLabelField] = searchTerm.value;
    newItemForm[props.itemDescriptionField] = '';
};

// Cancela a criação do novo item
const cancelCreating = () => {
    isCreatingNew.value = false;
    showResults.value = false;
};

// Inicia a edição de um item
const startEditingItem = (item) => {
    editingItem.value = item;
    isEditing.value = true;
    showResults.value = false;

    // Preenche o formulário de edição com os dados do item
    editItemForm[props.itemLabelField] = item[props.itemLabelField];
    editItemForm[props.itemDescriptionField] = item[props.itemDescriptionField] || '';
    editItemForm.difficulty_level = item.difficulty_level || 1;
};

// Cancela a edição
const cancelEditing = () => {
    isEditing.value = false;
    editingItem.value = null;
    editItemForm.reset();
};

// Salva as alterações do item
const saveEditedItem = () => {
    if (!editingItem.value) return;

    isLoading.value = true;

    const formData = {};
    formData[props.itemLabelField] = editItemForm[props.itemLabelField];
    formData[props.itemDescriptionField] = editItemForm[props.itemDescriptionField];
    if (props.createEntityName === 'referência legal') {
        formData.difficulty_level = editItemForm.difficulty_level;
    }

    // Usa a rota de update (PUT)
    const updateRoute = props.createRoute.replace('.store', '.update');

    axios.put(route(updateRoute, editingItem.value.slug || editingItem.value.id), formData)
        .then(response => {
            // Atualiza o item na lista local
            const index = props.items.findIndex(item => item.id === editingItem.value.id);
            if (index !== -1) {
                props.items[index] = { ...props.items[index], ...response.data };
            }

            // Se este item estava selecionado, atualiza a exibição
            if (selectedItem.value && selectedItem.value.id === editingItem.value.id) {
                selectedItem.value = { ...selectedItem.value, ...response.data };
                searchTerm.value = selectedItem.value[props.itemLabelField];
            }

            cancelEditing();

            // Emite evento para notificar o componente pai
            emit('item-created', response.data);
        })
        .catch(error => {
            console.error('Erro ao editar item:', error);
            alert('Ocorreu um erro ao editar o item. Tente novamente.');
        })
        .finally(() => {
            isLoading.value = false;
        });
};

// Salva o novo item
const saveItem = () => {
    isLoading.value = true;
    
    if (props.createRoute) {
        // Enviamos os dados usando os nomes de campo do backend
        const formData = {};
        formData[props.itemLabelField] = newItemForm[props.itemLabelField];
        formData[props.itemDescriptionField] = newItemForm[props.itemDescriptionField];
        if (props.createEntityName === 'referência legal') {
            formData.difficulty_level = newItemForm.difficulty_level;
        }
        
        axios.post(route(props.createRoute), formData)
            .then(response => {
                handleItemCreated(response.data);
            })
            .catch(error => {
                console.error('Erro ao criar item:', error);
                // Exibir mensagem de erro para o usuário
                if (error.response && error.response.data && error.response.data.errors) {
                    // Se o servidor retornar erros de validação específicos
                    const serverErrors = error.response.data.errors;
                    // Aqui você pode manipular os erros específicos
                } else {
                    // Erro genérico
                    alert('Ocorreu um erro ao criar o item. Tente novamente.');
                }
            })
            .finally(() => {
                isLoading.value = false;
            });
    } else {
        // Simulação para demonstração (sem envio real)
        setTimeout(() => {
            // Cria um novo item com ID simulado para demonstração
            const newItem = {
                [props.itemIdField]: Date.now(), // ID temporário baseado no timestamp
                [props.itemLabelField]: newItemForm[props.itemLabelField],
                [props.itemDescriptionField]: newItemForm[props.itemDescriptionField] || `Novo ${props.createEntityName} adicionado`
            };
            
            handleItemCreated(newItem);
            isLoading.value = false;
        }, 500);
    }
};

const handleItemCreated = (newItem) => {
    // Emite o evento com o novo item
    emit('item-created', newItem);
    
    // Seleciona o novo item
    selectItem(newItem);
    
    // Limpa o formulário e fecha a UI de criação
    isCreatingNew.value = false;
    newItemForm.reset();
};
</script>

<template>
    <div class="grid gap-2">
        <Label v-if="label" :for="label.toLowerCase().replace(/\s+/g, '-')">{{ label }}</Label>
        <div class="relative">
            <Input 
                :id="label.toLowerCase().replace(/\s+/g, '-')" 
                v-model="searchTerm" 
                :placeholder="placeholder"
                @focus="handleFocus"
                @blur="handleBlur"
                :required="required" 
            />
            
            <!-- Modo de busca -->
            <div v-if="showResults && !isCreatingNew" 
                 class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg">
                
                <!-- Resultados encontrados -->
                <ul v-if="filteredItems.length > 0" class="py-1 max-h-60 overflow-auto">
                    <li v-for="item in filteredItems"
                        :key="item[itemIdField]"
                        class="group relative px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <div @mousedown.prevent="selectItem(item)" class="cursor-pointer">
                            <div class="font-medium">{{ item[itemLabelField] }}</div>
                            <div v-if="item[itemDescriptionField] || $slots['item-description']" class="text-sm text-gray-500 dark:text-gray-400">
                                <slot name="item-description" :item="item">
                                    {{ item[itemDescriptionField] }}
                                </slot>
                            </div>
                        </div>

                        <!-- Botão de edição -->
                        <button
                            v-if="showEditButton"
                            @mousedown.prevent="startEditingItem(item)"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Editar item"
                        >
                            <Edit2 class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
                
                <!-- Nenhum resultado - opção de criar -->
                <div v-else-if="noResults" class="p-2">
                    <div class="p-2 text-sm text-gray-500 dark:text-gray-400">
                        Nenhum {{ createEntityName }} encontrado com esse nome.
                    </div>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="w-full flex items-center justify-center gap-2"
                        @mousedown.prevent="startCreatingItem"
                    >
                        <Plus class="h-4 w-4" /> 
                        Criar {{ createEntityName }} "{{ searchTerm }}"
                    </Button>
                </div>
            </div>
            
            <!-- Modo de criação de novo item -->
            <div v-if="isCreatingNew" 
                 class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg p-4">
                <h3 class="text-sm font-medium mb-2">Adicionar novo {{ createEntityName }}</h3>
                
                <div class="space-y-3">
                    <div>
                        <Label :for="`novo-${createEntityName}-nome`">Nome</Label>
                        <Input 
                            :id="`novo-${createEntityName}-nome`"
                            v-model="newItemForm[itemLabelField]" 
                            :placeholder="`Ex: Nome do ${createEntityName}`"
                            class="mt-1"
                            required
                            autofocus
                            @keydown.esc="cancelCreating"
                        />
                    </div>
                    
                    <div>
                        <Label :for="`novo-${createEntityName}-descricao`">Descrição (opcional)</Label>
                        <Input
                            :id="`novo-${createEntityName}-descricao`"
                            v-model="newItemForm[itemDescriptionField]"
                            :placeholder="`Breve descrição do ${createEntityName}`"
                            class="mt-1"
                            @keydown.esc="cancelCreating"
                        />
                    </div>

                    <!-- Campo de nível de dificuldade para referências legais -->
                    <div v-if="createEntityName === 'referência legal'">
                        <Label :for="`novo-${createEntityName}-dificuldade`">Nível de Dificuldade</Label>
                        <select
                            :id="`novo-${createEntityName}-dificuldade`"
                            v-model="newItemForm.difficulty_level"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="1">1 - Iniciante</option>
                            <option value="2">2 - Básico</option>
                            <option value="3">3 - Intermediário</option>
                            <option value="4">4 - Avançado</option>
                            <option value="5">5 - Especialista</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end gap-2 pt-2">
                        <Button 
                            type="button" 
                            variant="ghost" 
                            size="sm" 
                            @click="cancelCreating"
                        >
                            Cancelar
                        </Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="saveItem"
                            :disabled="isLoading || !newItemForm[itemLabelField]"
                        >
                            <LoaderCircle v-if="isLoading" class="h-4 w-4 animate-spin mr-1" />
                            Salvar
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Modal de edição -->
            <div v-if="isEditing"
                 class="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg p-4">
                <h3 class="text-sm font-medium mb-2">Editar {{ createEntityName }}</h3>

                <div class="space-y-3">
                    <div>
                        <Label :for="`edit-${createEntityName}-nome`">Nome</Label>
                        <Input
                            :id="`edit-${createEntityName}-nome`"
                            v-model="editItemForm[itemLabelField]"
                            :placeholder="`Ex: Nome do ${createEntityName}`"
                            class="mt-1"
                            required
                            autofocus
                            @keydown.esc="cancelEditing"
                        />
                    </div>

                    <div>
                        <Label :for="`edit-${createEntityName}-descricao`">Descrição (opcional)</Label>
                        <Input
                            :id="`edit-${createEntityName}-descricao`"
                            v-model="editItemForm[itemDescriptionField]"
                            :placeholder="`Breve descrição do ${createEntityName}`"
                            class="mt-1"
                            @keydown.esc="cancelEditing"
                        />
                    </div>

                    <!-- Campo de nível de dificuldade para referências legais -->
                    <div v-if="createEntityName === 'referência legal'">
                        <Label :for="`edit-${createEntityName}-dificuldade`">Nível de Dificuldade</Label>
                        <select
                            :id="`edit-${createEntityName}-dificuldade`"
                            v-model="editItemForm.difficulty_level"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="1">1 - Iniciante</option>
                            <option value="2">2 - Básico</option>
                            <option value="3">3 - Intermediário</option>
                            <option value="4">4 - Avançado</option>
                            <option value="5">5 - Especialista</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="cancelEditing"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="saveEditedItem"
                            :disabled="isLoading || !editItemForm[itemLabelField]"
                        >
                            <LoaderCircle v-if="isLoading" class="h-4 w-4 animate-spin mr-1" />
                            Salvar
                        </Button>
                    </div>
                </div>
            </div>
        </div>
        <InputError :message="errorMessage" />
    </div>
</template>