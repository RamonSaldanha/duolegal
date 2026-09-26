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

import { normalizeAvatarConfig } from './normalize';
import { earrings, glasses } from './parts/accessories';
import { beard } from './parts/beard';
import { clothes } from './parts/clothes';
import { brows, eyes, mouth, nose } from './parts/face';
import { hair } from './parts/hair';
import { ears, head, neck } from './parts/head';
import { outfit } from './parts/outfit';
import type { AvatarConfig, BuildOptions } from './types';

/** Sequência para ids internos quando quem chama não passa `uid`. */
let idSequence = 0;

export function buildAvatarSvg(raw: Partial<AvatarConfig> | null | undefined, options: BuildOptions = {}): string {
    const c = normalizeAvatarConfig(raw);
    const { crop, background = true } = options;

    const viewBox = crop ? crop.join(' ') : '0 0 200 200';
    const costume = outfit(c.outfit, c.body);

    const locks = hair(c.hair, c.hairColor);
    const whiskers = beard(c.beard, c.hairColor);
    const face = brows(c.brows, c.hairColor);

    // A cabeça inteira, da massa do cabelo aos óculos. Com capacete ela vai,
    // menor, dentro do visor; sem, é intercalada com a roupa logo abaixo.
    const headAbove =
        head(c.skin) +
        locks.front +
        whiskers.under +
        face.brow +
        eyes() +
        face.lashes +
        mouth(c.mouth, c.skin) +
        whiskers.over +
        nose(c.skin) +
        glasses(c.glasses, c.glassesColor);

    // Ordem de empilhamento, de trás para frente. A massa do cabelo (comprido e
    // chanel) vai no fundo, de modo que o pescoço e a roupa passem por cima
    // dela: no comprido, é o cabelo cobrindo toda a parte de trás do pescoço e
    // saindo por trás da gola. A cortina só pode vir depois da cabeça, senão o
    // rosto a cobre.
    //
    // Os seis encaixes que não são óbvios:
    //
    //   fantasia  o corpo entra no lugar da roupa, na mesma camada. Com
    //             capacete, a cabeça inteira fica entre o vidro do visor e a
    //             casca, a 80%, recortada pelo visor. A toga não mexe na ordem:
    //             só encolhe a cena inteira no fim.
    //   orelha  depois da massa, para aparecer por cima do cabelo, como nas
    //           referências; e antes da cabeça, para o rosto cobrir a parte de
    //           dentro e sobrar só a silhueta.
    //   brinco  entre a roupa e a cabeça. Pendura na orelha, e o rosto cobre o
    //           pedaço do aro que passa por cima dele — é isso que faz a argola
    //           atravessar o lóbulo em vez de ficar colada na bochecha.
    //   barba   depois da cabeça e da cortina, por cima da orelha, como na
    //           referência. A barba grossa vem antes da boca, que a atravessa;
    //           o bigode vem depois, e o sorriso sai por baixo dele. O nariz
    //           passa por cima dos dois.
    //   cílio   depois do olho, senão a cápsula branca o cobre.
    //   óculos  depois da barba, para a armação passar por cima da costeleta.
    const headBehind = locks.back + ears(c.skin) + neck(c.body, c.skin);
    const earring = earrings(c.earrings, c.earringColor);

    let defs = '';
    let scene: string;

    if (costume.helmet) {
        // O id do recorte tem de ser único na página: o mesmo avatar aparece
        // várias vezes (header, ranking, miniaturas do editor).
        const id = `visor-${(options.uid ?? `a${(++idSequence).toString(36)}`).replace(/[^a-zA-Z0-9-]/g, '')}`;
        const { glass, clip, transform } = costume.helmet;

        defs = `<defs><clipPath id="${id}"><path d="${clip}"/></clipPath></defs>`;
        scene =
            costume.body + glass + `<g clip-path="url(#${id})"><g transform="${transform}">${headBehind + earring + headAbove}</g></g>` + costume.top;
    } else {
        scene = headBehind + (costume.body || clothes(c.body, c.clothesColor, c.skin)) + earring + headAbove + costume.top;

        // O coque passa do topo da tela, e a cena inteira encolhe para ele
        // caber. Só no avatar inteiro: as miniaturas do editor recortadas no
        // rosto esperam a cabeça no lugar de sempre, e o recorte já cortaria o
        // alto do coque de qualquer jeito. Com capacete também não: a cabeça vai
        // para dentro do visor, que corta o coque.
        const partial = !!crop && (crop[2] < 200 || crop[3] < 200);

        if (locks.fit && !partial) {
            scene = `<g transform="${locks.fit}">${scene}</g>`;
        }
    }

    // A toga é desenhada na grade normal e passa do fundo da tela: a cena
    // inteira encolhe junto para ela caber, e o fundo continua sangrando. Com o
    // coque, os dois encaixes se somam: a cena fica a 83%, o topo do coque em
    // y 1,4 e a barra da toga ainda abaixo do pé da tela.
    if (costume.fit) {
        scene = `<g transform="${costume.fit}">${scene}</g>`;
    }

    const backdrop = background ? `<rect x="0" y="0" width="200" height="200" fill="${c.background}"/>` : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}" aria-hidden="true" focusable="false">` + defs + backdrop + scene + `</svg>`;
}
