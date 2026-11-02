<script setup lang="ts">
import StarBadge from '@/components/StarBadge.vue';
import StarRow from '@/components/StarRow.vue';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/UserAvatar.vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { AvatarConfig } from '@/lib/avatar';
import { Head, Link } from '@inertiajs/vue3';
import { Flame, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

interface Achievement {
    uuid: string;
    name: string;
    stars: number;
    badge_url: string | null;
    icon: string;
    color: string;
}

interface Props {
    profile: {
        first_name: string;
        last_name: string;
        avatar_config: AvatarConfig | null;
        bio: string | null;
        streak: number;
        is_self: boolean;
    };
    achievements: Achievement[];
}

const props = defineProps<Props>();

const fullName = computed(() => `${props.profile.first_name} ${props.profile.last_name}`.trim());

const streakLabel = computed(() => {
    if (props.profile.streak === 0) return 'Sem ofensiva no momento';
    return `${props.profile.streak} ${props.profile.streak === 1 ? 'dia' : 'dias'} de ofensiva`;
});

const breadcrumbs = computed(() => [
    { title: 'Home', href: '/' },
    { title: 'Ranking', href: '/ranking' },
    { title: fullName.value, href: '#' },
]);
</script>

<template>
    <Head :title="fullName" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 px-4 pb-8">
            <section class="flex flex-col items-center pt-5 text-center">
                <UserAvatar :config="profile.avatar_config" :name="fullName" class="size-28 rounded-full text-3xl" />

                <h1 class="mt-4 text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ fullName }}</h1>

                <p
                    class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold"
                    :class="profile.streak > 0 ? 'text-orange-500' : 'text-gray-400 dark:text-gray-500'"
                >
                    <Flame class="h-4 w-4 fill-current" aria-hidden="true" />
                    {{ streakLabel }}
                </p>

                <p v-if="profile.bio" class="mt-2 w-full max-w-md break-words text-sm text-gray-600 dark:text-gray-300">{{ profile.bio }}</p>
                <p v-else-if="profile.is_self" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Escreva uma descrição para quem visitar seu perfil.
                </p>

                <Button v-if="profile.is_self" variant="secondary" size="sm" class="mt-4" as-child>
                    <Link :href="route('profile.edit')">
                        <Pencil class="h-4 w-4" />
                        Editar perfil
                    </Link>
                </Button>
            </section>

            <section class="space-y-3">
                <h2 class="px-1 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Conquistas</h2>

                <div v-if="achievements.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="achievement in achievements"
                        :key="achievement.uuid"
                        class="flex flex-col items-center rounded-2xl border border-gray-200 bg-white p-4 dark:border-transparent dark:bg-gray-900"
                    >
                        <StarBadge
                            :badge-url="achievement.badge_url"
                            :name="achievement.name"
                            :stars="achievement.stars"
                            :icon="achievement.icon"
                            :color="achievement.color"
                        />

                        <p class="mt-3 text-center text-base font-bold leading-tight text-gray-900 dark:text-white">
                            {{ achievement.name }}
                        </p>

                        <StarRow class="mt-1.5" :stars="achievement.stars" />
                    </div>
                </div>

                <p
                    v-else
                    class="rounded-2xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                >
                    Nenhuma conquista para mostrar
                </p>
            </section>
        </div>
    </AppHeaderLayout>
</template>
