<script setup lang="ts">
import { DEMO_AVATARS } from '@/components/landing/demoAvatars';
import OnboardingChallenge from '@/components/onboarding/OnboardingChallenge.vue';
import {
    AREAS,
    GOALS,
    MINUTES,
    challengeFor,
    suggestTrail,
    type Area,
    type Goal,
    type LegislationOption,
    type Minutes,
} from '@/components/onboarding/quiz';
import UserAvatar from '@/components/UserAvatar.vue';
import { prefersReducedMotion } from '@/composables/useLandingMotion';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Check,
    Coffee,
    Flame,
    Gavel,
    Gift,
    Glasses,
    GraduationCap,
    HardHat,
    Landmark,
    ListChecks,
    Loader2,
    Palette,
    Rocket,
    Scale,
    ScrollText,
    Shirt,
    Sparkles,
    Timer,
    Users,
    Wand2,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';

interface Props {
    legislations: LegislationOption[];
    challengeDone: boolean;
    yearlyPlan: { price: number; monthly_price: number } | null;
    welcomeOffer: { percent_off: number; expires_at: string } | null;
}

const props = defineProps<Props>();
const page = usePage();

const ICONS: Record<string, Component> = {
    Scale,
    Landmark,
    GraduationCap,
    Briefcase,
    Sparkles,
    ScrollText,
    Gavel,
    Users,
    HardHat,
    Building2,
    Wand2,
    Coffee,
    Timer,
    Flame,
    Rocket,
};

const TINTS: Record<string, string> = {
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-600 dark:bg-purple-950/60 dark:text-purple-300',
    green: 'bg-green-100 text-green-600 dark:bg-green-950/60 dark:text-green-300',
    orange: 'bg-orange-100 text-orange-600 dark:bg-orange-950/60 dark:text-orange-300',
    pink: 'bg-pink-100 text-pink-600 dark:bg-pink-950/60 dark:text-pink-300',
    red: 'bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-300',
};

const CHALLENGE_XP = 25;
const OFFER_PERCENT = computed(() => props.welcomeOffer?.percent_off ?? 15);

const STEPS = ['welcome', 'goal', 'area', 'minutes', 'trail', 'challenge', 'avatar', 'offer'] as const;
type Step = (typeof STEPS)[number];

const stepIndex = ref(0);
const direction = ref<1 | -1>(1);
const step = computed<Step>(() => STEPS[stepIndex.value]);
const progress = computed(() => (stepIndex.value / (STEPS.length - 1)) * 100);

const firstName = computed(() => {
    const name = (page.props as { auth?: { user?: { name?: string } } }).auth?.user?.name ?? '';
    return name.trim().split(/\s+/)[0] || 'estudante';
});

function goTo(index: number) {
    direction.value = index > stepIndex.value ? 1 : -1;
    stepIndex.value = Math.max(0, Math.min(STEPS.length - 1, index));
    window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
}
const next = () => goTo(stepIndex.value + 1);
const back = () => goTo(stepIndex.value - 1);

// --- Quiz -------------------------------------------------------------------

const goal = ref<Goal | null>(null);
const area = ref<Area | null>(null);
const minutes = ref<Minutes | null>(null);
const advancing = ref(false);

type QuizOption = { value: Goal | Area | Minutes; title: string; caption: string; icon: string; tint: string; bars?: number };

const quizOptions = computed<QuizOption[]>(() => (step.value === 'goal' ? GOALS : step.value === 'area' ? AREAS : MINUTES));
const quizAnswer = computed(() => (step.value === 'goal' ? goal.value : step.value === 'area' ? area.value : minutes.value));

/** Marca a alternativa, deixa o "pop" aparecer e só então avança. */
function choose(value: QuizOption['value']) {
    if (advancing.value) return;
    if (step.value === 'goal') goal.value = value as Goal;
    else if (step.value === 'area') area.value = value as Area;
    else minutes.value = value as Minutes;
    advancing.value = true;
    setTimeout(
        () => {
            advancing.value = false;
            next();
        },
        prefersReducedMotion() ? 120 : 420,
    );
}

// --- Trilha -----------------------------------------------------------------

const trail = ref<number[]>([]);
const trailKey = ref('');
const showAllLaws = ref(false);
const savingTrail = ref(false);

watch(step, (current) => {
    if (current !== 'trail' || !goal.value || !area.value) return;
    // Refaz a sugestão só quando as respostas mudaram, para não desfazer ajustes.
    const key = `${goal.value}-${area.value}`;
    if (key !== trailKey.value) {
        trail.value = suggestTrail(goal.value, area.value, props.legislations);
        trailKey.value = key;
        showAllLaws.value = false;
    }
});

const trailLaws = computed(() => trail.value.map((id) => props.legislations.find((l) => l.id === id)).filter((l): l is LegislationOption => !!l));
const otherLaws = computed(() => props.legislations.filter((l) => !trail.value.includes(l.id)));

function toggleLaw(id: number) {
    trail.value = trail.value.includes(id) ? trail.value.filter((x) => x !== id) : [...trail.value, id];
}

function saveTrail() {
    if (!trail.value.length || savingTrail.value) return;
    savingTrail.value = true;
    router.post(
        route('onboarding.trail'),
        { goal: goal.value, area: area.value, minutes: minutes.value, legislation_ids: trail.value },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => next(),
            onFinish: () => (savingTrail.value = false),
        },
    );
}

// --- Desafio ----------------------------------------------------------------

const challenge = computed(() => challengeFor(goal.value ?? 'faculdade', area.value ?? 'indica'));
const challengeSolved = ref(props.challengeDone);

function onSolved() {
    challengeSolved.value = true;
    if (!props.challengeDone) {
        router.post(route('onboarding.challenge'), {}, { preserveState: true, preserveScroll: true });
    }
}

// --- Avatar -----------------------------------------------------------------

const AVATAR_NAMES = Object.keys(DEMO_AVATARS);
const avatarIndex = ref(0);
let avatarTimer: ReturnType<typeof setInterval> | undefined;

watch(step, (current) => {
    clearInterval(avatarTimer);
    if (current === 'avatar' && !prefersReducedMotion()) {
        avatarTimer = setInterval(() => (avatarIndex.value = (avatarIndex.value + 1) % AVATAR_NAMES.length), 1500);
    }
});

// --- Oferta -----------------------------------------------------------------

const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
const finishing = ref<'map' | 'subscription' | null>(null);

watch(step, (current) => {
    if (current === 'offer' && !props.welcomeOffer) {
        router.post(route('onboarding.offer'), {}, { preserveState: true, preserveScroll: true });
    }
});

const remaining = computed(() => {
    if (!props.welcomeOffer) return null;
    const ms = Math.max(0, new Date(props.welcomeOffer.expires_at).getTime() - now.value);
    const h = Math.floor(ms / 3_600_000);
    const m = Math.floor((ms % 3_600_000) / 60_000);
    const s = Math.floor((ms % 60_000) / 1000);
    return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
});

const formatCurrency = (cents: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
const discounted = computed(() => (props.yearlyPlan ? Math.round(props.yearlyPlan.price * (1 - OFFER_PERCENT.value / 100)) : null));

function finish(nextPage: 'map' | 'subscription') {
    if (finishing.value) return;
    finishing.value = nextPage;
    router.post(route('onboarding.finish'), { next: nextPage }, { onFinish: () => (finishing.value = null) });
}

// --- Pular ------------------------------------------------------------------

const showSkip = ref(false);

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') showSkip.value = false;
}

onMounted(() => {
    clock = setInterval(() => (now.value = Date.now()), 1000);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    clearInterval(clock);
    clearInterval(avatarTimer);
    window.removeEventListener('keydown', onKeydown);
});

const BENEFITS = [
    { title: 'Vidas infinitas', text: 'Erre à vontade e siga jogando' },
    { title: 'Fantasias exclusivas', text: 'Toga e astronauta para o seu avatar' },
    { title: 'Sem anúncios', text: 'Só você e a lei seca' },
    { title: 'Todas as legislações', text: 'A trilha que quiser, sem limite' },
    { title: 'Garantia de 7 dias', text: 'Não gostou, devolvemos tudo' },
];
</script>

<template>
    <Head title="Boas-vindas" />

    <div class="flex min-h-[100dvh] flex-col bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <!-- Topo: voltar, progresso, pular -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur dark:bg-gray-900/95">
            <div class="mx-auto flex h-16 max-w-xl items-center gap-3 px-4">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 disabled:invisible dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    :disabled="stepIndex === 0"
                    aria-label="Voltar"
                    @click="back"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <div
                    class="h-3.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                    role="progressbar"
                    :aria-valuenow="Math.round(progress)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="ob-progress relative h-full rounded-full bg-green-500" :style="{ width: `${Math.max(progress, 4)}%` }">
                        <span class="absolute inset-x-2 top-[3px] h-[3px] rounded-full bg-white/35" />
                    </div>
                </div>
                <button
                    v-if="step !== 'offer'"
                    type="button"
                    class="shrink-0 rounded-xl px-3 py-2 text-sm font-bold text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    @click="showSkip = true"
                >
                    Pular
                </button>
                <span v-else class="w-10 shrink-0" />
            </div>
        </header>

        <main class="relative mx-auto flex w-full max-w-xl flex-1 flex-col overflow-x-clip px-4 pb-8 pt-2">
            <Transition :name="direction === 1 ? 'ob-next' : 'ob-prev'" mode="out-in">
                <!-- 1. Boas-vindas -->
                <section v-if="step === 'welcome'" key="welcome" class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                    <div class="relative mb-8">
                        <div class="ob-bob">
                            <UserAvatar
                                :config="DEMO_AVATARS['Você']"
                                name="Guia"
                                class="h-36 w-36 rounded-full ring-8 ring-gray-50 dark:ring-gray-800"
                            />
                        </div>
                        <span
                            class="ob-float absolute -right-14 top-3 rounded-full border-2 border-purple-200 bg-white px-3 py-1 text-sm font-extrabold text-purple-600 shadow-[0_4px_0_#e9d5ff] dark:border-purple-900 dark:bg-gray-800 dark:text-purple-300 dark:shadow-[0_4px_0_#0f0f0f]"
                            style="--d: 0.5s"
                        >
                            +25 XP
                        </span>
                        <span
                            class="ob-float absolute -left-14 bottom-4 flex items-center gap-1 rounded-full border-2 border-orange-200 bg-white px-3 py-1 text-sm font-extrabold text-orange-500 shadow-[0_4px_0_#fed7aa] dark:border-orange-900 dark:bg-gray-800 dark:shadow-[0_4px_0_#0f0f0f]"
                            style="--d: 0.75s"
                        >
                            <Flame class="h-4 w-4 fill-current" /> 1 dia
                        </span>
                    </div>

                    <h1 class="ob-rise text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl" style="--i: 1">
                        Oi, {{ firstName }}! Vamos montar sua trilha.
                    </h1>
                    <p class="ob-rise mt-3 max-w-sm text-base text-gray-500 dark:text-gray-400" style="--i: 2">
                        Três perguntas rápidas, um desafio de 30 segundos e você já sai jogando.
                    </p>

                    <ul class="mt-8 w-full max-w-sm space-y-2.5 text-left">
                        <li
                            v-for="(item, i) in [
                                { icon: ListChecks, text: 'Conte seu objetivo', tint: TINTS.blue },
                                { icon: Zap, text: 'Jogue seu primeiro desafio', tint: TINTS.green },
                                { icon: Gift, text: 'Ganhe um presente no final', tint: TINTS.pink },
                            ]"
                            :key="item.text"
                            class="ob-rise flex items-center gap-3 rounded-2xl border-2 border-gray-100 px-4 py-3 font-semibold dark:border-gray-800"
                            :style="{ '--i': i + 3 }"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl" :class="item.tint">
                                <component :is="item.icon" class="h-5 w-5" />
                            </span>
                            {{ item.text }}
                        </li>
                    </ul>
                </section>

                <!-- 2-4. Perguntas -->
                <section v-else-if="step === 'goal' || step === 'area' || step === 'minutes'" :key="step" class="py-4">
                    <div class="mb-6 flex items-end gap-3">
                        <UserAvatar :config="DEMO_AVATARS['Você']" name="Guia" class="h-14 w-14 shrink-0 rounded-full" />
                        <div
                            class="ob-bubble relative rounded-2xl rounded-bl-md border-2 border-gray-200 bg-white px-4 py-3 text-lg font-bold leading-snug dark:border-gray-700 dark:bg-gray-800"
                        >
                            <template v-if="step === 'goal'">Qual é o seu objetivo com a lei seca?</template>
                            <template v-else-if="step === 'area'">Qual área você quer dominar primeiro?</template>
                            <template v-else>Quanto tempo por dia você topa estudar?</template>
                        </div>
                    </div>

                    <div class="space-y-3" role="radiogroup">
                        <button
                            v-for="(opt, i) in quizOptions"
                            :key="String(opt.value)"
                            type="button"
                            role="radio"
                            :aria-checked="quizAnswer === opt.value"
                            class="ob-option ob-rise flex w-full items-center gap-4 rounded-2xl border-2 px-4 py-3.5 text-left transition-[transform,background-color,border-color,box-shadow] duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                            :class="
                                quizAnswer === opt.value
                                    ? 'border-blue-400 bg-blue-50 shadow-[0_4px_0_#93c5fd] dark:border-blue-500 dark:bg-blue-950/40 dark:shadow-[0_4px_0_#1e3a8a]'
                                    : 'border-gray-200 bg-white shadow-[0_4px_0_#e5e5e5] hover:-translate-y-0.5 hover:border-gray-300 hover:bg-gray-50 active:translate-y-1 active:shadow-none dark:border-gray-700 dark:bg-gray-800 dark:shadow-[0_4px_0_#0f0f0f] dark:hover:border-gray-600 dark:hover:bg-gray-800/70'
                            "
                            :style="{ '--i': i }"
                            @click="choose(opt.value)"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="TINTS[opt.tint]">
                                <component :is="ICONS[opt.icon]" class="h-6 w-6" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold">{{ opt.title }}</span>
                                <span class="block text-sm text-gray-500 dark:text-gray-400">{{ opt.caption }}</span>
                            </span>
                            <!-- Intensidade do tempo de estudo -->
                            <span v-if="opt.bars" class="flex h-6 items-end gap-1" aria-hidden="true">
                                <i
                                    v-for="b in 4"
                                    :key="b"
                                    class="block w-1.5 rounded-full"
                                    :class="b <= opt.bars ? 'bg-current text-orange-400' : 'bg-gray-200 dark:bg-gray-700'"
                                    :style="{ height: `${b * 25}%` }"
                                />
                            </span>
                            <span
                                v-if="quizAnswer === opt.value"
                                class="ob-check flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white"
                            >
                                <Check class="h-4 w-4" stroke-width="3" />
                            </span>
                        </button>
                    </div>
                </section>

                <!-- 5. Trilha -->
                <section v-else-if="step === 'trail'" key="trail" class="py-4">
                    <div class="mb-6 flex items-end gap-3">
                        <UserAvatar :config="DEMO_AVATARS['Você']" name="Guia" class="h-14 w-14 shrink-0 rounded-full" />
                        <div
                            class="ob-bubble relative rounded-2xl rounded-bl-md border-2 border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <p class="text-lg font-bold leading-snug">
                                Pronto! Sua trilha tem {{ trailLaws.length }} {{ trailLaws.length === 1 ? 'lei' : 'leis' }}.
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Toque para tirar ou incluir. Dá para mudar depois no mapa.</p>
                        </div>
                    </div>

                    <TransitionGroup tag="ol" name="ob-law" class="relative space-y-3">
                        <li v-for="(law, i) in trailLaws" :key="law.id" class="ob-drop relative" :style="{ '--i': i }">
                            <button
                                type="button"
                                class="flex w-full items-center gap-4 rounded-2xl border-2 border-green-300 bg-green-50 px-4 py-3.5 text-left shadow-[0_4px_0_#86efac] transition-transform duration-150 hover:-translate-y-0.5 active:translate-y-1 active:shadow-none dark:border-green-800 dark:bg-green-950/40 dark:shadow-[0_4px_0_#14532d]"
                                :aria-label="`Tirar ${law.title} da trilha`"
                                @click="toggleLaw(law.id)"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-500 text-base font-extrabold text-white shadow-[0_3px_0_#15803d]"
                                >
                                    {{ i + 1 }}
                                </span>
                                <span class="flex-1 font-bold">{{ law.title }}</span>
                                <Check class="h-5 w-5 text-green-600 dark:text-green-400" stroke-width="3" />
                            </button>
                        </li>
                    </TransitionGroup>

                    <p
                        v-if="!trailLaws.length"
                        class="mt-2 rounded-2xl border-2 border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                    >
                        Escolha pelo menos uma lei para montar a trilha.
                    </p>

                    <div v-if="otherLaws.length" class="mt-6">
                        <button
                            v-if="!showAllLaws && trailLaws.length"
                            type="button"
                            class="w-full rounded-2xl border-2 border-dashed border-gray-300 px-4 py-3 text-sm font-bold text-gray-500 transition hover:border-gray-400 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200"
                            @click="showAllLaws = true"
                        >
                            Incluir outras leis ({{ otherLaws.length }})
                        </button>
                        <TransitionGroup v-else tag="div" name="ob-law" class="flex flex-wrap gap-2">
                            <button
                                v-for="law in otherLaws"
                                :key="law.id"
                                type="button"
                                class="rounded-xl border-2 border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 shadow-[0_3px_0_#e5e5e5] transition-transform hover:-translate-y-0.5 active:translate-y-0.5 active:shadow-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:shadow-[0_3px_0_#0f0f0f]"
                                @click="toggleLaw(law.id)"
                            >
                                + {{ law.title }}
                            </button>
                        </TransitionGroup>
                    </div>
                </section>

                <!-- 6. Desafio -->
                <section v-else-if="step === 'challenge'" key="challenge" class="py-4">
                    <div class="mb-6 flex items-end gap-3">
                        <UserAvatar :config="DEMO_AVATARS['Você']" name="Guia" class="h-14 w-14 shrink-0 rounded-full" />
                        <div
                            class="ob-bubble relative rounded-2xl rounded-bl-md border-2 border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <p class="text-lg font-bold leading-snug">Desafio relâmpago!</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Toque nas palavras para completar o artigo.</p>
                        </div>
                    </div>
                    <OnboardingChallenge
                        :article="challenge"
                        :solved-before="challengeSolved"
                        :xp-reward="CHALLENGE_XP"
                        controls-target="#onboarding-play-controls"
                        @solved="onSolved"
                    />
                </section>

                <!-- 7. Avatar -->
                <section v-else-if="step === 'avatar'" key="avatar" class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                    <div class="relative mb-8 flex h-48 w-48 items-center justify-center">
                        <span class="absolute inset-0 rounded-full bg-gray-100 dark:bg-gray-800" />
                        <!--
                            Todos empilhados e só o da vez visível. Com <Transition mode="out-in">
                            dentro da transição das telas, o primeiro saía e o próximo não entrava.
                        -->
                        <div
                            v-for="(avatarName, i) in AVATAR_NAMES"
                            :key="avatarName"
                            class="ob-swap absolute inset-2"
                            :class="{ 'is-current': i === avatarIndex }"
                            :aria-hidden="i !== avatarIndex"
                        >
                            <UserAvatar :config="DEMO_AVATARS[avatarName]" :name="avatarName" class="h-full w-full rounded-full" />
                        </div>
                        <span
                            v-for="(piece, i) in [
                                { icon: Shirt, cls: '-left-6 top-6', tint: TINTS.blue },
                                { icon: Glasses, cls: '-right-7 top-14', tint: TINTS.purple },
                                { icon: Palette, cls: '-left-4 bottom-4', tint: TINTS.orange },
                                { icon: Sparkles, cls: '-right-3 bottom-0', tint: TINTS.pink },
                            ]"
                            :key="i"
                            class="ob-float absolute flex h-11 w-11 items-center justify-center rounded-2xl border-2 border-white shadow-[0_4px_0_#e5e5e5] dark:border-gray-900 dark:shadow-[0_4px_0_#0f0f0f]"
                            :class="[piece.cls, piece.tint]"
                            :style="{ '--d': `${0.2 + i * 0.15}s` }"
                        >
                            <component :is="piece.icon" class="h-5 w-5" />
                        </span>
                    </div>

                    <h2 class="ob-rise text-3xl font-extrabold leading-tight tracking-tight" style="--i: 1">Seu avatar te espera</h2>
                    <p class="ob-rise mt-3 max-w-sm text-base text-gray-500 dark:text-gray-400" style="--i: 2">
                        Cabelo, roupa, óculos e cores. Monte o seu no perfil quando quiser e apareça no ranking com a sua cara.
                    </p>
                </section>

                <!-- 8. Oferta -->
                <section v-else key="offer" class="py-4">
                    <div
                        class="ob-rise relative overflow-hidden rounded-3xl border-2 border-gray-200 bg-white px-5 pb-6 pt-7 text-center shadow-[0_8px_0_#e5e5e5] dark:border-gray-700 dark:bg-gray-800 dark:shadow-[0_8px_0_#0f0f0f] sm:px-8"
                        style="--i: 0"
                    >
                        <span
                            class="ob-stamp absolute right-4 top-4 flex h-20 w-20 flex-col items-center justify-center rounded-full border-4 border-dashed border-green-500 font-extrabold leading-none text-green-600 dark:text-green-400"
                        >
                            <span class="text-2xl">-{{ OFFER_PERCENT }}%</span>
                            <span class="text-[10px] uppercase tracking-wide">anual</span>
                        </span>

                        <span
                            class="ob-gift mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-pink-100 text-pink-500 dark:bg-pink-950/60 dark:text-pink-300"
                        >
                            <Gift class="h-9 w-9" />
                        </span>
                        <p class="mt-4 text-sm font-bold text-pink-500 dark:text-pink-300">Presente de boas-vindas</p>
                        <h2 class="mt-1 text-3xl font-extrabold leading-tight tracking-tight">{{ OFFER_PERCENT }}% off no plano anual</h2>

                        <div v-if="yearlyPlan && discounted !== null" class="mt-5">
                            <p class="text-base font-semibold text-gray-400 line-through">{{ formatCurrency(yearlyPlan.price) }} por ano</p>
                            <p class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                                {{ formatCurrency(discounted) }}<span class="text-lg font-bold text-gray-500 dark:text-gray-400"> por ano</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Sai por {{ formatCurrency(Math.round(discounted / 12)) }} por mês
                            </p>
                        </div>

                        <p
                            class="mx-auto mt-5 inline-flex items-center gap-2 rounded-full bg-orange-50 px-4 py-2 text-sm font-bold text-orange-600 dark:bg-orange-950/50 dark:text-orange-300"
                        >
                            <Timer class="h-4 w-4" />
                            <template v-if="remaining"
                                >Termina em <span class="tabular-nums">{{ remaining }}</span></template
                            >
                            <template v-else>Válida por 24 horas</template>
                        </p>

                        <ul class="mt-6 divide-y-2 divide-gray-100 border-t-2 border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                            <li v-for="(b, i) in BENEFITS" :key="b.title" class="ob-rise py-2.5" :style="{ '--i': i + 2 }">
                                <span class="block text-sm font-bold">{{ b.title }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ b.text }}</span>
                            </li>
                        </ul>
                    </div>

                    <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                        Esse desconto só aparece agora, no seu cadastro. Ele fica guardado por 24 horas e depois some.
                    </p>
                </section>
            </Transition>
        </main>

        <!-- Rodapé com a ação da tela -->
        <footer
            v-if="step !== 'goal' && step !== 'area' && step !== 'minutes'"
            class="sticky bottom-0 z-20 bg-white/95 backdrop-blur dark:bg-gray-900/95"
            :class="step !== 'challenge' && 'border-t-2 border-gray-100 dark:border-gray-800'"
        >
            <div class="mx-auto flex max-w-xl flex-col gap-2 px-4 py-4">
                <!-- Palavras + Limpar/Verificar do desafio, no mesmo lugar da tela de fase -->
                <div v-if="step === 'challenge'" id="onboarding-play-controls" />
                <button v-if="step === 'offer'" type="button" class="ob-cta ob-cta-pulse" :disabled="!!finishing" @click="finish('subscription')">
                    <Loader2 v-if="finishing === 'subscription'" class="h-5 w-5 animate-spin" />
                    Garantir {{ OFFER_PERCENT }}% off
                </button>
                <button
                    v-else-if="step !== 'challenge' || challengeSolved"
                    type="button"
                    class="ob-cta"
                    :disabled="step === 'trail' && (!trail.length || savingTrail)"
                    @click="step === 'trail' ? saveTrail() : next()"
                >
                    <Loader2 v-if="savingTrail" class="h-5 w-5 animate-spin" />
                    <template v-if="step === 'welcome'">Bora começar</template>
                    <template v-else-if="step === 'trail'">Salvar minha trilha</template>
                    <template v-else>Continuar</template>
                </button>
                <button
                    v-if="step === 'offer'"
                    type="button"
                    class="rounded-xl py-2 text-sm font-bold text-gray-500 transition hover:text-gray-700 disabled:opacity-60 dark:text-gray-400 dark:hover:text-gray-200"
                    :disabled="!!finishing"
                    @click="finish('map')"
                >
                    <Loader2 v-if="finishing === 'map'" class="mr-1 inline h-4 w-4 animate-spin" />
                    Continuar no plano grátis
                </button>
            </div>
        </footer>

        <!-- Aviso ao pular -->
        <Transition name="ob-modal">
            <div
                v-if="showSkip"
                class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/50 p-4 sm:items-center"
                @click.self="showSkip = false"
            >
                <div
                    class="ob-sheet w-full max-w-sm rounded-3xl border-2 border-gray-200 bg-white p-6 text-center shadow-[0_8px_0_#e5e5e5] dark:border-gray-700 dark:bg-gray-800 dark:shadow-[0_8px_0_#0f0f0f]"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="skip-title"
                >
                    <span
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-pink-100 text-pink-500 dark:bg-pink-950/60 dark:text-pink-300"
                    >
                        <Gift class="h-7 w-7" />
                    </span>
                    <h2 id="skip-title" class="mt-4 text-xl font-extrabold">Tem certeza que quer pular?</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Se pular agora, você pode perder a oferta especial de boas-vindas e o estudo personalizado para o seu objetivo.
                    </p>
                    <div class="mt-6 flex flex-col gap-2">
                        <button type="button" class="ob-cta" @click="showSkip = false">Continuar personalizando</button>
                        <button
                            type="button"
                            class="rounded-xl py-2 text-sm font-bold text-gray-500 transition hover:text-red-500 disabled:opacity-60 dark:text-gray-400"
                            :disabled="!!finishing"
                            @click="finish('map')"
                        >
                            Pular mesmo assim
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.ob-progress {
    transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* Botão principal no estilo "chunky" do jogo */
.ob-cta {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.5rem;
    border: 4px solid #15803d;
    background: #22c55e;
    padding: 0.75rem 1.5rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 4px 0 #15803d;
    transition:
        transform 0.12s,
        box-shadow 0.12s,
        background-color 0.15s;
}
.ob-cta:hover:not(:disabled) {
    background: #16a34a;
    transform: translateY(2px);
    box-shadow: 0 2px 0 #15803d;
}
.ob-cta:active:not(:disabled) {
    transform: translateY(4px);
    box-shadow: none;
}
.ob-cta:disabled {
    cursor: not-allowed;
    border-color: #d4d4d4;
    background: #e5e5e5;
    color: #a3a3a3;
    box-shadow: 0 4px 0 #d4d4d4;
}
:root.dark .ob-cta:disabled {
    border-color: #525252;
    background: #404040;
    color: #737373;
    box-shadow: 0 4px 0 #0f0f0f;
}

.ob-cta-pulse:not(:disabled) {
    animation: cta-pulse 2.4s ease-in-out 1.2s infinite;
}
@keyframes cta-pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.02);
    }
}

/* Troca de tela */
.ob-next-enter-active,
.ob-next-leave-active,
.ob-prev-enter-active,
.ob-prev-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.28s cubic-bezier(0.34, 1.2, 0.64, 1);
}
.ob-next-enter-from,
.ob-prev-leave-to {
    opacity: 0;
    transform: translateX(48px);
}
.ob-next-leave-to,
.ob-prev-enter-from {
    opacity: 0;
    transform: translateX(-48px);
}

/* Entrada em cascata */
.ob-rise {
    animation: ob-rise 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
    animation-delay: calc(var(--i, 0) * 70ms + 120ms);
}
@keyframes ob-rise {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.97);
    }
}

.ob-bubble {
    transform-origin: left bottom;
    animation: ob-bubble 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) backwards 0.05s;
}
@keyframes ob-bubble {
    from {
        opacity: 0;
        transform: scale(0.6);
    }
}

.ob-check {
    animation: ob-check 0.4s cubic-bezier(0.34, 1.8, 0.64, 1);
}
@keyframes ob-check {
    from {
        transform: scale(0) rotate(-45deg);
    }
}

.ob-option[aria-checked='true'] {
    animation: ob-picked 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes ob-picked {
    40% {
        transform: scale(1.03);
    }
}

.ob-bob {
    animation: ob-bob 3.2s ease-in-out infinite;
}
@keyframes ob-bob {
    50% {
        transform: translateY(-8px);
    }
}

.ob-float {
    animation:
        ob-pop 0.5s cubic-bezier(0.34, 1.8, 0.64, 1) backwards var(--d, 0s),
        ob-hover 3s ease-in-out infinite calc(var(--d, 0s) + 0.5s);
}
@keyframes ob-pop {
    from {
        opacity: 0;
        transform: scale(0.3);
    }
}
@keyframes ob-hover {
    50% {
        transform: translateY(-6px) rotate(-3deg);
    }
}

/* Leis da trilha caindo no lugar */
.ob-drop {
    animation: ob-drop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
    animation-delay: calc(var(--i, 0) * 90ms + 200ms);
}
@keyframes ob-drop {
    from {
        opacity: 0;
        transform: translateY(-24px) scale(0.9);
    }
}
.ob-law-move,
.ob-law-enter-active,
.ob-law-leave-active {
    transition: all 0.35s cubic-bezier(0.34, 1.3, 0.64, 1);
}
.ob-law-enter-from,
.ob-law-leave-to {
    opacity: 0;
    transform: scale(0.85);
}
.ob-law-leave-active {
    position: absolute;
}

/* Avatar trocando de visual: o que sai gira para um lado, o que entra vem do outro */
.ob-swap {
    opacity: 0;
    transform: scale(0.85) rotate(8deg);
    transition:
        transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1),
        opacity 0.2s;
}
.ob-swap.is-current {
    opacity: 1;
    transform: none;
}

/* Oferta */
.ob-stamp {
    animation: ob-stamp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards 0.6s;
    transform: rotate(-12deg);
}
@keyframes ob-stamp {
    from {
        opacity: 0;
        transform: scale(2.4) rotate(-30deg);
    }
}
.ob-gift {
    animation:
        ob-pop 0.5s cubic-bezier(0.34, 1.8, 0.64, 1) backwards 0.2s,
        ob-wiggle 2.8s ease-in-out infinite 1.2s;
}
@keyframes ob-wiggle {
    0%,
    80%,
    100% {
        transform: rotate(0);
    }
    85% {
        transform: rotate(-10deg);
    }
    90% {
        transform: rotate(8deg);
    }
    95% {
        transform: rotate(-4deg);
    }
}

/* Aviso de pular */
.ob-modal-enter-active,
.ob-modal-leave-active {
    transition: opacity 0.2s;
}
.ob-modal-enter-active .ob-sheet,
.ob-modal-leave-active .ob-sheet {
    transition: transform 0.3s cubic-bezier(0.34, 1.4, 0.64, 1);
}
.ob-modal-enter-from,
.ob-modal-leave-to {
    opacity: 0;
}
.ob-modal-enter-from .ob-sheet,
.ob-modal-leave-to .ob-sheet {
    transform: translateY(40px) scale(0.95);
}

@media (prefers-reduced-motion: reduce) {
    .ob-rise,
    .ob-bubble,
    .ob-check,
    .ob-option[aria-checked='true'],
    .ob-bob,
    .ob-float,
    .ob-drop,
    .ob-stamp,
    .ob-gift,
    .ob-cta-pulse:not(:disabled) {
        animation: none;
    }
    .ob-next-enter-active,
    .ob-next-leave-active,
    .ob-prev-enter-active,
    .ob-prev-leave-active,
    .ob-progress {
        transition: none;
    }
}
</style>
