// Sons do jogo, feitos na hora com a Web Audio API: notas senoidais curtas e baixas, que
// somem rápido. Mais suaves que um arquivo de sino e sem nada para baixar.

let context: AudioContext | null = null;

function audioContext(): AudioContext | null {
    try {
        context ??= new AudioContext();
        if (context.state === 'suspended') void context.resume();
        return context;
    } catch {
        // Sem som não atrapalha o jogo
        return null;
    }
}

/** Toca as notas (em Hz) uma depois da outra, cada uma sumindo aos poucos. */
function chime(notes: number[], { gap = 0.11, length = 0.32, volume = 0.14 } = {}) {
    const ac = audioContext();
    if (!ac) return;

    notes.forEach((frequency, index) => {
        const start = ac.currentTime + index * gap;
        const oscillator = ac.createOscillator();
        const gain = ac.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + length);

        oscillator.connect(gain).connect(ac.destination);
        oscillator.start(start);
        oscillator.stop(start + length + 0.05);
    });
}

/** Acerto: duas notas subindo (sol e dó). */
export function playCorrect() {
    chime([783.99, 1046.5]);
}

/** Fim de fase: um arpejo curto de dó maior. */
export function playPhaseComplete() {
    chime([523.25, 659.25, 783.99, 1046.5], { gap: 0.12, length: 0.45, volume: 0.13 });
}
