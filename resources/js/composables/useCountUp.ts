import { onMounted, onUnmounted, ref, watch, type Ref } from 'vue';

interface CountUpOptions {
    /** Valor de onde a contagem parte ao montar. Sem ele, mostra o valor direto. */
    from?: number;
    /** Espera antes de começar a contar, em ms. */
    delay?: number;
    /** Duração da contagem, em ms. */
    duration?: number;
}

/**
 * Número que sobe contando até o valor de `target` (o XP, por exemplo). Conta de novo a cada
 * aumento; quedas e quem pede menos movimento veem o valor final direto.
 */
export function useCountUp(target: Ref<number>, options: CountUpOptions = {}) {
    const { delay = 0, duration = 900 } = options;
    const shown = ref(options.from ?? target.value);
    const animating = ref(false);

    let frame = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    function stop() {
        cancelAnimationFrame(frame);
        clearTimeout(timer);
    }

    function animateTo(end: number, wait = 0) {
        stop();
        const start = shown.value;
        const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        if (end <= start || reduced) {
            shown.value = end;
            animating.value = false;
            return;
        }

        timer = setTimeout(() => {
            animating.value = true;
            const startedAt = performance.now();

            const step = (now: number) => {
                const t = Math.min(1, (now - startedAt) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                shown.value = Math.round(start + (end - start) * eased);

                if (t < 1) {
                    frame = requestAnimationFrame(step);
                } else {
                    animating.value = false;
                }
            };

            frame = requestAnimationFrame(step);
        }, wait);
    }

    onMounted(() => {
        if (shown.value !== target.value) animateTo(target.value, delay);
    });

    watch(target, (value) => animateTo(value));

    onUnmounted(stop);

    return { shown, animating, animateTo };
}
