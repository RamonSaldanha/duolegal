<template>
    <AppLayout>
        <Head :title="user.name" />

        <div class="px-3 py-4 md:px-6 md:py-8">
            <div class="mx-auto w-full max-w-5xl space-y-5">
                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Usuários
                </Link>

                <!-- Cabeçalho -->
                <section class="flex flex-col gap-4 rounded-2xl border-2 border-b-4 bg-card p-5 sm:flex-row sm:items-center">
                    <UserAvatar :config="user.avatar_config" :name="user.name" class="h-20 w-20 rounded-2xl" />
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-2xl font-extrabold">{{ user.name }}</h1>
                        <p class="break-all text-sm text-muted-foreground">{{ user.email }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-bold">
                            <span
                                v-if="user.is_premium"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                <Crown class="h-3 w-3" /> Premium
                            </span>
                            <span v-else class="rounded-full border px-2 py-0.5 text-muted-foreground">Grátis</span>
                            <span v-if="user.is_admin" class="rounded-full border border-purple-300 px-2 py-0.5 text-purple-700 dark:text-purple-300"
                                >Admin</span
                            >
                            <span
                                v-if="user.xp_boost_until"
                                class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300"
                            >
                                <Zap class="h-3 w-3 fill-current" /> Boost até {{ formatDate(user.xp_boost_until, true) }}
                            </span>
                            <span
                                v-if="!user.receives_emails"
                                class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-red-600 dark:bg-red-900/30 dark:text-red-300"
                            >
                                <MailX class="h-3 w-3" /> Não recebe e-mails
                            </span>
                        </div>
                    </div>
                    <div class="text-sm text-muted-foreground sm:text-right">
                        <p>Cadastro em {{ formatDate(user.created_at) }}</p>
                        <p>
                            <template v-if="user.last_played_at">Jogou {{ timeAgo(user.last_played_at) }}</template>
                            <template v-else>Nunca jogou</template>
                        </p>
                    </div>
                </section>

                <!-- HUD -->
                <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-2xl border-2 border-b-4 bg-card p-4">
                        <p class="text-xs font-semibold text-muted-foreground">XP total</p>
                        <p class="mt-1 text-2xl font-extrabold text-purple-600 dark:text-purple-300">{{ formatNumber(user.xp) }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatNumber(progress.xp_30d) }} nos últimos 30 dias</p>
                    </div>
                    <div class="rounded-2xl border-2 border-b-4 bg-card p-4">
                        <p class="text-xs font-semibold text-muted-foreground">Vidas</p>
                        <p v-if="user.is_premium" class="mt-1 text-2xl font-extrabold text-blue-500">∞</p>
                        <div v-else class="mt-1 flex items-center gap-2">
                            <p class="flex items-center gap-1.5 text-2xl font-extrabold text-red-500">
                                <Heart class="h-5 w-5 fill-current" /> {{ user.lives }}
                            </p>
                            <button
                                type="button"
                                class="rounded-md border px-2 py-0.5 text-xs font-bold text-muted-foreground hover:border-red-300 hover:text-red-600"
                                @click="addLife"
                            >
                                +1
                            </button>
                        </div>
                    </div>
                    <div class="rounded-2xl border-2 border-b-4 bg-card p-4">
                        <p class="text-xs font-semibold text-muted-foreground">Ofensiva atual</p>
                        <p class="mt-1 flex items-center gap-1.5 text-2xl font-extrabold text-orange-500">
                            <Flame class="h-5 w-5 fill-current" /> {{ user.current_streak }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Recorde: {{ user.longest_streak }} {{ user.longest_streak === 1 ? 'dia' : 'dias' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border-2 border-b-4 bg-card p-4">
                        <p class="text-xs font-semibold text-muted-foreground">Blocos concluídos</p>
                        <p class="mt-1 text-2xl font-extrabold">{{ formatNumber(progress.blocks_completed) }}</p>
                        <p class="text-xs text-muted-foreground">
                            de {{ formatNumber(progress.blocks_attempted) }} tentados · {{ formatNumber(progress.articles_completed) }} artigos no
                            legado
                        </p>
                    </div>
                </section>

                <div class="grid gap-5 lg:grid-cols-2">
                    <!-- Objetivo -->
                    <section class="rounded-2xl border-2 border-b-4 bg-card p-5">
                        <h2 class="flex items-center gap-2 font-extrabold"><Target class="h-4 w-4 text-blue-500" /> Objetivo com o jogo</h2>
                        <dl v-if="user.goal" class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-muted-foreground">Objetivo</dt>
                            <dd class="font-semibold">{{ goalLabel(user.goal) }}</dd>
                            <dt class="text-muted-foreground">Área</dt>
                            <dd class="font-semibold">{{ areaLabel(user.area) ?? 'Sem resposta' }}</dd>
                            <dt class="text-muted-foreground">Meta diária</dt>
                            <dd class="font-semibold">{{ minutesLabel(user.minutes) ?? 'Sem resposta' }}</dd>
                        </dl>
                        <p v-else class="mt-3 text-sm text-muted-foreground">
                            Não respondeu o quiz do onboarding. Conta anterior ao onboarding ou criada pelo app antigo.
                        </p>

                        <h3 class="mt-5 text-sm font-bold">Trilha</h3>
                        <div v-if="trail.length" class="mt-2 flex flex-wrap gap-1.5">
                            <span v-for="title in trail" :key="title" class="rounded-full border-2 px-2.5 py-0.5 text-xs font-semibold">{{
                                title
                            }}</span>
                        </div>
                        <p v-else class="mt-1 text-sm text-muted-foreground">Nenhuma lei escolhida.</p>
                    </section>

                    <!-- Assinatura -->
                    <section class="rounded-2xl border-2 border-b-4 bg-card p-5">
                        <h2 class="flex items-center gap-2 font-extrabold"><Crown class="h-4 w-4 text-amber-500" /> Assinatura</h2>
                        <dl v-if="subscription" class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-muted-foreground">Situação</dt>
                            <dd class="font-semibold">{{ STATUS[subscription.status] ?? subscription.status }}</dd>
                            <dt class="text-muted-foreground">Desde</dt>
                            <dd class="font-semibold">{{ formatDate(subscription.created_at) }}</dd>
                            <template v-if="subscription.trial_ends_at">
                                <dt class="text-muted-foreground">Teste até</dt>
                                <dd class="font-semibold">{{ formatDate(subscription.trial_ends_at) }}</dd>
                            </template>
                            <template v-if="subscription.ends_at">
                                <dt class="text-muted-foreground">Termina em</dt>
                                <dd class="font-semibold">{{ formatDate(subscription.ends_at) }}</dd>
                            </template>
                        </dl>
                        <p v-else class="mt-3 text-sm text-muted-foreground">Nunca assinou.</p>
                        <p class="mt-4 text-xs text-muted-foreground">
                            E-mail {{ user.email_verified_at ? `verificado em ${formatDate(user.email_verified_at)}` : 'não verificado' }}.
                        </p>
                    </section>
                </div>

                <!-- Lembretes -->
                <section class="rounded-2xl border-2 border-b-4 bg-card p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="flex items-center gap-2 font-extrabold"><Gift class="h-4 w-4 text-purple-500" /> Lembretes por e-mail</h2>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="user.receives_emails">Recebe lembretes</template>
                            <template v-else>Pediu para sair em {{ formatDate(user.email_opt_out_at) }}</template>
                        </p>
                    </div>

                    <ul v-if="emails.length" class="mt-4 divide-y rounded-xl border-2">
                        <li
                            v-for="email in emails"
                            :key="email.sent_at"
                            class="flex flex-col gap-1 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{ email.sequence }}º lembrete ·
                                    {{ email.gift === 'xp_boost' ? 'Boost de XP' : '5 vidas' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Enviado em {{ formatDate(email.sent_at, true) }}, parado havia {{ email.inactive_days }} dias
                                </p>
                            </div>
                            <span
                                v-if="email.claimed_at"
                                class="self-start rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300 sm:self-auto"
                            >
                                Aberto em {{ formatDate(email.claimed_at) }}
                            </span>
                            <span v-else class="self-start rounded-full border px-2 py-0.5 text-xs font-semibold text-muted-foreground sm:self-auto"
                                >Não aberto</span
                            >
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">Nenhum lembrete enviado ainda.</p>
                </section>
            </div>
        </div>
        <Toaster />
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Crown, Flame, Gift, Heart, MailX, Target, Zap } from 'lucide-vue-next';

import { areaLabel, formatDate, formatNumber, goalLabel, minutesLabel, timeAgo } from '@/components/admin/users/format';
import Toaster from '@/components/ui/toast/Toaster.vue';
import { useToast } from '@/components/ui/toast/use-toast';
import UserAvatar from '@/components/UserAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AvatarConfig } from '@/lib/avatar';

interface UserDetail {
    id: number;
    name: string;
    email: string;
    avatar_config: AvatarConfig | null;
    is_admin: boolean;
    is_premium: boolean;
    lives: number;
    xp: number;
    current_streak: number;
    longest_streak: number;
    created_at: string;
    email_verified_at: string | null;
    last_played_at: string | null;
    goal: string | null;
    area: string | null;
    minutes: number | null;
    onboarding_completed_at: string | null;
    receives_emails: boolean;
    email_opt_out_at: string | null;
    xp_boost_until: string | null;
}

const props = defineProps<{
    user: UserDetail;
    subscription: { status: string; created_at: string | null; ends_at: string | null; trial_ends_at: string | null } | null;
    trail: string[];
    progress: { blocks_completed: number; blocks_attempted: number; articles_completed: number; xp_30d: number };
    emails: { sequence: number; gift: 'lives' | 'xp_boost'; inactive_days: number; sent_at: string; claimed_at: string | null }[];
}>();

const STATUS: Record<string, string> = {
    active: 'Ativa',
    trialing: 'Em teste',
    canceled: 'Cancelada',
    past_due: 'Pagamento atrasado',
    unpaid: 'Não paga',
    incomplete: 'Incompleta',
    incomplete_expired: 'Expirada',
};

const { toast } = useToast();

const addLife = () => {
    router.post(
        route('admin.users.add-lives', props.user.id),
        { lives: 1 },
        {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Vida adicionada!', description: `+1 vida para ${props.user.name}` }),
        },
    );
};
</script>
