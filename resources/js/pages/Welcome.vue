<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Book, Award, Brain, GraduationCap, Scale, Target, Users, PlayCircle, 
         Gavel, BookOpen, CheckCircle2, Menu, X, ChevronRight, Bookmark, 
         BarChart2, Star, Shield } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';


const mobileMenuOpen = ref(false);

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>
    <Head title="Memorize Direito - Aprenda legislação de forma divertida">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-white">
        <!-- Header - Reformulado -->
        <header class="w-full border-b border-[#19140035] dark:border-[#3E3E3A] sticky top-0 bg-[#FDFDFC]/95 dark:bg-[#0a0a0a]/95 backdrop-blur-sm z-50">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo />
                    </Link>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-6">
                    <Link href="#recursos" class="text-[#706f6c] hover:text-[#f6c402] font-medium transition-colors">
                        Recursos
                    </Link>
                    <Link href="#como-funciona" class="text-[#706f6c] hover:text-[#f6c402] font-medium transition-colors">
                        Como funciona
                    </Link>
                    
                    <div class="h-5 w-px bg-[#19140035] dark:bg-[#3E3E3A]"></div>
                    
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('play.map')"
                        class="rounded-full bg-[#f6c402] px-5 py-2 text-base font-medium text-white transition hover:bg-[#3a6903]"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-full px-5 py-2 text-base font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15] border border-[#19140035] dark:border-[#ffffff35] "
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-full bg-[#f6c402] px-5 py-2 text-base font-medium text-black transition hover:bg-[#c39b01]"
                        >
                            Começar
                        </Link>
                    </template>
                </nav>
                
                <!-- Mobile Menu Button -->
                <button 
                    @click="toggleMobileMenu" 
                    class="md:hidden flex items-center justify-center h-10 w-10 rounded-full hover:bg-[#19140015] dark:hover:bg-[#ffffff15] transition-colors"
                    aria-label="Menu"
                >
                    <Menu v-if="!mobileMenuOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <div 
                v-if="mobileMenuOpen" 
                class="md:hidden absolute top-16 inset-x-0 bg-[#FDFDFC] dark:bg-[#0a0a0a] border-b border-[#19140035] dark:border-[#3E3E3A] py-4 px-4"
            >
                <nav class="flex flex-col gap-3">
                    <Link 
                        href="#recursos" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Recursos</span>
                        <ChevronRight class="h-5 w-5 text-[#706f6c]" />
                    </Link>
                    <Link 
                        href="#como-funciona" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Como funciona</span>
                        <ChevronRight class="h-5 w-5 text-[#706f6c]" />
                    </Link>
                    <Link 
                        href="#precos" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Preços</span>
                        <ChevronRight class="h-5 w-5 text-[#706f6c]" />
                    </Link>
                    
                    <div class="h-px w-full bg-[#19140035] dark:bg-[#3E3E3A] my-2"></div>
                    
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('play.map')"
                        class="w-full rounded-lg bg-[#f6c402] px-4 py-3 text-center font-medium text-white transition hover:bg-[#3a6903]"
                        @click="closeMenu"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="w-full rounded-lg px-4 py-3 text-center font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                            @click="closeMenu"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="w-full rounded-lg bg-[#f6c402] px-4 py-3 text-center font-medium text-white transition hover:bg-[#3a6903]"
                            @click="closeMenu"
                        >
                            Começar
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section - Reformulada -->
        <section class="w-full pt-10 sm:pt-16 relative overflow-hidden " id="hero">
            <!-- Elementos decorativos de fundo -->
            <div class="absolute top-1/4 left-1/5 w-64 h-64 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-1/3 right-1/4 w-80 h-80 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 sm:px-6 relative z-10">
                <div class="flex flex-col lg:flex-row items-center gap-8 lg:gap-12">
                    <div class="flex flex-1 flex-col justify-center">
                        <div class="mb-6 inline-flex items-center rounded-full border border-[#19140035] bg-[#19140015] px-3 py-1">
                            <span class="text-sm font-medium">Novidade: Módulos de Direito Constitucional</span>
                            <ChevronRight class="ml-1 h-4 w-4" />
                        </div>
                        
                        <h1 class="mb-6 text-4xl sm:text-5xl font-extrabold leading-tight lg:text-6xl">
                            Aprenda legislação de forma <span class="text-[#f6c402] relative">
                                divertida
                                <svg class="absolute bottom-1 left-0 w-full" viewBox="0 0 300 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 10C50.6667 4 99.3333 1 148 1C196.667 1 245.333 4 294 10" stroke="#f6c402" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </h1>
                        
                        <p class="mb-8 text-lg sm:text-xl text-[#706f6c] dark:text-[#A1A09A] max-w-2xl">
                            O Memorize Direito transforma o estudo de legislação em uma experiência gamificada com desafios progressivos, recompensas e estatísticas de aprendizado.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-[#f6c402] px-6 sm:px-8 py-3.5 text-lg font-medium text-black transition hover:bg-[#c39b01] text-center flex items-center justify-center gap-2 shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
                            >
                                <PlayCircle class="h-5 w-5" /> Começar gratuitamente
                            </Link>
                            <Link
                                :href="route('login')"
                                class="rounded-xl border border-[#19140035] dark:border-[#ffffff35] px-6 sm:px-8 py-3.5 text-lg font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15] text-center"
                            >
                                Já tenho conta
                            </Link>
                        </div>
                        
                        <div class="mt-8 flex items-center gap-6">
                            <div class="flex items-center gap-2">
                                <div class="flex -space-x-2">
                                    <img class="h-8 w-8 rounded-full border-2 border-white" src="https://i.pravatar.cc/100?img=1" alt="Usuário" />
                                    <img class="h-8 w-8 rounded-full border-2 border-white" src="https://i.pravatar.cc/100?img=2" alt="Usuário" />
                                    <img class="h-8 w-8 rounded-full border-2 border-white" src="https://i.pravatar.cc/100?img=3" alt="Usuário" />
                                </div>
                                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">+3.500 estudantes</span>
                            </div>
                            
                            <div class="flex items-center gap-1">
                                <div class="flex">
                                    <Star class="h-5 w-5 fill-[#FFD700] text-[#FFD700]" />
                                    <Star class="h-5 w-5 fill-[#FFD700] text-[#FFD700]" />
                                    <Star class="h-5 w-5 fill-[#FFD700] text-[#FFD700]" />
                                    <Star class="h-5 w-5 fill-[#FFD700] text-[#FFD700]" />
                                    <Star class="h-5 w-5 fill-[#FFD700] text-[#FFD700]" />
                                </div>
                                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">4.9/5</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hidden md:flex flex-1 items-center justify-center mt-8 lg:mt-0">
                        <!-- App Mockup melhorado - escondido em telas mobile -->
                        <div class="relative w-[90%] sm:w-[80%] md:w-[70%] lg:w-full mx-auto max-w-[320px] md:max-w-[380px] overflow-hidden rounded-2xl shadow-2xl border border-[#e0e0e0] dark:border-[#2a2a2a]">
                            <!-- Forçando aspect ratio 1:1 de forma compatível com mobile -->
                            <div class="pb-[100%] relative">
                                <div class="absolute inset-0 flex flex-col items-center bg-[#fff] dark:bg-[#2a2a2a]">
                                    <!-- Barra superior do app -->
                                    <div class="absolute top-0 left-0 w-full h-8 bg-[#333333] flex items-center px-3">
                                        <div class="w-3 h-3 bg-[#ff605c] rounded-full mr-1.5 border border-[#e1483f]"></div>
                                        <div class="w-3 h-3 bg-[#ffbd44] rounded-full mr-1.5 border border-[#e0a63d]"></div>
                                        <div class="w-3 h-3 bg-[#00ca4e] rounded-full border border-[#14ae46]"></div>
                                        <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center">
                                            <span class="text-xs text-white font-medium">Memorize Direito</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Conteúdo principal -->
                                    <div class="text-[#333333] dark:text-white flex flex-col items-center w-full p-4 sm:p-6 mt-10">
                                        <div class="flex items-center gap-2">
                                            <div class="text-lg sm:text-xl font-bold">Memorize Direito</div>
                                        </div>
                                        <div class="text-xs sm:text-sm opacity-80 mt-1 mb-4 sm:mb-6">Nível 4 - Constituição Federal</div>

                                        <div class="bg-[#f6c402] text-black rounded-xl px-3 sm:px-5 py-2 sm:py-3 font-medium text-sm sm:text-base shadow-[inset_0_-4px_0_rgba(0,0,0,0.2)] w-full text-center mb-3 sm:mb-5">
                                            Complete o artigo:
                                        </div>

                                        <div class="bg-[#f0f0f0] dark:bg-[#3a3a3a] rounded-xl p-3 sm:p-4 w-full text-center border border-[#e0e0e0] dark:border-[#4a4a4a] text-sm sm:text-base mb-3 sm:mb-5">
                                            Art. 5º Todos são _____ perante a lei, sem distinção de qualquer natureza...
                                        </div>

                                        <div class="grid grid-cols-3 gap-2 sm:gap-3 w-full">
                                            <div class="bg-white text-[#333333] rounded-xl p-2 sm:p-3 text-xs sm:text-sm md:text-base font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1)] hover:bg-[#f5f5f5] transition-colors cursor-pointer text-center">diferentes</div>
                                            <div class="bg-white text-[#333333] rounded-xl p-2 sm:p-3 text-xs sm:text-sm md:text-base font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1)] hover:bg-[#f5f5f5] transition-colors cursor-pointer text-center">iguais</div>
                                            <div class="bg-white text-[#333333] rounded-xl p-2 sm:p-3 text-xs sm:text-sm md:text-base font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1)] hover:bg-[#f5f5f5] transition-colors cursor-pointer text-center">livres</div>
                                        </div>
                                        
                                        <div class="mt-4 sm:mt-6 w-full flex items-center justify-between">
                                            <div class="flex items-center gap-1.5">
                                                <Shield class="h-4 w-4 sm:h-5 sm:w-5 text-[#f6c402]" />
                                                <span class="text-xs sm:text-sm font-medium">3 vidas</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <BarChart2 class="h-4 w-4 sm:h-5 sm:w-5 text-[#f6c402]" />
                                                <span class="text-xs sm:text-sm font-medium">Nível 4/10</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Entre a seção Hero e a seção Recursos - Degradê de transição -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para o modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#FDFDFC] to-[#f5f5f3] dark:opacity-0"></div>
            
            <!-- Degradê para o modo escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#0a0a0a] to-[#121212] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- Seção de Recursos -->
        <section class="w-full bg-[#f5f5f3] dark:bg-[#121212] relative overflow-hidden" id="recursos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 relative z-10 ">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-6">Recursos <span class="text-[#f6c402]">poderosos</span></h2>
                    <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-2xl mx-auto">
                        O Memorize Direito combina técnicas de aprendizado eficientes com gamificação para tornar o estudo de legislação mais eficaz e envolvente.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Cartão 1 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <Book class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Biblioteca Jurídica</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Acesse códigos, leis e súmulas organizados por áreas do direito, com atualizações frequentes.
                        </p>
                    </div>
                    
                    <!-- Cartão 2 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <Brain class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Repetição Espaçada</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Sistema inteligente que programa revisões estratégicas para maximizar a retenção de longo prazo.
                        </p>
                    </div>
                    
                    <!-- Cartão 3 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <Award class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Conquistas e Recompensas</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Ganhe medalhas, desbloqueie níveis e acompanhe seu progresso para manter a motivação.
                        </p>
                    </div>
                    
                    <!-- Cartão 4 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <GraduationCap class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Trilhas de Aprendizado</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Percursos personalizados que orientam seu estudo progressivamente, do básico ao avançado.
                        </p>
                    </div>
                    
                    <!-- Cartão 5 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <Target class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Análise de Desempenho</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Estatísticas detalhadas que identificam seus pontos fortes e áreas que precisam de mais atenção.
                        </p>
                    </div>
                    
                    <!-- Cartão 6 -->
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] transition-transform hover:translate-y-[-4px]">
                        <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-[#f6c40220] text-[#f6c402] mb-4">
                            <Users class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold mb-3">Modo Competitivo</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Desafie amigos ou participe de torneios para testar seus conhecimentos em tempo real.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Entre a seção Recursos e a seção Como Funciona - Degradê de transição -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#f5f5f3] to-[#FDFDFC] dark:opacity-0"></div>
            
            <!-- Degradê para modo escuro - do cinza escuro para o mais escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#121212] to-[#0a0a0a] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- Como Funciona -->
        <section class="w-full relative overflow-hidden w-full py-16 sm:py-24 relative overflow-hidden border-b border-[#e0e0e0] dark:border-[#2a2a2a]" id="como-funciona">
            <div class="absolute top-20 left-10 w-40 h-40 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-60 h-60 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 sm:px-6 relative z-10">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-6">Como <span class="text-[#f6c402]">funciona</span></h2>
                    <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-2xl mx-auto">
                        Três passos simples para transformar seu aprendizado jurídico
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Passo 1 -->
                    <div class="flex flex-col items-center text-center">
                        <div class="h-16 w-16 flex items-center justify-center rounded-full bg-[#f6c402] text-black text-2xl font-bold mb-6">1</div>
                        <h3 class="text-xl font-bold mb-3">Escolha seu tema</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Selecione entre diversos temas jurídicos, como Direito Constitucional, Civil, Penal, Administrativo e muito mais.
                        </p>
                    </div>
                    
                    <!-- Passo 2 -->
                    <div class="flex flex-col items-center text-center">
                        <div class="h-16 w-16 flex items-center justify-center rounded-full bg-[#f6c402] text-black text-2xl font-bold mb-6">2</div>
                        <h3 class="text-xl font-bold mb-3">Jogue e aprenda</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Responda perguntas, complete artigos e teste seus conhecimentos em desafios interativos que tornam o aprendizado divertido.
                        </p>
                    </div>
                    
                    <!-- Passo 3 -->
                    <div class="flex flex-col items-center text-center">
                        <div class="h-16 w-16 flex items-center justify-center rounded-full bg-[#f6c402] text-black text-2xl font-bold mb-6">3</div>
                        <h3 class="text-xl font-bold mb-3">Acompanhe seu progresso</h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Visualize estatísticas detalhadas, desbloqueie conquistas e suba de nível à medida que domina o conteúdo.
                        </p>
                    </div>
                </div>
                
            </div>
        </section>

        <!-- Preços -->
        <!-- <section class="w-full py-16 sm:py-24 bg-[#f5f5f3] dark:bg-[#121212] relative overflow-hidden" id="precos">
            <div class="absolute top-1/4 right-1/5 w-64 h-64 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-1/3 left-1/4 w-80 h-80 rounded-full bg-[#f6c402] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 sm:px-6 relative z-10">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-6">Planos <span class="text-[#f6c402]">acessíveis</span></h2>
                    <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-2xl mx-auto">
                        Escolha o plano que melhor se adapta às suas necessidades de estudo
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] flex flex-col">
                        <div class="mb-6">
                            <span class="text-sm font-medium text-[#706f6c] dark:text-[#A1A09A]">PLANO</span>
                            <h3 class="text-2xl font-bold mt-1">Gratuito</h3>
                            <div class="mt-4 flex items-baseline">
                                <span class="text-3xl font-bold">R$ 0</span>
                                <span class="ml-1 text-[#706f6c] dark:text-[#A1A09A]">/mês</span>
                            </div>
                        </div>
                        
                        <ul class="mb-8 space-y-3 flex-grow">
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Acesso a conteúdos básicos</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>5 desafios diários</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Estatísticas básicas</span>
                            </li>
                        </ul>
                        
                        <Link
                            :href="route('register')"
                            class="rounded-xl bg-[#f6c402] px-6 py-3 text-center font-medium text-white transition hover:bg-[#3a6903] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2)]"
                        >
                            Começar gratuitamente
                        </Link>
                    </div>
                    
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-xl border-2 border-[#f6c402] relative flex flex-col transform scale-105">
                        <div class="absolute -top-4 left-0 right-0 flex justify-center">
                            <div class="bg-[#f6c402] text-black text-sm font-medium px-4 py-1 rounded-full">MAIS POPULAR</div>
                        </div>
                        
                        <div class="mb-6">
                            <span class="text-sm font-medium text-[#706f6c] dark:text-[#A1A09A]">PLANO</span>
                            <h3 class="text-2xl font-bold mt-1">Premium</h3>
                            <div class="mt-4 flex items-baseline">
                                <span class="text-3xl font-bold">R$ 19,90</span>
                                <span class="ml-1 text-[#706f6c] dark:text-[#A1A09A]">/mês</span>
                            </div>
                        </div>
                        
                        <ul class="mb-8 space-y-3 flex-grow">
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Acesso a todo conteúdo</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Desafios ilimitados</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Modo competitivo</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Estatísticas avançadas</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Sem anúncios</span>
                            </li>
                        </ul>
                        
                        <Link
                            :href="route('register')"
                            class="rounded-xl bg-[#f6c402] px-6 py-3 text-center font-medium text-black transition hover:bg-[#3a6903] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2)]"
                        >
                            Experimentar 7 dias grátis
                        </Link>
                    </div>
                    
                    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl p-6 shadow-lg border border-[#e0e0e0] dark:border-[#2a2a2a] flex flex-col">
                        <div class="mb-6">
                            <span class="text-sm font-medium text-[#706f6c] dark:text-[#A1A09A]">PLANO</span>
                            <h3 class="text-2xl font-bold mt-1">Anual</h3>
                            <div class="mt-4 flex items-baseline">
                                <span class="text-3xl font-bold">R$ 179,90</span>
                                <span class="ml-1 text-[#706f6c] dark:text-[#A1A09A]">/ano</span>
                            </div>
                            <div class="mt-1 text-sm text-[#f6c402] font-medium">
                                Economize 25%
                            </div>
                        </div>
                        
                        <ul class="mb-8 space-y-3 flex-grow">
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Todos os benefícios do Premium</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Acesso antecipado a novos módulos</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#f6c402] flex-shrink-0 mt-0.5" />
                                <span>Suporte prioritário</span>
                            </li>
                        </ul>
                        
                        <Link
                            :href="route('register')"
                            class="rounded-xl bg-[#f6c402] px-6 py-3 text-center font-medium text-white transition hover:bg-[#3a6903] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2)]"
                        >
                            Assinar com desconto
                        </Link>
                    </div>
                </div>
            </div>
        </section> -->

        <!-- CTA Section -->
        <section class="w-full py-16 sm:py-24 relative overflow-hidden">
            <div class="absolute inset-0 opacity-5">
                <div class="absolute top-0 left-1/4 w-32 h-32 rounded-full bg-[#f6c402] blur-3xl"></div>
                <div class="absolute bottom-0 right-1/3 w-40 h-40 rounded-full bg-[#1cb0f6] blur-3xl"></div>
                <div class="absolute top-1/2 right-1/4 w-24 h-24 rounded-full bg-[#ff9600] blur-3xl"></div>
                <div class="absolute bottom-1/3 left-1/5 w-20 h-20 rounded-full bg-[#ce82ff] blur-3xl"></div>
            </div>
            
            <div class="mx-auto max-w-3xl px-4 sm:px-6 text-center relative z-10">
                <div class="mb-8 flex justify-center">
                    <div class="bg-[#f6c402] rounded-full p-4 text-black">
                        <CheckCircle2 class="h-10 w-10" />
                    </div>
                </div>
                <h2 class="mb-6 text-3xl sm:text-4xl font-bold">Pronto para transformar seus estudos jurídicos?</h2>
                <p class="mb-8 text-lg text-[#706f6c] dark:text-[#A1A09A]">
                    Junte-se a milhares de estudantes que já estão dominando a legislação de forma divertida e eficiente.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('register')"
                        class="rounded-xl bg-[#f6c402] px-8 py-4 text-lg font-medium text-black transition hover:bg-[#c39b01] shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
                    >
                        Começar gratuitamente
                    </Link>
                    <Link
                        href="#recursos"
                        class="rounded-xl border border-[#19140035] dark:border-[#ffffff35] px-8 py-4 text-lg font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                    >
                        Ver recursos
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full border-t border-[#19140035] py-8 dark:border-[#3E3E3A]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Logo e descrição -->
                    <div class="md:col-span-1">
                        <div class="flex items-center gap-x-2 mb-4">
                            <AppLogo />
                        </div>
                        <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mb-4">
                            Transformando o estudo do direito em uma experiência divertida e eficiente.
                        </p>
                        <div class="flex gap-4">
                            <a href="#" class="text-[#706f6c] hover:text-[#f6c402]">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a href="#" class="text-[#706f6c] hover:text-[#f6c402]">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                                </svg>
                            </a>
                            <a href="#" class="text-[#706f6c] hover:text-[#f6c402]">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Links rápidos -->
                    <div>
                        <h3 class="text-sm font-semibold text-[#1b1b18] dark:text-white uppercase tracking-wider mb-4">Plataforma</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Dashboard</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Módulos</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Estatísticas</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Conquistas</a></li>
                        </ul>
                    </div>
                    
                    <!-- Suporte -->
                    <div>
                        <h3 class="text-sm font-semibold text-[#1b1b18] dark:text-white uppercase tracking-wider mb-4">Suporte</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">FAQ</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Contato</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Termos de Uso</a></li>
                            <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Política de Privacidade</a></li>
                        </ul>
                    </div>
                    
                    <!-- Newsletter -->
                    <div>
                        <h3 class="text-sm font-semibold text-[#1b1b18] dark:text-white uppercase tracking-wider mb-4">Newsletter</h3>
                        <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mb-4">Receba novidades e dicas de estudo.</p>
                        <form class="flex flex-col sm:flex-row gap-2">
                            <input 
                                type="email" 
                                placeholder="Seu e-mail" 
                                class="px-3 py-2 bg-white dark:bg-[#1a1a1a] border border-[#e0e0e0] dark:border-[#2a2a2a] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#f6c402] focus:border-transparent"
                            />
                            <button 
                                type="submit" 
                                class="bg-[#f6c402] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#3a6903] transition-colors"
                            >
                                Inscrever
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-[#19140035] dark:border-[#3E3E3A] flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">© 2025 Memorize Direito. Todos os direitos reservados.</p>
                    <div class="mt-4 md:mt-0">
                        <img src="/img/Memorize Direito-brain.PNG" alt="Memorize Direito" class="h-6 w-auto" />
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>