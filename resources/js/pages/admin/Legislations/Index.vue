<!-- filepath: /C:/Users/ramon/Desktop/study/resources/js/pages/admin/Legislations/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Eye, Plus } from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

// Importações corretas de paginação conforme a documentação do Shadcn
import {
  Pagination,
  PaginationEllipsis,
  PaginationFirst,
  PaginationLast,
  PaginationList,
  PaginationListItem,
  PaginationNext,
  PaginationPrev,
} from '@/components/ui/pagination';

// Recebe os dados de legislações do backend
const props = defineProps({
  legalReferences: {
    type: Array,
    default: () => []
  }
});

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
];

// Formatação de data
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('pt-BR').format(date);
};
</script>

<template>
  <Head title="Legislações" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container p-8">
      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <div>
            <CardTitle class="text-2xl font-bold">Legislações</CardTitle>
            <CardDescription>
              Visualize e gerencie todas as legislações disponíveis no sistema.
            </CardDescription>
          </div>
          
          <Link :href="route('admin.form.create')">
            <Button>
              <Plus class="h-4 w-4 mr-2" />
              Nova Legislação
            </Button>
          </Link>
        </CardHeader>
        
        <CardContent>
          <Table>
            <TableCaption>Lista de legislações cadastradas</TableCaption>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Descrição</TableHead>
                <TableHead>Artigos</TableHead>
                <TableHead>Data de Criação</TableHead>
                <TableHead class="text-right">Ações</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="legislation in props.legalReferences" :key="legislation.id">
                <TableCell class="font-medium">{{ legislation.name }}</TableCell>
                <TableCell>{{ legislation.description }}</TableCell>
                <TableCell>{{ legislation.articles_count || 0 }}</TableCell>
                <TableCell>{{ formatDate(legislation.created_at) }}</TableCell>
                <TableCell class="text-right">
                  <Link :href="route('admin.legislations.show', legislation.uuid)">
                    <Button variant="ghost" size="icon">
                      <Eye class="h-4 w-4" />
                      <span class="sr-only">Visualizar</span>
                    </Button>
                  </Link>
                </TableCell>
              </TableRow>
              <TableRow v-if="props.legalReferences.length === 0">
                <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
                  Nenhuma legislação encontrada
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
          
          <!-- Paginação implementada corretamente conforme a documentação -->
          <div class="mt-4">
            <Pagination :items-per-page="10" :total="props.legalReferences.length" :sibling-count="1" show-edges :default-page="1">
              <template v-slot="{ page }">
                <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                  <PaginationPrev />

                  <template v-for="(item, index) in items">
                    <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                      <Button class="w-10 h-10 p-0" :variant="item.value === page ? 'default' : 'outline'">
                        {{ item.value }}
                      </Button>
                    </PaginationListItem>
                    <PaginationEllipsis v-else :key="item.type" :index="index" />
                  </template>

                  <PaginationNext />
                </PaginationList>
              </template>
            </Pagination>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>