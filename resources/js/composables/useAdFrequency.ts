import { ref, computed } from 'vue';

/**
 * Composable para gerenciar a frequência de exibição de anúncios entre artigos
 *
 * A lógica funciona assim:
 * - Cada vez que o usuário completa um artigo com sucesso (>= 70%), incrementamos o contador
 * - Quando o contador atinge AD_FREQUENCY, mostramos o anúncio e resetamos
 * - O contador persiste no sessionStorage para manter durante a sessão
 */

const STORAGE_KEY = 'ad_frequency_counter';

// Contador global de artigos completados desde o último anúncio
const articlesCompletedSinceLastAd = ref<number>(0);

// Frequência configurada (quantos artigos entre cada anúncio)
const adFrequency = ref<number>(3); // Default, será sobrescrito pelo backend

export function useAdFrequency() {
    /**
     * Inicializa o composable com a frequência do backend
     * e restaura o contador do sessionStorage se existir
     */
    const initialize = (frequency: number) => {
        // Garantir que sempre tenha um valor válido (mínimo 0, padrão 3)
        if (frequency === null || frequency === undefined || isNaN(frequency)) {
            adFrequency.value = 3; // Valor padrão
        } else {
            adFrequency.value = Math.max(0, frequency); // Não permitir valores negativos
        }

        // Restaurar contador da sessão
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = parseInt(stored, 10);
            if (!isNaN(parsed)) {
                articlesCompletedSinceLastAd.value = parsed;
            }
        }
    };

    /**
     * Registra que um artigo foi completado com sucesso
     * Retorna true se devemos mostrar um anúncio agora
     */
    const registerArticleCompletion = (): boolean => {
        // Se frequência é 0 ou negativa, nunca mostra anúncios
        if (adFrequency.value <= 0) {
            return false;
        }

        articlesCompletedSinceLastAd.value++;
        saveToStorage();

        // Verifica se atingiu a frequência
        if (articlesCompletedSinceLastAd.value >= adFrequency.value) {
            return true;
        }

        return false;
    };

    /**
     * Marca que o anúncio foi exibido e reseta o contador
     */
    const markAdShown = () => {
        articlesCompletedSinceLastAd.value = 0;
        saveToStorage();
    };

    /**
     * Reseta manualmente o contador (útil para testes ou admin)
     */
    const reset = () => {
        articlesCompletedSinceLastAd.value = 0;
        saveToStorage();
    };

    /**
     * Salva o contador atual no sessionStorage
     */
    const saveToStorage = () => {
        sessionStorage.setItem(STORAGE_KEY, articlesCompletedSinceLastAd.value.toString());
    };

    /**
     * Computed: Quantos artigos faltam até o próximo anúncio
     */
    const articlesUntilNextAd = computed(() => {
        if (adFrequency.value <= 0) {
            return Infinity;
        }
        return Math.max(0, adFrequency.value - articlesCompletedSinceLastAd.value);
    });

    /**
     * Computed: Progresso em % até o próximo anúncio
     */
    const progressToNextAd = computed(() => {
        if (adFrequency.value <= 0) {
            return 0;
        }
        return (articlesCompletedSinceLastAd.value / adFrequency.value) * 100;
    });

    return {
        // State
        articlesCompletedSinceLastAd: computed(() => articlesCompletedSinceLastAd.value),
        adFrequency: computed(() => adFrequency.value),

        // Computed
        articlesUntilNextAd,
        progressToNextAd,

        // Methods
        initialize,
        registerArticleCompletion,
        markAdShown,
        reset,
    };
}
