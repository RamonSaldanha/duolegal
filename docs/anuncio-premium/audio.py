# Trilha do anúncio: batida leve sintetizada + sons do app nos momentos da animação
import wave
import numpy as np

SR = 44100
DUR = 24.6
N = int(SR * DUR)
rng = np.random.default_rng(4)
APP = '../../../../memorize-mobile/assets/audio/'
OUT = 'track.wav'

music = np.zeros((N, 2))
sfx = np.zeros((N, 2))


def put(buf, t, sig, gain=1.0, pan=0.0):
    i = int(t * SR)
    if sig.ndim == 1:
        sig = np.stack([sig * (1 - max(pan, 0)), sig * (1 + min(pan, 0))], axis=1)
    j = min(N, i + len(sig))
    if j > i:
        buf[i:j] += sig[: j - i] * gain


def env(n, a=0.005, d=0.2):
    t = np.arange(n) / SR
    return np.minimum(1, t / a) * np.exp(-t / d)


def note(f):
    return 440 * 2 ** ((f - 69) / 12)


def lowpass(x, cutoff):
    # filtro de um polo, suficiente para amaciar
    a = np.exp(-2 * np.pi * cutoff / SR)
    y = np.zeros_like(x)
    acc = 0.0
    for k in range(len(x)):
        acc = (1 - a) * x[k] + a * acc
        y[k] = acc
    return y


def kick():
    n = int(0.35 * SR)
    t = np.arange(n) / SR
    f = 45 + 95 * np.exp(-t * 28)
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 9)


def hat():
    n = int(0.06 * SR)
    x = rng.standard_normal(n)
    x = x - np.concatenate([[0], x[:-1]])
    return x * env(n, 0.001, 0.015) * 0.5


def clap():
    n = int(0.22 * SR)
    x = rng.standard_normal(n)
    x = x - lowpass(x, 900)
    e = np.zeros(n)
    for off in (0, 0.012, 0.024):
        e += np.roll(env(n, 0.001, 0.05), int(off * SR))
    return x * e * 0.35


def pluck(freq, dur, bright=0.3):
    n = int(dur * SR)
    t = np.arange(n) / SR
    x = sum(np.sin(2 * np.pi * freq * h * t) * (bright ** (h - 1)) / h for h in range(1, 6))
    return x * env(n, 0.004, dur * 0.35)


def pad(freqs, dur, vol=0.12):
    n = int(dur * SR)
    t = np.arange(n) / SR
    x = sum(np.sin(2 * np.pi * f * t + 0.3 * np.sin(2 * np.pi * 0.5 * t)) + 0.3 * np.sin(2 * np.pi * 2 * f * t) for f in freqs)
    fade = np.minimum(1, t / 0.25) * np.minimum(1, (dur - t) / 0.3)
    return x * fade * vol / len(freqs)


def load(name):
    w = wave.open(APP + name)
    x = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype(float) / 32768
    if w.getnchannels() == 2:
        x = x.reshape(-1, 2)
    return x


# ---- música
BEAT = 0.5  # 120 bpm
# antes do Premium: um pad menor e apagado
put(music, 0.0, pad([note(57), note(60), note(64)], 3.05, 0.10))
# descida de "bateria acabando"
n = int(0.9 * SR); tt = np.arange(n) / SR
put(sfx, 0.6, np.sin(2 * np.pi * np.cumsum(660 * np.exp(-tt * 1.6)) / SR) * env(n, 0.01, 0.45) * 0.18)

prog = [(48, [60, 64, 67]), (43, [59, 62, 67]), (45, [57, 60, 64]), (41, [57, 60, 65])]  # C G Am F
t0 = 3.0
bar = 0
t = t0
while t < 23.4:
    root, chord = prog[(bar // 1) % 4]
    for b in range(4):
        tb = t + b * BEAT
        if tb >= 23.4:
            break
        put(music, tb, kick(), 0.55)
        put(music, tb + BEAT / 2, hat(), 0.25, pan=0.3)
        if tb >= 5.6 and b in (1, 3):
            put(music, tb, clap(), 0.5, pan=-0.1)
        for e8 in range(2):
            put(music, tb + e8 * BEAT / 2, pluck(note(root), 0.24, 0.45), 0.22)
        if b in (0, 2):
            put(music, tb + 0.25, pad([note(c + 12) for c in chord], 0.3, 0.16), 1.0, pan=0.15 * (1 if b else -1))
    put(music, t, pad([note(c) for c in chord], 2.0, 0.09))
    t += 4 * BEAT
    bar += 1
# fecho em dó
put(music, 23.4, kick(), 0.6)
put(music, 23.4, pad([note(48), note(60), note(64), note(67), note(72)], 1.2, 0.22))

# ---- efeitos


def whoosh(dur=0.6):
    n = int(dur * SR)
    x = rng.standard_normal(n)
    t = np.arange(n) / SR
    out = np.zeros(n)
    # passa-faixa que sobe
    a_lo, acc_lo, acc_hi = 0, 0.0, 0.0
    for k in range(n):
        fc = 300 + 3500 * (k / n)
        a = np.exp(-2 * np.pi * fc / SR)
        acc_lo = (1 - a) * x[k] + a * acc_lo
        out[k] = acc_lo
    shape = np.sin(np.pi * t / dur) ** 2
    return out * shape * 2.2


def zap():
    n = int(0.7 * SR)
    t = np.arange(n) / SR
    f = 1800 * np.exp(-t * 6) + 80
    saw = 2 * ((np.cumsum(f) / SR) % 1) - 1
    noise = rng.standard_normal(n) * np.exp(-t * 14)
    return (saw * 0.35 + noise * 0.5) * env(n, 0.002, 0.25)


def tap():
    n = int(0.05 * SR)
    t = np.arange(n) / SR
    return np.sin(2 * np.pi * 1400 * t) * env(n, 0.001, 0.012)


def blip(freq):
    return pluck(freq, 0.18, 0.2)


def pop():
    n = int(0.18 * SR)
    t = np.arange(n) / SR
    f = 300 + 900 * (1 - np.exp(-t * 30))
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.002, 0.06)


def puff():
    n = int(0.4 * SR)
    x = lowpass(rng.standard_normal(n), 1200)
    return x * env(n, 0.005, 0.12) * 3


for mid in (5.6, 10.4, 21.2):
    put(sfx, mid - 0.32, whoosh(), 0.35)
put(sfx, 3.0, zap(), 0.5)
put(sfx, 3.7, load('bell-win.wav'), 0.55)
for tt_ in (6.55, 7.15, 7.78):
    put(sfx, tt_, tap(), 0.5)
put(sfx, 7.88, load('correct.wav'), 0.9)
put(sfx, 8.05, zap(), 0.4)
for i in range(6):
    put(sfx, 11.3 + i * 0.16, blip(note(72 + [0, 2, 4, 7, 9, 12][i])), 0.35)
put(sfx, 13.8, pop(), 0.6)
put(sfx, 14.65, pop(), 0.6)
put(sfx, 16.85, puff(), 0.45)
put(sfx, 17.15, load('correct.wav'), 0.8)
put(sfx, 18.4, pop(), 0.5)
for i in range(4):
    put(sfx, 19.85 + i * 0.12, blip(note(76 + [0, 3, 7, 12][i])), 0.25)
put(sfx, 21.6, load('phase-complete.wav'), 0.9)
put(sfx, 23.3, tap(), 0.5)

mix = music * 0.8 + sfx
# fade final
fade = np.ones(N)
fn = int(0.6 * SR)
fade[-fn:] = np.linspace(1, 0, fn)
mix *= fade[:, None]
mix = np.tanh(mix * 1.1) / np.tanh(1.1)
mix *= 0.89 / np.max(np.abs(mix))
w = wave.open(OUT, 'wb')
w.setnchannels(2)
w.setsampwidth(2)
w.setframerate(SR)
w.writeframes((mix * 32767).astype(np.int16).tobytes())
w.close()
print('ok', OUT)
