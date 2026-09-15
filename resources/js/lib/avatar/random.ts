import { luminance } from './color';
import { BACKGROUND_COLORS, BODIES_OPTIONS, BROWS, CLOTHES_COLORS, HAIRS, HAIR_COLORS, SKIN_COLORS } from './options';
import type { AvatarConfig } from './types';

function pick<T>(list: readonly T[]): T {
    return list[Math.floor(Math.random() * list.length)];
}

/**
 * Cores da lista que destoam o bastante da pele para não sumirem contra ela.
 * Vale para o fundo (senão a cabeça some) e para a sobrancelha (senão o rosto
 * fica sem expressão). Escolher na mão continua livre: o editor mostra tudo.
 */
function contrastingWith(skin: string, list: string[]): string[] {
    const usable = list.filter((c) => Math.abs(luminance(c) - luminance(skin)) > 40);

    return usable.length ? usable : list;
}

export function randomAvatarConfig(): AvatarConfig {
    const skin = pick(SKIN_COLORS);

    return {
        body: pick(BODIES_OPTIONS).id,
        hair: pick(HAIRS).id,
        brows: pick(BROWS).id,
        skin,
        hairColor: pick(contrastingWith(skin, HAIR_COLORS)),
        clothesColor: pick(CLOTHES_COLORS),
        background: pick(contrastingWith(skin, BACKGROUND_COLORS)),
    };
}
