<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Book, Award, Brain, GraduationCap, Scale, Target, Users, PlayCircle, Gavel, BookOpen, CheckCircle2 } from 'lucide-vue-next';
</script>

<template>
    <Head title="Memorize Direito - Aprenda legislação de forma divertida">
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
                        Memorize Direito
                    </div>
                </div>
                <nav class="flex items-center gap-2 sm:gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('play.map')"
                        class="rounded-full bg-[#498304] px-3 py-2 sm:px-5 text-sm sm:text-base font-medium text-white transition hover:bg-[#3a6903] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]"
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
                            class="rounded-full bg-[#498304] px-3 py-2 sm:px-5 text-sm sm:text-base font-medium text-white transition hover:bg-[#3a6903] shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]"
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
                            Aprenda legislação de forma <span class="text-[#498304]">divertida</span>
                        </h1>
                        <p class="mb-6 sm:mb-8 text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A] md:text-xl">
                            O Memorize Direito transforma o estudo do direito em um jogo envolvente, com desafios progressivos e recompensas.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-[#498304] px-6 sm:px-8 py-3 text-base sm:text-lg font-medium text-white transition hover:bg-[#3a6903] text-center flex items-center justify-center gap-2 shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
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
                        <!-- Modificado para ser mais responsivo -->
                        <div class="relative w-full max-w-[320px] sm:max-w-[360px] aspect-square overflow-hidden rounded-lg shadow-xl">
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-[#fff] dark:bg-[#2a2a2a]">
                                <!-- Barra superior do app -->
                                <div class="absolute top-0 left-0 w-full h-6 bg-[#333333] flex items-center px-3">
                                    <div class="w-3 h-3 bg-[#ff605c] rounded-full mr-1.5 border border-[#e1483f]"></div>
                                    <div class="w-3 h-3 bg-[#ffbd44] rounded-full mr-1.5 border border-[#e0a63d]"></div>
                                    <div class="w-3 h-3 bg-[#00ca4e] rounded-full border border-[#14ae46]"></div>
                                    <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center">
                                        <span class="text-[10px] text-white font-medium">Memorize Direito</span>
                                    </div>
                                </div>
                                
                                <!-- Conteúdo principal -->
                                <div class="text-[#333333] dark:text-white flex flex-col items-center w-full p-4 mt-8">
                                    <div class="text-xl sm:text-2xl font-bold">Memorize Direito</div>
                                    <div class="text-xs sm:text-sm opacity-80 mt-1">Aprendizado jurídico gamificado</div>

                                    <div class="bg-[#498304] text-white rounded-lg px-4 py-2 mt-4 sm:mt-6 font-medium text-sm sm:text-base shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] w-full sm:w-auto text-center">
                                        Complete o artigo:
                                    </div>

                                    <div class="bg-[#f0f0f0] dark:bg-[#3a3a3a] rounded-lg p-3 sm:p-4 mt-3 sm:mt-4 w-full text-center border border-[#e0e0e0] dark:border-[#4a4a4a] text-sm sm:text-base">
                                        Art. 5º Todos são _____ perante a lei, sem distinção de qualquer natureza...
                                    </div>

                                    <div class="flex flex-wrap justify-center gap-2 mt-3 sm:mt-4">
                                        <div class="bg-white text-[#333333] rounded-md px-2 sm:px-3 py-1 text-xs sm:text-sm font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">diferentes</div>
                                        <div class="bg-white text-[#333333] rounded-md px-2 sm:px-3 py-1 text-xs sm:text-sm font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">iguais</div>
                                        <div class="bg-white text-[#333333] rounded-md px-2 sm:px-3 py-1 text-xs sm:text-sm font-medium border border-[#e0e0e0] shadow-[inset_0_-3px_0_rgba(0,0,0,0.1),0_2px_3px_rgba(0,0,0,0.05)]">livres</div>
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
            <div class="absolute top-20 left-10 w-40 h-40 rounded-full bg-[#498304] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-60 h-60 rounded-full bg-[#498304] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
                    <!-- Lado da imagem -->
                    <div class="w-full lg:w-1/2">
                        <div class="relative">
                            <div class="absolute -bottom-3 -right-3 w-full h-full rounded-xl"></div>
                            <div class="relative rounded-xl overflow-hidden">
                                <img 
                                    src="/img/happy-monster-removebg-preview.png" 
                                    alt="Estudante usando o Memorize Direito" 
                                    class="w-full h-auto object-cover"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lado do texto -->
                    <div class="w-full lg:w-1/2">
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">Estude com <span class="text-[#498304]">qualquer dispositivo</span></h2>
                        <p class="text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A]">
                            Acesse o Memorize Direito no celular, tablet ou computador e continue seus estudos jurídicos de onde parou.
                        </p>
                    </div>
                </div>
            </div>
        </section>
 
        <section class="w-full py-12 sm:py-16 relative overflow-hidden">
            <!-- Formas decorativas de fundo sutis -->
            <div class="absolute top-20 right-10 w-40 h-40 rounded-full bg-[#498304] opacity-5 blur-3xl"></div>
            <div class="absolute bottom-10 left-10 w-60 h-60 rounded-full bg-[#498304] opacity-5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
                    <!-- Lado do texto (agora à esquerda) -->
                    <div class="w-full lg:w-1/2">
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">Domine a <span class="text-[#498304]">legislação</span> enquanto se diverte</h2>
                        <p class="text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A] mb-6">
                            Transforme horas de estudo cansativo em uma experiência divertida e eficiente. O Memorize Direito utiliza técnicas comprovadas de memorização para ajudá-lo a reter conhecimento jurídico por mais tempo.
                        </p>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-none">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#49830420] text-[#498304]">
                                        <CheckCircle2 class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium">Aprenda no seu ritmo, sem pressão</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-none">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#49830420] text-[#498304]">
                                        <CheckCircle2 class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium">Acompanhe seu progresso e celebre conquistas</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-none">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#49830420] text-[#498304]">
                                        <CheckCircle2 class="h-5 w-5" />
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium">Ganhe confiança para provas e concursos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lado da imagem (agora à direita) -->
                    <div class="w-full lg:w-1/2 order-first lg:order-last">
                        <div class="relative">
                            <div class="absolute -bottom-3 -left-3 w-full h-full rounded-xl"></div>
                            <div class="relative rounded-xl overflow-hidden">
                                <img 
                                    src="/img/selecionados/lawyer-monster.png" 
                                    alt="Estudante usando o Memorize Direito" 
                                    class="w-full h-auto object-cover"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="w-full py-12 sm:py-16 relative overflow-hidden">
            <div class="absolute inset-0 opacity-5">
                <div class="absolute top-0 left-1/4 w-32 h-32 rounded-full bg-[#498304] blur-3xl"></div>
                <div class="absolute bottom-0 right-1/3 w-40 h-40 rounded-full bg-[#1cb0f6] blur-3xl"></div>
                <div class="absolute top-1/2 right-1/4 w-24 h-24 rounded-full bg-[#ff9600] blur-3xl"></div>
                <div class="absolute bottom-1/3 left-1/5 w-20 h-20 rounded-full bg-[#ce82ff] blur-3xl"></div>
            </div>
            
            <div class="mx-auto max-w-3xl px-4 text-center relative z-10">
                <div class="mb-8 flex justify-center">
                    <div class="bg-[#498304] rounded-full p-4 text-white shadow-[inset_0_-4px_0_rgba(0,0,0,0.2),0_4px_6px_rgba(0,0,0,0.1)]">
                        <CheckCircle2 class="h-10 w-10" />
                    </div>
                </div>
                <h2 class="mb-4 sm:mb-6 text-2xl sm:text-3xl font-bold md:text-4xl">Pronto para revolucionar seus estudos jurídicos?</h2>
                <p class="mb-6 sm:mb-8 text-base sm:text-lg text-[#706f6c] dark:text-[#A1A09A]">
                    Junte-se a milhares de estudantes que já estão transformando o aprendizado de legislação em uma experiência divertida e eficiente.
                </p>
                <Link
                    :href="route('register')"
                    class="inline-block rounded-xl bg-[#498304] px-6 sm:px-8 py-3 text-base sm:text-lg font-medium text-white transition hover:bg-[#3a6903] shadow-[inset_0_-5px_0_rgba(0,0,0,0.2),0_5px_6px_rgba(0,0,0,0.1)] active:translate-y-1 active:shadow-[inset_0_-2px_0_rgba(0,0,0,0.2),0_2px_3px_rgba(0,0,0,0.1)]"
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
                        <div class="flex h-8 items-center justify-center">
                            <img src="/img/Memorize Direito-brain.PNG" alt="Memorize Direito" class="h-8 w-auto" />
                        </div>
                        <span class="text-sm font-medium">© 2025</span>
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