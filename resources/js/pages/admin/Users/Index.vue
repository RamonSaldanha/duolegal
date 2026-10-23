<template>
    <AppLayout title="Usuários">
        <Head title="Usuários" />

        <div class="px-3 py-4 md:px-6 md:py-8">
            <div class="mx-auto w-full max-w-[1600px] space-y-6">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-extrabold md:text-3xl">Usuários</h1>
                        <p class="text-sm text-muted-foreground">Quem joga, quem sumiu e quem recebe os lembretes por e-mail.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" class="gap-2" as-child>
                            <a :href="route('admin.emails.preview')" target="_blank" rel="noopener">
                                <Mail class="h-4 w-4" />
                                Ver e-mail de lembrete
                            </a>
                        </Button>
                        <Button variant="outline" class="gap-2" @click="exportBrevoContacts">
                            <Download class="h-4 w-4" />
                            Exportar (Brevo)
                        </Button>
                    </div>
                </div>

                <!-- Indicadores -->
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <div v-for="tile in tiles" :key="tile.label" class="rounded-2xl border-2 border-b-4 bg-card p-4">
                        <div class="flex items-center gap-2">
                            <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-xl', tile.chip]">
                                <component :is="tile.icon" class="h-4 w-4" />
                            </span>
                            <span class="text-xs font-semibold leading-tight text-muted-foreground">{{ tile.label }}</span>
                        </div>
                        <p class="mt-3 text-2xl font-extrabold">{{ tile.value }}</p>
                        <p v-if="tile.hint" class="mt-0.5 text-xs text-muted-foreground">{{ tile.hint }}</p>
                    </div>
                </div>

                <!-- Crescimento -->
                <section class="rounded-2xl border-2 border-b-4 bg-card p-4 md:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-extrabold">Crescimento</h2>
                            <p class="text-sm text-muted-foreground">
                                Novos cadastros e novos pagantes por dia. Conversão no período:
                                <strong class="text-foreground">{{ conversion }}</strong>
                            </p>
                        </div>
                        <div class="inline-flex self-start rounded-xl border-2 bg-muted/40 p-1" role="group" aria-label="Período do gráfico">
                            <button
                                v-for="days in PERIODS"
                                :key="days"
                                type="button"
                                :class="[
                                    'rounded-lg px-3 py-1 text-sm font-bold transition-colors',
                                    period === days ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                ]"
                                :aria-pressed="period === days"
                                @click="changePeriod(days)"
                            >
                                {{ days }} dias
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-6 lg:grid-cols-2">
                        <div>
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="flex items-center gap-2 text-sm font-semibold">
                                    <span class="h-2.5 w-2.5 rounded-sm" :style="{ background: COLORS.users }" />
                                    Novos cadastros
                                </p>
                                <p class="text-2xl font-extrabold">{{ formatNumber(growthTotals.users) }}</p>
                            </div>
                            <GrowthChart class="mt-2" :points="usersPoints" :color="COLORS.users" :unit="['cadastro', 'cadastros']" />
                        </div>
                        <div>
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="flex items-center gap-2 text-sm font-semibold">
                                    <span class="h-2.5 w-2.5 rounded-sm" :style="{ background: COLORS.paying }" />
                                    Novos pagantes
                                </p>
                                <p class="text-2xl font-extrabold">{{ formatNumber(growthTotals.paying) }}</p>
                            </div>
                            <GrowthChart class="mt-2" :points="payingPoints" :color="COLORS.paying" :unit="['pagante', 'pagantes']" />
                        </div>
                    </div>
                </section>

                <!-- Lista -->
                <section class="rounded-2xl border-2 border-b-4 bg-card">
                    <div class="space-y-3 border-b-2 p-4 md:p-6">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <h2 class="text-lg font-extrabold">Cadastrados</h2>
                            <div class="flex gap-2">
                                <div class="relative w-full md:w-80">
                                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input v-model="search" type="search" placeholder="Buscar por nome ou e-mail" class="pl-9" />
                                </div>
                                <!-- No celular não há cabeçalho de coluna: a ordem vem daqui -->
                                <select
                                    v-model="sortKey"
                                    class="h-9 rounded-md border border-input bg-background px-2 text-sm lg:hidden"
                                    aria-label="Ordenar por"
                                    @change="sortDir = defaultDir(sortKey)"
                                >
                                    <option v-for="column in SORTABLE" :key="column.key" :value="column.key">{{ column.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                            <button
                                v-for="item in filterOptions"
                                :key="item.key"
                                type="button"
                                :class="[
                                    'flex shrink-0 items-center gap-1.5 rounded-full border-2 px-3 py-1 text-sm font-bold transition-colors',
                                    filter === item.key
                                        ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                        : 'border-border text-muted-foreground hover:text-foreground',
                                ]"
                                :aria-pressed="filter === item.key"
                                @click="filter = item.key"
                            >
                                {{ item.label }}
                                <span class="rounded-full bg-muted px-1.5 text-xs tabular-nums">{{ item.count }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tabela (telas largas) -->
                    <div class="hidden lg:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th
                                        v-for="column in COLUMNS"
                                        :key="column.label"
                                        :class="['px-4 py-3 font-semibold', column.align === 'right' && 'text-right']"
                                    >
                                        <button
                                            v-if="column.key"
                                            type="button"
                                            :class="[
                                                'inline-flex items-center gap-1 uppercase hover:text-foreground',
                                                sortKey === column.key && 'text-foreground',
                                            ]"
                                            @click="sortBy(column.key)"
                                        >
                                            {{ column.label }}
                                            <ArrowUp v-if="sortKey === column.key && sortDir === 'asc'" class="h-3.5 w-3.5" />
                                            <ArrowDown v-else-if="sortKey === column.key" class="h-3.5 w-3.5" />
                                            <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-40" />
                                        </button>
                                        <span v-else>{{ column.label }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in visibleUsers"
                                    :key="user.id"
                                    class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                                    @click="openUser(user)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <UserAvatar :config="user.avatar_config" :name="user.name" class="h-9 w-9 rounded-full" />
                                            <div class="min-w-0">
                                                <Link
                                                    :href="route('admin.users.show', user.id)"
                                                    class="block truncate font-semibold hover:underline"
                                                    @click.stop
                                                >
                                                    {{ user.name }}
                                                </Link>
                                                <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <PlanBadge :premium="user.is_premium" />
                                            <Badge
                                                v-if="user.is_admin"
                                                variant="outline"
                                                class="border-purple-300 text-purple-700 dark:text-purple-300"
                                                >Admin</Badge
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <template v-if="goalLabel(user.goal)">
                                            <p class="font-medium">{{ goalLabel(user.goal) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ [minutesLabel(user.minutes), areaLabel(user.area)].filter(Boolean).join(' · ') }}
                                            </p>
                                        </template>
                                        <span v-else class="text-xs text-muted-foreground">Sem resposta</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatNumber(user.xp) }}</td>
                                    <td class="px-4 py-3">
                                        <LivesCell :user="user" @add="addLife(user)" />
                                    </td>
                                    <td class="px-4 py-3" :title="formatDate(user.last_played_at, true)">
                                        <span v-if="user.last_played_at" :class="lastPlayedTone(user.last_played_at)">{{
                                            timeAgo(user.last_played_at)
                                        }}</span>
                                        <span v-else class="text-muted-foreground">Nunca jogou</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <EmailCell :user="user" />
                                    </td>
                                    <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ formatDate(user.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cartões (celular e tablet) -->
                    <ul class="divide-y lg:hidden">
                        <li v-for="user in visibleUsers" :key="user.id">
                            <Link :href="route('admin.users.show', user.id)" class="flex gap-3 p-4 transition-colors active:bg-muted/60">
                                <UserAvatar :config="user.avatar_config" :name="user.name" class="h-11 w-11 rounded-full" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold">{{ user.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                                        </div>
                                        <ChevronRight class="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                        <PlanBadge :premium="user.is_premium" />
                                        <span
                                            class="rounded-full bg-purple-100 px-2 py-0.5 font-bold text-purple-700 dark:bg-purple-900/40 dark:text-purple-300"
                                        >
                                            XP {{ formatNumber(user.xp) }}
                                        </span>
                                        <span
                                            v-if="!user.is_premium"
                                            class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-bold text-red-600 dark:bg-red-900/30 dark:text-red-300"
                                        >
                                            <Heart class="h-3 w-3 fill-current" /> {{ user.lives }}
                                        </span>
                                        <span v-if="!user.receives_emails" class="rounded-full bg-muted px-2 py-0.5 font-bold text-muted-foreground"
                                            >Sem e-mails</span
                                        >
                                    </div>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        <span v-if="user.last_played_at">Jogou {{ timeAgo(user.last_played_at) }}</span>
                                        <span v-else>Nunca jogou</span>
                                        · Cadastro {{ formatDate(user.created_at) }}
                                        <template v-if="goalLabel(user.goal)"> · {{ goalLabel(user.goal) }}</template>
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>

                    <div v-if="sortedUsers.length === 0" class="px-4 py-12 text-center text-muted-foreground">
                        <UserSearch class="mx-auto mb-3 h-10 w-10" />
                        Ninguém por aqui com esses filtros.
                    </div>

                    <div v-else class="flex flex-col items-center gap-2 border-t-2 p-4 text-sm text-muted-foreground">
                        Mostrando {{ visibleUsers.length }} de {{ sortedUsers.length }}
                        <Button v-if="visibleUsers.length < sortedUsers.length" variant="outline" size="sm" @click="limit += PAGE_SIZE"
                            >Mostrar mais</Button
                        >
                    </div>
                </section>
            </div>
        </div>
        <Toaster />
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronRight,
    Crown,
    Download,
    Gift,
    Heart,
    Mail,
    MailX,
    Moon,
    Search,
    UserSearch,
    Users,
} from 'lucide-vue-next';
import { computed, defineComponent, h, ref, watch } from 'vue';

import { areaLabel, daysSince, formatDate, formatNumber, goalLabel, minutesLabel, timeAgo } from '@/components/admin/users/format';
import GrowthChart from '@/components/admin/users/GrowthChart.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Toaster from '@/components/ui/toast/Toaster.vue';
import { useToast } from '@/components/ui/toast/use-toast';
import UserAvatar from '@/components/UserAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AvatarConfig } from '@/lib/avatar';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    avatar_config: AvatarConfig | null;
    lives: number;
    xp: number;
    is_admin: boolean;
    is_premium: boolean;
    goal: string | null;
    area: string | null;
    minutes: number | null;
    receives_emails: boolean;
    emails_sent: number;
    gifts_claimed: number;
    last_played_at: string | null;
    created_at: string;
}

interface Stats {
    total_users: number;
    paying_users: number;
    active_7d: number;
    inactive_15d: number;
    opted_out: number;
    emails_sent_30d: number;
    gifts_claimed_30d: number;
}

interface DailyGrowth {
    date: string;
    label: string;
    users: number;
    paying_users: number;
}

const props = defineProps<{
    users: AdminUser[];
    stats: Stats;
    period: number;
    daily_growth: DailyGrowth[];
}>();

const { toast } = useToast();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuários', href: '/admin/users' },
];

// Validadas para claro e escuro (azul 500 e âmbar 600): a mesma cor nos dois temas.
const COLORS = { users: '#3b82f6', paying: '#d97706' };
const PERIODS = [7, 30, 90];
const PAGE_SIZE = 50;
const INACTIVE_DAYS = 15;

const percent = (part: number, total: number) =>
    total > 0 ? `${((part / total) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%` : '0%';

const tiles = computed(() => [
    {
        label: 'Usuários',
        value: formatNumber(props.stats.total_users),
        hint: null,
        icon: Users,
        chip: 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300',
    },
    {
        label: 'Premium ativos',
        value: formatNumber(props.stats.paying_users),
        hint: `${percent(props.stats.paying_users, props.stats.total_users)} da base`,
        icon: Crown,
        chip: 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
    },
    {
        label: 'Jogaram em 7 dias',
        value: formatNumber(props.stats.active_7d),
        hint: `${percent(props.stats.active_7d, props.stats.total_users)} da base`,
        icon: Activity,
        chip: 'bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-300',
    },
    {
        label: `Parados há ${INACTIVE_DAYS}+ dias`,
        value: formatNumber(props.stats.inactive_15d),
        hint: 'inclui quem nunca jogou',
        icon: Moon,
        chip: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    },
    {
        label: 'Lembretes (30 dias)',
        value: formatNumber(props.stats.emails_sent_30d),
        hint: `${formatNumber(props.stats.gifts_claimed_30d)} presentes abertos (${percent(props.stats.gifts_claimed_30d, props.stats.emails_sent_30d)})`,
        icon: Gift,
        chip: 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300',
    },
    {
        label: 'Não recebem e-mail',
        value: formatNumber(props.stats.opted_out),
        hint: 'pediram para sair',
        icon: MailX,
        chip: 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300',
    },
]);

// ---- Gráfico ----
const usersPoints = computed(() => props.daily_growth.map((d) => ({ date: d.date, label: d.label, value: d.users })));
const payingPoints = computed(() => props.daily_growth.map((d) => ({ date: d.date, label: d.label, value: d.paying_users })));
const growthTotals = computed(() => ({
    users: props.daily_growth.reduce((sum, d) => sum + d.users, 0),
    paying: props.daily_growth.reduce((sum, d) => sum + d.paying_users, 0),
}));
const conversion = computed(() => percent(growthTotals.value.paying, growthTotals.value.users));

const changePeriod = (days: number) => {
    router.get(route('admin.users.index'), { days }, { only: ['daily_growth', 'period'], preserveState: true, preserveScroll: true, replace: true });
};

// ---- Filtros ----
type FilterKey = 'all' | 'premium' | 'free' | 'active' | 'inactive' | 'never' | 'optout';

const FILTERS: { key: FilterKey; label: string; test: (u: AdminUser) => boolean }[] = [
    { key: 'all', label: 'Todos', test: () => true },
    { key: 'premium', label: 'Premium', test: (u) => u.is_premium },
    { key: 'free', label: 'Gratuitos', test: (u) => !u.is_premium },
    { key: 'active', label: 'Ativos (7 dias)', test: (u) => !!u.last_played_at && daysSince(u.last_played_at) < 7 },
    { key: 'inactive', label: `Parados (${INACTIVE_DAYS}+ dias)`, test: (u) => daysSince(u.last_played_at ?? u.created_at) >= INACTIVE_DAYS },
    { key: 'never', label: 'Nunca jogaram', test: (u) => !u.last_played_at },
    { key: 'optout', label: 'Sem e-mails', test: (u) => !u.receives_emails },
];

const filter = ref<FilterKey>('all');
const search = ref('');

const filterOptions = computed(() => FILTERS.map((f) => ({ key: f.key, label: f.label, count: props.users.filter(f.test).length })));

const filteredUsers = computed(() => {
    const test = FILTERS.find((f) => f.key === filter.value)!.test;
    const query = search.value.trim().toLowerCase();

    return props.users.filter((u) => test(u) && (!query || u.name.toLowerCase().includes(query) || u.email.toLowerCase().includes(query)));
});

// ---- Ordenação ----
type SortKey = 'name' | 'xp' | 'lives' | 'last_played_at' | 'emails_sent' | 'created_at';

const SORTABLE: { key: SortKey; label: string }[] = [
    { key: 'created_at', label: 'Cadastro' },
    { key: 'last_played_at', label: 'Último jogo' },
    { key: 'xp', label: 'XP' },
    { key: 'lives', label: 'Vidas' },
    { key: 'emails_sent', label: 'Lembretes' },
    { key: 'name', label: 'Nome' },
];

const COLUMNS: { label: string; key?: SortKey; align?: 'right' }[] = [
    { label: 'Usuário', key: 'name' },
    { label: 'Plano' },
    { label: 'Objetivo' },
    { label: 'XP', key: 'xp', align: 'right' },
    { label: 'Vidas', key: 'lives' },
    { label: 'Último jogo', key: 'last_played_at' },
    { label: 'Lembretes', key: 'emails_sent' },
    { label: 'Cadastro', key: 'created_at' },
];

const sortKey = ref<SortKey>('created_at');
const sortDir = ref<'asc' | 'desc'>('desc');

const defaultDir = (key: SortKey): 'asc' | 'desc' => (key === 'name' ? 'asc' : 'desc');

const sortBy = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = defaultDir(key);
    }
};

/** Valor de ordenação; nulo sempre vai para o fim, nos dois sentidos. */
const sortValue = (u: AdminUser): string | number | null => {
    switch (sortKey.value) {
        case 'name':
            return u.name.toLocaleLowerCase('pt-BR');
        case 'lives':
            return u.is_premium ? Number.POSITIVE_INFINITY : u.lives;
        case 'last_played_at':
            return u.last_played_at ? new Date(u.last_played_at).getTime() : null;
        case 'created_at':
            return new Date(u.created_at).getTime();
        default:
            return u[sortKey.value];
    }
};

const sortedUsers = computed(() => {
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return [...filteredUsers.value].sort((a, b) => {
        const va = sortValue(a);
        const vb = sortValue(b);
        if (va === vb) return 0;
        if (va === null) return 1;
        if (vb === null) return -1;
        if (typeof va === 'string' && typeof vb === 'string') return va.localeCompare(vb, 'pt-BR') * direction;
        return (va < vb ? -1 : 1) * direction;
    });
});

const limit = ref(PAGE_SIZE);
watch([filter, search, sortKey, sortDir], () => (limit.value = PAGE_SIZE));
const visibleUsers = computed(() => sortedUsers.value.slice(0, limit.value));

const lastPlayedTone = (iso: string) => {
    const days = daysSince(iso);
    if (days < 7) return 'font-semibold text-green-600 dark:text-green-400';
    if (days < INACTIVE_DAYS) return 'text-foreground';
    return 'text-muted-foreground';
};

const openUser = (user: AdminUser) => router.visit(route('admin.users.show', user.id));

const addLife = (user: AdminUser) => {
    router.post(
        route('admin.users.add-lives', user.id),
        { lives: 1 },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast({ title: 'Vida adicionada!', description: `+1 vida para ${user.name}` }),
            onError: () => toast({ title: 'Erro', description: 'Não foi possível adicionar a vida.', variant: 'destructive' }),
        },
    );
};

const exportBrevoContacts = () => {
    window.location.href = route('admin.users.export-brevo') as string;
};

// ---- Pedaços pequenos da linha ----
const PlanBadge = defineComponent({
    props: { premium: { type: Boolean, required: true } },
    setup: (p) => () =>
        p.premium
            ? h(
                  'span',
                  {
                      class: 'inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                  },
                  [h(Crown, { class: 'h-3 w-3' }), 'Premium'],
              )
            : h('span', { class: 'inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold text-muted-foreground' }, 'Grátis'),
});

const LivesCell = defineComponent({
    props: { user: { type: Object as () => AdminUser, required: true } },
    emits: ['add'],
    setup:
        (p, { emit }) =>
        () =>
            p.user.is_premium
                ? h('span', { class: 'font-semibold text-blue-500', title: 'Vidas infinitas' }, '∞')
                : h('span', { class: 'inline-flex items-center gap-2' }, [
                      h('span', { class: 'inline-flex items-center gap-1 font-semibold tabular-nums' }, [
                          h(Heart, { class: 'h-3.5 w-3.5 fill-current text-red-500' }),
                          String(p.user.lives),
                      ]),
                      h(
                          'button',
                          {
                              type: 'button',
                              class: 'rounded-md border px-1.5 text-xs font-bold text-muted-foreground hover:border-red-300 hover:text-red-600',
                              title: `Dar 1 vida para ${p.user.name}`,
                              onClick: (e: Event) => {
                                  e.stopPropagation();
                                  emit('add');
                              },
                          },
                          '+1',
                      ),
                  ]),
});

const EmailCell = defineComponent({
    props: { user: { type: Object as () => AdminUser, required: true } },
    setup: (p) => () => {
        const parts = [];
        if (!p.user.receives_emails) {
            parts.push(
                h(
                    'span',
                    {
                        class: 'mb-1 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600 dark:bg-red-900/30 dark:text-red-300',
                    },
                    [h(MailX, { class: 'h-3 w-3' }), 'Não recebe'],
                ),
            );
        }
        parts.push(
            h(
                'p',
                { class: 'text-xs text-muted-foreground' },
                p.user.emails_sent > 0
                    ? `${p.user.emails_sent} enviado${p.user.emails_sent > 1 ? 's' : ''} · ${p.user.gifts_claimed} aberto${p.user.gifts_claimed === 1 ? '' : 's'}`
                    : 'Nenhum enviado',
            ),
        );
        return h('div', parts);
    },
});
</script>
