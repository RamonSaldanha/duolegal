/**
 * Montagem do SVG do avatar.
 *
 * `buildAvatarSvg` é uma função pura de string — sem DOM, sem estado. Isso deixa
 * barato chamá-la dentro de `computed`, inclusive nas miniaturas do editor.
 *
 * **Sem moldura.** O fundo sangra o quadrado inteiro e quem recorta é o container
 * onde o avatar aparece: círculo no header e no ranking, quadrado arredondado no
 * palco do editor.
 */

import { earrings, glasses } from './parts/accessories';
import { clothes } from './parts/clothes';
import { brows, eyes, mouth, nose } from './parts/face';
import { hair } from './parts/hair';
import { ears, head, neck } from './parts/head';
import { normalizeAvatarConfig } from './normalize';
import type { AvatarConfig, BuildOptions } from './types';

export function buildAvatarSvg(raw: Partial<AvatarConfig> | null | undefined, options: BuildOptions = {}): string {
    const c = normalizeAvatarConfig(raw);
    const { crop, background = true } = options;

    const viewBox = crop ? crop.join(' ') : '0 0 200 200';
    const locks = hair(c.hair, c.hairColor);
    const face = brows(c.brows, c.hairColor);

    // Ordem de empilhamento, de trás para frente. A orelha vai no fundo, para a
    // cabeça cobrir a parte de dentro e sobrar só a silhueta — e, com cabelo
    // comprido, para a massa escondê-la por inteiro. A massa vem em seguida, de
    // modo que o pescoço e a roupa passem por cima dela: é o cabelo cobrindo
    // toda a parte de trás do pescoço e saindo por trás da gola. A cortina só
    // pode vir depois da cabeça, senão o rosto a cobre.
    //
    // Os três encaixes que não são óbvios:
    //
    //   brinco  entre a roupa e a cabeça. Depois da massa do cabelo, senão a
    //           argola fica enterrada junto com a orelha; e antes da cabeça,
    //           para o rosto cobrir o pedaço do aro que passa por cima dele —
    //           é isso que faz a argola atravessar o lóbulo em vez de ficar
    //           colada na bochecha.
    //   cílio   depois do olho, senão a cápsula branca o cobre.
    //   óculos  depois do nariz, para a ponte passar por cima da listra.
    const scene =
        ears(c.skin) +
        locks.back +
        neck(c.body, c.skin) +
        clothes(c.body, c.clothesColor, c.skin) +
        earrings(c.earrings, c.earringColor) +
        head(c.skin) +
        locks.front +
        face.brow +
        eyes() +
        face.lashes +
        nose(c.skin) +
        glasses(c.glasses, c.glassesColor) +
        mouth(c.skin);

    const backdrop = background ? `<rect x="0" y="0" width="200" height="200" fill="${c.background}"/>` : '';

    return (
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}" aria-hidden="true" focusable="false">` +
        backdrop +
        scene +
        `</svg>`
    );
}
