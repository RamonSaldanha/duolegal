import { onBeforeUnmount, onMounted, ref, type Directive, type Ref } from 'vue';

/**
 * Peças de motion da landing. Tudo é CSS + IntersectionObserver, sem biblioteca:
 * a página pública precisa carregar rápido e o resto do app não usa nada disso.
 */

export function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * `v-reveal` — o elemento entra (sobe e aparece) na primeira vez que fica visível.
 * O atraso vem do valor da diretiva, em ms: `v-reveal="120"`.
 * O estado inicial e a transição moram em `.lp-reveal` / `.lp-reveal-in` (Welcome.vue).
 */
export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        el.classList.add('lp-reveal');
        el.style.setProperty('--reveal-delay', `${binding.value ?? 0}ms`);

        if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
            el.classList.add('lp-reveal-in');
            return;
        }

        const io = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        el.classList.add('lp-reveal-in');
                        io.disconnect();
                    }
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );
        io.observe(el);
        (el as HTMLElement & { _revealIo?: IntersectionObserver })._revealIo = io;
    },
    unmounted(el) {
        (el as HTMLElement & { _revealIo?: IntersectionObserver })._revealIo?.disconnect();
    },
};

/**
 * Se o elemento está na tela agora. As animações em loop param quando saem de
 * vista para não gastar CPU de quem está lendo o FAQ.
 */
export function useInView(target: Ref<HTMLElement | null>, options: { once?: boolean; threshold?: number } = {}) {
    const inView = ref(false);
    let io: IntersectionObserver | null = null;

    onMounted(() => {
        if (!target.value) return;
        if (!('IntersectionObserver' in window)) {
            inView.value = true;
            return;
        }
        io = new IntersectionObserver(
            ([entry]) => {
                inView.value = entry.isIntersecting;
                if (entry.isIntersecting && options.once) io?.disconnect();
            },
            { threshold: options.threshold ?? 0.2 },
        );
        io.observe(target.value);
    });

    onBeforeUnmount(() => io?.disconnect());

    return inView;
}

/** Conta de `from` até `to` com ease-out. Devolve a função que dispara. */
export function useCountUp(from: number, to: () => number, duration = 1400) {
    const value = ref(from);
    let raf = 0;

    const start = () => {
        cancelAnimationFrame(raf);
        const target = to();
        if (prefersReducedMotion()) {
            value.value = target;
            return;
        }
        const t0 = performance.now();
        const begin = value.value;
        const step = (now: number) => {
            const p = Math.min(1, (now - t0) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            value.value = Math.round(begin + (target - begin) * eased);
            if (p < 1) raf = requestAnimationFrame(step);
        };
        raf = requestAnimationFrame(step);
    };

    onBeforeUnmount(() => cancelAnimationFrame(raf));

    return { value, start };
}

export const sleep = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms));
