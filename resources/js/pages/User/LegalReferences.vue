<template>
  <AppLayout title="Minhas Leis">
    <div class="container py-4 md:py-8 px-3 md:px-4">
      <div class="w-full sm:w-[95%] lg:w-[50rem] mx-auto">
        <Card>
          <CardHeader>
            <CardTitle class="text-2xl font-bold">Selecione as leis que deseja estudar</CardTitle>
          </CardHeader>
          
          <CardContent>
            <!-- Campo de pesquisa -->
            <div class="mb-6">
              <div class="relative">
                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Pesquisar legislação..."
                  class="pl-10"
                />
              </div>
            </div>
            
            <form @submit.prevent="submit">
              <div v-if="filteredReferences.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <Card v-for="reference in filteredReferences" :key="reference.id" class="transition-colors">
                  <CardContent class="p-4">
                    <div class="flex items-center space-x-2">
                      <Checkbox 
                        :id="`reference-${reference.id}`"
                        :checked="isChecked(reference.id)" 
                        @update:checked="toggleReference(reference.id)"
                      />
                      <Label :for="`reference-${reference.id}`" class="font-medium">{{ reference.name }}</Label>
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

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Search, FileSearch } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import Toaster from '@/components/ui/toast/Toaster.vue';

const { toast } = useToast();

const props = defineProps({
  legalReferences: Array,
  userReferences: Array,
});

const form = useForm({
  references: props.userReferences || [],
});

// Campo de pesquisa
const searchQuery = ref('');

// Filtragem de referências baseada na pesquisa
const filteredReferences = computed(() => {
  if (!searchQuery.value) {
    return props.legalReferences;
  }
  
  const query = searchQuery.value.toLowerCase();
  return props.legalReferences.filter(reference => 
    reference.name.toLowerCase().includes(query) || 
    (reference.description && reference.description.toLowerCase().includes(query))
  );
});

// Verifica se uma referência está selecionada
const isChecked = (referenceId) => {
  return form.references.includes(referenceId);
};

// Adiciona ou remove uma referência do array quando o checkbox é clicado
const toggleReference = (referenceId) => {
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