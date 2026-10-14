<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { prefersReducedMotion, sleep, useInView } from '@/composables/useLandingMotion';
import type { AvatarConfig } from '@/lib/avatar';
import { Crown } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { DEMO_AVATARS } from './demoAvatars';

/**
 * Ranking em movimento: "Você" ganha XP fase após fase e vai subindo até o topo,
 * com as linhas trocando de lugar. Os avatares são os do editor do app.
 */

interface Player {
    id: number;
    name: string;
    xp: number;
    start: number;
    you?: boolean;
    avatar: Partial<AvatarConfig>;
}

const players = reactive<Player[]>([
    {
        id: 1,
        name: 'Marina',
        xp: 980,
        start: 980,
        avatar: DEMO_AVATARS['Marina'],
    },
    {
        id: 2,
        name: 'Rafael',
        xp: 910,
        start: 910,
        avatar: DEMO_AVATARS['Rafael'],
    },
    {
        id: 3,
        name: 'Júlia',
        xp: 860,
        start: 860,
        avatar: DEMO_AVATARS['Júlia'],
    },
    {
        id: 4,
        name: 'Você',
        xp: 800,
        start: 800,
        you: true,
        avatar: DEMO_AVATARS['Você'],
    },
    {
        id: 5,
        name: 'Pedro',
        xp: 740,
        start: 740,
        avatar: DEMO_AVATARS['Pedro'],
    },
]);

const sorted = computed(() => [...players].sort((a, b) => b.xp - a.xp));
const leader = computed(() => sorted.value[0]);
const flash = ref<number | null>(null);
const flashAmount = ref(0);
const flashKey = ref(0);

const root = ref<HTMLElement | null>(null);
const inView = useInView(root, { threshold: 0.25 });
let alive = true;

async function waitVisible() {
    while (alive && (!inView.value || document.hidden)) await sleep(250);
}

async function gain(p: Player, amount: number) {
    flash.value = p.id;
    flashAmount.value = amount;
    flashKey.value++;
    const from = p.xp;
    const steps = 14;
    for (let s = 1; s <= steps; s++) {
        p.xp = Math.round(from + (amount * s) / steps);
        await sleep(28);
    }
}

async function play() {
    const you = players.find((p) => p.you)!;
    const rivals = players.filter((p) => !p.you);
    while (alive) {
        await sleep(1200);
        for (let round = 0; round < 6 && alive; round++) {
            await waitVisible();
            await gain(you, 40);
            await sleep(900);
            if (round % 2 === 1) {
                await waitVisible();
                await gain(rivals[(round * 3) % rivals.length], 15);
                await sleep(900);
            }
        }
        await sleep(2600);
        flash.value = null;
        players.forEach((p) => (p.xp = p.start));
    }
}

onMounted(() => {
    if (prefersReducedMotion()) return;
    play();
});

onBeforeUnmount(() => {
    alive = false;
});
</script>

<template>
    <div
        ref="root"
        class="rounded-3xl border-2 border-gray-200 bg-white p-3 shadow-[0_8px_0_#e5e5e5] dark:border-gray-800 dark:bg-gray-900 dark:shadow-[0_8px_0_#262626] sm:p-4"
        aria-hidden="true"
    >
        <div class="mb-2 flex items-center justify-between px-2 pt-1">
            <span class="text-sm font-extrabold uppercase tracking-wide text-gray-400">Ranking</span>
            <span class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-bold text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300"
                >Esta semana</span
            >
        </div>
        <TransitionGroup tag="ol" name="rank" class="relative space-y-2">
            <li
                v-for="(p, i) in sorted"
                :key="p.id"
                class="flex items-center gap-3 rounded-2xl border-2 px-3 py-2.5 transition-colors duration-300"
                :class="
                    p.you
                        ? 'border-blue-300 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/60'
                        : 'border-transparent bg-gray-50 dark:bg-gray-800/60'
                "
            >
                <span
                    class="w-6 text-center text-lg font-extrabold tabular-nums"
                    :class="
                        i === 0 ? 'text-yellow-500' : i === 1 ? 'text-gray-400' : i === 2 ? 'text-orange-400' : 'text-gray-400 dark:text-gray-500'
                    "
                >
                    {{ i + 1 }}
                </span>
                <div class="relative">
                    <UserAvatar :config="p.avatar" :name="p.name" class="h-11 w-11 rounded-full" />
                    <Crown v-if="leader.id === p.id" class="crown absolute -right-1.5 -top-2.5 h-5 w-5 rotate-12 fill-yellow-400 text-yellow-500" />
                </div>
                <span class="flex-1 font-bold" :class="p.you ? 'text-blue-700 dark:text-blue-300' : 'text-gray-800 dark:text-gray-100'">
                    {{ p.name }}
                </span>
                <span class="relative font-extrabold tabular-nums text-gray-600 dark:text-gray-300">
                    {{ p.xp }} <span class="text-xs font-bold text-gray-400">XP</span>
                    <span
                        v-if="flash === p.id"
                        :key="flashKey"
                        class="rank-gain absolute -top-3 right-0 text-sm font-extrabold text-purple-600 dark:text-purple-300"
                    >
                        +{{ flashAmount }}
                    </span>
                </span>
            </li>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.rank-move {
    transition: transform 0.6s cubic-bezier(0.34, 1.3, 0.64, 1);
}

.rank-gain {
    animation: rank-gain 1s ease-out forwards;
}

@keyframes rank-gain {
    0% {
        transform: translateY(4px);
        opacity: 0;
    }
    25% {
        transform: translateY(-6px);
        opacity: 1;
    }
    100% {
        transform: translateY(-22px);
        opacity: 0;
    }
}

.crown {
    animation: crown-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes crown-in {
    0% {
        transform: translateY(-10px) rotate(-20deg) scale(0.4);
        opacity: 0;
    }
    100% {
        transform: rotate(12deg) scale(1);
        opacity: 1;
    }
}
</style>
