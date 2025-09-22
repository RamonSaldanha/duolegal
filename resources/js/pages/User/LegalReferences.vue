<template>
  <AppLayout title="Minhas Leis">
    <div class="container py-4 md:py-8 px-3 md:px-4">
      <div class="w-full sm:w-[95%] lg:w-[50rem] mx-auto">
        <Card>
          <CardHeader>
            <CardTitle class="text-2xl font-bold">Selecione as leis que deseja estudar</CardTitle>
          </CardHeader>
          
          <CardContent>
            <!-- Campo de pesquisa e filtros -->
            <div class="mb-6 space-y-4">
              <div class="relative">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Pesquisar legislação..."
                  class="pl-10"
                />
              </div>
              
              <!-- Filtros por nível -->
              <div class="flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  :class="{ 'bg-primary text-primary-foreground': selectedLevel === null }"
                  @click="selectedLevel = null"
                >
                  Todos os níveis
                </Button>
                <Button
                  v-for="level in [1, 2, 3, 4, 5]"
                  :key="level"
                  variant="outline"
                  size="sm"
                  :class="{ 'bg-primary text-primary-foreground': selectedLevel === level }"
                  @click="selectedLevel = level"
                >
                  {{ getDifficultyText(level) }}
                </Button>
              </div>
            </div>
            
            <form @submit.prevent="submit">
              <div v-if="filteredReferences.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <Card v-for="reference in filteredReferences" :key="reference.id" class="transition-colors">
                  <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                      <div class="flex items-start space-x-2 flex-1">
                        <Checkbox 
                          :id="`reference-${reference.id}`"
                          :checked="isChecked(reference.id)" 
                          @update:checked="toggleReference(reference.id)"
                        />
                        <div class="flex-1">
                          <Label :for="`reference-${reference.id}`" class="font-medium">{{ reference.name }}</Label>
                          <div class="flex items-center mt-1">
                            <Badge variant="secondary" class="text-xs">
                              {{ getDifficultyText(reference.difficulty_level || 1) }}
                            </Badge>
                          </div>
                        </div>
                      </div>
                    </div>
                    <p v-if="reference.description" class="text-sm text-muted-foreground mt-2 pl-6">
                      {{ reference.description }}
                    </p>
                  </CardContent>
                </Card>
              </div>
              
              <div v-else class="text-center py-10">
                <div class="text-muted-foreground">
                  <FileSearch class="h-12 w-12 mx-auto mb-3" />
                  <p>Nenhuma legislação encontrada para "{{ searchQuery }}"</p>
                </div>
              </div>
              
              <div class="flex justify-end">
                <Button 
                  type="submit" 
                  :disabled="form.processing"
                >
                  Salvar preferências
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
    <Toaster />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Search, FileSearch } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import Toaster from '@/components/ui/toast/Toaster.vue';

interface LegalReference {
  id: number;
  name: string;
  description?: string;
  difficulty_level: number;
}

const { toast } = useToast();

const props = defineProps<{
  legalReferences: LegalReference[];
  userReferences: number[];
}>();

const form = useForm({
  references: props.userReferences || [],
});

// Campo de pesquisa
const searchQuery = ref('');

// Filtro por nível
const selectedLevel = ref<number | null>(null);

// Função para converter nível numérico em texto descritivo
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

// Filtragem de referências baseada na pesquisa e nível
const filteredReferences = computed(() => {
  let references = props.legalReferences;
  
  // Filtro por nível
  if (selectedLevel.value !== null) {
    references = references.filter(reference => reference.difficulty_level === selectedLevel.value);
  }
  
  // Filtro por pesquisa
  if (!searchQuery.value) {
    return references;
  }
  
  const query = searchQuery.value.toLowerCase();
  return references.filter(reference => 
    reference.name.toLowerCase().includes(query) || 
    (reference.description && reference.description.toLowerCase().includes(query))
  );
});

// Verifica se uma referência está selecionada
const isChecked = (referenceId: number): boolean => {
  return form.references.includes(referenceId);
};

// Adiciona ou remove uma referência do array quando o checkbox é clicado
const toggleReference = (referenceId: number) => {
  const index = form.references.indexOf(referenceId);
  if (index === -1) {
    // Adiciona ao array se não estiver presente
    form.references.push(referenceId);
  } else {
    // Remove do array se já estiver presente
    form.references.splice(index, 1);
  }
};

const submit = () => {
  form.post(route('user.legal-references.store'), {
    preserveScroll: true,
    onSuccess: () => {
      toast({
        title: "Sucesso!",
        description: "Suas preferências de legislações foram salvas com sucesso.",
      });
    },
    onError: (errors) => {
      console.error('Erros detalhados:', errors);
      
      // Criar uma mensagem de erro mais descritiva
      const errorMessages = Object.values(errors).flat().join(", ");
      
      toast({
        variant: "destructive",
        title: "Erro!",
        description: errorMessages || "Ocorreu um erro ao salvar suas preferências.",
      });
    },
  });
};
</script>