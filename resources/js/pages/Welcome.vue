<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Book, Award, Brain, GraduationCap, Scale, Target, Users, PlayCircle, Gavel, BookOpen, CheckCircle2 } from 'lucide-vue-next';
</script>

<template>
    <Head title="Jus-it - Aprenda legislação de forma divertida">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-white">
        <!-- Header -->
        <header class="w-full border-b border-[#19140035] dark:border-[#3E3E3A]">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center">
                    <div class="flex items-center gap-x-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#528b8f] text-white shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_4px_rgba(0,0,0,0.1)]">
                            <Gavel class="h-5 w-5" />
                        </div>
                        <span class="text-xl font-bold">Jus-it</span>
                    </div>
                </div>
                <nav class="flex items-center gap-2 sm:gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-full bg-[#528b8f] px-3 py-2 sm:px-5 text-sm sm:text-base font-medium text-white transition hover:bg-[#254450] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-full px-3 py-2 sm:px-5 text-sm sm:text-base font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15]"
                        >
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-full bg-[#528b8f] px-3 py-2 sm:px-5 text-sm sm:text-base font-medium text-white transition hover:bg-[#254450] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]"
                        >
                            Começar
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="w-full py-8 sm:py-16 relative overflow-hidden">
            
            <div class="mx-auto max-w-7xl px-4 sm:py-16 relative z-10">
                <div class="flex flex-col gap-8 lg:flex-row">
                    <div class="flex flex-1 flex-col justify-center">
                        <h1 class="mb-4 text-3xl sm:text-4xl font-bold leading-tight md:text-5xl lg:text-6xl">
                            Aprenda legislação de forma <span class="text-[#528b8f]">divertida</span>
                        </h1>
                        <p class="mb-6 sm:mb-8 text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A] md:text-xl">
                            O Jus-it transforma o estudo do direito em um jogo envolvente, com desafios progressivos e recompensas.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-[#528b8f] px-6 sm:px-8 py-3 text-base sm:text-lg font-medium text-white transition hover:bg-[#254450] text-center flex items-center justify-center gap-2 shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
                            >
                                <PlayCircle class="h-5 w-5" /> Começar gratuitamente
                            </Link>
                            <Link
                                :href="route('login')"
                                class="rounded-xl border border-[#19140035] dark:border-[#ffffff35] px-6 sm:px-8 py-3 text-base sm:text-lg font-medium transition hover:bg-[#19140015] dark:hover:bg-[#ffffff15] text-center mt-3 sm:mt-0"
                            >
                                Já tenho conta
                            </Link>
                        </div>
                    </div>
                    <div class="flex flex-1 items-center justify-center mt-8 lg:mt-0">
                        <div class="relative h-64 w-full sm:h-80 sm:w-80 overflow-hidden rounded-lg shadow-xl">
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-[#fff] dark:bg-[#2a2a2a]">
                                <div class="absolute top-0 left-0 w-full h-6 bg-[#333333] flex items-center px-3">
                                    <div class="w-3 h-3 bg-[#ff605c] rounded-full mr-1.5 border border-[#e1483f] "></div>
                                    <div class="w-3 h-3 bg-[#ffbd44] rounded-full mr-1.5 border border-[#e0a63d] "></div>
                                    <div class="w-3 h-3 bg-[#00ca4e] rounded-full border border-[#14ae46]"></div>
                                    <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center">
                                        <span class="text-[10px] text-white font-medium">Jus-it</span>
                                    </div>
                                </div>
                                <div class="text-[#333333] dark:text-white flex flex-col items-center mt-10">
                                    <Gavel class="h-12 w-12 mb-4 text-[#1379a9]" />
                                    <div class="text-2xl font-bold">Jus-it</div>
                                    <div class="text-sm opacity-80 mt-1">Aprendizado jurídico gamificado</div>

                                    <div class="bg-[#528b8f] text-white rounded-lg px-4 py-2 mt-6 font-medium shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)]">
                                        Complete o artigo:
                                    </div>

                                    <div class="bg-[#f0f0f0] dark:bg-[#3a3a3a] rounded-lg p-4 mt-4 w-[80%] text-center border border-[#e0e0e0] dark:border-[#4a4a4a]">
                                        Art. 5º Todos são _____ perante a lei, sem distinção de qualquer natureza...
                                    </div>

                                    <div class="flex gap-2 mt-4">
                                        <div class="bg-white text-[#333333] rounded-md px-3 py-1 font-medium border border-[#e0e0e0] shadow-[inset_0_-5px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">diferentes</div>
                                        <div class="bg-white text-[#333333] rounded-md px-3 py-1 font-medium border border-[#e0e0e0] shadow-[inset_0_-5px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">iguais</div>
                                        <div class="bg-white text-[#333333] rounded-md px-3 py-1 font-medium border border-[#e0e0e0] shadow-[inset_0_-5px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">livres</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Showcase Section -->
        <section class="w-full py-12 sm:py-16 relative overflow-hidden">
            <!-- Formas decorativas de fundo sutis -->
            <div class="absolute top-20 left-10 w-40 h-40 rounded-full bg-[#528b8f] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-60 h-60 rounded-full bg-[#528b8f] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
                    <!-- Lado da imagem -->
                    <div class="w-full lg:w-1/2">
                        <div class="relative">
                            <div class="absolute -bottom-3 -right-3 w-full h-full rounded-xl"></div>
                            <div class="relative rounded-xl overflow-hidden">
                                <img 
                                    src="/img/selecionados/happy-monster-removebg-preview.png" 
                                    alt="Estudante usando o Jus-it" 
                                    class="w-full h-auto object-cover"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lado do texto -->
                    <div class="w-full lg:w-1/2">
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">Estude com <span class="text-[#528b8f]">qualquer dispositivo</span></h2>
                        <p class="text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A]">
                            Acesse o Jus-it no celular, tablet ou computador e continue seus estudos jurídicos de onde parou.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <section class="w-full bg-gradient-to-b from-[#f0f8f0] to-[#f9f9f8] py-12 sm:py-16 dark:from-[#0a1e0a] dark:to-[#121211] relative overflow-hidden">
            <!-- Formas decorativas de fundo -->
            <div class="absolute -top-20 -left-20 w-60 h-60 rounded-full bg-[#528b8f] opacity-10 blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-80 h-80 rounded-full bg-[#528b8f] opacity-5 blur-[100px]"></div>
            <div class="absolute top-40 right-10 w-40 h-40 rounded-full bg-[#254450] opacity-5 blur-[80px]"></div>
            
                        
            <div class="mx-auto max-w-7xl px-4 relative z-10">
                <h2 class="mb-8 sm:mb-12 text-center text-2xl sm:text-3xl font-bold md:text-4xl">Como funciona</h2>
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col md:flex-row gap-6 items-start md:items-center">
                        <div class="flex-none">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#528b8f] to-[#254450] text-2xl font-bold text-white shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_0_rgba(0,0,0,0.1)]">1</div>
                        </div>
                        <div class="flex-1 rounded-xl bg-white p-5 sm:p-6 shadow-[0_5px_0_rgba(0,0,0,0.05)] dark:bg-[#161615] w-full relative overflow-hidden border border-[#528b8f10] dark:border-[#528b8f20]">
                            <div class="absolute top-0 right-0 h-full w-1/3 bg-gradient-to-l from-[#528b8f08] to-transparent"></div>
                            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                                <div class="flex-1">
                                    <h3 class="mb-2 text-lg sm:text-xl font-medium text-[#254450] dark:text-[#528b8f]">Escolha sua fase</h3>
                                    <p class="text-sm sm:text-base text-[#706f6c] dark:text-[#A1A09A]">
                                        Navegue pelo mapa interativo e escolha uma fase para estudar artigos específicos da legislação.
                                    </p>
                                </div>
                                <div class="flex justify-center md:justify-end">
                                    <div class="bg-gradient-to-br from-[#528b8f] to-[#254450] rounded-lg p-3 text-white shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_0_rgba(0,0,0,0.1)]">
                                        <BookOpen class="h-12 w-12" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col md:flex-row gap-6 items-start md:items-center">
                        <div class="flex-none">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#528b8f] to-[#254450] text-2xl font-bold text-white shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_0_rgba(0,0,0,0.1)]">2</div>
                        </div>
                        <div class="flex-1 rounded-xl bg-white p-5 sm:p-6 shadow-[0_5px_0_rgba(0,0,0,0.05)] dark:bg-[#161615] w-full relative overflow-hidden border border-[#528b8f10] dark:border-[#528b8f20]">
                            <div class="absolute top-0 right-0 h-full w-1/3 bg-gradient-to-l from-[#528b8f08] to-transparent"></div>
                            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                                <div class="flex-1">
                                    <h3 class="mb-2 text-lg sm:text-xl font-medium text-[#254450] dark:text-[#528b8f]">Complete os desafios</h3>
                                    <p class="text-sm sm:text-base text-[#706f6c] dark:text-[#A1A09A]">
                                        Preencha as lacunas nos textos jurídicos, colocando as palavras corretas nos lugares certos para completar os artigos.
                                    </p>
                                </div>
                                <div class="flex justify-center md:justify-end">
                                    <div class="bg-gradient-to-br from-[#528b8f] to-[#254450] rounded-lg p-3 text-white shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_0_rgba(0,0,0,0.1)]">
                                        <Gavel class="h-12 w-12" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col md:flex-row gap-6 items-start md:items-center">
                        <div class="flex-none">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#528b8f] to-[#254450] text-2xl font-bold text-white shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_0_rgba(0,0,0,0.1)]">3</div>
                        </div>
                        <div class="flex-1 rounded-xl bg-white p-5 sm:p-6 shadow-[0_5px_0_rgba(0,0,0,0.05)] dark:bg-[#161615] w-full relative overflow-hidden border border-[#528b8f10] dark:border-[#528b8f20]">
                            <div class="absolute top-0 right-0 h-full w-1/3 bg-gradient-to-l from-[#528b8f08] to-transparent"></div>
                            <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                                <div class="flex-1">
                                    <h3 class="mb-2 text-lg sm:text-xl font-medium text-[#254450] dark:text-[#528b8f]">Receba recompensas</h3>
                                    <p class="text-sm sm:text-base text-[#706f6c] dark:text-[#A1A09A]">
                                        Ganhe pontos, suba de nível e desbloqueie novas fases à medida que avança em seu aprendizado jurídico.
                                    </p>
                                </div>
                                <div class="flex justify-center md:justify-end">
                                    <div class="bg-gradient-to-br from-[#528b8f] to-[#254450] rounded-lg p-3 text-white shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_0_rgba(0,0,0,0.1)]">
                                        <Award class="h-12 w-12" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="w-full py-12 sm:py-16 relative overflow-hidden">
            <div class="absolute inset-0 opacity-5">
                <div class="absolute top-0 left-1/4 w-32 h-32 rounded-full bg-[#528b8f] blur-3xl"></div>
                <div class="absolute bottom-0 right-1/3 w-40 h-40 rounded-full bg-[#1cb0f6] blur-3xl"></div>
                <div class="absolute top-1/2 right-1/4 w-24 h-24 rounded-full bg-[#ff9600] blur-3xl"></div>
                <div class="absolute bottom-1/3 left-1/5 w-20 h-20 rounded-full bg-[#ce82ff] blur-3xl"></div>
            </div>
            
            <div class="mx-auto max-w-3xl px-4 text-center relative z-10">
                <div class="mb-8 flex justify-center">
                    <div class="bg-[#528b8f] rounded-full p-4 text-white shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]">
                        <CheckCircle2 class="h-10 w-10" />
                    </div>
                </div>
                <h2 class="mb-4 sm:mb-6 text-2xl sm:text-3xl font-bold md:text-4xl">Pronto para revolucionar seus estudos jurídicos?</h2>
                <p class="mb-6 sm:mb-8 text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A]">
                    Junte-se a milhares de estudantes que já estão transformando o aprendizado de legislação em uma experiência divertida e eficiente.
                </p>
                <Link
                    :href="route('register')"
                    class="inline-block rounded-xl bg-[#528b8f] px-6 sm:px-8 py-3 text-base sm:text-lg font-medium text-white transition hover:bg-[#254450] shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
                >
                    Começar gratuitamente
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="w-full border-t border-[#19140035] py-6 dark:border-[#3E3E3A]">
            <div class="mx-auto max-w-7xl px-4">
                <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                    <div class="flex items-center gap-x-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#528b8f] text-white">
                            <Gavel class="h-4 w-4" />
                        </div>
                        <span class="text-sm font-medium">Jus-it © 2025</span>
                    </div>
                    <div class="flex gap-4 sm:gap-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        <a href="#" class="hover:text-[#1b1b18] hover:underline dark:hover:text:white">Termos</a>
                        <a href="#" class="hover:text-[#1b1b18] hover:underline dark:hover:text:white">Privacidade</a>
                        <a href="#" class="hover:text-[#1b1b18] hover:underline dark:hover:text:white">Contato</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>