<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Plus, FileText, Trash2, ExternalLink } from 'lucide-vue-next';
import type { LegislationListItem } from '@/types/legislation';
import { ref } from 'vue';

const props = defineProps<{
    legislations: LegislationListItem[];
}>();

const showCreateDialog = ref(false);

const form = useForm({
    title: '',
});

function submit() {
    form.post(route('beta.editor.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
        },
    });
}

function deleteLegislation(uuid: string) {
    if (confirm('Tem certeza que deseja excluir esta legislação?')) {
        router.delete(route('beta.editor.destroy', uuid));
    }
}
</script>

<template>
    <AppLayout title="Editor de Legislação">
        <Head title="Editor de Legislação" />

        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Editor de Legislação</h1>
                    <p class="text-sm text-gray-500 mt-1">Gerencie legislações e crie blocos de desafio</p>
                </div>
                <Button @click="showCreateDialog = true" class="gap-2">
                    <Plus class="w-4 h-4" />
                    Nova Legislação
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Legislações</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="legislations.length === 0" class="text-center py-12 text-gray-500">
                        <FileText class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                        <p class="text-lg font-medium">Nenhuma legislação cadastrada</p>
                        <p class="text-sm mt-1">Clique em "Nova Legislação" para começar</p>
                    </div>

                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Título</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-center">Segmentos</TableHead>
                                <TableHead class="text-center">Blocos</TableHead>
                                <TableHead>Criado em</TableHead>
                                <TableHead class="text-right">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="leg in legislations" :key="leg.uuid">
                                <TableCell>
                                    <Link
                                        :href="route('beta.editor.show', leg.uuid)"
                                        class="font-medium text-gray-900 dark:text-white hover:underline"
                                    >
                                        {{ leg.title }}
                                    </Link>
                                    <p v-if="leg.legal_reference" class="text-xs text-gray-500 mt-0.5">
                                        {{ leg.legal_reference }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="leg.status === 'published' ? 'default' : 'outline'">
                                        {{ leg.status === 'published' ? 'Publicado' : 'Rascunho' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">{{ leg.segments_count }}</TableCell>
                                <TableCell class="text-center">{{ leg.blocks_count }}</TableCell>
                                <TableCell class="text-sm text-gray-500">{{ leg.created_at }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            v-if="leg.source_url"
                                            :href="leg.source_url"
                                            target="_blank"
                                            class="text-gray-400 hover:text-gray-600"
                                        >
                                            <ExternalLink class="w-4 h-4" />
                                        </a>
                                        <button
                                            @click="deleteLegislation(leg.uuid)"
                                            class="text-gray-400 hover:text-red-600"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Dialog de criação -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nova Legislação</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label for="title">Título</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="Ex: Constituição Federal de 1988"
                            class="mt-1"
                        />
                        <p v-if="form.errors.title" class="text-sm text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>
                    <p class="text-sm text-gray-500">Você poderá importar o texto a partir de uma URL no editor.</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreateDialog = false">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Criando...' : 'Criar Legislação' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
