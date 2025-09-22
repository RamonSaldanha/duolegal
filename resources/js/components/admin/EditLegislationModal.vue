<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog';
import { LoaderCircle } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import InputError from '@/components/InputError.vue';

interface LegalReference {
  id: number;
  uuid: string;
  name: string;
  description?: string;
  difficulty_level?: number;
}

interface Props {
  open: boolean;
  legalReference: LegalReference;
}

interface Emits {
  (e: 'update:open', value: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { toast } = useToast();

// Computed para controlar abertura do modal
const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value)
});

// Form para edição da legislação
const form = useForm({
  name: '',
  description: '',
  difficulty_level: 1,
});

// Watch para preencher o form quando o modal abrir com novos dados
watch(() => [props.open, props.legalReference], ([open, legalRef]) => {
  if (open && legalRef) {
    form.name = legalRef.name;
    form.description = legalRef.description || '';
    form.difficulty_level = legalRef.difficulty_level || 1;
  }
}, { immediate: true });

// Função para salvar as alterações
const saveChanges = () => {
  form.put(route('admin.legal-references.update', props.legalReference.uuid), {
    onSuccess: () => {
      toast({
        title: "Sucesso!",
        description: "Legislação atualizada com sucesso.",
      });
      // Fechar modal após sucesso - o Inertia.js já redirecionará e atualizará os dados
      isOpen.value = false;
    },
    onError: (errors) => {
      console.error('Erros de validação:', errors);

      // Mostrar toast de erro
      const errorMessages = Object.values(errors).flat().join(", ");
      toast({
        variant: "destructive",
        title: "Erro!",
        description: errorMessages || "Ocorreu um erro ao atualizar a legislação.",
      });
    },
  });
};

// Função para cancelar e fechar modal
const cancel = () => {
  form.reset();
  form.clearErrors();
  isOpen.value = false;
};

// Níveis de dificuldade disponíveis
const difficultyLevels = [
  { value: 1, label: '1 - Iniciante' },
  { value: 2, label: '2 - Básico' },
  { value: 3, label: '3 - Intermediário' },
  { value: 4, label: '4 - Avançado' },
  { value: 5, label: '5 - Especialista' },
];
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle>Editar Legislação</DialogTitle>
        <DialogDescription>
          Faça alterações nos dados da legislação. Clique em salvar quando terminar.
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="saveChanges" class="grid gap-4 py-4">
        <!-- Campo Nome -->
        <div class="grid gap-2">
          <Label for="name">Nome da Legislação *</Label>
          <Input
            id="name"
            v-model="form.name"
            placeholder="Ex: Código Civil"
            required
            :disabled="form.processing"
          />
          <InputError :message="form.errors.name" />
        </div>

        <!-- Campo Descrição -->
        <div class="grid gap-2">
          <Label for="description">Descrição (opcional)</Label>
          <Textarea
            id="description"
            v-model="form.description"
            placeholder="Breve descrição da legislação..."
            rows="3"
            :disabled="form.processing"
          />
          <InputError :message="form.errors.description" />
        </div>

        <!-- Campo Nível de Dificuldade -->
        <div class="grid gap-2">
          <Label for="difficulty_level">Nível de Dificuldade</Label>
          <select
            id="difficulty_level"
            v-model="form.difficulty_level"
            :disabled="form.processing"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option
              v-for="level in difficultyLevels"
              :key="level.value"
              :value="level.value"
            >
              {{ level.label }}
            </option>
          </select>
          <InputError :message="form.errors.difficulty_level" />
        </div>
      </form>

      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          @click="cancel"
          :disabled="form.processing"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          @click="saveChanges"
          :disabled="form.processing || !form.name"
        >
          <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
          Salvar Alterações
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>