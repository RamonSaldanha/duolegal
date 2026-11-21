<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Loader2, MailCheck, MailX } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Parar (ou voltar a) receber os lembretes por e-mail. Abre sem login, pelo
 * link assinado do rodapé do e-mail.
 */
const props = defineProps<{
    name: string;
    email: string;
    optedOut: boolean;
    unsubscribeUrl: string;
    resubscribeUrl: string;
}>();

const saving = ref(false);

const submit = (url: string) => {
    saving.value = true;
    router.post(url, {}, { preserveScroll: true, onFinish: () => (saving.value = false) });
};
</script>

<template>
    <Head title="Lembretes por e-mail" />

    <div class="flex min-h-[100dvh] flex-col bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <header class="flex justify-center px-4 pt-6">
            <Link :href="route('home')" aria-label="Memorize Direito">
                <img src="/img/logomemorizeblack.svg" alt="Memorize Direito" class="h-12 w-auto dark:hidden" />
                <img src="/img/logomemorize.svg" alt="Memorize Direito" class="hidden h-12 w-auto dark:block" />
            </Link>
        </header>

        <main class="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-4 py-8">
            <div
                class="w-full rounded-3xl border-2 border-gray-200 bg-card px-6 py-8 text-center dark:border-gray-700 dark:bg-gray-800"
            >
                <template v-if="!props.optedOut">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-b-4 border-blue-200 bg-blue-50 text-blue-500 dark:border-blue-900 dark:bg-blue-950/40"
                    >
                        <MailX class="h-8 w-8" />
                    </div>
                    <h1 class="mt-5 text-2xl font-extrabold">Vai mesmo, {{ name }}?</h1>
                    <p class="mt-3 text-gray-600 dark:text-gray-300">
                        A gente só manda lembrete quando você some do jogo, e sempre com presente. Se preferir não receber mais em
                        <strong class="break-all text-gray-900 dark:text-gray-100">{{ email }}</strong
                        >, é só confirmar.
                    </p>
                    <button type="button" class="mz-btn mz-lg mz-second mt-6 w-full" :disabled="saving" @click="submit(unsubscribeUrl)">
                        <Loader2 v-if="saving" class="h-5 w-5 animate-spin" />
                        Não quero mais receber
                    </button>
                    <Link :href="route('play.map')" class="mt-3 inline-block text-sm font-bold text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        Mudei de ideia, quero jogar
                    </Link>
                </template>

                <template v-else>
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-b-4 border-green-200 bg-green-50 text-green-600 dark:border-green-900 dark:bg-green-950/40"
                    >
                        <MailCheck class="h-8 w-8" />
                    </div>
                    <h1 class="mt-5 text-2xl font-extrabold">Pronto, sem mais lembretes</h1>
                    <p class="mt-3 text-gray-600 dark:text-gray-300">
                        <strong class="break-all text-gray-900 dark:text-gray-100">{{ email }}</strong> não vai mais receber nossos lembretes. Sem
                        ressentimentos (tá, só um pouquinho). A porta fica aberta.
                    </p>
                    <button type="button" class="mz-btn mz-lg mz-main mt-6 w-full" :disabled="saving" @click="submit(resubscribeUrl)">
                        <Loader2 v-if="saving" class="h-5 w-5 animate-spin" />
                        Quero voltar a receber
                    </button>
                </template>
            </div>
        </main>
    </div>
</template>
