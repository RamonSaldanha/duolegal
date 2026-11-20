<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Edit, Plus, RefreshCw, Trash2 } from 'lucide-vue-next';
import { type BadgeConfig, exportBadgeImages } from '@/lib/badges/export';
import { computed, ref } from 'vue';
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
    badge_url: string | null;
    badge_config: BadgeConfig | null;
    badge_outdated: boolean;
  }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Disciplinas', href: '/admin/disciplines' },
];

// Badges gerados no admin antes do estilo chapado: o navegador desenha de novo e envia, um por vez.
const outdated = computed(() => props.disciplines.filter((discipline) => discipline.badge_outdated && discipline.badge_config));
const redrawing = ref(false);
const redrawError = ref('');

const sendBadge = (uuid: string, config: BadgeConfig, images: File[]) =>
  new Promise<void>((resolve, reject) => {
    router.post(route('admin.disciplines.badge', uuid as any), { badge_config: config, badge_images: images, _method: 'put' }, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => resolve(),
      onError: () => reject(new Error('rejected')),
      onCancel: () => reject(new Error('cancelled')),
    });
  });

const redrawBadges = async () => {
  redrawing.value = true;
  redrawError.value = '';
  try {
    for (const discipline of [...outdated.value]) {
      const config = { ...discipline.badge_config! };
      await sendBadge(discipline.uuid, config, await exportBadgeImages(config));
    }
  } catch {
    redrawError.value = 'Não foi possível redesenhar todos os badges. Tente de novo.';
  } finally {
    redrawing.value = false;
  }
};

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
          <div v-if="outdated.length" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
            <p class="text-sm">
              {{ outdated.length === 1 ? '1 badge feito aqui ainda está' : `${outdated.length} badges feitos aqui ainda estão` }} com a arte antiga.
              <span v-if="redrawError" class="block text-destructive">{{ redrawError }}</span>
            </p>
            <Button :disabled="redrawing" @click="redrawBadges">
              <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': redrawing }" />
              {{ redrawing ? 'Redesenhando…' : 'Redesenhar no estilo novo' }}
            </Button>
          </div>

          <Table>
            <TableCaption>Lista de disciplinas cadastradas</TableCaption>
            <TableHeader>
              <TableRow>
                <TableHead>Badge</TableHead>
                <TableHead>Nome</TableHead>
                <TableHead>Slug</TableHead>
                <TableHead>Descricao</TableHead>
                <TableHead>Legislacoes</TableHead>
                <TableHead class="text-right">Acoes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="discipline in props.disciplines" :key="discipline.id">
                <TableCell>
                  <img
                    v-if="discipline.badge_url"
                    :src="discipline.badge_url"
                    :alt="`Badge de ${discipline.name}`"
                    class="aspect-square h-14 w-14 object-contain"
                  />
                  <span v-else class="text-xs text-muted-foreground">Sem badge</span>
                </TableCell>
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
                <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
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
