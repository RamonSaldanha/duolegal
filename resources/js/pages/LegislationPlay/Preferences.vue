<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Search, FileSearch } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';
import Toaster from '@/components/ui/toast/Toaster.vue';

interface LegislationPreference {
    id: number;
    uuid: string;
    title: string;
    total_blocks: number;
    completed_blocks: number;
    percentage: number;
    is_selected: boolean;
}

const { toast } = useToast();

const props = defineProps<{
    legislations: LegislationPreference[];
}>();

const form = useForm({
    legislation_ids: props.legislations.filter(l => l.is_selected).map(l => l.id),
});

const searchQuery = ref('');

const filteredLegislations = computed(() => {
    if (!searchQuery.value) return props.legislations;

    const query = searchQuery.value.toLowerCase();
    return props.legislations.filter(leg =>
        leg.title.toLowerCase().includes(query),
    );
});

const isChecked = (id: number): boolean => {
    return form.legislation_ids.includes(id);
};

const toggleLegislation = (id: number) => {
    const index = form.legislation_ids.indexOf(id);
    if (index === -1) {
        form.legislation_ids.push(id);
    } else {
        form.legislation_ids.splice(index, 1);
    }
};

const submit = () => {
    if (form.legislation_ids.length === 0) {
        toast({
            variant: 'destructive',
            title: 'Atenção',
            description: 'Selecione pelo menos uma legislação.',
        });
        return;
    }

    form.post(route('play.preferences.save'), {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Sucesso!',
                description: 'Suas preferências foram salvas.',
            });
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(', ');
            toast({
                variant: 'destructive',
                title: 'Erro!',
                description: errorMessages || 'Ocorreu um erro ao salvar suas preferências.',
            });
        },
    });
};
</script>

<template>
    <Head title="Preferências de Legislações" />

    <AppLayout>
        <div class="container py-4 md:py-8 px-3 md:px-4">
            <div class="w-full sm:w-[95%] lg:w-[50rem] mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-2xl font-bold">
                            Selecione as legislações que deseja estudar
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <!-- Busca -->
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
                            <div
                                v-if="filteredLegislations.length > 0"
                                class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6"
                            >
                                <Card
                                    v-for="leg in filteredLegislations"
                                    :key="leg.id"
                                    class="transition-colors"
                                    :class="isChecked(leg.id) ? 'border-blue-400 dark:border-blue-500' : ''"
                                >
                                    <CardContent class="p-4">
                                        <div class="flex items-start space-x-3">
                                            <Checkbox
                                                :id="`leg-${leg.id}`"
                                                :checked="isChecked(leg.id)"
                                                @update:checked="toggleLegislation(leg.id)"
                                                class="mt-0.5"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <Label :for="`leg-${leg.id}`" class="font-medium text-sm leading-tight">
                                                    {{ leg.title }}
                                                </Label>

                                                <!-- Barra de progresso -->
                                                <div class="mt-3">
                                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                        <span>{{ leg.completed_blocks }} de {{ leg.total_blocks }} blocos</span>
                                                        <span class="font-medium" :class="leg.percentage === 100 ? 'text-green-600 dark:text-green-400' : ''">
                                                            {{ leg.percentage }}%
                                                        </span>
                                                    </div>
                                                    <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                        <div
                                                            class="h-full rounded-full transition-all duration-300"
                                                            :class="leg.percentage === 100 ? 'bg-green-500' : 'bg-blue-500'"
                                                            :style="{ width: `${leg.percentage}%` }"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
