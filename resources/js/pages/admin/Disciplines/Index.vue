<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Edit, Plus, Trash2 } from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

const props = defineProps<{
  disciplines: Array<{
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    legislations_count: number;
  }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Disciplinas', href: '/admin/disciplines' },
];

const deleteDiscipline = (uuid: string, name: string) => {
  if (confirm(`Tem certeza que deseja excluir a disciplina "${name}"?\n\nEsta acao nao pode ser desfeita.`)) {
    router.delete(route('admin.disciplines.destroy', uuid as any), {
      onSuccess: () => router.reload(),
    });
  }
};
</script>

<template>
  <Head title="Disciplinas" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container p-8">
      <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
          <div>
            <CardTitle class="text-2xl font-bold">Disciplinas</CardTitle>
            <CardDescription>
              Gerencie as disciplinas do direito e associe legislacoes a cada uma.
            </CardDescription>
          </div>

          <Link :href="route('admin.disciplines.create')">
            <Button>
              <Plus class="h-4 w-4 mr-2" />
              Nova Disciplina
            </Button>
          </Link>
        </CardHeader>

        <CardContent>
          <Table>
            <TableCaption>Lista de disciplinas cadastradas</TableCaption>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Slug</TableHead>
                <TableHead>Descricao</TableHead>
                <TableHead>Legislacoes</TableHead>
                <TableHead class="text-right">Acoes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="discipline in props.disciplines" :key="discipline.id">
                <TableCell class="font-medium">{{ discipline.name }}</TableCell>
                <TableCell class="text-muted-foreground">{{ discipline.slug }}</TableCell>
                <TableCell>{{ discipline.description || '-' }}</TableCell>
                <TableCell>{{ discipline.legislations_count }}</TableCell>
                <TableCell class="text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Link :href="route('admin.disciplines.edit', discipline.uuid as any)">
                      <Button variant="ghost" size="icon">
                        <Edit class="h-4 w-4" />
                        <span class="sr-only">Editar</span>
                      </Button>
                    </Link>

                    <Button
                      variant="ghost"
                      size="icon"
                      @click="deleteDiscipline(discipline.uuid, discipline.name)"
                      class="text-destructive hover:text-destructive hover:bg-destructive/10"
                    >
                      <Trash2 class="h-4 w-4" />
                      <span class="sr-only">Deletar</span>
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
              <TableRow v-if="props.disciplines.length === 0">
                <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
                  Nenhuma disciplina encontrada
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
