<script setup lang="ts">
import StudySummary from '@/components/StudySummary.vue';
import { GripHorizontal, Minus, Pause, Play, Plus, Rows3, WholeWord } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Leitura guiada do resumo, como um exercício de leitura dinâmica: o texto escurece e um grupo
// de palavras claras avança na velocidade escolhida. São dois modos, alternados no painel:
// por blocos (uma janela de palavras que desliza palavra a palavra) e régua de leitura (uma linha
// inteira por vez). Rolar a tela leva a leitura para o trecho que ficou à vista, e tocar numa
// palavra leva a leitura até ela; apertar e segurar o texto prende o realce. O resumo só conta como lido quando a leitura, tocando, passou
// por quase todas as palavras: rolar ou pular até o fim não vale. Os controles ficam num painel
// vertical flutuante, que a pessoa arrasta para perto do dedão.
const props = defineProps<{
    html: string;
    // Elemento que rola o resumo (a área de conteúdo da fase)
    scroller: HTMLElement | null;
}>();

const WPM_MIN = 100;
const WPM_MAX = 800;
const WPM_STEP = 20;
const WPM_DEFAULT = 240;
// Leitura por blocos: palavras claras antes e depois da palavra atual
const WINDOW_BEHIND = 1;
const WINDOW_AHEAD = 8;
// Parte das palavras que a leitura precisa ter percorrido, tocando, para o resumo contar como lido
const REQUIRED_COVERAGE = 0.9;
const WPM_KEY = 'memorize:reading-wpm';
const PANEL_KEY = 'memorize:reading-panel-v2';
const MODE_KEY = 'memorize:reading-mode';

type Mode = 'chunk' | 'line';
const MODE_LABELS: Record<Mode, string> = { chunk: 'Leitura por blocos', line: 'Régua de leitura' };

type State = 'idle' | 'playing' | 'paused';

const emit = defineEmits<{
    // A leitura tocando chegou à última palavra, tendo percorrido o resumo (rolar ou pular não conta)
    finished: [];
    // Quanto do resumo a leitura já percorreu, de 0 a 1
    progress: [fraction: number];
}>();

const root = ref<HTMLElement | null>(null);
const state = ref<State>('idle');
const wpm = ref(WPM_DEFAULT);
const mode = ref<Mode>('chunk');

let words: HTMLElement[] = [];
// Primeira palavra do grupo atual
let index = 0;
// Grupos que clareiam juntos (blocos ou linhas): a primeira palavra de cada um e o grupo de cada palavra
let groupStart: number[] = [];
let groupOfWord = new Uint32Array(0);
let litFrom = 0;
let litTo = -1;
// Palavras em que a leitura ficou o tempo dela, tocando
let seen = new Uint8Array(0);
let seenCount = 0;
let completed = false;
let lastPercent = 0;
let timer: ReturnType<typeof setTimeout> | null = null;
// Até quando as rolagens são as que a própria leitura provocou (não mexem na posição)
let autoScrollUntil = 0;

function readStored<T>(key: string, fallback: T): T {
    try {
        const raw = localStorage.getItem(key);
        return raw === null ? fallback : (JSON.parse(raw) as T);
    } catch {
        return fallback;
    }
}

function store(key: string, value: unknown) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Sem armazenamento (aba privada): só não lembra da próxima vez
    }
}

// === Palavras ===

/** Envolve cada palavra do resumo num span, para poder clarear uma a uma. */
function wrapWords() {
    words = [];
    const element = root.value;
    if (!element) return;

    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const textNodes: Text[] = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode as Text);

    for (const node of textNodes) {
        const text = node.nodeValue ?? '';
        if (!text.trim()) continue;

        const fragment = document.createDocumentFragment();
        for (const part of text.split(/(\s+)/)) {
            if (!part) continue;
            if (/^\s+$/.test(part)) {
                fragment.appendChild(document.createTextNode(part));
                continue;
            }
            const span = document.createElement('span');
            span.className = 'rw';
            span.textContent = part;
            span.dataset.i = String(words.length);
            words.push(span);
            fragment.appendChild(span);
        }
        node.replaceWith(fragment);
    }
}

/**
 * Divide as palavras nos grupos em que a leitura avança: uma linha do texto por vez na régua,
 * palavra a palavra na leitura por blocos (que clareia uma janela em volta da palavra atual).
 */
function buildGroups() {
    groupStart = [];
    groupOfWord = new Uint32Array(words.length);

    let lineTop = 0;

    for (let i = 0; i < words.length; i++) {
        let starts = i === 0 || mode.value === 'chunk';

        if (mode.value === 'line') {
            const box = words[i].getBoundingClientRect();
            if (starts || Math.abs(box.top - lineTop) > box.height * 0.5) {
                starts = true;
                lineTop = box.top;
            }
        }

        if (starts) groupStart.push(i);
        groupOfWord[i] = groupStart.length - 1;
    }
}

/** Primeira e última palavra do grupo em que a palavra está. */
function groupRange(wordIndex: number): [number, number] {
    const group = groupOfWord[wordIndex] ?? 0;

    return [groupStart[group] ?? 0, (groupStart[group + 1] ?? words.length) - 1];
}

/** Leva uma palavra qualquer para o começo do grupo dela. */
function snap(wordIndex: number): number {
    return groupRange(Math.min(Math.max(0, wordIndex), words.length - 1))[0];
}

function paint() {
    let [from, to] = groupRange(index);
    if (mode.value === 'chunk') {
        from = Math.max(0, index - WINDOW_BEHIND);
        to = Math.min(words.length - 1, index + WINDOW_AHEAD);
    }

    for (let i = litFrom; i <= litTo; i++) {
        if (i < from || i > to) words[i]?.classList.remove('on');
    }
    for (let i = from; i <= to; i++) words[i].classList.add('on');

    litFrom = from;
    litTo = to;
}

function clearPaint() {
    for (let i = litFrom; i <= litTo; i++) words[i]?.classList.remove('on');
    litFrom = 0;
    litTo = -1;
}

/** Primeira palavra a partir de uma altura da área visível (fração de 0 a 1). */
function wordAt(fraction: number): number {
    const scroller = props.scroller;
    if (!scroller || !words.length) return 0;

    const box = scroller.getBoundingClientRect();
    const line = box.top + box.height * fraction;

    let low = 0;
    let high = words.length - 1;
    while (low < high) {
        const middle = (low + high) >> 1;
        if (words[middle].getBoundingClientRect().bottom < line) low = middle + 1;
        else high = middle;
    }

    return low;
}

// === Andamento ===

/** Conta o grupo atual como lido: a leitura ficou nele o tempo da velocidade escolhida. */
function markSeen() {
    if (!words.length) return;

    const [from, to] = groupRange(index);
    for (let i = from; i <= to; i++) {
        if (seen[i]) continue;
        seen[i] = 1;
        seenCount++;
    }

    // Avisa a tela a cada ponto percentual, não a cada grupo
    const percent = Math.floor((seenCount / words.length) * 100);
    if (percent !== lastPercent) {
        lastPercent = percent;
        emit('progress', percent / 100);
    }

    // Leu o bastante; o resumo só conta como lido quando a leitura chegar à última palavra
    if (seenCount >= Math.ceil(words.length * REQUIRED_COVERAGE)) completed = true;
}

/** Tempo na palavra: o da velocidade, com uma pausa maior na pontuação e em palavra longa. */
function delayFor(word: HTMLElement): number {
    const text = word.textContent ?? '';
    let factor = 1;
    if (/[.!?:]["”')\]]?$/.test(text)) factor += 0.6;
    else if (/[,;]["”')\]]?$/.test(text)) factor += 0.3;
    if (text.length > 9) factor += 0.2;

    return (60000 / wpm.value) * factor;
}

/** Mantém a palavra atual na faixa confortável da tela. */
function follow() {
    const scroller = props.scroller;
    const word = words[index];
    if (!scroller || !word) return;

    const box = scroller.getBoundingClientRect();
    const top = word.getBoundingClientRect().top - box.top;
    if (top > box.height * 0.55 || top < box.height * 0.08) {
        autoScrollUntil = performance.now() + 700;
        scroller.scrollBy({ top: top - box.height * 0.3, behavior: 'smooth' });
    }
}

function schedule() {
    if (timer) clearTimeout(timer);
    // O grupo fica aceso pelo tempo somado das palavras dele
    const [from, to] = groupRange(index);
    let delay = 0;
    for (let i = from; i <= to; i++) delay += delayFor(words[i]);
    timer = setTimeout(step, delay);
}

function step() {
    if (state.value !== 'playing') return;

    markSeen();

    const next = groupRange(index)[1] + 1;
    if (next >= words.length) {
        if (completed) finish();
        else resumeAtUnread();
        return;
    }

    index = next;
    paint();
    follow();
    schedule();
}

/** Chegou ao fim com trechos pulados: a leitura volta, pausada, ao primeiro deles. */
function resumeAtUnread() {
    stopTimer();
    state.value = 'paused';
    index = snap(seen.indexOf(0));
    paint();
    follow();
}

function finish() {
    emit('finished');
    stopTimer();
    state.value = 'idle';
    clearPaint();
    index = 0;
    // Chegou ao fim: mostra o que vem depois do resumo
    const scroller = props.scroller;
    if (scroller) {
        autoScrollUntil = performance.now() + 700;
        scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' });
    }
}

function stopTimer() {
    if (timer) clearTimeout(timer);
    timer = null;
}

function play() {
    if (!words.length) return;
    // Começa pelo que está à vista, não pelo começo do resumo
    if (state.value === 'idle') {
        buildGroups();
        index = snap(wordAt(0.02));
    }

    state.value = 'playing';
    paint();
    follow();
    schedule();
}

function pause() {
    stopTimer();
    held.value = false;
    state.value = 'paused';
}

function toggle() {
    if (state.value === 'playing') pause();
    else play();
}

function changeSpeed(direction: 1 | -1) {
    wpm.value = Math.min(WPM_MAX, Math.max(WPM_MIN, wpm.value + direction * WPM_STEP));
    store(WPM_KEY, wpm.value);
}

// === Modo ===

const modeHint = ref(false);
let modeHintTimer: ReturnType<typeof setTimeout> | null = null;

/** Refaz os grupos (mudou o modo ou a largura do texto) sem tirar a leitura do lugar. */
function regroup() {
    const current = index;
    buildGroups();
    index = snap(current);
    if (state.value === 'idle') return;

    paint();
    if (state.value === 'playing') schedule();
}

function toggleMode() {
    mode.value = mode.value === 'chunk' ? 'line' : 'chunk';
    store(MODE_KEY, mode.value);
    regroup();

    // Balão discreto com o nome do modo escolhido
    modeHint.value = true;
    if (modeHintTimer) clearTimeout(modeHintTimer);
    modeHintTimer = setTimeout(() => (modeHint.value = false), 1800);
}

// Rolou a tela com a leitura aberta: a janela vai para o trecho que ficou à vista
function onScroll() {
    if (state.value === 'idle') return;

    // Rolagem que a própria leitura provocou: enquanto ela durar, não mexe na posição
    const now = performance.now();
    if (now < autoScrollUntil) {
        autoScrollUntil = Math.max(autoScrollUntil, now + 200);
        return;
    }

    // Só muda de lugar se a rolagem tirou a palavra atual da tela. Rolagem pequena, ou a da
    // página que encolheu ou cresceu, não tira a leitura de onde está.
    const scroller = props.scroller;
    const word = words[index];
    if (scroller && word) {
        const box = scroller.getBoundingClientRect();
        const rect = word.getBoundingClientRect();
        if (rect.bottom > box.top && rect.top < box.bottom) return;
    }

    // Rolar só muda de onde a leitura segue; o que foi pulado não conta como lido
    const target = snap(wordAt(0.25));
    if (target === index) return;

    index = target;
    paint();
    if (state.value === 'playing') schedule();
}

// === Segurar ===

// Apertar e segurar o texto prende o realce onde está; soltar faz a leitura seguir
const HOLD_MIN = 300;
const held = ref(false);
let holdStartedAt = 0;
let ignoreClickUntil = 0;

function onHoldStart(event: PointerEvent) {
    if (state.value !== 'playing' || held.value) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;

    held.value = true;
    holdStartedAt = performance.now();
    stopTimer();
}

// Também chega quando o toque vira rolagem (pointercancel): a leitura segue
function onHoldEnd() {
    if (!held.value) return;

    held.value = false;
    // Soltar depois de segurar não conta como toque na palavra
    if (performance.now() - holdStartedAt >= HOLD_MIN) ignoreClickUntil = performance.now() + 400;
    if (state.value === 'playing') schedule();
}

// Tocar numa palavra leva a leitura até ela
function onWordClick(event: MouseEvent) {
    if (state.value === 'idle' || performance.now() < ignoreClickUntil) return;
    const target = (event.target as HTMLElement).closest<HTMLElement>('.rw');
    if (!target?.dataset.i) return;

    index = snap(Number(target.dataset.i));
    paint();
    if (state.value === 'playing') schedule();
}

// === Painel flutuante ===

const panel = ref<HTMLElement | null>(null);
const panelPosition = ref({ x: 0, y: 0 });
const draggingPanel = ref(false);
let dragStart: { pointerX: number; pointerY: number; x: number; y: number; moved: boolean } | null = null;

function clampPanel(x: number, y: number) {
    const width = panel.value?.offsetWidth ?? 44;
    const height = panel.value?.offsetHeight ?? 170;

    return {
        x: Math.min(Math.max(4, x), Math.max(4, window.innerWidth - width - 4)),
        y: Math.min(Math.max(4, y), Math.max(4, window.innerHeight - height - 4)),
    };
}

function onPanelPointerDown(event: PointerEvent) {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    dragStart = { pointerX: event.clientX, pointerY: event.clientY, ...panelPosition.value, moved: false };
}

function onPanelPointerMove(event: PointerEvent) {
    if (!dragStart) return;

    const dx = event.clientX - dragStart.pointerX;
    const dy = event.clientY - dragStart.pointerY;
    if (!dragStart.moved) {
        if (Math.hypot(dx, dy) < 6) return;
        dragStart.moved = true;
        draggingPanel.value = true;
        panel.value?.setPointerCapture(event.pointerId);
    }

    panelPosition.value = clampPanel(dragStart.x + dx, dragStart.y + dy);
}

function onPanelPointerUp() {
    if (dragStart?.moved) store(PANEL_KEY, panelPosition.value);
    dragStart = null;
    // O clique que vem logo depois de soltar o arrasto não aciona botão
    setTimeout(() => (draggingPanel.value = false));
}

function onPanelClickCapture(event: MouseEvent) {
    if (draggingPanel.value) {
        event.stopPropagation();
        event.preventDefault();
    }
}

// O balão do modo abre para o lado em que há espaço
const panelOnRight = computed(() => panelPosition.value.x > window.innerWidth / 2);

function onResize() {
    panelPosition.value = clampPanel(panelPosition.value.x, panelPosition.value.y);
    // As linhas mudam com a largura do texto
    if (mode.value === 'line') regroup();
}

async function setUp() {
    stopTimer();
    state.value = 'idle';
    index = 0;
    litFrom = 0;
    litTo = -1;
    await nextTick();
    wrapWords();
    seen = new Uint8Array(words.length);
    seenCount = 0;
    completed = false;
    lastPercent = 0;
    buildGroups();
    // Resumo sem texto não tem o que ler
    if (!words.length) emit('finished');
}

watch(() => props.html, setUp);

watch(
    () => props.scroller,
    (scroller, previous) => {
        previous?.removeEventListener('scroll', onScroll);
        scroller?.addEventListener('scroll', onScroll, { passive: true });
    },
    { immediate: true },
);

onMounted(async () => {
    const stored = Number(readStored(WPM_KEY, WPM_DEFAULT));
    // O valor guardado entra na grade do passo atual
    wpm.value = Number.isFinite(stored) ? Math.min(WPM_MAX, Math.max(WPM_MIN, Math.round(stored / WPM_STEP) * WPM_STEP)) : WPM_DEFAULT;

    if (readStored<Mode>(MODE_KEY, 'chunk') === 'line') mode.value = 'line';

    await setUp();

    const saved = readStored<{ x: number; y: number } | null>(PANEL_KEY, null);
    panelPosition.value =
        saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)
            ? clampPanel(saved.x, saved.y)
            : // À direita, na altura do dedão
              clampPanel(window.innerWidth, window.innerHeight * 0.5 - (panel.value?.offsetHeight ?? 170) / 2);

    window.addEventListener('resize', onResize);
    window.addEventListener('pointerup', onHoldEnd);
    window.addEventListener('pointercancel', onHoldEnd);
});

onBeforeUnmount(() => {
    stopTimer();
    if (modeHintTimer) clearTimeout(modeHintTimer);
    props.scroller?.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('pointerup', onHoldEnd);
    window.removeEventListener('pointercancel', onHoldEnd);
});
</script>

<template>
    <div
        ref="root"
        class="guided-reading"
        :class="{ 'is-reading': state !== 'idle', 'is-paused': state === 'paused' }"
        @click="onWordClick"
        @pointerdown="onHoldStart"
        @contextmenu="state === 'playing' && $event.preventDefault()"
    >
        <StudySummary :html="html" />
    </div>

    <Teleport to="body">
        <div
            ref="panel"
            class="reading-panel fixed z-40 flex select-none flex-col items-center gap-1 rounded-xl border-2 border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            :class="{ 'is-dragging': draggingPanel }"
            :style="{ left: `${panelPosition.x}px`, top: `${panelPosition.y}px` }"
            role="group"
            aria-label="Controles da leitura guiada"
            @pointerdown="onPanelPointerDown"
            @pointermove="onPanelPointerMove"
            @pointerup="onPanelPointerUp"
            @pointercancel="onPanelPointerUp"
            @click.capture="onPanelClickCapture"
        >
            <GripHorizontal class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />

            <div class="relative">
                <button type="button" class="panel-button" :aria-label="`Modo: ${MODE_LABELS[mode]}. Trocar o modo de leitura`" @click="toggleMode">
                    <WholeWord v-if="mode === 'chunk'" class="h-4 w-4" />
                    <Rows3 v-else class="h-4 w-4" />
                </button>
                <Transition name="mode-hint">
                    <div
                        v-if="modeHint"
                        class="pointer-events-none absolute top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-md dark:bg-gray-100 dark:text-gray-900"
                        :class="panelOnRight ? 'right-full mr-2.5' : 'left-full ml-2.5'"
                        role="status"
                    >
                        {{ MODE_LABELS[mode] }}
                    </div>
                </Transition>
            </div>

            <button type="button" class="panel-button" :disabled="wpm >= WPM_MAX" aria-label="Aumentar a velocidade" @click="changeSpeed(1)">
                <Plus class="h-4 w-4" />
            </button>

            <div class="flex flex-col items-center leading-none" aria-live="polite">
                <span class="text-xs font-bold tabular-nums text-gray-900 dark:text-white">{{ wpm }}</span>
                <span class="mt-0.5 text-[9px] text-gray-500 dark:text-gray-400" title="palavras por minuto">ppm</span>
            </div>

            <button type="button" class="panel-button" :disabled="wpm <= WPM_MIN" aria-label="Diminuir a velocidade" @click="changeSpeed(-1)">
                <Minus class="h-4 w-4" />
            </button>

            <button
                type="button"
                class="panel-button panel-button-main mt-1"
                :aria-label="state === 'playing' ? 'Pausar a leitura guiada' : 'Iniciar a leitura guiada'"
                @click="toggle"
            >
                <Pause v-if="state === 'playing'" class="h-4 w-4" fill="currentColor" />
                <Play v-else class="h-4 w-4" fill="currentColor" />
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
/* Fonte um pouco maior no resumo da fase, para acompanhar a leitura */
.guided-reading :deep(.study-summary) {
    font-size: 1.1875rem;
    line-height: 1.75;
}

.guided-reading :deep(.rw) {
    transition: opacity 0.25s ease;
}
/* Com a leitura aberta, segurar o texto prende o realce: nada de selecionar ou abrir menu */
.guided-reading.is-reading {
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}
.guided-reading.is-reading :deep(.rw) {
    opacity: 0.2;
    cursor: pointer;
}
.guided-reading.is-reading.is-paused :deep(.rw) {
    opacity: 0.45;
}
.guided-reading.is-reading :deep(.rw.on) {
    opacity: 1;
}

.mode-hint-enter-active,
.mode-hint-leave-active {
    transition: opacity 0.2s ease;
}
.mode-hint-enter-from,
.mode-hint-leave-to {
    opacity: 0;
}

.reading-panel {
    touch-action: none;
    cursor: grab;
}
.reading-panel.is-dragging {
    cursor: grabbing;
}

.panel-button {
    @apply flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-700 transition-colors dark:bg-gray-700 dark:text-gray-200;
}
.panel-button:disabled {
    @apply cursor-not-allowed opacity-40;
}
@media (hover: hover) {
    .panel-button:not(:disabled):hover {
        @apply bg-gray-200 dark:bg-gray-600;
    }
    .panel-button-main:not(:disabled):hover {
        @apply bg-purple-600;
    }
}
.panel-button-main {
    @apply bg-purple-500 text-white dark:bg-purple-500 dark:text-white;
}
</style>
