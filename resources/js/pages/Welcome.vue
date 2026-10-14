<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle, ChevronDown, ChevronRight, Download, Flame, Menu, RotateCcw, Smartphone, Target, Trophy, X, Zap } from 'lucide-vue-next';

import AppLogo from '@/components/AppLogo.vue';
import { DEMO_AVATARS } from '@/components/landing/demoAvatars';
import HeroGameDemo from '@/components/landing/HeroGameDemo.vue';
import RankingDemo from '@/components/landing/RankingDemo.vue';
import TrailDemo from '@/components/landing/TrailDemo.vue';
import GameButton from '@/components/ui/GameButton.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useAppearance } from '@/composables/useAppearance';
import { prefersReducedMotion, useCountUp, useInView, vReveal } from '@/composables/useLandingMotion';
import { isIosDevice } from '@/composables/usePlatform';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const mobileMenuOpen = ref(false);
const page = usePage<{
    auth: {
        user: any;
    };
}>();

const { logoTheme } = useAppearance();

// Quem está no iPhone/iPad não pode instalar o app Android e desistia ao ver a
// oferta. Para eles a landing fala só da versão web.
const showAndroid = !isIosDevice();

const props = defineProps<{
    articlesCount: number;
    firstFourLaws: Array<{
        name: string;
        uuid: string;
        slug?: string;
    }>;
    laws?: Array<{
        name: string;
        uuid: string;
        slug?: string;
    }>;
}>();

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMenu = () => {
    mobileMenuOpen.value = false;
};

// Helper function para URLs seguras com fallback
const getLawUrl = (law: { uuid: string; slug?: string }) => {
    // Prefer slug-based URL if available, fallback to UUID
    if (law.slug) {
        return route('public.law', { legalReference: law.slug });
    }
    // Fallback to legacy UUID route
    return route('public.law.legacy', { uuid: law.uuid });
};

// === Hero: as palavras sobem e "jogo." cai na lacuna do título ===
const heroWordsIn = ref(false);
const heroGapFilled = ref(false);

const lawCount = computed(() => props.laws?.length ?? props.firstFourLaws.length);
const heroAvatars = ['Marina', 'Rafael', 'Júlia', 'Pedro'].map((name) => ({ name, config: DEMO_AVATARS[name] }));

// === Barra de leitura no topo + sombra do header ===
const scrollProgress = ref(0);
const scrolled = ref(false);
let scrollRaf = 0;
const onScroll = () => {
    if (scrollRaf) return;
    scrollRaf = requestAnimationFrame(() => {
        scrollRaf = 0;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        scrollProgress.value = max > 0 ? window.scrollY / max : 0;
        scrolled.value = window.scrollY > 8;
    });
};

// === Números que contam ao aparecer ===
const researchEl = ref<HTMLElement | null>(null);
const researchInView = useInView(researchEl, { once: true, threshold: 0.35 });
const judgesCounter = useCountUp(0, () => 120, 1600);
const rateCounter = useCountUp(0, () => 619, 1600);

const ctaEl = ref<HTMLElement | null>(null);
const ctaInView = useInView(ctaEl, { once: true, threshold: 0.5 });

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (prefersReducedMotion()) {
        heroWordsIn.value = true;
        heroGapFilled.value = true;
        return;
    }
    requestAnimationFrame(() => (heroWordsIn.value = true));
    setTimeout(() => (heroGapFilled.value = true), 1100);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    cancelAnimationFrame(scrollRaf);
});

// Dispara as contagens quando cada bloco aparece.
watch(researchInView, (v) => {
    if (v) {
        judgesCounter.start();
        rateCounter.start();
    }
});

const ringOffset = computed(() => (researchInView.value ? 565.48 * (1 - 0.619) : 565.48));

// === Faixa de leis ===
const lawList = computed(() => {
    const list = props.laws?.length ? props.laws : props.firstFourLaws;
    // Garante largura suficiente para o loop nunca mostrar um buraco
    const out = [...list];
    while (out.length && out.length < 10) out.push(...list);
    return out;
});
const lawListReversed = computed(() => [...lawList.value].reverse());

// === Passos ===
const steps = [
    {
        title: 'Leia o artigo',
        text: 'Cada fase traz artigos de lei seca, na íntegra e na ordem do código.',
        color: 'bg-yellow-400',
    },
    {
        title: 'Complete as lacunas',
        text: 'Palavras-chave somem do texto. Você escolhe a certa no banco de palavras.',
        color: 'bg-blue-500',
    },
    {
        title: 'Avance na trilha',
        text: 'Acertou 70% ou mais? A próxima fase destrava e você ganha XP.',
        color: 'bg-green-500',
    },
];

// === FAQ ===
const faqs = [
    {
        q: 'Como funciona o sistema de gamificação?',
        a: 'Você ganha XP (experiência) a cada resposta correta, sobe de nível conforme progride e pode acompanhar seu progresso através de mapas visuais. O sistema é similar ao Duolingo, mas focado na legislação brasileira.',
    },
    {
        q: 'Quais leis estão disponíveis na plataforma?',
        a: 'Atualmente oferecemos estudo da Constituição Federal, Código Civil, Código Penal e outras legislações importantes do direito brasileiro. Nosso conteúdo é constantemente atualizado.',
    },
    {
        q: 'É realmente gratuito?',
        a: 'Sim! Você pode começar a estudar gratuitamente. Temos planos premium com recursos adicionais, mas o acesso básico à plataforma é sempre gratuito.',
    },
    {
        q: 'Quanto tempo devo estudar por dia?',
        a: 'Recomendamos sessões curtas e diárias, entre 15-30 minutos. A consistência é mais importante que a duração. Nossa pesquisa mostra que o estudo diário é fundamental para aprovação em concursos.',
    },
    {
        q: 'Posso usar no celular?',
        a: 'Sim! Nossa plataforma é totalmente responsiva e funciona perfeitamente em celulares, tablets e computadores. Você pode estudar onde e quando quiser.',
        android: true,
    },
];
const openFaq = ref<number | null>(0);
const toggleFaq = (i: number) => {
    openFaq.value = openFaq.value === i ? null : i;
};
</script>

<template>
    <Head title="Memorize Direito - O Duolingo para Direito">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="true" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>
    <div class="lp-root flex min-h-screen flex-col items-center overflow-x-clip bg-white text-gray-800 dark:bg-black dark:text-gray-100">
        <!-- Header -->
        <header
            class="sticky top-0 z-50 w-full border-b bg-white transition-shadow duration-300 dark:bg-black"
            :class="scrolled ? 'border-gray-200 shadow-[0_2px_0_rgba(0,0,0,0.04)] dark:border-gray-800' : 'border-transparent'"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo :theme="logoTheme" />
                    </Link>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden items-center gap-6 md:flex">
                    <a href="#features" class="lp-navlink font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300">
                        Como funciona
                    </a>
                    <a href="#testimonials" class="lp-navlink font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300">
                        Benefícios
                    </a>
                    <a href="#research" class="lp-navlink font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300">
                        Pesquisa
                    </a>
                    <a href="#faq" class="lp-navlink font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300"> FAQ </a>
                    <Link
                        :href="route('public.laws')"
                        class="lp-navlink font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300"
                    >
                        Todas as Leis
                    </Link>
                    <a
                        v-if="showAndroid"
                        href="#app-android"
                        class="inline-flex items-center gap-1.5 font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        <Smartphone class="h-4 w-4" />
                        Android (novo)
                    </a>

                    <div class="h-5 w-px bg-gray-300 dark:bg-gray-700"></div>

                    <Link v-if="page.props.auth.user" :href="route('play.map')" class="flex items-center justify-center">
                        <GameButton variant="green" size="md">Continuar Estudando</GameButton>
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="font-medium text-gray-600 transition-colors hover:text-green-600 dark:text-gray-300">
                            Entrar
                        </Link>
                        <Link :href="route('play.map')" class="flex items-center justify-center">
                            <GameButton variant="green" size="md">Começar grátis</GameButton>
                        </Link>
                    </template>
                </nav>

                <!-- Mobile Menu Button -->
                <button
                    @click="toggleMobileMenu"
                    class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-900 md:hidden"
                    aria-label="Menu"
                >
                    <Menu v-if="!mobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>

            <!-- Barra de leitura -->
            <div class="absolute inset-x-0 bottom-0 h-[3px] origin-left bg-green-500" :style="{ transform: `scaleX(${scrollProgress})` }" />

            <!-- Mobile Navigation -->
            <Transition name="lp-menu">
                <div
                    v-if="mobileMenuOpen"
                    class="absolute inset-x-0 top-16 z-50 border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-black md:hidden"
                >
                    <nav class="flex flex-col gap-3">
                        <a
                            v-for="item in [
                                { href: '#features', label: 'Como funciona' },
                                { href: '#testimonials', label: 'Benefícios' },
                                { href: '#research', label: 'Pesquisa' },
                                { href: '#faq', label: 'FAQ' },
                            ]"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center justify-between rounded-lg px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-900"
                            @click="closeMenu"
                        >
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ item.label }}</span>
                            <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                        </a>
                        <Link
                            :href="route('public.laws')"
                            class="flex items-center justify-between rounded-lg px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-900"
                            @click="closeMenu"
                        >
                            <span class="font-medium text-gray-900 dark:text-gray-100">Todas as Leis</span>
                            <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                        </Link>
                        <a
                            v-if="showAndroid"
                            href="#app-android"
                            class="flex items-center justify-between rounded-lg px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-900"
                            @click="closeMenu"
                        >
                            <span class="font-medium text-blue-600 dark:text-blue-400">Android (novo)</span>
                            <Smartphone class="h-5 w-5 text-blue-500 dark:text-blue-400" />
                        </a>

                        <div class="my-2 h-px w-full bg-gray-200 dark:bg-gray-800"></div>

                        <Link v-if="page.props.auth.user" :href="route('play.map')" class="flex items-center justify-center" @click="closeMenu">
                            <GameButton variant="green" class="w-full text-center">Continuar Estudando</GameButton>
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="px-4 py-2 text-center font-medium text-gray-600 dark:text-gray-300"
                                @click="closeMenu"
                            >
                                Entrar
                            </Link>
                            <Link :href="route('play.map')" class="flex items-center justify-center" @click="closeMenu">
                                <GameButton variant="green" class="w-full text-center">Começar grátis</GameButton>
                            </Link>
                        </template>
                    </nav>
                </div>
            </Transition>
        </header>

        <!-- Hero -->
        <section class="w-full overflow-hidden bg-gray-50 dark:bg-gray-950" id="hero">
            <div
                class="mx-auto grid max-w-7xl items-center gap-16 px-4 pb-24 pt-10 sm:px-6 sm:pt-14 lg:grid-cols-[1.05fr_1fr] lg:gap-12 lg:pb-28 lg:pt-16"
            >
                <!-- Texto -->
                <div class="text-center lg:text-left">
                    <h1
                        class="heading-font text-[2.75rem] font-extrabold leading-[1.06] tracking-tight text-gray-900 dark:text-white sm:text-6xl lg:text-[4.4rem]"
                    >
                        <span class="sr-only">Estudar lei seca virou jogo.</span>
                        <span aria-hidden="true">
                            <span
                                v-for="(word, i) in ['Estudar', 'lei', 'seca', 'virou']"
                                :key="word"
                                class="mr-[0.22em] inline-block overflow-hidden pb-[0.1em] align-bottom"
                            >
                                <span class="lp-word inline-block" :class="heroWordsIn && 'lp-word-in'" :style="{ transitionDelay: `${i * 110}ms` }">
                                    {{ word }}
                                </span>
                            </span>
                            <span
                                class="lp-hero-gap mt-1 inline-flex items-center justify-center rounded-[0.3em] px-[0.25em] align-bottom"
                                :class="[
                                    heroWordsIn && 'lp-hero-gap-in',
                                    heroGapFilled
                                        ? 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400'
                                        : 'bg-yellow-100 text-yellow-600/70 dark:bg-yellow-900/40 dark:text-yellow-300/70',
                                ]"
                            >
                                <span v-if="!heroGapFilled" class="px-[0.6em] text-[0.7em]">(...)</span>
                                <span v-else class="lp-drop-in inline-block">jogo.</span>
                            </span>
                        </span>
                    </h1>

                    <p
                        class="lp-hero-intro mx-auto mt-7 max-w-xl text-lg leading-relaxed text-gray-600 dark:text-gray-300 sm:text-xl lg:mx-0"
                        style="animation-delay: 0.55s"
                    >
                        Complete as lacunas dos artigos, ganhe XP e suba no ranking. Poucos minutos por dia, do jeito que os aprovados estudam.
                    </p>

                    <div
                        class="lp-hero-intro mt-9 flex flex-col items-center gap-4 sm:flex-row sm:justify-center lg:justify-start"
                        style="animation-delay: 0.7s"
                    >
                        <Link :href="route('play.map')" class="flex items-center justify-center">
                            <GameButton variant="green" size="lg" class="px-9 text-lg">
                                {{ page.props.auth.user ? 'Continuar estudando' : 'Começar grátis' }}
                            </GameButton>
                        </Link>
                        <Link
                            v-if="!page.props.auth.user"
                            :href="route('login')"
                            class="px-2 py-2 font-semibold text-gray-600 underline-offset-4 transition-colors hover:text-gray-900 hover:underline dark:text-gray-300 dark:hover:text-white"
                        >
                            Já tenho conta
                        </Link>
                    </div>

                    <!-- Prova social -->
                    <div class="lp-hero-intro mt-10 flex items-center justify-center gap-3 lg:justify-start" style="animation-delay: 0.85s">
                        <div class="flex -space-x-3">
                            <UserAvatar
                                v-for="a in heroAvatars"
                                :key="a.name"
                                :config="a.config"
                                :name="a.name"
                                class="h-10 w-10 rounded-full ring-[3px] ring-gray-50 dark:ring-gray-950"
                            />
                        </div>
                        <p class="text-left text-sm leading-snug text-gray-500 dark:text-gray-400">
                            <span class="font-bold text-gray-900 dark:text-white">Mais de 9 mil estudantes já jogando</span><br />
                            {{ articlesCount.toLocaleString('pt-BR') }} artigos de {{ lawCount }} leis
                        </p>
                    </div>
                </div>

                <!-- Demo do jogo -->
                <div class="lp-hero-demo relative mx-auto w-full max-w-[460px]">
                    <!-- Etiquetas flutuando nos cantos do card -->
                    <div
                        class="lp-float absolute -right-8 -top-5 z-30 hidden items-center gap-2 rounded-2xl border-2 border-purple-200 bg-purple-50 px-3 py-2 text-sm font-extrabold text-purple-700 shadow-[0_4px_0_#e9d5ff] dark:border-purple-900 dark:bg-purple-950 dark:text-purple-300 dark:shadow-[0_4px_0_#3b0764] sm:flex"
                        style="animation-delay: -1.2s"
                        aria-hidden="true"
                    >
                        <Zap class="h-4 w-4 fill-current" /> Nível 12
                    </div>
                    <div
                        class="lp-float absolute -bottom-6 -left-6 z-30 hidden items-center gap-2 rounded-2xl border-2 border-gray-200 bg-white px-3 py-2 text-sm font-bold shadow-[0_4px_0_#e5e5e5] dark:border-gray-800 dark:bg-gray-900 dark:shadow-[0_4px_0_#262626] sm:flex"
                        style="animation-delay: -2.4s"
                        aria-hidden="true"
                    >
                        <Flame class="h-5 w-5 fill-orange-400 text-orange-500" />
                        <span>7 dias seguidos</span>
                    </div>

                    <HeroGameDemo />
                </div>
            </div>
        </section>

        <!-- Faixa de leis em movimento -->
        <section class="w-full border-y-2 border-gray-100 bg-white py-6 dark:border-gray-900 dark:bg-black" aria-label="Leis disponíveis">
            <div class="lp-marquee-mask space-y-3 overflow-hidden">
                <div class="lp-marquee flex w-max gap-3">
                    <Link
                        v-for="(law, i) in [...lawList, ...lawList]"
                        :key="`a-${i}`"
                        :href="getLawUrl(law)"
                        :tabindex="i >= lawList.length ? -1 : undefined"
                        :aria-hidden="i >= lawList.length ? 'true' : undefined"
                        class="whitespace-nowrap rounded-xl border-2 border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-[0_4px_0_#e5e5e5] transition-all hover:-translate-y-0.5 hover:border-green-300 hover:text-green-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:shadow-[0_4px_0_#262626] dark:hover:border-green-800 dark:hover:text-green-400"
                    >
                        {{ law.name }}
                    </Link>
                </div>
                <div class="lp-marquee lp-marquee-reverse flex w-max gap-3" aria-hidden="true">
                    <Link
                        v-for="(law, i) in [...lawListReversed, ...lawListReversed]"
                        :key="`b-${i}`"
                        :href="getLawUrl(law)"
                        tabindex="-1"
                        class="whitespace-nowrap rounded-xl border-2 border-yellow-200 bg-yellow-50 px-4 py-2 text-sm font-bold text-yellow-800 shadow-[0_4px_0_#fef08a] transition-all hover:-translate-y-0.5 dark:border-yellow-900/60 dark:bg-yellow-950/40 dark:text-yellow-200 dark:shadow-[0_4px_0_#422006]"
                    >
                        {{ law.name }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- App Android Section -->
        <section v-if="showAndroid" class="w-full bg-blue-50 py-16 dark:bg-blue-950" id="app-android">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div v-reveal class="flex flex-col items-center gap-8 text-center lg:flex-row lg:text-left">
                    <div class="flex-shrink-0">
                        <div class="lp-phone flex h-24 w-24 items-center justify-center rounded-3xl bg-blue-100 dark:bg-blue-900 sm:h-32 sm:w-32">
                            <Smartphone class="h-14 w-14 text-blue-600 dark:text-blue-400 sm:h-16 sm:w-16" />
                        </div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <div class="flex items-center justify-center gap-3 lg:justify-start">
                            <h2 class="heading-font text-2xl font-bold text-gray-800 dark:text-white sm:text-3xl">App Android disponível</h2>
                            <span
                                class="rounded-full bg-blue-200 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-800 dark:text-blue-300"
                                >Beta</span
                            >
                        </div>
                        <p class="max-w-xl text-base text-gray-600 dark:text-gray-300 sm:text-lg">
                            Baixe o Memorize Direito para o seu celular Android e estude legislação brasileira onde quiser, mesmo sem internet.
                            Primeira versão em testes, sua opinião é bem-vinda!
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="/memorize-app.apk" download class="flex w-full items-center justify-center sm:w-auto">
                            <GameButton variant="blue" size="lg" class="flex items-center gap-3">
                                <Download class="lp-download-icon h-6 w-6" />
                                Baixar Grátis
                            </GameButton>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Como funciona -->
        <section class="w-full bg-white py-20 dark:bg-black" id="features">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div v-reveal class="mb-14 text-center">
                    <h2 class="heading-font mb-5 text-4xl font-bold text-gray-800 dark:text-white sm:text-5xl">
                        Como o <span class="text-green-600 dark:text-green-400">Memorize Direito</span> funciona?
                    </h2>
                    <p class="mx-auto max-w-3xl text-xl text-gray-600 dark:text-gray-300">
                        Uma plataforma simples e eficiente para aprender legislação brasileira através de exercícios gamificados.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div
                        v-for="(step, i) in steps"
                        :key="step.title"
                        v-reveal="i * 120"
                        class="group rounded-3xl border-2 border-gray-200 bg-white p-6 shadow-[0_6px_0_#e5e5e5] transition-transform duration-300 hover:-translate-y-1 dark:border-gray-800 dark:bg-gray-900 dark:shadow-[0_6px_0_#262626]"
                    >
                        <!-- Mini animação de cada passo -->
                        <div
                            class="mb-6 flex h-36 items-center justify-center overflow-hidden rounded-2xl bg-gray-50 dark:bg-gray-950"
                            aria-hidden="true"
                        >
                            <!-- 1: grifando o artigo -->
                            <div v-if="i === 0" class="w-4/5 space-y-2.5">
                                <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-800" />
                                <div class="relative h-2.5 w-11/12 rounded-full bg-gray-200 dark:bg-gray-800">
                                    <span class="lp-highlight absolute -inset-y-1 left-0 rounded bg-yellow-300/80 dark:bg-yellow-500/60" />
                                </div>
                                <div class="h-2.5 w-10/12 rounded-full bg-gray-200 dark:bg-gray-800" />
                                <div class="relative h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-800">
                                    <span
                                        class="lp-highlight absolute -inset-y-1 left-0 rounded bg-yellow-300/80 dark:bg-yellow-500/60"
                                        style="animation-delay: 0.9s"
                                    />
                                </div>
                                <div class="h-2.5 w-7/12 rounded-full bg-gray-200 dark:bg-gray-800" />
                            </div>

                            <!-- 2: palavra caindo na lacuna -->
                            <div v-else-if="i === 1" class="flex flex-col items-center gap-5">
                                <span
                                    class="lp-mini-chip rounded-xl border-2 border-gray-300 bg-white px-3 py-1 text-sm font-bold text-gray-800 shadow-[0_3px_0_#d4d4d4] dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:shadow-[0_3px_0_#404040]"
                                >
                                    iguais
                                </span>
                                <p class="text-base font-medium text-gray-700 dark:text-gray-300">
                                    Todos são
                                    <span class="lp-mini-gap mx-1 inline-block min-w-[4rem] rounded-lg px-2 text-center font-bold">
                                        <span class="lp-mini-gap-text">iguais</span>
                                    </span>
                                    perante a lei
                                </p>
                            </div>

                            <!-- 3: fases destravando -->
                            <div v-else class="flex items-center">
                                <template v-for="n in 4" :key="n">
                                    <span v-if="n > 1" class="relative h-2 w-6 overflow-hidden bg-gray-200 dark:bg-gray-800">
                                        <span
                                            class="lp-mini-line absolute inset-0 origin-left bg-green-400"
                                            :style="{ animationDelay: `${(n - 1) * 0.55 - 0.3}s` }"
                                        />
                                    </span>
                                    <span
                                        class="lp-mini-node flex h-11 w-11 items-center justify-center rounded-full border-4 text-white"
                                        :style="{ animationDelay: `${(n - 1) * 0.55}s` }"
                                    >
                                        <CheckCircle class="h-5 w-5" stroke-width="3" />
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="mb-2 flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-extrabold text-white" :class="step.color">{{
                                i + 1
                            }}</span>
                            <h3 class="heading-font text-2xl font-bold text-gray-800 dark:text-white">{{ step.title }}</h3>
                        </div>
                        <p class="leading-relaxed text-gray-600 dark:text-gray-300">{{ step.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trilha (benefícios) -->
        <section class="w-full bg-gray-50 py-20 dark:bg-gray-950" id="testimonials">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-start gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div class="lg:sticky lg:top-28">
                    <div v-reveal>
                        <h2 class="heading-font mb-5 text-4xl font-bold text-gray-800 dark:text-white sm:text-5xl">
                            Uma trilha que <span class="text-blue-600 dark:text-blue-400">não te deixa parar</span>
                        </h2>
                        <p class="mb-8 text-xl text-gray-600 dark:text-gray-300">
                            Role a página e veja: cada fase concluída pinta o caminho e destrava a próxima.
                        </p>
                    </div>

                    <ul class="space-y-4">
                        <li v-reveal="0" class="flex gap-4 rounded-2xl border-2 border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-yellow-100 dark:bg-yellow-900/40">
                                <Zap class="h-6 w-6 text-yellow-600 dark:text-yellow-300" />
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white">Aprendizado rápido</h3>
                                <p class="text-gray-600 dark:text-gray-300">Fases curtas, com 6 artigos cada. Cabem no intervalo do café.</p>
                            </div>
                        </li>
                        <li v-reveal="100" class="flex gap-4 rounded-2xl border-2 border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40">
                                <RotateCcw class="h-6 w-6 text-purple-600 dark:text-purple-300" />
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white">Revisão no tempo certo</h3>
                                <p class="text-gray-600 dark:text-gray-300">
                                    A cada 3 fases, uma revisão volta ao que você já viu para fixar de vez.
                                </p>
                            </div>
                        </li>
                        <li v-reveal="200" class="flex gap-4 rounded-2xl border-2 border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/40">
                                <Target class="h-6 w-6 text-green-600 dark:text-green-300" />
                            </span>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white">Várias leis ao mesmo tempo</h3>
                                <p class="text-gray-600 dark:text-gray-300">As leis se alternam na trilha, como num edital de verdade.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-center">
                    <TrailDemo />
                </div>
            </div>
        </section>

        <!-- Ranking + avatar -->
        <section class="w-full bg-white py-20 dark:bg-black" id="ranking">
            <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                <div v-reveal class="order-2 w-full max-w-md justify-self-center lg:order-1">
                    <RankingDemo />
                </div>
                <div v-reveal="120" class="order-1 lg:order-2">
                    <h2 class="heading-font mb-5 text-4xl font-bold text-gray-800 dark:text-white sm:text-5xl">
                        Cada artigo vale <span class="text-purple-600 dark:text-purple-400">XP</span>
                    </h2>
                    <p class="mb-6 text-xl text-gray-600 dark:text-gray-300">
                        Suba no ranking estudando lei seca. Monte seu avatar, vista a toga e mostre quem chega primeiro na aprovação.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <span
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-50 px-3 py-2 font-bold text-purple-700 dark:bg-purple-950/50 dark:text-purple-300"
                        >
                            <Zap class="h-4 w-4 fill-current" /> XP por fase
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-xl bg-orange-50 px-3 py-2 font-bold text-orange-600 dark:bg-orange-950/50 dark:text-orange-300"
                        >
                            <Flame class="h-4 w-4 fill-current" /> Ofensiva diária
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-xl bg-yellow-50 px-3 py-2 font-bold text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-300"
                        >
                            <Trophy class="h-4 w-4" /> Ranking
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção da Pesquisa -->
        <section class="w-full bg-gray-50 py-20 dark:bg-gray-950" id="research">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div v-reveal class="mb-12 text-center">
                    <h2 class="heading-font mb-4 text-3xl font-bold text-gray-800 dark:text-white sm:text-4xl">
                        Dados que <span class="text-green-600 dark:text-green-400">comprovam</span> a eficácia
                    </h2>
                </div>

                <div
                    ref="researchEl"
                    v-reveal
                    class="rounded-3xl border-2 border-gray-200 bg-white p-8 shadow-[0_8px_0_#e5e5e5] dark:border-gray-800 dark:bg-gray-900 dark:shadow-[0_8px_0_#262626]"
                >
                    <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                        <div class="text-center lg:text-left">
                            <div class="mb-6">
                                <div class="mb-2 text-6xl font-extrabold tabular-nums text-green-600 dark:text-green-400 sm:text-7xl">
                                    {{ judgesCounter.value.value }}
                                </div>
                                <div class="text-xl text-gray-600 dark:text-gray-300">de 194 juízes aprovados</div>
                            </div>
                            <h3 class="mb-4 text-2xl font-bold text-gray-800 dark:text-white">
                                Estudaram legislação <span class="text-green-600 dark:text-green-400">todo dia</span>
                            </h3>
                            <p class="mb-6 leading-relaxed text-gray-600 dark:text-gray-300">
                                Pesquisa realizada pela <strong>Magistratura Estadual em Foco</strong>
                                comprova que o estudo diário e consistente da legislação é o método mais eficaz para aprovação em concursos jurídicos.
                            </p>
                            <a
                                href="https://magistraturaestadualemfoco.com/pesquisa"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 font-medium text-green-600 transition-colors hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
                            >
                                <span>Consultar pesquisa completa</span>
                                <ChevronRight class="h-4 w-4" />
                            </a>
                        </div>

                        <div class="flex justify-center">
                            <div class="relative h-64 w-64">
                                <svg class="absolute inset-0 h-full w-full -rotate-90" viewBox="0 0 200 200">
                                    <circle cx="100" cy="100" r="90" fill="none" class="stroke-gray-100 dark:stroke-gray-800" stroke-width="16" />
                                    <circle
                                        cx="100"
                                        cy="100"
                                        r="90"
                                        fill="none"
                                        class="lp-ring stroke-green-500 dark:stroke-green-400"
                                        stroke-width="16"
                                        stroke-linecap="round"
                                        stroke-dasharray="565.48"
                                        :stroke-dashoffset="ringOffset"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                    <div class="text-4xl font-extrabold tabular-nums text-green-700 dark:text-green-400">
                                        {{ (rateCounter.value.value / 10).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }}%
                                    </div>
                                    <div class="text-sm font-medium text-green-600 dark:text-green-300">Taxa de aprovação</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">com estudo diário</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="w-full bg-white py-20 dark:bg-black" id="faq">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <div v-reveal class="mb-12 text-center">
                    <h2 class="heading-font mb-4 text-3xl font-bold text-gray-800 dark:text-white sm:text-4xl">
                        Perguntas <span class="text-green-600 dark:text-green-400">Frequentes</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">Tire suas dúvidas sobre a plataforma</p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(faq, i) in faqs"
                        :key="faq.q"
                        v-reveal="i * 70"
                        class="overflow-hidden rounded-2xl border-2 transition-colors duration-300"
                        :class="
                            openFaq === i
                                ? 'border-green-300 bg-green-50/60 dark:border-green-900 dark:bg-green-950/20'
                                : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900'
                        "
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left font-bold text-gray-800 transition-colors hover:text-green-600 dark:text-white dark:hover:text-green-400"
                            :aria-expanded="openFaq === i"
                            @click="toggleFaq(i)"
                        >
                            {{ faq.q }}
                            <ChevronDown class="h-5 w-5 shrink-0 transition-transform duration-300" :class="openFaq === i && 'rotate-180'" />
                        </button>
                        <div class="lp-accordion grid" :class="openFaq === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                            <div class="overflow-hidden">
                                <div class="space-y-4 px-5 pb-5 text-gray-600 dark:text-gray-300">
                                    <p>{{ faq.a }}</p>
                                    <a
                                        v-if="faq.android && showAndroid"
                                        href="#app-android"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
                                    >
                                        <Smartphone class="h-4 w-4" />
                                        Android (novo)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Principal -->
        <section ref="ctaEl" class="relative w-full overflow-hidden bg-gray-900 py-20 text-white dark:bg-gray-950" id="cta">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <span class="lp-drift absolute left-[8%] top-[20%] h-4 w-4 rounded-md bg-yellow-400 opacity-40" />
                <span class="lp-drift absolute right-[10%] top-[30%] h-3 w-3 rounded-full bg-green-400 opacity-50" style="animation-delay: -2s" />
                <span class="lp-drift absolute bottom-[18%] left-[18%] h-3 w-3 rounded-full bg-blue-400 opacity-50" style="animation-delay: -4s" />
                <span
                    class="lp-drift absolute bottom-[22%] right-[20%] h-5 w-5 rotate-12 rounded-md bg-purple-400 opacity-30"
                    style="animation-delay: -6s"
                />
            </div>
            <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6">
                <h2 class="heading-font mb-6 text-4xl font-extrabold sm:text-6xl">
                    Comece a estudar
                    <span
                        class="mt-2 inline-flex items-center rounded-2xl px-3 transition-colors duration-500"
                        :class="ctaInView ? 'bg-green-500/15 text-green-400' : 'bg-yellow-400/15 text-yellow-300/70'"
                    >
                        <span v-if="ctaInView" class="lp-drop-in inline-block" style="animation-delay: 0.25s">hoje mesmo!</span>
                        <span v-else class="px-6">(...)</span>
                    </span>
                </h2>
                <p v-reveal class="mb-10 text-xl leading-relaxed opacity-90">
                    Junte-se aos estudantes que já descobriram uma nova forma de aprender legislação brasileira.
                </p>

                <div v-reveal="120" class="mb-10 flex flex-col justify-center gap-4 sm:flex-row">
                    <Link :href="route('play.map')" class="lp-cta-button flex items-center justify-center">
                        <GameButton variant="green" size="lg" class="flex items-center gap-3 px-10 py-4 text-xl">
                            <Target class="h-6 w-6" />
                            {{ page.props.auth.user ? 'Continuar estudando' : 'Começar gratuitamente' }}
                        </GameButton>
                    </Link>
                </div>

                <div v-reveal="200" class="flex flex-wrap justify-center gap-8 text-sm opacity-80">
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5" />
                        <span>Acesso gratuito</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5" />
                        <span>Sem compromisso</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5" />
                        <span>Comece agora</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full bg-gray-900 py-12 text-white dark:bg-black">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-3 lg:grid-cols-4">
                    <!-- Logo e descrição -->
                    <div class="text-center md:text-left">
                        <div class="mb-4 flex items-center justify-center gap-2 md:justify-start">
                            <AppLogo theme="dark" height="80px" />
                        </div>
                    </div>

                    <!-- Leis principais -->
                    <div class="text-center md:text-left">
                        <h4 class="mb-4 font-semibold text-white">Leis Principais</h4>
                        <div class="flex flex-col gap-2 text-sm text-gray-400">
                            <Link v-for="law in firstFourLaws" :key="law.uuid" :href="getLawUrl(law)" class="transition-colors hover:text-white">
                                {{ law.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Links legais -->
                    <div class="text-center md:text-left">
                        <h4 class="mb-4 font-semibold text-white">Links</h4>
                        <div class="flex flex-col gap-2 text-sm text-gray-400">
                            <Link :href="route('public.laws')" class="transition-colors hover:text-white">Todas as Leis</Link>
                            <Link :href="route('privacy-policy')" class="transition-colors hover:text-white">Políticas de Privacidade</Link>
                            <Link :href="route('cookies')" class="transition-colors hover:text-white">Cookies</Link>
                            <Link :href="route('terms')" class="transition-colors hover:text-white">Termos de Uso</Link>
                        </div>
                    </div>

                    <!-- Redes sociais -->
                    <div class="text-center md:text-left">
                        <h4 class="mb-4 font-semibold text-white">Redes Sociais</h4>
                        <div class="mb-6 flex justify-center gap-4 md:justify-start">
                            <a
                                href="https://www.facebook.com/reel/1036534338539492"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-gray-400 transition-colors hover:text-white"
                                aria-label="Facebook"
                            >
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                                    />
                                </svg>
                            </a>
                            <a
                                href="https://www.instagram.com/memorizedireitoapp/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-gray-400 transition-colors hover:text-white"
                                aria-label="Instagram"
                            >
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 6.621 5.367 11.988 11.988 11.988s11.988-5.367 11.988-11.988C24.005 5.367 18.638.001 12.017.001zM8.449 16.988c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541zm7.508 0c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541z"
                                    />
                                </svg>
                            </a>
                        </div>
                        <a
                            v-if="showAndroid"
                            href="#app-android"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            <Smartphone class="h-4 w-4" />
                            Android (novo)
                        </a>
                    </div>
                </div>

                <div class="mt-8 border-t border-gray-800 pt-8 text-center dark:border-gray-800">
                    <p class="text-gray-400 dark:text-gray-500">© {{ new Date().getFullYear() }} Memorize Direito. Todos os direitos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
.heading-font {
    font-family: 'Poppins', sans-serif;
}

.game-button {
    transition: all 0.2s ease;
}

.game-button:hover {
    transform: translateY(2px);
}

.game-button:active {
    transform: translateY(4px);
    box-shadow: none !important;
}

/* Âncoras não ficam escondidas atrás do header fixo */
.lp-root section[id] {
    scroll-margin-top: 4.5rem;
}

/* ---------- Revelar ao rolar (v-reveal) ---------- */
.lp-reveal {
    opacity: 0;
    transform: translateY(28px);
    transition:
        opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    transition-delay: var(--reveal-delay, 0ms);
}

.lp-reveal.lp-reveal-in {
    opacity: 1;
    transform: none;
}

/* ---------- Hero ---------- */
.lp-hero-intro {
    animation: lp-fade-up 0.8s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.lp-word {
    transform: translateY(110%);
    transition: transform 0.75s cubic-bezier(0.22, 1, 0.36, 1);
}

.lp-word.lp-word-in {
    transform: none;
}

.lp-hero-gap {
    opacity: 0;
    transform: scale(0.85);
    transition:
        opacity 0.4s ease 0.45s,
        transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.45s,
        background-color 0.5s,
        color 0.5s;
}

.lp-hero-gap.lp-hero-gap-in {
    opacity: 1;
    transform: none;
}

.lp-hero-demo {
    animation: lp-demo-in 1s cubic-bezier(0.22, 1, 0.36, 1) 0.3s backwards;
}

@keyframes lp-demo-in {
    from {
        opacity: 0;
        transform: translateY(40px) rotate(2deg);
    }
}

.lp-float {
    animation: lp-float 4s ease-in-out infinite;
}

@keyframes lp-float {
    0%,
    100% {
        transform: translateY(0) rotate(-3deg);
    }
    50% {
        transform: translateY(-8px) rotate(1deg);
    }
}

@keyframes lp-fade-up {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

.lp-drop-in {
    animation: lp-drop-in 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes lp-drop-in {
    0% {
        opacity: 0;
        transform: translateY(-0.9em) rotate(-4deg) scale(0.9);
    }
    100% {
        opacity: 1;
        transform: none;
    }
}

.lp-drift {
    animation: lp-drift 9s ease-in-out infinite;
}

@keyframes lp-drift {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
    }
    33% {
        transform: translate(14px, -18px) rotate(var(--drift-r, 45deg));
    }
    66% {
        transform: translate(-10px, 10px) rotate(calc(var(--drift-r, 45deg) * -1));
    }
}

/* ---------- Faixa de leis ---------- */
.lp-marquee {
    animation: lp-marquee 60s linear infinite;
    padding-bottom: 6px;
}

.lp-marquee-reverse {
    animation-direction: reverse;
    animation-duration: 75s;
}

.lp-marquee-mask:hover .lp-marquee {
    animation-play-state: paused;
}

@keyframes lp-marquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(calc(-50% - 6px));
    }
}

/* ---------- Mini animações dos passos ---------- */
.lp-highlight {
    animation: lp-highlight 3.2s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}

@keyframes lp-highlight {
    0% {
        width: 0;
        opacity: 1;
    }
    35%,
    80% {
        width: 100%;
        opacity: 1;
    }
    100% {
        width: 100%;
        opacity: 0;
    }
}

.lp-mini-chip {
    animation: lp-mini-chip 3s cubic-bezier(0.45, 0, 0.25, 1) infinite;
}

@keyframes lp-mini-chip {
    0%,
    12% {
        transform: translateY(0);
        opacity: 1;
    }
    32% {
        transform: translateY(46px) scale(0.92);
        opacity: 0;
    }
    33%,
    85% {
        transform: translateY(0);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

.lp-mini-gap {
    animation: lp-mini-gap 3s step-end infinite;
}

@keyframes lp-mini-gap {
    0% {
        background: #fef9c3;
        color: transparent;
    }
    32% {
        background: #dcfce7;
        color: #15803d;
    }
    90% {
        background: #fef9c3;
        color: transparent;
    }
}

.lp-mini-gap-text {
    display: inline-block;
    animation: lp-mini-gap-pop 3s cubic-bezier(0.34, 1.56, 0.64, 1) infinite;
}

@keyframes lp-mini-gap-pop {
    0%,
    31% {
        transform: scale(0.5);
    }
    40%,
    100% {
        transform: scale(1);
    }
}

.dark .lp-mini-gap {
    animation-name: lp-mini-gap-dark;
}

@keyframes lp-mini-gap-dark {
    0% {
        background: rgba(113, 63, 18, 0.45);
        color: transparent;
    }
    32% {
        background: rgba(20, 83, 45, 0.6);
        color: #86efac;
    }
    90% {
        background: rgba(113, 63, 18, 0.45);
        color: transparent;
    }
}

.lp-mini-node {
    animation: lp-mini-node 3.6s cubic-bezier(0.34, 1.56, 0.64, 1) infinite;
    background: #e5e5e5;
    border-color: #d4d4d4;
}

.dark .lp-mini-node {
    animation-name: lp-mini-node-dark;
}

@keyframes lp-mini-node {
    0% {
        background: #e5e5e5;
        border-color: #d4d4d4;
        transform: scale(1);
    }
    8% {
        background: #22c55e;
        border-color: #16a34a;
        transform: scale(1.18);
    }
    16%,
    85% {
        background: #22c55e;
        border-color: #16a34a;
        transform: scale(1);
    }
    92%,
    100% {
        background: #e5e5e5;
        border-color: #d4d4d4;
    }
}

@keyframes lp-mini-node-dark {
    0% {
        background: #262626;
        border-color: #404040;
        transform: scale(1);
    }
    8% {
        background: #22c55e;
        border-color: #16a34a;
        transform: scale(1.18);
    }
    16%,
    85% {
        background: #22c55e;
        border-color: #16a34a;
        transform: scale(1);
    }
    92%,
    100% {
        background: #262626;
        border-color: #404040;
    }
}

.lp-mini-line {
    animation: lp-mini-line 3.6s ease-out infinite;
}

@keyframes lp-mini-line {
    0% {
        transform: scaleX(0);
    }
    10%,
    85% {
        transform: scaleX(1);
    }
    92%,
    100% {
        transform: scaleX(0);
    }
}

/* ---------- Diversos ---------- */
.lp-ring {
    transition: stroke-dashoffset 1.6s cubic-bezier(0.22, 1, 0.36, 1);
}

.lp-accordion {
    transition: grid-template-rows 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

.lp-phone {
    animation: lp-phone 3.5s ease-in-out infinite;
}

@keyframes lp-phone {
    0%,
    70%,
    100% {
        transform: rotate(0deg);
    }
    75% {
        transform: rotate(-8deg);
    }
    80% {
        transform: rotate(7deg);
    }
    85% {
        transform: rotate(-5deg);
    }
    90% {
        transform: rotate(3deg);
    }
}

.lp-download-icon {
    animation: lp-download 1.8s ease-in-out infinite;
}

@keyframes lp-download {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(3px);
    }
}

.lp-cta-button {
    animation: lp-cta 2.4s ease-in-out infinite;
}

@keyframes lp-cta {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.04);
    }
}

.lp-menu-enter-active,
.lp-menu-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.lp-menu-enter-from,
.lp-menu-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .lp-hero-intro,
    .lp-hero-demo,
    .lp-float,
    .lp-drop-in,
    .lp-drift,
    .lp-marquee,
    .lp-highlight,
    .lp-mini-chip,
    .lp-mini-gap,
    .lp-mini-gap-text,
    .lp-mini-node,
    .lp-mini-line,
    .lp-phone,
    .lp-download-icon,
    .lp-cta-button {
        animation: none !important;
    }

    .lp-word,
    .lp-hero-gap,
    .lp-reveal {
        transition: none !important;
    }
}
</style>
