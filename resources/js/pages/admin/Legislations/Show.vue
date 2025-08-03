<!-- filepath: /C:/Users/ramon/Desktop/study/resources/js/pages/admin/Legislations/Show.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ChevronLeft, PenSquare, Trash2 } from 'lucide-vue-next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';

// Recebe os dados da legislação específica do backend
const props = defineProps<{
  legalReference: {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    articles: Array<{
      id: number;
      uuid: string;
      article_reference: string;
      original_content: string;
      practice_content: string;
      options?: Array<{
        id: number;
        word: string;
        is_correct: boolean;
        position?: number;
      }>;
    }>;
  };
}>();

// Artigo atualmente selecionado para visualização
const selectedArticleId = ref(props.legalReference.articles[0]?.id || null);

// Define os breadcrumbs para navegação
const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
  },
  {
    title: 'Legislações',
    href: '/admin/legislations',
  },
  {
    title: props.legalReference.name,
    href: `/admin/legislations/${props.legalReference.id}`,
  },
];

// Encontra o artigo selecionado
const selectedArticle = computed(() => {
  return props.legalReference.articles.find(article => article.id === selectedArticleId.value) || null;
});

// Função para selecionar um artigo
const selectArticle = (articleId: number) => {
  selectedArticleId.value = articleId;
};

// Separar as opções em corretas e incorretas
const articleOptions = computed(() => {
    if (!selectedArticle.value || !selectedArticle.value.options) return { correct: [], incorrect: [] };
    
    const correct = selectedArticle.value.options
        .filter(option => option.is_correct)
        .sort((a, b) => (a.position || 0) - (b.position || 0));
        
    const incorrect = selectedArticle.value.options
        .filter(option => !option.is_correct);
        
    return { correct, incorrect };
});

// Função para deletar uma legislação
const deleteLegislation = () => {
  if (confirm(`Tem certeza que deseja excluir a legislação "${props.legalReference.name}"?\n\nEsta ação irá deletar todos os artigos, opções e progresso dos usuários relacionados a esta legislação. Esta ação não pode ser desfeita.`)) {
    router.delete(route('admin.legal-references.destroy', props.legalReference.uuid as any), {
      onSuccess: () => {
        router.visit(route('admin.legislations.index'));
      },
      onError: (errors) => {
        console.error('Erro ao deletar legislação:', errors);
        alert('Erro ao deletar a legislação. Tente novamente.');
      }
    });
  }
};
</script>

<template>
  <Head :title="legalReference.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container p-8">
      
      <Card class="mb-6">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <div>
            <CardTitle class="text-2xl font-bold">{{ legalReference.name }}</CardTitle>
            <CardDescription>
              {{ legalReference.description }}
            </CardDescription>
          </div>
          
          <div class="flex gap-2">
            <Link :href="route('admin.legislations.index')">
              <Button variant="outline">
                <ChevronLeft class="h-4 w-4 mr-2" />
                Voltar
              </Button>
            </Link>
            
            <Link v-if="selectedArticle" :href="route('admin.form.edit', selectedArticle.uuid as any)">
              <Button>
                <PenSquare class="h-4 w-4 mr-2" />
                Editar
              </Button>
            </Link>
            
            <Button 
              variant="destructive"
              @click="deleteLegislation"
            >
              <Trash2 class="h-4 w-4 mr-2" />
              Deletar Legislação
            </Button>
          </div>
        </CardHeader>
      </Card>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Lista de artigos (coluna da esquerda) -->
        <Card class="md:col-span-1">
          <CardHeader>
            <CardTitle>Artigos</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <Button 
                v-for="article in legalReference.articles" 
                :key="article.id"
                variant="ghost"
                :class="{'bg-secondary': article.id === selectedArticleId}"
                class="w-full justify-start"
                @click="selectArticle(article.id)"
              >
                Art. {{ article.article_reference }}
              </Button>
              
              <div v-if="legalReference.articles.length === 0" class="text-center text-muted-foreground py-4">
                Nenhum artigo encontrado
              </div>
            </div>
          </CardContent>
        </Card>
      
        <!-- Visualização do artigo (coluna da direita) -->
        <div class="md:col-span-3">
          <Tabs v-if="selectedArticle" default-value="original" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
              <TabsTrigger value="original">Texto Original</TabsTrigger>
              <TabsTrigger value="practice">Texto com Lacunas</TabsTrigger>
            </TabsList>
            <TabsContent value="original" class="mt-4">
              <Card>
                <CardHeader>
                  <CardTitle>Art. {{ selectedArticle.article_reference }}</CardTitle>
                  <CardDescription>Texto original do artigo</CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="p-4 bg-muted rounded-md">
                    <p class="whitespace-pre-wrap">{{ selectedArticle.original_content }}</p>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
            <TabsContent value="practice" class="mt-4">
              <Card>
                <CardHeader>
                  <CardTitle>Art. {{ selectedArticle.article_reference }}</CardTitle>
                  <CardDescription>Texto com lacunas para prática</CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="p-4 bg-muted rounded-md">
                    <p class="whitespace-pre-wrap">{{ selectedArticle.practice_content }}</p>
                  </div>
                  
                  <!-- Seção de opções -->
                  <div class="mt-6">
                      <h3 class="font-medium mb-2">Opções de Preenchimento</h3>
                      
                      <div class="flex flex-col gap-4">
                          <!-- Opções corretas (palavras removidas para criar lacunas) -->
                          <div v-if="articleOptions.correct.length > 0">
                              <h4 class="text-sm text-muted-foreground mb-2">Palavras do texto original:</h4>
                              <div class="flex flex-wrap gap-2">
                                  <Badge 
                                      v-for="option in articleOptions.correct" 
                                      :key="`correct-${option.id}`"
                                      variant="default"
                                  >
                                      {{ option.word }}
                                  </Badge>
                              </div>
                          </div>
                          
                          <!-- Opções incorretas (alternativas adicionais) -->
                          <div v-if="articleOptions.incorrect.length > 0">
                              <h4 class="text-sm text-muted-foreground mb-2">Alternativas adicionais:</h4>
                              <div class="flex flex-wrap gap-2">
                                  <Badge 
                                      v-for="option in articleOptions.incorrect" 
                                      :key="`incorrect-${option.id}`"
                                      variant="outline"
                                  >
                                      {{ option.word }}
                                  </Badge>
                              </div>
                          </div>
                          
                          <!-- Caso não tenha opções -->
                          <p v-if="articleOptions.correct.length === 0 && articleOptions.incorrect.length === 0" 
                             class="text-sm text-muted-foreground">
                              Não há opções de preenchimento definidas para este artigo.
                          </p>
                      </div>
                  </div>
                  
                  <div class="mt-4">
                      <Link :href="route('admin.form.edit', selectedArticle.uuid as any)" class="text-sm text-primary hover:underline">
                          Editar este artigo para modificar as opções
                      </Link>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
          
          <Card v-else>
            <CardContent class="text-center text-muted-foreground py-12">
              Selecione um artigo para visualizá-lo
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Estilos adicionais se necessários */
</style>
