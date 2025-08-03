<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Book, PlayCircle, Menu, X, ChevronRight, Target, CheckCircle, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';

const mobileMenuOpen = ref(false);
const page = usePage<{
    auth: {
        user: any;
    }
}>();

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMenu = () => {
    mobileMenuOpen.value = false;
};

// Props para receber dados do backend
defineProps<{
    articlesCount: number;
}>();
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
    <div class="flex min-h-screen flex-col items-center bg-white text-gray-800">
        <!-- Header -->
        <header class="w-full bg-white border-b border-gray-200 sticky top-0 z-50">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo />
                    </Link>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-6">
                    <Link href="#features" class="text-gray-600 hover:text-[green]-600 font-medium transition-colors">
                        Como funciona
                    </Link>
                    <Link href="#testimonials" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                        Benefícios
                    </Link>
                    <Link href="#research" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                        Pesquisa
                    </Link>
                    <Link href="#faq" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                        FAQ
                    </Link>
                    
                    <div class="h-5 w-px bg-gray-300"></div>
                    
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('play.map')"
                        class="game-button px-6 py-2 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold hover:transform hover:translate-y-1 hover:shadow-[0_2px_0_theme(colors.green.700)] transition-all"
                    >
                        Continuar Estudando
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-gray-600 hover:text-green-600 font-medium transition-colors"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="game-button px-6 py-2 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold hover:transform hover:translate-y-1 hover:shadow-[0_2px_0_theme(colors.green.700)] transition-all"
                        >
                            Começar Grátis
                        </Link>
                    </template>
                </nav>
                
                <!-- Mobile Menu Button -->
                <button 
                    @click="toggleMobileMenu" 
                    class="md:hidden flex items-center justify-center h-10 w-10 rounded-lg hover:bg-gray-100 transition-colors"
                    aria-label="Menu"
                >
                    <Menu v-if="!mobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <div 
                v-if="mobileMenuOpen" 
                class="md:hidden absolute top-16 inset-x-0 bg-white border-b border-gray-200 py-4 px-4 z-50"
            >
                <nav class="flex flex-col gap-3">
                    <Link 
                        href="#features" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Como funciona</span>
                        <ChevronRight class="h-5 w-5 text-gray-500" />
                    </Link>
                    <Link 
                        href="#testimonials" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Benefícios</span>
                        <ChevronRight class="h-5 w-5 text-gray-500" />
                    </Link>
                    <Link 
                        href="#research" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Pesquisa</span>
                        <ChevronRight class="h-5 w-5 text-gray-500" />
                    </Link>
                    <Link 
                        href="#faq" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                        @click="closeMenu"
                    >
                        <span class="font-medium">FAQ</span>
                        <ChevronRight class="h-5 w-5 text-gray-500" />
                    </Link>
                    
                    <div class="h-px w-full bg-gray-200 my-2"></div>
                    
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('play.map')"
                        class="game-button px-4 py-3 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold text-center"
                        @click="closeMenu"
                    >
                        Continuar Estudando
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 text-center text-gray-600 font-medium"
                            @click="closeMenu"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="game-button px-4 py-3 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_4px_0_theme(colors.green.700)] font-bold text-center"
                            @click="closeMenu"
                        >
                            Começar Grátis
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="w-full py-16 md:py-24" id="hero">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-16">
                    <!-- Conteúdo Principal -->
                    <div class="w-full lg:w-1/2 text-center lg:text-left">
                        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold heading-font mb-6 text-gray-800">
                            O <span style="color: rgb(246, 196, 2)">Duolingo</span> para <span class="text-gray-700">Direito</span>
                        </h1>
                        
                        <p class="text-xl text-gray-600 mb-8 leading-relaxed max-w-2xl">
                            Aprenda legislação brasileira de forma divertida e eficiente. 
                            Gamificação que transforma o estudo jurídico em uma jornada envolvente.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 mb-12">
                            <Link
                                v-if="!page.props.auth.user"
                                :href="route('register')"
                                class="w-full sm:w-auto px-6 py-2 text-white rounded-lg border-4 font-bold text-xl hover:transform hover:translate-y-1 transition-all flex items-center justify-center gap-2"
                                style="background-color: rgb(246, 196, 2); border-color: rgb(180, 140, 0); box-shadow: 0 6px 0 rgb(180, 140, 0);"
                                onmouseover="this.style.boxShadow = '0 4px 0 rgb(180, 140, 0)'"
                                onmouseout="this.style.boxShadow = '0 6px 0 rgb(180, 140, 0)'"
                            >
                                <PlayCircle class="h-6 w-6" />
                                Começar gratuitamente
                            </Link>
                            <Link
                                v-else
                                :href="route('play.map')"
                                class="w-full sm:w-auto px-6 py-2 text-white rounded-lg border-4 font-bold text-xl hover:transform hover:translate-y-1 transition-all flex items-center justify-center gap-2"
                                style="background-color: rgb(246, 196, 2); border-color: rgb(180, 140, 0); box-shadow: 0 6px 0 rgb(180, 140, 0);"
                                onmouseover="this.style.boxShadow = '0 4px 0 rgb(180, 140, 0)'"
                                onmouseout="this.style.boxShadow = '0 6px 0 rgb(180, 140, 0)'"
                            >
                                <PlayCircle class="h-6 w-6" />
                                Continuar Estudando
                            </Link>
                        </div>
                        
                        <!-- Stats -->
                        <div class="flex flex-wrap gap-8 justify-center lg:justify-start text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <Book class="h-5 w-5 text-gray-500" />
                                <span>{{ articlesCount }}+ artigos disponíveis</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Trophy class="h-5 w-5 text-gray-500" />
                                <span>Sistema de XP e níveis</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ilustração Principal -->
                    <div class="w-full lg:w-1/2 relative">
                        <div class="bg-white rounded-3xl p-8 border-4 border-gray-200">
                            <!-- Personagem ilustrativo -->
                            <div class="text-center mb-6">
                                <div class="w-32 h-32 mx-auto bg-gray-200 rounded-full flex items-center justify-center text-6xl mb-4">
                                    ⚖️
                                </div>
                                <h3 class="text-xl font-bold text-gray-700 mb-2">Estudante de Direito</h3>
                                <p class="text-gray-600">Level 12 • 1.247 XP</p>
                            </div>
                            
                            <!-- Progress bars -->
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Constituição Federal</span>
                                        <span class="text-sm text-gray-600 font-bold">85%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-gray-600 h-3 rounded-full" style="width: 85%"></div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Código Civil</span>
                                        <span class="text-sm text-gray-600 font-bold">42%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-gray-600 h-3 rounded-full" style="width: 42%"></div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Código Penal</span>
                                        <span class="text-sm text-gray-600 font-bold">67%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-gray-600 h-3 rounded-full" style="width: 67%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção de Recursos -->
        <section class="w-full py-16 bg-gray-50" id="features">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl sm:text-5xl font-bold heading-font mb-6 text-gray-800">
                        Como o <span class="text-green-600">Memorize Direito</span> funciona?
                    </h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Uma plataforma simples e eficiente para aprender legislação brasileira 
                        através de exercícios gamificados.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Recurso 1 -->
                    <div class="bg-white rounded-2xl p-8 text-center border border-gray-200">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            📚
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Estude por Artigos</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Aprenda cada artigo da legislação brasileira de forma estruturada e progressiva.
                        </p>
                    </div>
                    
                    <!-- Recurso 2 -->
                    <div class="bg-white rounded-2xl p-8 text-center border border-gray-200">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🎮
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Sistema de XP</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Ganhe experiência (XP) a cada resposta correta e suba de nível conforme progride.
                        </p>
                    </div>
                    
                    <!-- Recurso 3 -->
                    <div class="bg-white rounded-2xl p-8 text-center border border-gray-200">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🏆
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Progresso Visual</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Acompanhe seu aprendizado com mapas de progresso e estatísticas detalhadas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção de Depoimentos -->
        <section class="w-full py-16 bg-white" id="testimonials">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center mb-16">
                    <h2 class="text-4xl sm:text-5xl font-bold heading-font mb-6 text-gray-800">
                        Aprenda Direito de forma <span class="text-gray-700">eficiente</span>
                    </h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Transforme o estudo da legislação brasileira em uma jornada engajante 
                        com nossa metodologia gamificada.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Benefício 1 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            ⚡
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Aprendizado Rápido</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Sessões curtas e focadas que se encaixam na sua rotina. 
                            Aprenda um pouco todos os dias.
                        </p>
                    </div>
                    
                    <!-- Benefício 2 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            🎯
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Foco na Prática</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Exercícios práticos baseados em situações reais do direito brasileiro. 
                            Aprenda aplicando o conhecimento.
                        </p>
                    </div>
                    
                    <!-- Benefício 3 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto flex items-center justify-center text-4xl mb-6">
                            📈
                        </div>
                        <h3 class="text-2xl font-bold heading-font mb-4 text-gray-800">Progresso Contínuo</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Acompanhe sua evolução com métricas claras e motivadoras. 
                            Celebre cada conquista.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seção da Pesquisa -->
        <section class="w-full py-16 bg-gray-50" id="research">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl font-bold heading-font mb-4 text-gray-800">
                        Dados que <span class="text-green-600">comprovam</span> a eficácia
                    </h2>
                </div>
                
                <div class="bg-white rounded-2xl p-8 border border-gray-200">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                        <!-- Lado esquerdo: Estatística -->
                        <div class="text-center lg:text-left">
                            <div class="mb-6">
                                <div class="text-6xl sm:text-7xl font-bold text-green-600 mb-2">120</div>
                                <div class="text-xl text-gray-600">de 194 juízes aprovados</div>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                                Estudaram legislação <span class="text-green-600">todo dia</span>
                            </h3>
                            <p class="text-gray-600 leading-relaxed mb-6">
                                Pesquisa realizada pela <strong>Magistratura Estadual em Foco</strong> 
                                comprova que o estudo diário e consistente da legislação é o método 
                                mais eficaz para aprovação em concursos jurídicos.
                            </p>
                            <a 
                                href="https://magistraturaestadualemfoco.com/pesquisa" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 font-medium transition-colors"
                            >
                                <span>Consultar pesquisa completa</span>
                                <ChevronRight class="h-4 w-4" />
                            </a>
                        </div>
                        
                        <!-- Lado direito: Gráfico visual -->
                        <div class="flex justify-center">
                            <div class="w-64 h-64 relative">
                                <!-- Círculo de fundo -->
                                <div class="w-full h-full bg-gray-200 rounded-full flex items-center justify-center">
                                    <!-- Círculo de progresso -->
                                    <div class="w-48 h-48 bg-green-100 rounded-full flex items-center justify-center relative">
                                        <div class="text-center">
                                            <div class="text-3xl font-bold text-green-700 mb-1">61.9%</div>
                                            <div class="text-sm text-green-600 font-medium">Taxa de aprovação</div>
                                            <div class="text-xs text-gray-500 mt-1">com estudo diário</div>
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
                                                stroke-width="8"
                                            />
                                            <!-- Círculo de progresso (61.9% = 222.84 de 360 graus) -->
                                            <circle
                                                cx="100"
                                                cy="100"
                                                r="90"
                                                fill="none"
                                                stroke="#10b981"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                stroke-dasharray="565.48"
                                                stroke-dashoffset="215.24"
                                                class="transition-all duration-1000 ease-out"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="w-full py-16 bg-white" id="faq">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl font-bold heading-font mb-4 text-gray-800">
                        Perguntas <span class="text-green-600">Frequentes</span>
                    </h2>
                    <p class="text-xl text-gray-600">
                        Tire suas dúvidas sobre a plataforma
                    </p>
                </div>
                
                <div class="space-y-4">
                    <!-- FAQ 1 -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 hover:text-green-600 transition-colors">
                            Como funciona o sistema de gamificação?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Você ganha XP (experiência) a cada resposta correta, sobe de nível conforme progride e pode acompanhar seu progresso através de mapas visuais. O sistema é similar ao Duolingo, mas focado na legislação brasileira.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 2 -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 hover:text-green-600 transition-colors">
                            Quais leis estão disponíveis na plataforma?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Atualmente oferecemos estudo da Constituição Federal, Código Civil, Código Penal e outras legislações importantes do direito brasileiro. Nosso conteúdo é constantemente atualizado.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 3 -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 hover:text-green-600 transition-colors">
                            É realmente gratuito?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Sim! Você pode começar a estudar gratuitamente. Temos planos premium com recursos adicionais, mas o acesso básico à plataforma é sempre gratuito.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 4 -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 hover:text-green-600 transition-colors">
                            Quanto tempo devo estudar por dia?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Recomendamos sessões curtas e diárias, entre 15-30 minutos. A consistência é mais importante que a duração. Nossa pesquisa mostra que o estudo diário é fundamental para aprovação em concursos.</p>
                        </div>
                    </details>
                    
                    <!-- FAQ 5 -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="p-6 cursor-pointer font-medium text-gray-800 hover:text-green-600 transition-colors">
                            Posso usar no celular?
                        </summary>
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Sim! Nossa plataforma é totalmente responsiva e funciona perfeitamente em celulares, tablets e computadores. Você pode estudar onde e quando quiser.</p>
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <!-- CTA Principal -->
        <section class="w-full py-16 bg-gray-800 text-white">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center">
                <h2 class="text-4xl sm:text-6xl font-bold heading-font mb-6">
                    Comece a estudar <span class="text-green-400">hoje mesmo!</span>
                </h2>
                <p class="text-xl mb-8 leading-relaxed opacity-90">
                    Junte-se aos estudantes que já descobriram uma nova forma 
                    de aprender legislação brasileira.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                    <Link
                        v-if="!page.props.auth.user"
                        :href="route('register')"
                        class="game-button px-10 py-4 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_6px_0_theme(colors.green.700)] font-bold text-xl hover:transform hover:translate-y-1 hover:shadow-[0_4px_0_theme(colors.green.700)] transition-all flex items-center justify-center gap-3"
                    >
                        <Target class="h-6 w-6" />
                        Começar gratuitamente
                    </Link>
                    <Link
                        v-else
                        :href="route('play.map')"
                        class="game-button px-10 py-4 bg-green-500 text-white rounded-lg border-4 border-green-700 shadow-[0_6px_0_theme(colors.green.700)] font-bold text-xl hover:transform hover:translate-y-1 hover:shadow-[0_4px_0_theme(colors.green.700)] transition-all flex items-center justify-center gap-3"
                    >
                        <Target class="h-6 w-6" />
                        Continuar estudando
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
        <footer class="w-full py-12 bg-gray-900 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                    <!-- Logo e descrição -->
                    <div class="text-center md:text-left">
                        <div class="flex items-center gap-2 mb-4 justify-center md:justify-start">
                            <AppLogo />
                        </div>
                    </div>
                    
                    <!-- Links legais -->
                    <div class="flex flex-col sm:flex-row gap-4 text-sm text-gray-400">
                        <Link href="#" class="hover:text-white transition-colors">Políticas de Privacidade</Link>
                        <Link href="#" class="hover:text-white transition-colors">Cookies</Link>
                        <Link href="#" class="hover:text-white transition-colors">Termos de Uso</Link>
                    </div>
                    
                    <!-- Redes sociais -->
                    <div class="flex gap-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors" aria-label="Facebook">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors" aria-label="Instagram">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 6.621 5.367 11.988 11.988 11.988s11.988-5.367 11.988-11.988C24.005 5.367 18.638.001 12.017.001zM8.449 16.988c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541zm7.508 0c-2.508 0-4.541-2.033-4.541-4.541s2.033-4.541 4.541-4.541 4.541 2.033 4.541 4.541-2.033 4.541-4.541 4.541z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors" aria-label="LinkedIn">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p class="text-gray-400">© 2025 Memorize Direito. Todos os direitos reservados.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
