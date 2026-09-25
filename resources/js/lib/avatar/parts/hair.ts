/**
 * Cabelo.
 *
 * Dois cortes, cada um com a sua própria lógica de camada:
 *
 *   comprido  sai em **duas** camadas. A massa (`back`) é maciça e vai antes do
 *             pescoço e da roupa, então a gola passa por cima dela — é o cabelo
 *             cobrindo a nuca e saindo por trás da roupa. A cortina (`front`)
 *             cai sobre a testa e vai depois da cabeça, senão o rosto a cobre.
 *   curto     só `front`. Ele para no alto da orelha, então não existe nada
 *             dele atrás do pescoço para desenhar.
 *
 * Os dois foram traçados a partir de imagens de referência, adaptados à grade
 * daqui — o rosto das referências é mais largo que alto e o daqui é o contrário
 * (94x132), então as curvas foram remapeadas em proporção da cabeça, não
 * copiadas em escala.
 *
 * O limite que vale para qualquer corte novo: **a franja não pode passar de
 * y 46**. A sobrancelha é desenhada depois do cabelo, então franja que desça
 * sobre a faixa dela (y 49..62) faz a sobrancelha reaparecer por cima do cabelo,
 * o que lê como erro de camada. Nas referências isso não é problema porque
 * nenhuma delas tem sobrancelha desenhada.
 */

import type { HairStyle } from '../types';

export interface HairLayers {
    back: string;
    front: string;
}

const EMPTY: HairLayers = { back: '', front: '' };

/**
 * Massa.
 *
 * O que dá a fluidez é a **tangente contínua em toda junção**: cada trecho sai
 * na mesma direção em que o anterior chegou, então a silhueta nunca quebra. A
 * única quina de propósito é o entalhe do alto. A versão anterior tinha platôs
 * retos e aberturas bruscas, e lia como capacete.
 *
 * O alto são dois lóbulos redondos, como na referência. O esquerdo é maior e
 * mais alto, com pico em (74,3). O direito tem pico em (138,9), e o entalhe
 * entre os dois fica em (112,14), fora do centro. Cada lóbulo é quase um quarto
 * de círculo: com raio muito diferente na horizontal e na vertical, ele vira
 * canto de caixa arredondado.
 *
 * As laterais são uma onda só, quase espelhada; a assimetria fica no alto.
 *
 *   bojo     x 12 / 188, em y≈105 — na altura da orelha, que aparece por cima
 *   cintura  x 31 / 169, em y≈155 — no queixo
 *   pé       x 6 / 194 — volta a abrir e sai pela borda do canvas
 *
 * No bojo e na cintura, o lado direito fica 2px abaixo do esquerdo: o bastante
 * para não sair como carimbo espelhado.
 *
 * Recorte redondo: os lóbulos encostam no círculo inscrito e perdem no máximo
 * 1–3px. O bojo e a cintura ficam dentro dele, então a onda sobrevive no avatar
 * de 40px do header. Só a abertura do pé é cortada.
 */
const MASS =
    'M112,14C104,4 90,1 74,3C52,5 33,24 30,52C28,76 12,84 12,104C12,128 31,132 31,154C31,174 12,184 6,200' +
    'L194,200C188,184 169,174 169,156C169,134 188,130 188,106C188,86 174,72 172,48C170,24 156,9 138,9C126,9 117,10 112,14Z';

/**
 * Cortina: a parte do cabelo que cobre o alto da cabeça.
 *
 * O contorno de cima **reaproveita as curvas da massa**, dos dois lóbulos e do
 * entalhe, ponto por ponto. Assim não existe emenda entre as camadas, e nenhum
 * canto do rosto vaza entre elas num risco de pele.
 *
 * A borda de baixo é a linha do cabelo, com a risca no meio e o vértice em
 * (100,23). Cada metade desce íngreme perto da risca, atravessa a testa em
 * y≈39–45 e desce pela lateral do rosto. É isso que faz as duas metades
 * parecerem cabelo caindo para os lados, não uma faixa. Sobre a sobrancelha
 * (x 62–85), o ponto mais baixo é y≈44,7, na ponta de fora — dentro do limite
 * de y 46.
 *
 * Nas laterais a cortina fecha por dentro da massa, onde a emenda é invisível,
 * e desce só até y 64. A orelha começa em 78 e aparece por cima do cabelo, então
 * a cortina não pode descer até ela.
 */
const CURTAIN =
    'M30,52C33,24 52,5 74,3C90,1 104,4 112,14C117,10 126,9 138,9C156,9 170,24 172,48' +
    'L147,64C147,58 146,54 144,50C140,42 128,42 118,39C109,36 103,30 100,23' +
    'C97,30 91,36 82,39C72,42 60,42 56,50C54,54 53,58 53,64Z';

/**
 * Curto.
 *
 * Capacete que sobra para fora da cabeça dos dois lados, desce até encostar no
 * alto da orelha (y 78) e termina em costeleta de ponta arredondada.
 *
 * O que dá o caráter não é a silhueta de fora, é a **franja recortada**: a linha
 * do cabelo não é um arco, é uma sequência de lobos arredondados pendurados,
 * separados por entalhes que sobem em ponta. Franja lisa no mesmo contorno vira
 * touca de natação.
 *
 * Três medidas mandam aqui:
 *
 *   **O pé da franja para em y 46.** A faixa da sobrancelha começa em 49, e a
 *   sobrancelha é desenhada DEPOIS do cabelo — descer mais e ela reapareceria
 *   por cima da franja, o que lê como erro de camada.
 *
 *   **A costeleta para em y 76.** A orelha começa em 78; passar disso cobre o
 *   alto dela e o corte perde a referência de tamanho.
 *
 *   **O topete.** Uma mecha em pé fora do eixo, à esquerda. É o único ponto
 *   assimétrico de propósito do desenho; sem ele o capacete fica cabeça de lego.
 */
const CAP =
    // Contorno de fora: costeleta esquerda, lado, topete, coroa, lado, costeleta direita.
    'M57,72C57,79 50,83 45,76C40,62 40,43 49,30C54,22 61,16 68,13' +
    'L67,4C71,6 74,8 77,10C90,3 114,3 128,13C144,22 157,35 157,52' +
    'C157,67 152,76 147,79C142,81 140,75 140,68' +
    // Franja, da direita para a esquerda: varredura longa, entalhe, lobo, entalhe, lobo.
    'C139,57 133,46 126,47C118,48 108,46 103,41' +
    'C101,39 99,37 98,34C95,39 91,45 86,45' +
    'C81,45 77,41 74,36C71,41 69,46 66,46C62,46 58,54 57,72Z';

export function hair(style: HairStyle, color: string): HairLayers {
    if (style === 'comprido') {
        return {
            back: `<path d="${MASS}" fill="${color}"/>`,
            front: `<path d="${CURTAIN}" fill="${color}"/>`,
        };
    }

    // O curto não tem camada de trás: ele para no alto da orelha, então não há
    // nada dele atrás do pescoço nem da roupa.
    if (style === 'curto') {
        return { back: '', front: `<path d="${CAP}" fill="${color}"/>` };
    }

    return EMPTY;
}
