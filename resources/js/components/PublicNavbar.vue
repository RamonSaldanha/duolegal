<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X, ChevronRight } from 'lucide-vue-next';
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
</script>

<style>
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
    <header class="w-full bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
            <div class="flex items-center">
                <Link :href="route('home')" class="flex items-center gap-x-2">
                    <AppLogo theme="light" />
                </Link>
            </div>
            
            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center gap-6">
                <Link :href="route('home') + '#features'" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                    Como funciona
                </Link>
                <Link :href="route('home') + '#testimonials'" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                    Benefícios
                </Link>
                <Link :href="route('home') + '#research'" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                    Pesquisa
                </Link>
                <Link :href="route('home') + '#faq'" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                    FAQ
                </Link>
                <Link :href="route('public.laws')" class="text-gray-600 hover:text-green-600 font-medium transition-colors">
                    Todas as Leis
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
                    :href="route('home') + '#features'" 
                    class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                    @click="closeMenu"
                >
                    <span class="font-medium">Como funciona</span>
                    <ChevronRight class="h-5 w-5 text-gray-500" />
                </Link>
                <Link 
                    :href="route('home') + '#testimonials'" 
                    class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                    @click="closeMenu"
                >
                    <span class="font-medium">Benefícios</span>
                    <ChevronRight class="h-5 w-5 text-gray-500" />
                </Link>
                <Link 
                    :href="route('home') + '#research'" 
                    class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                    @click="closeMenu"
                >
                    <span class="font-medium">Pesquisa</span>
                    <ChevronRight class="h-5 w-5 text-gray-500" />
                </Link>
                <Link 
                    :href="route('home') + '#faq'" 
                    class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                    @click="closeMenu"
                >
                    <span class="font-medium">FAQ</span>
                    <ChevronRight class="h-5 w-5 text-gray-500" />
                </Link>
                <Link 
                    :href="route('public.laws')" 
                    class="flex items-center justify-between py-2 px-4 rounded-lg hover:bg-gray-50"
                    @click="closeMenu"
                >
                    <span class="font-medium">Todas as Leis</span>
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
</template>