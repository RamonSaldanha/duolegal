<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import Icon from '@/components/Icon.vue';
import DisciplineBadge from '@/components/DisciplineBadge.vue';
import { ref } from 'vue';

const props = defineProps<{
  discipline: {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    icon: string;
    color: string;
  };
  legislations: Array<{
    id: number;
    uuid: string;
    title: string;
  }>;
  selectedLegislationIds: number[];
  availableIcons: string[];
  availableColors: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Disciplinas', href: '/admin/disciplines' },
  { title: 'Editar', href: '#' },
];

const form = ref({
  name: props.discipline.name,
  description: props.discipline.description || '',
  icon: props.discipline.icon || 'Scale',
  color: props.discipline.color || '#EAB308',
  legislation_ids: [...props.selectedLegislationIds],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const toggleLegislation = (id: number, checked: boolean) => {
  if (checked) {
    form.value.legislation_ids.push(id);
  } else {
    form.value.legislation_ids = form.value.legislation_ids.filter(lid => lid !== id);
  }
};

const submit = () => {
  processing.value = true;
  router.put(route('admin.disciplines.update', props.discipline.uuid as any), form.value, {
    onError: (e) => {
      errors.value = e;
      processing.value = false;
    },
    onFinish: () => {
      processing.value = false;
    },
  });
};
</script>

<template>
  <Head :title="'Editar: ' + discipline.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container max-w-2xl p-8">
      <Card>
        <CardHeader>
          <CardTitle class="text-2xl font-bold">Editar Disciplina</CardTitle>
          <CardDescription>
            Edite os dados da disciplina "{{ discipline.name }}".
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
              <Label for="name">Nome</Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Ex: Direito Digital"
                required
              />
              <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="description">Descricao</Label>
              <Textarea
                id="description"
                v-model="form.description"
                placeholder="Descricao da disciplina (opcional)"
                rows="3"
              />
              <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description }}</p>
            </div>

            <!-- Icon picker -->
            <div class="space-y-3">
              <Label>Icone do Brasao</Label>
              <div class="grid grid-cols-6 sm:grid-cols-9 gap-2">
                <button
                  v-for="iconName in availableIcons"
                  :key="iconName"
                  type="button"
                  @click="form.icon = iconName"
                  class="flex items-center justify-center w-10 h-10 rounded-lg border-2 transition-all"
                  :class="form.icon === iconName
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                >
                  <Icon :name="iconName" :size="20" class="text-gray-700 dark:text-gray-300" />
                </button>
              </div>
            </div>

            <!-- Color picker -->
            <div class="space-y-3">
              <Label>Cor do Brasao</Label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="colorHex in availableColors"
                  :key="colorHex"
                  type="button"
                  @click="form.color = colorHex"
                  class="w-9 h-9 rounded-full border-2 transition-all"
                  :class="form.color === colorHex
                    ? 'border-gray-900 dark:border-white scale-110'
                    : 'border-transparent hover:scale-105'"
                  :style="{ backgroundColor: colorHex }"
                />
              </div>
            </div>

            <!-- Preview -->
            <div class="space-y-2">
              <Label>Pre-visualizacao</Label>
              <div class="flex items-center gap-6 p-4 border rounded-lg bg-gray-50 dark:bg-gray-800/50">
                <DisciplineBadge :icon="form.icon" :color="form.color" :level="1" size="sm" />
                <div>
                  <p class="font-bold text-gray-900 dark:text-white text-sm">{{ form.name || 'Nome da disciplina' }}</p>
                  <p class="text-xs text-gray-500">15 XP / 100 XP</p>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <Label>Legislacoes Associadas</Label>
              <p class="text-sm text-muted-foreground">Selecione as legislacoes que fazem parte desta disciplina.</p>

              <div class="space-y-2 max-h-64 overflow-y-auto border rounded-md p-3">
                <div
                  v-for="leg in legislations"
                  :key="leg.id"
                  class="flex items-center gap-2"
                >
                  <Checkbox
                    :id="'leg-' + leg.id"
                    :checked="form.legislation_ids.includes(leg.id)"
                    @update:checked="(checked: boolean) => toggleLegislation(leg.id, checked)"
                  />
                  <Label :for="'leg-' + leg.id" class="font-normal cursor-pointer">
                    {{ leg.title }}
                  </Label>
                </div>

                <p v-if="legislations.length === 0" class="text-sm text-muted-foreground text-center py-2">
                  Nenhuma legislacao disponivel
                </p>
              </div>
            </div>

            <div class="flex justify-end gap-3">
              <Button type="button" variant="outline" @click="router.visit(route('admin.disciplines.index'))">
                Cancelar
              </Button>
              <Button type="submit" :disabled="processing">
                Salvar Alteracoes
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
