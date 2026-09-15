/**
 * Traços do rosto: sobrancelha, olho, nariz e boca.
 *
 * Tudo chapado e sem contorno. Dois pontos são a assinatura do estilo e não têm
 * variação: o olho tem parte branca com pupila por cima, e o nariz é uma listra
 * reta e grossa — nada de "L", de curva ou de triangulinho.
 */

import { skinInk } from '../color';
import {
    BROW_T,
    BROW_W,
    BROW_X,
    BROW_Y,
    EYE_H,
    EYE_W,
    EYE_X,
    EYE_Y,
    INK,
    MOUTH_DROP,
    MOUTH_W,
    MOUTH_Y,
    NOSE_H,
    NOSE_W,
    NOSE_Y,
    PUPIL_R,
    PUPIL_X,
    PUPIL_Y,
    roundedRect,
} from '../geometry';
import type { BrowStyle } from '../types';
import { both } from './svg';

/**
 * Reta é a barra grossa chapada; arqueada é um traço fino que sobe no meio e
 * desce nas duas pontas. As duas ocupam a mesma faixa (y 49..62), então trocar
 * de uma para a outra não mexe no resto do rosto.
 */
export function brows(style: BrowStyle, color: string): string {
    if (style === 'arqueada') {
        const arch = `M${BROW_X},60Q${BROW_X + 12},49 ${BROW_X + BROW_W},56`;

        return both(`<path d="${arch}" fill="none" stroke="${color}" stroke-width="5.5" stroke-linecap="round"/>`);
    }

    return both(`<path d="${roundedRect(BROW_X, BROW_Y, BROW_W, BROW_T, BROW_T / 2)}" fill="${color}"/>`);
}

/** Cápsula branca com a pupila por cima, puxada para o lado de dentro. */
export function eyes(): string {
    return both(
        `<path d="${roundedRect(EYE_X, EYE_Y, EYE_W, EYE_H, EYE_W / 2)}" fill="#FFFFFF"/>` +
            `<circle cx="${PUPIL_X}" cy="${PUPIL_Y}" r="${PUPIL_R}" fill="${INK}"/>`,
    );
}

/**
 * Listra reta e grossa, num tom de contraste da pele. Começa na altura do olho
 * e desce entre os dois, como nas referências.
 */
export function nose(skin: string): string {
    return `<path d="${roundedRect(100 - NOSE_W / 2, NOSE_Y, NOSE_W, NOSE_H, NOSE_W / 2)}" fill="${skinInk(skin, 0.2)}"/>`;
}

/** Arco raso, do mesmo tom da listra do nariz, para não competir com ela. */
export function mouth(skin: string): string {
    const x = 100 - MOUTH_W / 2;

    return (
        `<path d="M${x},${MOUTH_Y}Q100,${MOUTH_Y + MOUTH_DROP * 2} ${x + MOUTH_W},${MOUTH_Y}"` +
        ` fill="none" stroke="${skinInk(skin, 0.26)}" stroke-width="5" stroke-linecap="round"/>`
    );
}
