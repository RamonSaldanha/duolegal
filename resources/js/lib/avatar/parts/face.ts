/**
 * Traços do rosto: sobrancelha, cílio, olho, nariz e boca.
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
    LASH_T,
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
 * A sobrancelha sai em duas camadas porque o cílio tem de ser desenhado **depois
 * do olho** — antes, a cápsula branca o cobriria. Mesmo arranjo do cabelo.
 */
export interface BrowLayers {
    brow: string;
    lashes: string;
}

/**
 * Cílios: três traços no canto de fora e de cima da cápsula do olho.
 *
 * Duas medidas decidem se a peça lê como cílio ou como garra de passarinho, e
 * ambas erraram na primeira tentativa:
 *
 *   **A abertura do leque.** Espalhados em 68 graus — o de baixo quase deitado,
 *   o de cima quase em pé — os três viram uma pata. Fechados em 25 graus, todos
 *   varrendo para cima e para fora, viram cílio.
 *
 *   **A proporção.** Traço de 7 de comprimento por 4 de grossura é um toco. A
 *   2:1 (LASH_T 3) ele afina o bastante para ler como fio.
 *
 * Cada raiz entra ~1px na cápsula (centro 73,5/79,5, raio 11,5): desenhados
 * depois do olho, é esse pedacinho por cima do branco que prende o cílio à
 * pálpebra em vez de deixá-lo boiando ao lado do rosto.
 *
 * As pontas param em x 59 — a cabeça começa em 53, e cílio furando a silhueta lê
 * como falha de render. Em y param em 63, com 5px de folga até a sobrancelha.
 */
const LASHES = ['M64.5,74L59,70', 'M67.5,70.5L63,65.5', 'M71,68.5L68,63'];

/**
 * Três opções na mesma faixa (y 49..62), então trocar de uma para a outra nunca
 * mexe no resto do rosto:
 *
 *   reta      barra grossa chapada
 *   arqueada  traço que sobe no meio e desce nas duas pontas
 *   cilios    o mesmo traço, mais fino e de arco mais alto, com os cílios
 *
 * `cilios` não é só "arqueada mais cílio": a sobrancelha dela é 1px mais fina e
 * o arco sobe quase 2px a mais. Senão duas das três opções sairiam iguais na
 * miniatura do editor.
 */
export function brows(style: BrowStyle, color: string): BrowLayers {
    if (style === 'cilios') {
        const arch = `M${BROW_X},60Q${BROW_X + 12},46 ${BROW_X + BROW_W},55`;

        return {
            brow: both(`<path d="${arch}" fill="none" stroke="${color}" stroke-width="4.5" stroke-linecap="round"/>`),
            lashes: both(
                LASHES.map((d) => `<path d="${d}" fill="none" stroke="${color}" stroke-width="${LASH_T}" stroke-linecap="round"/>`).join(''),
            ),
        };
    }

    if (style === 'arqueada') {
        const arch = `M${BROW_X},60Q${BROW_X + 12},49 ${BROW_X + BROW_W},56`;

        return {
            brow: both(`<path d="${arch}" fill="none" stroke="${color}" stroke-width="5.5" stroke-linecap="round"/>`),
            lashes: '',
        };
    }

    return {
        brow: both(`<path d="${roundedRect(BROW_X, BROW_Y, BROW_W, BROW_T, BROW_T / 2)}" fill="${color}"/>`),
        lashes: '',
    };
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
