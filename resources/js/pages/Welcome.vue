<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Book, PlayCircle, Menu, X, ChevronRight, Target, CheckCircle, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import GameButton from '@/components/ui/GameButton.vue';
import { useAppearance } from '@/composables/useAppearance';

const mobileMenuOpen = ref(false);
const page = usePage<{
    auth: {
        user: any;
    }
}>();

const { logoTheme } = useAppearance();

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMenu = () => {
    mobileMenuOpen.value = false;
};

// Props para receber dados do backend
defineProps<{
    articlesCount: number;
    firstFourLaws: Array<{
        name: string;
        uuid: string;
        slug?: string;
    }>;
}>();

// Redirecionamento agora é feito no backend (WelcomeController)

// Helper function para URLs seguras com fallback
const getLawUrl = (law: { uuid: string; slug?: string }) => {
    // Prefer slug-based URL if available, fallback to UUID
    if (law.slug) {
        return route('public.law', { legalReference: law.slug });
    }
    // Fallback to legacy UUID route
    return route('public.law.legacy', { uuid: law.uuid });
};
</script>

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
</style>

<template>
    <Head title="Memorize Direito - O Duolingo para Direito">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="true">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-white dark:bg-black text-gray-800 dark:text-gray-100">
        <!-- Header -->
        <header class="w-full bg-white dark:bg-black border-b border-gray-200 dark:border-gray-800 sticky top-0 z-50">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo :theme="logoTheme" />
                    </Link>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-6">
                    <Link href="#features" class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors">
                        Como funciona
                    </Link>
                    <Link href="#testimonials" class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors">
                        Benefícios
                    </Link>
                    <Link href="#research" class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors">
                        Pesquisa
                    </Link>
                    <Link href="#faq" class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors">
                        FAQ
                    </Link>
                    <Link :href="route('public.laws')" class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors">
                        Todas as Leis
                    </Link>
                    
                    <div class="h-5 w-px bg-gray-300 dark:bg-gray-700"></div>
                    
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('play.map')"
                        class="flex items-center justify-center"
                    >
                        <GameButton variant="green" size="md">
                            Continuar Estudando
                        </GameButton>
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-gray-600 dark:text-gray-300 hover:text-green-600 font-medium transition-colors"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('play.map')"
                            class="flex items-center justify-center"
                        >
                            <GameButton variant="green" size="md">
                                Começar Grátis
                            </GameButton>
                        </Link>
                    </template>
                </nav>
                
                <!-- Mobile Menu Button -->
                <button 
                    @click="toggleMobileMenu" 
                    class="md:hidden flex items-center justify-center h-10 w-10 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-900 transition-colors"
                    aria-label="Menu"
                >
                    <Menu v-if="!mobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <div 
                v-if="mobileMenuOpen" 
                class="md:hidden absolute top-16 inset-x-0 bg-white dark:bg-black border-b border-gray-200 dark:border-gray-800 py-4 px-4 z-50"
            >
                <nav class="flex flex-col gap-3">
                    <Link 
                        href="#features" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900"
                        @click="closeMenu"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">Como funciona</span>
                        <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </Link>
                    <Link 
                        href="#testimonials" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900"
                        @click="closeMenu"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">Benefícios</span>
                        <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </Link>
                    <Link 
                        href="#research" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900"
                        @click="closeMenu"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">Pesquisa</span>
                        <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </Link>
                    <Link 
                        href="#faq" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900"
                        @click="closeMenu"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">FAQ</span>
                        <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </Link>
                    <Link 
                        :href="route('public.laws')" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900"
                        @click="closeMenu"
                    >
                        <span class="font-medium text-gray-900 dark:text-gray-100">Todas as Leis</span>
                        <ChevronRight class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </Link>
                    
                    <div class="h-px w-full bg-gray-200 dark:bg-gray-800 my-2"></div>
                    
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('play.map')"
                        class="flex items-center justify-center"
                        @click="closeMenu"
                    >
                        <GameButton variant="green" class="w-full text-center">
                            Continuar Estudando
                        </GameButton>
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 text-center text-gray-600 dark:text-gray-300 font-medium"
                            @click="closeMenu"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('play.map')"
                            class="flex items-center justify-center"
                            @click="closeMenu"
                        >
                            <GameButton variant="green" class="w-full text-center">
                                Começar Grátis
                            </GameButton>
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="w-full py-20 md:py-24 bg-gradient-to-br from-slate-50 to-gray-100 dark:from-gray-900 dark:to-black" id="hero">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-20">
                    <!-- Conteúdo Principal -->
                    <div class="w-full lg:w-[60%] text-center lg:text-left">
                        <div class="space-y-6">
                            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold heading-font text-gray-900 dark:text-white leading-tight">
                                Lei seca 
                                <span class="text-gray-700 dark:text-gray-300">gamificada</span>
                            </h1>
                            
                            <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl">
                                Aprenda legislação brasileira de forma divertida e eficiente. 
                                Gamificação que transforma o estudo jurídico em uma jornada envolvente.
                            </p>
                            
                            <div class="flex flex-col sm:flex-row gap-4 pt-4">
                                <Link
                                    v-if="!page.props.auth.user"
                                    :href="route('play.map')"
                                    class="w-full sm:w-auto flex items-center justify-center"
                                >
                                    <GameButton variant="blue" size="lg" class="w-full sm:w-auto px-8 py-4 text-xl flex items-center gap-3">
                                        <PlayCircle class="h-6 w-6" />
                                        Começar gratuitamente
                                    </GameButton>
                                </Link>
                                <Link
                                    v-else
                                    :href="route('play.map')"
                                    class="w-full sm:w-auto flex items-center justify-center"
                                >
                                    <GameButton variant="blue" size="lg" class="w-full sm:w-auto px-8 py-4 text-xl flex items-center gap-3">
                                        <PlayCircle class="h-6 w-6" />
                                        Continuar Estudando
                                    </GameButton>
                                </Link>
                                
                                <a href="#features" class="w-full sm:w-auto flex items-center justify-center">
                                    <GameButton variant="white" size="lg" class="w-full sm:w-auto px-8 py-4 text-xl flex items-center gap-3">
                                        Entenda mais
                                    </GameButton>
                                </a>
                            </div>
                            
                            <!-- Stats -->
                            <div class="flex flex-wrap gap-8 justify-center lg:justify-start pt-8">
                                <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300">
                                    <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-lg">
                                        <Book class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                                    </div>
                                    <span class="font-medium">{{ articlesCount }}+ artigos disponíveis</span>
                                </div>
                                <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300">
                                    <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-lg">
                                        <Trophy class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                                    </div>
                                    <span class="font-medium">Sistema de XP e níveis</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ilustração Principal -->
                    <div class="w-full lg:w-[40%] relative">
                        <div class="relative max-w-lg mx-auto">
                            <!-- Container das imagens com melhor layout -->
                            <div class="relative h-[400px] sm:h-[500px]">
                                <!-- Imagem de trás (quadradomapa.png) - Posicionada primeiro para ficar atrás -->
                                <div class="absolute top-8 right-4 w-64 sm:w-80 z-10 transform rotate-3 hover:rotate-6 hover:scale-105 hover:z-30 transition-all duration-300 cursor-pointer">
                                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xl hover:shadow-2xl transition-shadow duration-300 overflow-hidden">
                                        <img 
                                            src="/img/quadradomapa.png"
                                            alt="Mapa do sistema" 
                                            class="w-full h-auto object-cover"
                                        />
                                    </div>
                                </div>
                                
                                <!-- Imagem de frente (quadradoscreen.png) - Posicionada por último para ficar na frente -->
                                <div class="absolute top-0 left-0 w-64 sm:w-80 z-20 transform -rotate-2 hover:rotate-0 hover:scale-105 hover:z-30 transition-all duration-300 cursor-pointer">
                                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-2xl hover:shadow-3xl transition-shadow duration-300 overflow-hidden">
                                        <img 
                                            src="/img/quadradoscreen.png"
                                            alt="Tela do sistema" 
                                            class="w-full h-auto object-cover"
                                        />
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Elemento decorativo de fundo -->
                            <div class="absolute inset-0 -z-10 overflow-hidden">
                                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-72 h-72 sm:w-96 sm:h-96 bg-gradient-to-br from-gray-200/30 to-gray-300/30 dark:from-gray-800/20 dark:to-black/20 rounded-full blur-3xl"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção de Recursos -->
        <section class="w-full py-16 bg-gray-50 dark:bg-gray-950" id="features">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl sm:text-5xl font-bold heading-font mb-6 text-gray-800 dark:text-white">
                        Como o <span class="text-green-600 dark:text-green-400">Memorize Direito</span> funciona?
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Uma plataforma simples e eficiente para aprender legislação brasileira 
                        através de exercícios gamificados.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Recurso 1 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-200 dark:border-gray-700">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            📚
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Estude por Artigos</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Aprenda cada artigo da legislação brasileira de forma estruturada e progressiva.
                        </p>
                    </div>
                    
                    <!-- Recurso 2 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-200 dark:border-gray-700">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🎮
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Sistema de XP</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Ganhe experiência (XP) a cada resposta correta e suba de nível conforme progride.
                        </p>
                    </div>
                    
                    <!-- Recurso 3 -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center border border-gray-200 dark:border-gray-700">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🏆
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Progresso Visual</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Acompanhe seu aprendizado com mapas de progresso e estatísticas detalhadas.
                        </p>
                    </div>
                </div>
                
                <!-- Botão para próxima seção -->
                <div class="text-center mt-12">
                    <a href="#testimonials" class="inline-flex items-center justify-center">
                        <GameButton variant="white" class="flex items-center gap-2">
                            <span>Ver Benefícios</span>
                            <ChevronRight class="h-5 w-5" />
                        </GameButton>
                    </a>
                </div>
            </div>
        </section>

        <!-- Seção de Depoimentos -->
        <section class="w-full py-16 bg-white dark:bg-gray-900" id="testimonials">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl sm:text-5xl font-bold heading-font mb-6 text-gray-800 dark:text-white">
                        Aprenda Direito de forma <span class="text-gray-700 dark:text-gray-300">eficiente</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Transforme o estudo da legislação brasileira em uma jornada engajante 
                        com nossa metodologia gamificada.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Benefício 1 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-800 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            ⚡
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Aprendizado Rápido</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Sessões curtas e focadas que se encaixam na sua rotina. 
                            Aprenda um pouco todos os dias.
                        </p>
                    </div>
                    
                    <!-- Benefício 2 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-800 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🎯
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Foco na Prática</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Exercícios práticos baseados em situações reais do direito brasileiro. 
                            Aprenda aplicando o conhecimento.
                        </p>
                    </div>
                    
                    <!-- Benefício 3 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-800 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            📈
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800 dark:text-white">Progresso Contínuo</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            Acompanhe sua evolução com métricas claras e motivadoras. 
                            Celebre cada conquista.
                        </p>
                    </div>
                </div>
                
                <!-- Botão para próxima seção -->
                <div class="text-center mt-12">
                    <a href="#research" class="inline-flex items-center justify-center">
                        <GameButton variant="white" class="flex items-center gap-2">
                            <span>Sinta o Impacto</span>
                            <ChevronRight class="h-5 w-5" />
                        </GameButton>
                    </a>
                </div>
            </div>
        </section>

        <!-- Seção da Pesquisa -->
        <section class="w-full py-16 bg-gray-50 dark:bg-gray-950" id="research">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl font-bold heading-font mb-4 text-gray-800 dark:text-white">
                        Dados que <span class="text-green-600 dark:text-green-400">comprovam</span> a eficácia
                    </h2>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                        <!-- Lado esquerdo: Estatística -->
                        <div class="text-center lg:text-left">
                            <div class="mb-6">
                                <div class="text-6xl sm:text-7xl font-bold text-green-600 dark:text-green-400 mb-2">120</div>
                                <div class="text-xl text-gray-600 dark:text-gray-300">de 194 juízes aprovados</div>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                                Estudaram legislação <span class="text-green-600 dark:text-green-400">todo dia</span>
                            </h3>
                            <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-6">
                                Pesquisa realizada pela <strong>Magistratura Estadual em Foco</strong> 
                                comprova que o estudo diário e consistente da legislação é o método 
                                mais eficaz para aprovação em concursos jurídicos.
                            </p>
                            <a 
                                href="https://magistraturaestadualemfoco.com/pesquisa" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 font-medium transition-colors"
                            >
                                <span>Consultar pesquisa completa</span>
                                <ChevronRight class="h-4 w-4" />
                            </a>
                        </div>
                        
                        <!-- Lado direito: Gráfico visual -->
                        <div class="flex justify-center">
                            <div class="w-64 h-64 relative">
                                <!-- Círculo de fundo -->
                                <div class="w-full h-full bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                    <!-- Círculo de progresso -->
                                    <div class="w-48 h-48 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center relative">
                                        <div class="text-center">
                                            <div class="text-3xl font-bold text-green-700 dark:text-green-400 mb-1">61.9%</div>
                                            <div class="text-sm text-green-600 dark:text-green-300 font-medium">Taxa de aprovação</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">com estudo diário</div>
                                        </div>
                                        <!-- SVG para progresso preciso -->
                                        <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 200 200">
                                            <!-- Círculo de fundo -->
                                            <circle
                                                cx="100"
                                                cy="100"
                                                r="90"
                                                fill="none"
                                                stroke="#e5e7eb"
                                                class="dark:stroke-gray-600"
                                                stroke-width="8"
                                            />
                                            <!-- Círculo de progresso (61.9% = 222.84 de 360 graus) -->
                                            <circle
                                                cx="100"
                                                cy="100"
                                                r="90"
                                                fill="none"
                                                stroke="#10b981"
                                                class="dark:stroke-green-400"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                stroke-dasharray="565.48"
                                                stroke-dashoffset="215.24"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botão para próxima seção -->
                <div class="text-center mt-12">
                    <a href="#faq" class="inline-flex items-center justify-center">
                        <GameButton variant="white" class="flex items-center gap-2">
                            <span>Dúvidas Frequentes</span>
                            <ChevronRight class="h-5 w-5" />
                        </GameButton>
                    </a>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="w-full py-16 bg-white dark:bg-gray-900" id="faq">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl font-bold heading-font mb-4 text-gray-800 dark:text-white">
                        Perguntas <span class="text-green-600 dark:text-green-400">Frequentes</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300">
                        Tire suas dúvidas sobre a plataforma
                    </p>
                </div>
                
                <div class="space-y-4">
                    <!-- FAQ 1 -->
                    <details class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 dark:text-white hover:text-green-600 dark:hover:text-green-400 transition-colors">
                            Como funciona o sistema de gamificação?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600 dark:text-gray-300">
                            <p>Você ganha XP (experiência) a cada resposta correta, sobe de nível conforme progride e pode acompanhar seu progresso através de mapas visuais. O sistema é similar ao Duolingo, mas focado na legislação brasileira.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 2 -->
                    <details class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 dark:text-white hover:text-green-600 dark:hover:text-green-400 transition-colors">
                            Quais leis estão disponíveis na plataforma?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600 dark:text-gray-300">
                            <p>Atualmente oferecemos estudo da Constituição Federal, Código Civil, Código Penal e outras legislações importantes do direito brasileiro. Nosso conteúdo é constantemente atualizado.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 3 -->
                    <details class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 dark:text-white hover:text-green-600 dark:hover:text-green-400 transition-colors">
                            É realmente gratuito?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600 dark:text-gray-300">
                            <p>Sim! Você pode começar a estudar gratuitamente. Temos planos premium com recursos adicionais, mas o acesso básico à plataforma é sempre gratuito.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 4 -->
                    <details class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 dark:text-white hover:text-green-600 dark:hover:text-green-400 transition-colors">
                            Quanto tempo devo estudar por dia?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600 dark:text-gray-300">
                            <p>Recomendamos sessões curtas e diárias, entre 15-30 minutos. A consistência é mais importante que a duração. Nossa pesquisa mostra que o estudo diário é fundamental para aprovação em concursos.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 5 -->
                    <details class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 dark:text-white hover:text-green-600 dark:hover:text-green-400 transition-colors">
                            Posso usar no celular?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600 dark:text-gray-300">
                            <p>Sim! Nossa plataforma é totalmente responsiva e funciona perfeitamente em celulares, tablets e computadores. Você pode estudar onde e quando quiser.</p>
                        </div>
                    </details>
                </div>
                
                <!-- Botão para próxima seção -->
                <div class="text-center mt-12">
                    <a href="#cta" class="inline-flex items-center justify-center">
                        <GameButton variant="blue" class="flex items-center gap-2">
                            <span>Começar Agora</span>
                            <ChevronRight class="h-5 w-5" />
                        </GameButton>
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA Principal -->
        <section class="w-full py-16 bg-gray-800 dark:bg-gray-950 text-white" id="cta">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <h2 class="text-4xl sm:text-6xl font-bold heading-font mb-6">
                    Comece a estudar <span class="text-green-400 dark:text-green-300">hoje mesmo!</span>
                </h2>
                <p class="text-xl mb-8 leading-relaxed opacity-90">
                    Junte-se aos estudantes que já descobriram uma nova forma 
                    de aprender legislação brasileira.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                    <Link
                        v-if="!page.props.auth.user"
                        :href="route('play.map')"
                        class="flex items-center justify-center"
                    >
                        <GameButton variant="green" size="lg" class="px-10 py-4 text-xl flex items-center gap-3">
                            <Target class="h-6 w-6" />
                            Começar gratuitamente
                        </GameButton>
                    </Link>
                    <Link
                        v-else
                        :href="route('play.map')"
                        class="flex items-center justify-center"
                    >
                        <GameButton variant="green" size="lg" class="px-10 py-4 text-xl flex items-center gap-3">
                            <Target class="h-6 w-6" />
                            Continuar estudando
                        </GameButton>
                    </Link>
                </div>
                
                <div class="flex flex-wrap gap-8 justify-center text-sm opacity-80">
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
        <footer class="w-full py-12 bg-gray-900 dark:bg-black text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-8">
                    <!-- Logo e descrição -->
                    <div class="text-center md:text-left">
                        <div class="flex items-center gap-2 mb-4 justify-center md:justify-start">
                            <AppLogo theme="dark" height="80px" />
                        </div>
                    </div>
                    
                    <!-- Leis principais -->
                    <div class="text-center md:text-left">
                        <h4 class="font-semibold text-white mb-4">Leis Principais</h4>
                        <div class="flex flex-col gap-2 text-sm text-gray-400">
                            <Link
                                v-for="law in firstFourLaws"
                                :key="law.uuid"
                                :href="getLawUrl(law)"
                                class="hover:text-white transition-colors"
                            >
                                {{ law.name }}
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Links legais -->
                    <div class="text-center md:text-left">
                        <h4 class="font-semibold text-white mb-4">Links</h4>
                        <div class="flex flex-col gap-2 text-sm text-gray-400">
                            <Link :href="route('public.laws')" class="hover:text-white transition-colors">Todas as Leis</Link>
                            <Link :href="route('privacy-policy')" class="hover:text-white transition-colors">Políticas de Privacidade</Link>
                            <Link :href="route('cookies')" class="hover:text-white transition-colors">Cookies</Link>
                            <Link :href="route('terms')" class="hover:text-white transition-colors">Termos de Uso</Link>
                        </div>
                    </div>
                    
                    <!-- Redes sociais -->
                    <div class="text-center md:text-left">
                        <h4 class="font-semibold text-white mb-4">Redes Sociais</h4>
                        <div class="flex gap-4 justify-center md:justify-start">
                            <a href="#" class="text-gray-400 hover:text-white dark:text-gray-500 dark:hover:text-gray-300 transition-colors" aria-label="Facebook">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white dark:text-gray-500 dark:hover:text-gray-300 transition-colors" aria-label="Instagram">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 6.621 5.367 11.988 11.988 11.988s11.988-5.367 11.988-11.988C24.005 5.367 18.638.001 12.017.001zM8.449 16.988c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541zm7.508 0c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541z"/>
                                </svg>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white dark:text-gray-500 dark:hover:text-gray-300 transition-colors" aria-label="LinkedIn">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-gray-800 dark:border-gray-800 text-center">
                    <p class="text-gray-400 dark:text-gray-500">© 2025 Memorize Direito. Todos os direitos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
