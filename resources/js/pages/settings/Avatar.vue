<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Lock, Shuffle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AvatarPreview from '@/components/avatar-editor/AvatarPreview.vue';
import ColorSwatches from '@/components/avatar-editor/ColorSwatches.vue';
import OptionTile from '@/components/avatar-editor/OptionTile.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    BACKGROUND_COLORS,
    BEARDS,
    BEARD_CROP,
    BODIES_OPTIONS,
    BROWS,
    CLOTHES_COLORS,
    EARRINGS,
    EARRING_COLORS,
    EAR_CROP,
    FACE_CROP,
    FULL_CROP,
    GLASSES,
    GLASSES_COLORS,
    HAIRS,
    HAIR_COLORS,
    MOUTHS,
    MOUTH_CROP,
    OUTFITS,
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

interface Tab {
    id: string;
    label: string;
    groups: Group[];
}

// Uma aba por região do desenho, com a peça e a cor dela lado a lado — trocar de
// cabelo numa aba e ir pintar em outra seria o pior dos dois mundos.
//
// A ordem acompanha como se monta um personagem: corpo, rosto, cabelo, enfeite.
// A cor do fundo cai em "Corpo" por eliminação, já que é o único controle que
// não é parte do boneco.
const tabs: Tab[] = [
    {
        id: 'corpo',
        label: 'Corpo',
        groups: [
            { kind: 'shape', key: 'body', label: 'Corpo', options: BODIES_OPTIONS, crop: FULL_CROP },
            { kind: 'color', key: 'clothesColor', label: 'Cor da roupa', colors: CLOTHES_COLORS },
            { kind: 'color', key: 'background', label: 'Cor do fundo', colors: BACKGROUND_COLORS },
        ],
    },
    {
        id: 'rosto',
        label: 'Rosto',
        groups: [
            { kind: 'color', key: 'skin', label: 'Tom da pele', colors: SKIN_COLORS },
            { kind: 'shape', key: 'brows', label: 'Sobrancelha', options: BROWS, crop: FACE_CROP },
            { kind: 'shape', key: 'mouth', label: 'Boca', options: MOUTHS, crop: MOUTH_CROP },
        ],
    },
    {
        id: 'cabelo',
        label: 'Cabelo',
        groups: [
            { kind: 'shape', key: 'hair', label: 'Cabelo', options: HAIRS, crop: FULL_CROP },
            // A barba fica aqui, e não em "Rosto", porque a cor dela é a do cabelo.
            { kind: 'shape', key: 'beard', label: 'Barba', options: BEARDS, crop: BEARD_CROP },
            { kind: 'color', key: 'hairColor', label: 'Cor do cabelo, da sobrancelha e da barba', colors: HAIR_COLORS },
        ],
    },
    {
        id: 'acessorios',
        label: 'Acessórios',
        groups: [
            { kind: 'shape', key: 'earrings', label: 'Brinco', options: EARRINGS, crop: EAR_CROP },
            { kind: 'color', key: 'earringColor', label: 'Cor do brinco', colors: EARRING_COLORS },
            { kind: 'shape', key: 'glasses', label: 'Óculos', options: GLASSES, crop: FACE_CROP },
            { kind: 'color', key: 'glassesColor', label: 'Cor dos óculos', colors: GLASSES_COLORS },
        ],
    },
    {
        // Por último: a fantasia cobre o corpo inteiro por cima de tudo que
        // foi montado nas outras abas.
        id: 'fantasia',
        label: 'Fantasia',
        groups: [{ kind: 'shape', key: 'outfit', label: 'Fantasia', options: OUTFITS, crop: FULL_CROP }],
    },
];

/**
 * Assinatura ativa. É só para a interface: o servidor recusa sozinho uma peça
 * exclusiva salva por quem não assina.
 */
const isSubscriber = computed(() => !!page.props.auth.user?.has_infinite_lives);

/** Opção exclusiva que a pessoa tentou escolher sem assinar; abre o convite. */
const lockedPick = ref<string | null>(null);

function isLocked(option: ShapeOption<string>): boolean {
    return !!option.premium && !isSubscriber.value;
}

/**
 * Config do avatar com uma peça trocada, para desenhar a miniatura da opção.
 *
 * As miniaturas recortadas no rosto saem sem fantasia: o capacete e a toga
 * mudam o tamanho e a altura da cabeça, e o rosto sairia do recorte.
 */
function previewWith(key: AvatarShapeKey, optionId: string, crop: AvatarCrop): AvatarConfig {
    const config = { ...form.avatar, [key]: optionId } as AvatarConfig;

    return crop === FULL_CROP ? config : { ...config, outfit: 'nenhum' };
}

function select(key: AvatarShapeKey, option: ShapeOption<string>) {
    if (isLocked(option)) {
        lockedPick.value = option.label;
        return;
    }

    lockedPick.value = null;
    (form.avatar as Record<string, unknown>)[key] = option.id;
}

// O sorteio troca o personagem, mas mantém a fantasia: ela é escolha de
// assinante, não algo para sair e entrar ao acaso.
function shuffle() {
    form.avatar = { ...randomAvatarConfig(), outfit: form.avatar.outfit };
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

                <AvatarPreview :config="form.avatar" :name="page.props.auth.user?.name" sticky>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full border-2 border-gray-200 bg-white px-3.5 py-2 text-xs font-bold text-gray-600 transition-colors hover:border-purple-400 hover:text-purple-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-purple-400 dark:hover:text-purple-300"
                        @click="shuffle"
                    >
                        <Shuffle class="h-4 w-4" />
                        Sortear
                    </button>
                </AvatarPreview>

                <Tabs default-value="corpo" class="w-full">
                    <TabsList class="grid w-full grid-cols-5">
                        <TabsTrigger v-for="tab in tabs" :key="tab.id" :value="tab.id" class="text-xs sm:text-sm">
                            {{ tab.label }}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent v-for="tab in tabs" :key="tab.id" :value="tab.id" class="mt-4 space-y-6">
                        <section v-for="group in tab.groups" :key="group.key">
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
                                    :preview="previewWith(group.key, option.id, group.crop)"
                                    :selected="form.avatar[group.key] === option.id"
                                    :locked="isLocked(option)"
                                    @click="select(group.key, option)"
                                />
                            </div>

                            <div
                                v-if="group.kind === 'shape' && lockedPick && group.options.some((o) => o.premium)"
                                class="mt-3 flex items-start gap-3 rounded-2xl border-2 border-amber-200 bg-amber-50 p-3 dark:border-amber-500/40 dark:bg-amber-500/10"
                            >
                                <Lock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                                <p class="text-sm text-amber-900 dark:text-amber-100">
                                    “{{ lockedPick }}” é exclusiva para assinantes.
                                    <Link :href="route('subscription.index')" class="font-bold underline underline-offset-2"
                                        >Assinar para desbloquear</Link
                                    >
                                </p>
                            </div>
                        </section>
                    </TabsContent>
                </Tabs>

                <!-- Fora das abas de propósito: erro dentro de um painel fechado some da tela. -->
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
