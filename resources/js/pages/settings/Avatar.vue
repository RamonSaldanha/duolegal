<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AvatarPreview from '@/components/avatar-editor/AvatarPreview.vue';
import ColorSwatches from '@/components/avatar-editor/ColorSwatches.vue';
import OptionTile from '@/components/avatar-editor/OptionTile.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    BACKGROUND_COLORS,
    BODIES_OPTIONS,
    BROWS,
    CLOTHES_COLORS,
    FACE_CROP,
    FULL_CROP,
    HAIRS,
    HAIR_COLORS,
    SKIN_COLORS,
    normalizeAvatarConfig,
    randomAvatarConfig,
    type AvatarColorKey,
    type AvatarConfig,
    type AvatarCrop,
    type AvatarShapeKey,
    type ShapeOption,
} from '@/lib/avatar';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Avatar', href: '/settings/avatar' }];

const page = usePage<SharedData>();

// Sem avatar salvo, o editor começa num sorteio — bem mais convidativo que abrir
// sempre no mesmo boneco padrão.
const form = useForm<{ avatar: AvatarConfig }>({
    avatar: page.props.auth.user?.avatar_config ? normalizeAvatarConfig(page.props.auth.user.avatar_config) : randomAvatarConfig(),
});

type Group =
    | { kind: 'shape'; key: AvatarShapeKey; label: string; options: ShapeOption<string>[]; crop: AvatarCrop }
    | { kind: 'color'; key: AvatarColorKey; label: string; colors: string[] };

// Enquanto couber numa tela só, não há motivo para abas — elas voltam quando o
// catálogo crescer.
const groups: Group[] = [
    { kind: 'shape', key: 'body', label: 'Corpo', options: BODIES_OPTIONS, crop: FULL_CROP },
    { kind: 'shape', key: 'hair', label: 'Cabelo', options: HAIRS, crop: FULL_CROP },
    { kind: 'shape', key: 'brows', label: 'Sobrancelha', options: BROWS, crop: FACE_CROP },
    { kind: 'color', key: 'skin', label: 'Tom da pele', colors: SKIN_COLORS },
    { kind: 'color', key: 'hairColor', label: 'Cor do cabelo e da sobrancelha', colors: HAIR_COLORS },
    { kind: 'color', key: 'clothesColor', label: 'Cor da roupa', colors: CLOTHES_COLORS },
    { kind: 'color', key: 'background', label: 'Cor do fundo', colors: BACKGROUND_COLORS },
];

/** Config do avatar com uma peça trocada, para desenhar a miniatura da opção. */
function previewWith(key: AvatarShapeKey, optionId: string): AvatarConfig {
    return { ...form.avatar, [key]: optionId } as AvatarConfig;
}

function select(key: AvatarShapeKey, optionId: string) {
    (form.avatar as Record<string, unknown>)[key] = optionId;
}

function shuffle() {
    form.avatar = randomAvatarConfig();
}

const firstError = computed(() => Object.values(form.errors)[0]);

const submit = () => form.put(route('avatar.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Meu avatar" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Meu avatar" description="Monte o personagem que representa você no ranking e no perfil." />

                <AvatarPreview :config="form.avatar" @shuffle="shuffle" />

                <div class="space-y-6">
                    <section v-for="group in groups" :key="group.key">
                        <h3 class="mb-3 text-sm font-bold text-gray-900 dark:text-white">{{ group.label }}</h3>

                        <ColorSwatches
                            v-if="group.kind === 'color'"
                            :colors="group.colors"
                            :label="group.label"
                            :model-value="form.avatar[group.key]"
                            @update:model-value="form.avatar[group.key] = $event"
                        />

                        <div v-else class="grid grid-cols-3 gap-2.5 sm:grid-cols-4">
                            <OptionTile
                                v-for="option in group.options"
                                :key="option.id"
                                :label="option.label"
                                :crop="group.crop"
                                :uid="`${group.key}-${option.id}`"
                                :preview="previewWith(group.key, option.id)"
                                :selected="form.avatar[group.key] === option.id"
                                @click="select(group.key, option.id)"
                            />
                        </div>
                    </section>
                </div>

                <p v-if="firstError" class="text-sm font-medium text-red-600">{{ firstError }}</p>

                <div class="flex items-center gap-4">
                    <Button :disabled="form.processing" @click="submit">Salvar avatar</Button>

                    <TransitionRoot
                        :show="form.recentlySuccessful"
                        enter="transition ease-in-out"
                        enter-from="opacity-0"
                        leave="transition ease-in-out"
                        leave-to="opacity-0"
                    >
                        <p class="text-sm text-neutral-600">Salvo.</p>
                    </TransitionRoot>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
