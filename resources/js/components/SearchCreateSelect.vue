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
import { LoaderCircle, Plus } from 'lucide-vue-next';
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
    }
});

const emit = defineEmits(['update:value', 'item-selected', 'item-created']);

// Estado para busca de itens
const searchTerm = ref('');
const showResults = ref(false);
const isCreatingNew = ref(false);
const isLoading = ref(false);
const selectedItem = ref(null);

// Estado para o formulário de novo item
const newItemForm = useForm({
    [props.itemLabelField]: '',
    [props.itemDescriptionField]: '',
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

// Salva o novo item
const saveItem = () => {
    isLoading.value = true;
    
    if (props.createRoute) {
        // Enviamos os dados usando os nomes de campo do backend
        const formData = {};
        formData[props.itemLabelField] = newItemForm[props.itemLabelField];
        formData[props.itemDescriptionField] = newItemForm[props.itemDescriptionField];
        
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
                        @mousedown.prevent="selectItem(item)"
                        class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <div class="font-medium">{{ item[itemLabelField] }}</div>
                        <div v-if="item[itemDescriptionField]" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ item[itemDescriptionField] }}
                        </div>
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
        </div>
        <InputError :message="errorMessage" />
    </div>
</template>