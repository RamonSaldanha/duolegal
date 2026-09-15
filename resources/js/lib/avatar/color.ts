/**
 * Utilitários de cor do avatar.
 *
 * Todo o volume do desenho vem de camadas de cor sólida derivadas destas
 * funções — o sistema não usa gradiente em lugar nenhum.
 */

const HEX = /^#[0-9a-f]{6}$/i;

export function isHexColor(value: unknown): value is string {
    return typeof value === 'string' && HEX.test(value);
}

/**
 * Clareia (`amount` positivo) ou escurece (`amount` negativo) uma cor hex.
 * `amount` vai de -1 a 1: `shade('#3366cc', -0.28)` devolve o tom de sombra.
 */
export function shade(hex: string, amount: number): string {
    const n = parseInt(hex.slice(1), 16);
    const target = amount < 0 ? 0 : 255;
    const p = Math.min(Math.abs(amount), 1);

    const mix = (channel: number) => Math.round((target - channel) * p + channel);

    const r = mix((n >> 16) & 255);
    const g = mix((n >> 8) & 255);
    const b = mix(n & 255);

    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

/** Luminância percebida, de 0 a 255. */
export function luminance(hex: string): number {
    const n = parseInt(hex.slice(1), 16);
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;

    return (r * 299 + g * 587 + b * 114) / 1000;
}

/**
 * Tom de contraste sobre a pele — orelha, pescoço, listra do nariz e boca.
 *
 * Sempre escurece, em toda a paleta. Nos tons retintos isso deixa o traço bem
 * discreto (no `#45291D` o nariz quase encosta no tom da pele), e a alternativa
 * de clarear nesses tons foi comparada lado a lado e descartada: a sombra em
 * volta do rosto tem que ser sombra, mesmo quando sutil.
 *
 * `shade` com valor negativo multiplica os canais, então o matiz se mantém — o
 * marrom escurece continuando marrom, sem puxar para cinza.
 */
export function skinInk(skin: string, strength: number): string {
    return shade(skin, -strength);
}
