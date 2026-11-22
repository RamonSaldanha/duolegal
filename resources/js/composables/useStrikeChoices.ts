import { ref, type Ref } from 'vue';

// Arrastar uma alternativa para o lado risca (ou desrisca) ela, para eliminar opções como se faz
// na prova; apertar e segurar faz o mesmo. Arrastar na vertical continua rolando a tela. Usado no
// desafio do quadrado e no Dia da prova.

// Distância que arma o risco (a alternativa já aparece riscada antes de soltar)
const STRIKE_DISTANCE = 40;
// Até onde o cartão acompanha o dedo; depois disso, resiste
const FOLLOW_MAX = 72;
// Movimento que decide se o gesto é arrasto para o lado ou rolagem
const DIRECTION_SLOP = 6;

type Drag = { key: string; startX: number; startY: number; dx: number; horizontal: boolean | null; armed: boolean };

export function useStrikeChoices(selected: Ref<string | null>, locked: () => boolean) {
    const struck = ref<Record<string, boolean>>({});
    const drag = ref<Drag | null>(null);
    // O clique que o navegador manda depois de um arrasto ou de segurar não marca a alternativa
    let ignoreClickUntil = 0;

    const buzz = () => navigator.vibrate?.(8);

    function toggle(key: string) {
        struck.value[key] = !struck.value[key];
        if (struck.value[key] && selected.value === key) selected.value = null;
    }

    function onPointerDown(event: PointerEvent, key: string) {
        if (locked() || (event.pointerType === 'mouse' && event.button !== 0)) return;
        drag.value = { key, startX: event.clientX, startY: event.clientY, dx: 0, horizontal: null, armed: false };
    }

    function onPointerMove(event: PointerEvent) {
        const current = drag.value;
        if (!current) return;

        const dx = event.clientX - current.startX;
        const dy = event.clientY - current.startY;

        if (current.horizontal === null) {
            if (Math.max(Math.abs(dx), Math.abs(dy)) < DIRECTION_SLOP) return;
            current.horizontal = Math.abs(dx) > Math.abs(dy);
            if (!current.horizontal) {
                drag.value = null;
                return;
            }
            (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
        }

        // Para os dois lados: o cartão segue o dedo e, passado o limite, resiste
        const distance = Math.abs(dx);
        const shown = distance <= FOLLOW_MAX ? distance : FOLLOW_MAX + (distance - FOLLOW_MAX) * 0.25;
        current.dx = Math.sign(dx) * shown;

        const armed = distance >= STRIKE_DISTANCE;
        if (armed !== current.armed) {
            current.armed = armed;
            if (armed) buzz();
        }
    }

    function onPointerUp() {
        const current = drag.value;
        drag.value = null;
        if (!current?.horizontal) return;

        ignoreClickUntil = performance.now() + 400;
        if (current.armed) toggle(current.key);
    }

    function onPointerCancel() {
        drag.value = null;
    }

    // Apertar e segurar (ou o botão direito do mouse) também risca
    function onContextMenu(event: Event, key: string) {
        event.preventDefault();
        if (locked() || drag.value?.horizontal) return;
        drag.value = null;
        toggle(key);
        buzz();
        ignoreClickUntil = performance.now() + 600;
    }

    /** Tocar marca a alternativa (e tira o risco dela). Devolve se o toque valeu. */
    function onClick(key: string): boolean {
        if (performance.now() < ignoreClickUntil) return false;
        selected.value = key;
        struck.value[key] = false;
        return true;
    }

    function offset(key: string) {
        return drag.value?.key === key && drag.value.horizontal ? drag.value.dx : 0;
    }

    function isDragging(key: string) {
        return drag.value?.key === key && !!drag.value.horizontal;
    }

    /** Riscada na tela: durante o arrasto armado, já mostra como vai ficar ao soltar. */
    function looksStruck(key: string) {
        const armedHere = drag.value?.key === key && drag.value.armed;
        return armedHere ? !struck.value[key] : !!struck.value[key];
    }

    function reset() {
        struck.value = {};
        drag.value = null;
    }

    return { struck, onPointerDown, onPointerMove, onPointerUp, onPointerCancel, onContextMenu, onClick, offset, isDragging, looksStruck, reset };
}
