<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Zap, Square, Link as LinkIcon, Globe, EyeOff } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import type { Legislation } from '@/types/legislation';

const props = defineProps<{
    legislation: Legislation;
    publishing: boolean;
    autoCreating: boolean;
    hasText: boolean;
    autoCreateProgress: { current: number; total: number } | null;
}>();

const emit = defineEmits<{
    autoCreate: [];
    stopAutoCreate: [];
    togglePublish: [];
}>();
</script>

<template>
    <header class="h-14 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-4 flex-shrink-0">
        <div class="flex items-center gap-3">
            <Link :href="route('editor.index')" class="text-gray-400 hover:text-gray-600">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div>
                <h1 class="text-sm font-bold text-gray-900 dark:text-white leading-tight truncate max-w-xs">
                    {{ legislation.title }}
                </h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <Badge :variant="legislation.status === 'published' ? 'default' : 'outline'" class="text-[10px]">
                        {{ legislation.status === 'published' ? 'Publicado' : 'Rascunho' }}
                    </Badge>
                    <span v-if="legislation.legal_reference" class="text-[10px] text-gray-400">
                        {{ legislation.legal_reference }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a
                v-if="legislation.source_url"
                :href="legislation.source_url"
                target="_blank"
                class="text-gray-400 hover:text-gray-600 p-2"
            >
                <LinkIcon class="w-4 h-4" />
            </a>
            <template v-if="hasText">
                <!-- Durante auto-criação: progresso + botão Parar -->
                <template v-if="autoCreating">
                    <span class="text-xs text-gray-500 tabular-nums">
                        {{ autoCreateProgress?.current ?? 0 }}/{{ autoCreateProgress?.total ?? '...' }}
                    </span>
                    <Button
                        variant="destructive"
                        size="sm"
                        @click="emit('stopAutoCreate')"
                        class="gap-1.5 text-xs"
                    >
                        <Square class="w-3 h-3" />
                        Parar
                    </Button>
                </template>

                <!-- Estado normal: botão auto-criar -->
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    @click="emit('autoCreate')"
                    class="gap-1.5 text-xs"
                >
                    <Zap class="w-3.5 h-3.5" />
                    Auto-criar Blocos
                </Button>

                <Button
                    size="sm"
                    :variant="legislation.status === 'published' ? 'outline' : 'default'"
                    @click="emit('togglePublish')"
                    :disabled="publishing || autoCreating"
                    class="gap-1.5 text-xs"
                >
                    <template v-if="publishing">
                        Salvando...
                    </template>
                    <template v-else-if="legislation.status === 'published'">
                        <EyeOff class="w-3.5 h-3.5" />
                        Despublicar
                    </template>
                    <template v-else>
                        <Globe class="w-3.5 h-3.5" />
                        Publicar
                    </template>
                </Button>
            </template>
        </div>
    </header>
</template>
