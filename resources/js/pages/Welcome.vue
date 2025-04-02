<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Book, Award, Brain, PlayCircle, 
         Menu, X, ChevronRight, Star } from 'lucide-vue-next';
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

<style>
.heading-font {
    font-family: 'Poppins', sans-serif;
}
</style>

<template>
    <Head title="Memorize Direito - Aprenda legislação de forma divertida">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-white">
        <!-- Header - Simplificado -->
        <header class="w-full border-b border-[#19140035] dark:border-[#3E3E3A] sticky top-0 bg-[#FDFDFC]/95 dark:bg-[#0a0a0a]/95 backdrop-blur-sm z-50">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <Link :href="route('home')" class="flex items-center gap-x-2">
                        <AppLogo />
                    </Link>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-6">
                    <Link href="#sobre" class="text-[#706f6c] hover:text-[#f6c402] font-medium transition-colors">
                        Sobre
                    </Link>
                    <Link href="#recursos" class="text-[#706f6c] hover:text-[#f6c402] font-medium transition-colors">
                        Recursos
                    </Link>
                    
                    <div class="h-5 w-px bg-[#19140035] dark:bg-[#3E3E3A]"></div>
                    
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('play.map')"
                        class="rounded-full bg-[#f6c402] px-5 py-2 text-base font-medium text-black transition hover:bg-[#c39b01]"
                    >
                        Jogar
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
                class="md:hidden absolute top-16 inset-x-0 bg-[#FDFDFC] dark:bg-[#0a0a0a] border-b border-[#19140035] dark:border-[#3E3E3A] py-4 px-4 z-50"
            >
                <nav class="flex flex-col gap-3">
                    <Link 
                        href="#sobre" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Sobre</span>
                        <ChevronRight class="h-5 w-5 text-[#706f6c]" />
                    </Link>
                    <Link 
                        href="#recursos" 
                        class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        @click="closeMenu"
                    >
                        <span class="font-medium">Recursos</span>
                        <ChevronRight class="h-5 w-5 text-[#706f6c]" />
                    </Link>
                    
                    <div class="h-px w-full bg-[#19140035] dark:bg-[#3E3E3A] my-2"></div>
                    
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('play.map')"
                        class="w-full rounded-lg bg-[#f6c402] px-4 py-3 text-center font-medium text-black transition hover:bg-[#c39b01]"
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
                            class="w-full rounded-lg bg-[#f6c402] px-4 py-3 text-center font-medium text-black transition hover:bg-[#c39b01]"
                            @click="closeMenu"
                        >
                            Começar
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section - Minimalista com Arara Feliz -->
        <section class="w-full py-0 pt-8 md:pt-16 relative overflow-hidden" id="hero">
     
            <div class="mx-auto max-w-7xl px-4 sm:px-6 relative z-10">
                <div class="flex flex-col md:flex-row items-center gap-8 md:gap-16">
                    <!-- Conteúdo -->
                    <div class="w-full md:w-1/2 text-center md:text-left">
                        <h1 class="mb-6 text-4xl sm:text-5xl font-extrabold leading-tight heading-font">
                            Aprenda legislação de forma <span class="">divertida</span>
                        </h1>
                        
                        <p class="mb-8 text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-lg mx-auto md:mx-0">
                            O Memorize Direito transforma o estudo jurídico em uma experiência gamificada com desafios progressivos e recompensas.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-[#f6c402] px-6 py-3 text-lg font-medium text-black transition hover:bg-[#c39b01] flex items-center justify-center gap-2"
                            >
                                <PlayCircle class="h-5 w-5" /> Começar agora
                            </Link>
                            <Link
                                :href="route('login')"
                                class="rounded-xl border border-[#19140035] dark:border-[#ffffff35] px-6 py-3 text-lg font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                            >
                                Já tenho conta
                            </Link>
                        </div>
                        
                        <div class="mt-8 flex items-center justify-center md:justify-start">
                            <div class="flex -space-x-2 mr-3">
                                <img class="h-8 w-8 rounded-full" src="https://i.pravatar.cc/100?img=1" alt="Usuário" />
                                <img class="h-8 w-8 rounded-full" src="https://i.pravatar.cc/100?img=2" alt="Usuário" />
                                <img class="h-8 w-8 rounded-full" src="https://i.pravatar.cc/100?img=3" alt="Usuário" />
                            </div>
                            <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">+3.500 estudantes</span>
                        </div>
                    </div>
                    
                    <!-- Imagem -->
                    <div class="w-full md:w-1/2">
                        <img 
                            src="/img/app-mockup.png" 
                            alt="Arara Feliz - Mascote do Memorize Direito" 
                            class="w-full max-w-sm mx-auto"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Degradê do branco/preto para o cinza -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para o modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#FDFDFC] to-[#f5f5f3] dark:opacity-0"></div>
            <!-- Degradê para o modo escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#0a0a0a] to-[#121212] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- Seção Sobre - Comparativo simplificado -->
        <section class="w-full py-0  bg-[#f5f5f3] dark:bg-[#121212]" id="sobre">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">

            <div class="text-center mb-16">
                <h2 class="text-2xl sm:text-5xl font-bold heading-font">
                <span>Diferencial</span> do Memorize Direito
                </h2>
                <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-2xl mx-auto mt-4">
                    Conheça as vantagens que tornam nossa <span class="text-[#f6c402] font-bold">plataforma única</span> para o aprendizado jurídico
                </p>
            </div>
                       
            <div class="flex flex-col gap-8">
                <!-- Método Tradicional -->
                <div class="flex flex-col md:flex-row-reverse items-center gap-10">
                    <!-- Imagem à direita -->
                    <div class="w-full md:w-1/2">
                        <img 
                        src="/img/arara-exausta.png" 
                        alt="Arara exausta estudando pelo método tradicional" 
                        class="w-full rounded-lg max-w-[280px] mx-auto"
                        />
                    </div>
                    
                    <!-- Conteúdo à esquerda (com largura reduzida) -->
                    <div class="w-full md:w-1/2 md:max-w-[450px] md:ml-auto">
                        <h3 class="text-4xl font-bold mb-4 heading-font text-center md:text-left">Método Tradicional</h3>
                        <p class="text-center md:text-justify text-lg text-[#706f6c] dark:text-[#A1A09A]">
                            Estudar legislação de <span class="text-[#1c79b2] font-bold">forma tradicional é cansativo e desmotivador</span>. 
                            Textos densos e memorização forçada tornam o processo ineficiente.
                        </p>
                    </div>
                </div>
                
                <!-- Com Memorize Direito -->
                <div class="flex flex-col md:flex-row items-center gap-10">
                <!-- Imagem à esquerda -->
                <div class="w-full md:w-1/2">
                    <img 
                    src="/img/arara-feliz.png" 
                    alt="Arara feliz estudando com o Memorize Direito" 
                    class="w-full rounded-lg  max-w-[280px] mx-auto"
                    />
                </div>
                
                <!-- Conteúdo à direita (com largura reduzida) -->
                <div class="w-full md:w-1/2 md:max-w-[450px]">
                    <h3 class="text-4xl font-bold mb-4 heading-font text-center md:text-left">Com Memorize Direito</h3>
                    <p class="text-center md:text-justify text-lg text-[#706f6c] dark:text-[#A1A09A]">
                    Transformamos o estudo jurídico em uma experiência divertida! 
                    Nossa plataforma <span class="text-[#1c79b2] font-bold">gamificada mantém você motivado</span> com desafios 
                    interativos.
                    </p>
                    
                </div>
                </div>
            </div>
            </div>
        </section>

        <!-- Degradê do cinza para o branco/preto -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#f5f5f3] to-[#FDFDFC] dark:opacity-0"></div>
            <!-- Degradê para modo escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#121212] to-[#0a0a0a] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- App Preview Section - Simplificado -->
        <section class="w-full py-0 md:py-8 bg-[#FDFDFC] dark:bg-[#0a0a0a]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col-reverse md:flex-row items-center gap-6">
                   
                    <!-- Conteúdo (agora à direita) -->
                    <div class="w-full md:w-1/2 text-center md:text-left">
                        <h2 class="text-3xl sm:text-4xl font-bold mb-4 heading-font text-center md:text-end">
                            Interface <span class="">intuitiva</span>
                        </h2>
                        
                        <p class="text-lg text-center md:text-end text-[#706f6c] dark:text-[#A1A09A] mb-8 md:mx-0">
                            Uma experiência de estudo fluida e agradável que torna o aprendizado jurídico mais eficiente.
                        </p>
                        
                        <div class="flex justify-center md:justify-end">
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-[#f6c402] px-6 py-3 text-lg font-medium text-black transition hover:bg-[#c39b01]"
                            >
                                Experimentar agora
                            </Link>
                        </div>
                    </div>
                    <!-- Imagem (agora à esquerda) -->
                    <div class="w-full md:w-1/2 flex justify-center md:justify-start">
                        <img 
                            src="/img/bicho-preguica-feliz.png" 
                            alt="Memorize Direito App Preview" 
                            class="w-full max-w-sm rounded-xl"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Degradê do branco/preto para o cinza -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para o modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#FDFDFC] to-[#f5f5f3] dark:opacity-0"></div>
            <!-- Degradê para o modo escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#0a0a0a] to-[#121212] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- Recursos Section - Simplificado -->
        <section class="w-full py-0 bg-[#f5f5f3] dark:bg-[#121212]" id="recursos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center mb-8">
                    <h2 class="text-3xl sm:text-4xl font-bold mb-4 heading-font">Principais <span class="">recursos</span></h2>
                    <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] max-w-2xl mx-auto mb-12">
                        Técnicas de aprendizado eficientes com gamificação para tornar o estudo mais envolvente
                    </p>
                </div>
                
                <div class="flex flex-col md:flex-row items-center gap-12">
                    <!-- Conteúdo -->
                    <div class="w-full md:w-1/2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Item 1 -->
                            <div class="bg-white dark:bg-[#1a1a1a] rounded-xl p-4 shadow-md">
                                <div class="flex items-center mb-2">
                                    <div class="h-8 w-8 flex items-center justify-center rounded-full bg-[#f6c40220] text-[#f6c402] mr-3">
                                        <Book class="h-4 w-4" />
                                    </div>
                                    <h3 class="font-bold">Biblioteca Jurídica</h3>
                                </div>
                                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">
                                    Acesso a códigos e leis organizados por áreas do direito
                                </p>
                            </div>
                            
                            <!-- Item 2 -->
                            <div class="bg-white dark:bg-[#1a1a1a] rounded-xl p-4 shadow-md">
                                <div class="flex items-center mb-2">
                                    <div class="h-8 w-8 flex items-center justify-center rounded-full bg-[#f6c40220] text-[#f6c402] mr-3">
                                        <Brain class="h-4 w-4" />
                                    </div>
                                    <h3 class="font-bold">Repetição Espaçada</h3>
                                </div>
                                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">
                                    Sistema que otimiza suas revisões para retenção de longo prazo
                                </p>
                            </div>
                            
                            <!-- Item 3 -->
                            <div class="bg-white dark:bg-[#1a1a1a] rounded-xl p-4 shadow-md">
                                <div class="flex items-center mb-2">
                                    <div class="h-8 w-8 flex items-center justify-center rounded-full bg-[#f6c40220] text-[#f6c402] mr-3">
                                        <Award class="h-4 w-4" />
                                    </div>
                                    <h3 class="font-bold">Conquistas</h3>
                                </div>
                                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">
                                    Medalhas e troféus para manter sua motivação elevada
                                </p>
                            </div>
                            
                            <!-- Item 4 -->
                            <div class="bg-white dark:bg-[#1a1a1a] rounded-xl p-4 shadow-md">
                                <div class="flex items-center mb-2">
                                    <div class="h-8 w-8 flex items-center justify-center rounded-full bg-[#f6c40220] text-[#f6c402] mr-3">
                                        <Star class="h-4 w-4" />
                                    </div>
                                    <h3 class="font-bold">Estatísticas</h3>
                                </div>
                                <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm">
                                    Acompanhe seu progresso e identifique áreas para melhorar
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Imagem -->
                    <div class="w-full md:w-1/2">
                        <img 
                            src="/img/bicho-preguica-e-arara-medalha.png" 
                            alt="Mascotes do Memorize Direito comemorando conquistas" 
                            class="w-full max-w-sm mx-auto"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Degradê do cinza para o branco/preto -->
        <div class="w-full h-28 relative overflow-hidden">
            <!-- Degradê para modo claro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#f5f5f3] to-[#FDFDFC] dark:opacity-0"></div>
            <!-- Degradê para modo escuro -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#121212] to-[#0a0a0a] opacity-0 dark:opacity-100"></div>
        </div>

        <!-- CTA Section - Minimalista -->
        <section class="w-full py-16 bg-[#FDFDFC] dark:bg-[#0a0a0a]">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 text-center">
                <h2 class="text-3xl sm:text-4xl font-bold mb-6 heading-font">Pronto para começar?</h2>
                <p class="text-lg text-[#706f6c] dark:text-[#A1A09A] mb-8">
                    Junte-se a milhares de estudantes e transforme seu aprendizado jurídico hoje.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link
                        :href="route('register')"
                        class="rounded-xl bg-[#f6c402] px-8 py-4 text-lg font-medium text-black transition hover:bg-[#c39b01]"
                    >
                        Começar gratuitamente
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer - Simplificado -->
        <footer class="w-full py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Logo e descrição -->
                    <div>
                        <div class="flex items-center gap-x-2 mb-4">
                            <AppLogo />
                        </div>
                        <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            Transformando o estudo jurídico em uma experiência divertida.
                        </p>
                    </div>
                    
                    <!-- Links -->
                    <div class="flex flex-col md:flex-row justify-between md:col-span-2">
                        <!-- Plataforma -->
                        <div class="mb-6 md:mb-0">
                            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Plataforma</h3>
                            <ul class="space-y-2">
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Jogar</a></li>
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Módulos</a></li>
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Estatísticas</a></li>
                            </ul>
                        </div>
                        
                        <!-- Suporte -->
                        <div class="mb-6 md:mb-0">
                            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Suporte</h3>
                            <ul class="space-y-2">
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">FAQ</a></li>
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Contato</a></li>
                                <li><a href="#" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402] text-sm">Termos de Uso</a></li>
                            </ul>
                        </div>

                        <!-- Redes Sociais -->
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Redes Sociais</h3>
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
                    </div>
                </div>
                
                <div class="mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">© 2025 Memorize Direito. Todos os direitos reservados.</p>
                    <div class="mt-4 md:mt-0">
                        <a href="#" class="text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402]">Política de Privacidade</a>
                        <span class="mx-2 text-[#706f6c] dark:text-[#A1A09A]">•</span>
                        <a href="#" class="text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f6c402]">Termos de Uso</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>