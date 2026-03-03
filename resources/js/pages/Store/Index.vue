<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import { Head } from '@inertiajs/vue3';
import GameButton from '@/components/ui/GameButton.vue';
import { Coins, Check, Shield, Flame, Target, Zap, Trophy, BookOpen, Scale, Crown, Star, Palette, Moon, Sun, Sunset, Sparkles } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const breadcrumbs = [
    { title: 'Home', href: '/' },
    { title: 'Loja', href: '/store' },
];

const coins = ref(20000);

type Category = 'avatares' | 'insignias' | 'temas';
const activeCategory = ref<Category>('avatares');

const categories: { id: Category; label: string }[] = [
    { id: 'avatares', label: 'Avatares' },
    { id: 'insignias', label: 'Insígnias' },
    { id: 'temas', label: 'Temas' },
];

interface StoreItem {
    id: number;
    name: string;
    price: number;
    image?: string;
    icon?: any;
    iconColor?: string;
    owned: boolean;
    equipped: boolean;
    colors?: { from: string; to: string };
    themeImage?: string;
}

const avatars = ref<StoreItem[]>([
    { id: 1, name: 'Estudante', image: '/avatares/avatar-01.png', price: 0, owned: true, equipped: true },
    { id: 2, name: 'Pensador', image: '/avatares/avatar-02.png', price: 200, owned: true, equipped: false },
    { id: 3, name: 'Jurista', image: '/avatares/avatar-03.png', price: 500, owned: false, equipped: false },
    { id: 4, name: 'Magistrado', image: '/avatares/avatar-04.png', price: 750, owned: false, equipped: false },
    { id: 5, name: 'Desembargador', image: '/avatares/avatar-05.png', price: 1000, owned: false, equipped: false },
    { id: 6, name: 'Procurador', image: '/avatares/avatar-06.png', price: 1200, owned: false, equipped: false },
    { id: 7, name: 'Constitucionalista', image: '/avatares/avatar-07.png', price: 2000, owned: false, equipped: false },
    { id: 8, name: 'Legislador', image: '/avatares/avatar-08.png', price: 3000, owned: false, equipped: false },
    { id: 9, name: 'Concurseiro', image: '/avatares/avatar-09.png', price: 1500, owned: false, equipped: false },
    { id: 10, name: 'Hacker Jurídico', image: '/avatares/avatar-10.png', price: 1800, owned: false, equipped: false },
    { id: 11, name: 'Advogada', image: '/avatares/avatar-11.png', price: 2200, owned: false, equipped: false },
    { id: 13, name: 'Juíza Digital', image: '/avatares/avatar-13.png', price: 3500, owned: false, equipped: false },
    { id: 14, name: 'Militar', image: '/avatares/avatar-14.png', price: 1500, owned: false, equipped: false },
    { id: 15, name: 'Soldado', image: '/avatares/avatar-15.png', price: 1800, owned: false, equipped: false },
    { id: 16, name: 'Universitária', image: '/avatares/avatar-16.png', price: 1000, owned: false, equipped: false },
    { id: 17, name: 'Dedicado', image: '/avatares/avatar-17.png', price: 2200, owned: false, equipped: false },
    { id: 18, name: 'Pensativo', image: '/avatares/avatar-18.png', price: 800, owned: false, equipped: false },
    { id: 19, name: 'Policial Rodoviária', image: '/avatares/avatar-19.png', price: 2800, owned: false, equipped: false },
]);

const badges = ref<StoreItem[]>([
    { id: 101, name: 'Primeira Fase', icon: Star, iconColor: 'text-amber-500', price: 0, owned: true, equipped: true },
    { id: 102, name: 'Estudioso', icon: BookOpen, iconColor: 'text-blue-500', price: 300, owned: true, equipped: false },
    { id: 103, name: 'Escudo de Ferro', icon: Shield, iconColor: 'text-gray-500', price: 500, owned: false, equipped: false },
    { id: 104, name: 'Chama Ardente', icon: Flame, iconColor: 'text-orange-500', price: 600, owned: false, equipped: false },
    { id: 105, name: 'Mira Certeira', icon: Target, iconColor: 'text-red-500', price: 800, owned: false, equipped: false },
    { id: 106, name: 'Raio Veloz', icon: Zap, iconColor: 'text-yellow-500', price: 1000, owned: false, equipped: false },
    { id: 107, name: 'Balança da Justiça', icon: Scale, iconColor: 'text-purple-500', price: 1500, owned: false, equipped: false },
    { id: 108, name: 'Troféu de Ouro', icon: Trophy, iconColor: 'text-amber-600', price: 2000, owned: false, equipped: false },
    { id: 109, name: 'Coroa Suprema', icon: Crown, iconColor: 'text-yellow-500', price: 3500, owned: false, equipped: false },
]);

const themes = ref<StoreItem[]>([
    { id: 201, name: 'Clássico', icon: Sun, iconColor: 'text-gray-600', price: 0, owned: true, equipped: true, colors: { from: '#f9fafb', to: '#e5e7eb' } },
    { id: 202, name: 'Noturno', icon: Moon, iconColor: 'text-indigo-400', price: 400, owned: true, equipped: false, colors: { from: '#1e1b4b', to: '#312e81' } },
    { id: 203, name: 'Aurora', icon: Sunset, iconColor: 'text-pink-500', price: 800, owned: false, equipped: false, colors: { from: '#fce7f3', to: '#fef3c7' } },
    { id: 204, name: 'Oceano', icon: Sparkles, iconColor: 'text-cyan-500', price: 800, owned: false, equipped: false, colors: { from: '#164e63', to: '#0e7490' } },
    { id: 205, name: 'Floresta', icon: Sparkles, iconColor: 'text-emerald-500', price: 1200, owned: false, equipped: false, colors: { from: '#064e3b', to: '#059669' } },
    { id: 206, name: 'Dourado Imperial', icon: Crown, iconColor: 'text-amber-500', price: 2500, owned: false, equipped: false, colors: { from: '#78350f', to: '#d97706' } },
    { id: 207, name: 'Escritório', price: 600, owned: false, equipped: false, themeImage: '/paisagens/tema-escritorio.png' },
    { id: 208, name: 'Tribunal', price: 1000, owned: false, equipped: false, themeImage: '/paisagens/tema-tribunal.png' },
    { id: 209, name: 'Estrada', price: 800, owned: false, equipped: false, themeImage: '/paisagens/tema-estrada.png' },
    { id: 210, name: 'Faculdade', price: 900, owned: false, equipped: false, themeImage: '/paisagens/tema-faculdade.png' },
    { id: 211, name: 'Biblioteca', price: 1500, owned: false, equipped: false, themeImage: '/paisagens/tema-biblioteca.png' },
    { id: 213, name: 'Acampamento', price: 2000, owned: false, equipped: false, themeImage: '/paisagens/tema-acampamento.png' },
    { id: 214, name: 'Observatório', price: 3000, owned: false, equipped: false, themeImage: '/paisagens/tema-observatorio.png' },
]);

const currentItems = computed(() => {
    switch (activeCategory.value) {
        case 'avatares': return avatars.value;
        case 'insignias': return badges.value;
        case 'temas': return themes.value;
    }
});

const selectedItem = ref<StoreItem | null>(null);
const showDialog = ref(false);
const purchaseSuccess = ref(false);

const openPurchase = (item: StoreItem) => {
    if (item.owned) return;
    selectedItem.value = item;
    purchaseSuccess.value = false;
    showDialog.value = true;
};

const confirmPurchase = () => {
    if (!selectedItem.value || coins.value < selectedItem.value.price) return;
    coins.value -= selectedItem.value.price;
    selectedItem.value.owned = true;
    // Auto-equip after purchase
    equipItem(selectedItem.value);
    purchaseSuccess.value = true;
    setTimeout(() => { showDialog.value = false; selectedItem.value = null; }, 1000);
};

// Debug: add coins for testing
const addCoins = () => { coins.value += 5000; };

const isTheme = (item: StoreItem) => !!(item.colors || item.themeImage);

const equipItem = (item: StoreItem) => {
    const list = isTheme(item) ? themes : item.image ? avatars : badges;
    list.value.forEach((i) => (i.equipped = false));
    item.equipped = true;
};

const canAfford = (item: StoreItem) => coins.value >= item.price;

// Equipped items for avatar preview
const equippedAvatar = computed(() => avatars.value.find((i) => i.equipped));
const equippedBadge = computed(() => badges.value.find((i) => i.equipped));
const equippedTheme = computed(() => themes.value.find((i) => i.equipped));
</script>

<template>
    <Head title="Loja" />

    <AppHeaderLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto space-y-4 px-4 pt-5 pb-6" style="width: min(100%, 28rem)">
            <!-- Header -->
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Loja</h1>
                <div class="inline-flex items-center gap-1.5">
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 dark:bg-amber-900/20">
                        <Coins class="h-4 w-4 text-amber-500" />
                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ coins.toLocaleString() }}</span>
                    </div>
                    <button
                        @click="addCoins"
                        class="rounded-full bg-green-100 px-2 py-1 text-[11px] font-bold text-green-700 hover:bg-green-200 transition-colors dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50"
                    >
                        +5000
                    </button>
                </div>
            </div>

            <!-- Avatar preview -->
            <div class="flex flex-col items-center gap-2">
                <div class="relative">
                    <!-- Theme ring (outer) -->
                    <div
                        class="relative flex h-28 w-28 items-center justify-center rounded-full transition-all overflow-hidden"
                        :style="equippedTheme?.colors
                            ? { background: `linear-gradient(135deg, ${equippedTheme.colors.from}, ${equippedTheme.colors.to})` }
                            : !equippedTheme?.themeImage ? { background: '#e5e7eb' } : {}"
                    >
                        <img
                            v-if="equippedTheme?.themeImage"
                            :src="equippedTheme.themeImage"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <!-- Avatar image (inner) -->
                        <img
                            v-if="equippedAvatar?.image"
                            :src="equippedAvatar.image"
                            :alt="equippedAvatar.name"
                            class="relative z-10 h-full w-full rounded-full object-cover"
                        />
                    </div>
                    <!-- Badge icon (corner) -->
                    <div
                        v-if="equippedBadge?.icon"
                        class="absolute -bottom-1 -right-1 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow-md border border-gray-200 dark:bg-gray-800 dark:border-gray-700"
                    >
                        <component :is="equippedBadge.icon" class="h-5 w-5" :class="equippedBadge.iconColor" />
                    </div>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ equippedAvatar?.name }}</span>
            </div>

            <!-- Category tabs -->
            <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="activeCategory = cat.id"
                    :class="[
                        'flex-1 rounded-md py-2 text-sm font-semibold transition-all',
                        activeCategory === cat.id
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 dark:text-gray-400',
                    ]"
                >
                    {{ cat.label }}
                </button>
            </div>

            <!-- Items grid (unified 3-col for all tabs) -->
            <div class="grid grid-cols-3 gap-3">
                <button
                    v-for="item in currentItems"
                    :key="item.id"
                    @click="item.owned ? equipItem(item) : openPurchase(item)"
                    class="relative flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 transition-all hover:border-gray-300 active:scale-[0.98] dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
                    :class="item.equipped ? 'ring-2 ring-purple-500 ring-offset-1 dark:ring-offset-gray-900' : ''"
                >
                    <!-- Visual -->
                    <div class="relative">
                        <!-- Avatar -->
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.name"
                            class="h-20 w-20 rounded-xl object-cover"
                            :class="!item.owned ? 'opacity-50 grayscale-[40%]' : ''"
                        />
                        <!-- Theme image (landscape) -->
                        <div
                            v-else-if="item.themeImage"
                            class="h-20 w-20 rounded-full overflow-hidden border border-gray-200 dark:border-gray-600"
                            :class="!item.owned ? 'opacity-50 grayscale-[40%]' : ''"
                        >
                            <img :src="item.themeImage" :alt="item.name" class="h-full w-full object-cover" />
                        </div>
                        <!-- Badge icon -->
                        <div
                            v-else-if="item.icon && !item.colors"
                            class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-700"
                        >
                            <component :is="item.icon" class="h-8 w-8" :class="item.iconColor" />
                        </div>
                        <!-- Theme gradient -->
                        <div
                            v-else-if="item.colors"
                            class="h-20 w-20 rounded-full border border-gray-200 dark:border-gray-600"
                            :style="{ background: `linear-gradient(135deg, ${item.colors.from}, ${item.colors.to})` }"
                        />
                        <!-- Equipped check -->
                        <div v-if="item.equipped" class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-purple-500 ring-2 ring-white dark:ring-gray-800">
                            <Check class="h-3.5 w-3.5 text-white" />
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="text-center w-full">
                        <h3 class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ item.name }}</h3>
                        <p v-if="item.equipped" class="text-[11px] font-medium text-purple-500">Em uso</p>
                        <p v-else-if="item.owned" class="text-[11px] text-gray-400">Adquirido</p>
                        <div v-else class="flex items-center justify-center gap-1">
                            <Coins class="h-3 w-3 text-amber-500" />
                            <span class="text-[11px] font-bold" :class="canAfford(item) ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'">
                                {{ item.price.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Purchase Dialog -->
        <Teleport to="body">
            <Transition name="dialog">
                <div v-if="showDialog && selectedItem" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center">
                    <div class="absolute inset-0 bg-black/40" @click="showDialog = false" />

                    <div class="relative w-full max-w-sm rounded-t-2xl sm:rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-800 space-y-4">
                        <template v-if="purchaseSuccess">
                            <div class="text-center space-y-2 py-3">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                    <Check class="h-6 w-6 text-green-500" />
                                </div>
                                <p class="font-bold text-gray-900 dark:text-white">Compra realizada!</p>
                            </div>
                        </template>

                        <template v-else>
                            <!-- Item preview -->
                            <div class="flex items-center gap-3">
                                <img v-if="selectedItem.image" :src="selectedItem.image" :alt="selectedItem.name" class="h-14 w-14 rounded-full object-cover" />
                                <div v-else-if="selectedItem.themeImage" class="h-14 w-14 rounded-full overflow-hidden">
                                    <img :src="selectedItem.themeImage" :alt="selectedItem.name" class="h-full w-full object-cover" />
                                </div>
                                <div v-else-if="selectedItem.icon && !selectedItem.colors" class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                                    <component :is="selectedItem.icon" class="h-7 w-7" :class="selectedItem.iconColor" />
                                </div>
                                <div v-else-if="selectedItem.colors" class="h-14 w-14 rounded-full overflow-hidden" :style="{ background: `linear-gradient(135deg, ${selectedItem.colors.from}, ${selectedItem.colors.to})` }" />
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white">{{ selectedItem.name }}</h3>
                                    <div class="flex items-center gap-1">
                                        <Coins class="h-4 w-4 text-amber-500" />
                                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ selectedItem.price.toLocaleString() }}</span>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Saldo após compra:
                                <span class="font-bold" :class="canAfford(selectedItem) ? 'text-gray-700 dark:text-gray-300' : 'text-red-500'">
                                    {{ Math.max(0, coins - selectedItem.price).toLocaleString() }} moedas
                                </span>
                            </p>

                            <div class="flex gap-3">
                                <GameButton variant="white" class="flex-1" @click="showDialog = false">
                                    Cancelar
                                </GameButton>
                                <GameButton
                                    :variant="canAfford(selectedItem) ? 'green' : 'white'"
                                    class="flex-1"
                                    :disabled="!canAfford(selectedItem)"
                                    @click="confirmPurchase"
                                >
                                    {{ canAfford(selectedItem) ? 'Confirmar' : 'Sem moedas' }}
                                </GameButton>
                            </div>
                        </template>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppHeaderLayout>
</template>

<style scoped>
.dialog-enter-active,
.dialog-leave-active {
    transition: opacity 0.2s ease;
}
.dialog-enter-active > div:last-child,
.dialog-leave-active > div:last-child {
    transition: transform 0.2s ease;
}
.dialog-enter-from,
.dialog-leave-to {
    opacity: 0;
}
.dialog-enter-from > div:last-child {
    transform: translateY(100%);
}
.dialog-leave-to > div:last-child {
    transform: translateY(20px);
}
@media (min-width: 640px) {
    .dialog-enter-from > div:last-child {
        transform: scale(0.95);
    }
    .dialog-leave-to > div:last-child {
        transform: scale(0.95);
    }
}
</style>
