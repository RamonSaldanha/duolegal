/**
 * Cabelo.
 *
 * Três cortes, cada um com a sua própria lógica de camada:
 *
 *   comprido  sai em **duas** camadas. A massa (`back`) é maciça e vai antes do
 *             pescoço e da roupa, então a gola passa por cima dela — é o cabelo
 *             cobrindo a nuca e saindo por trás da roupa. A cortina (`front`)
 *             cai sobre a testa e vai depois da cabeça, senão o rosto a cobre.
 *   chanel    as mesmas duas camadas, mas a massa termina na altura do queixo,
 *             antes de chegar à roupa, e a cortina é uma franja lateral.
 *   curto     só `front`. Ele para no alto da orelha, então não existe nada
 *             dele atrás do pescoço para desenhar.
 *
 * Os três foram traçados a partir de imagens de referência, adaptados à grade
 * daqui — o rosto das referências é mais largo que alto e o daqui é o contrário
 * (94x132), então as curvas foram remapeadas em proporção da cabeça, não
 * copiadas em escala.
 *
 * O limite que vale para qualquer corte novo: **a franja não pode cortar a
 * sobrancelha ao meio**. A sobrancelha é desenhada depois do cabelo e na mesma
 * cor dele, então só existem dois jeitos certos:
 *
 *   parar acima da faixa dela (y 49..62), com a borda em y 46 no máximo — é o
 *   que o curto e o comprido fazem;
 *
 *   descer abaixo dela e cobri-la inteira, a partir de y 63 — ela some na cor
 *   do cabelo. É o que a franja do chanel faz do lado esquerdo.
 *
 * Borda no meio da faixa faz a parte de baixo da sobrancelha reaparecer grudada
 * na franja, o que lê como erro de camada. E nenhuma franja pode chegar ao olho
 * (y 68): ele também é desenhado depois e apareceria por cima do cabelo. As
 * referências não têm sobrancelha e deixam a franja cobrir o olho; aqui não dá.
 */

import type { HairStyle } from '../types';

export interface HairLayers {
    back: string;
    front: string;
}

const EMPTY: HairLayers = { back: '', front: '' };

/**
 * Coroa do comprido e do chanel: dois lóbulos redondos, como nas referências.
 * O esquerdo é maior e mais alto, com pico em (74,3); o direito tem pico em
 * (138,9), e o entalhe entre os dois fica em (112,14), fora do centro. Cada
 * lóbulo é quase um quarto de círculo: com raio muito diferente na horizontal e
 * na vertical, ele vira canto de caixa arredondado.
 *
 * Vai da lateral esquerda (30,52) à direita (172,48) passando por cima da
 * cabeça. A massa e a cortina de cada corte começam por ela, então as duas
 * camadas têm o mesmo contorno de cima: não existe emenda entre elas, e nenhum
 * canto do rosto vaza no meio num risco de pele.
 *
 * No recorte redondo os lóbulos encostam no círculo inscrito e perdem no máximo
 * 1–3px.
 */
const CROWN = 'M30,52C33,24 52,5 74,3C90,1 104,4 112,14C117,10 126,9 138,9C156,9 170,24 172,48';

/**
 * Massa do comprido.
 *
 * O que dá a fluidez é a **tangente contínua em toda junção**: cada trecho sai
 * na mesma direção em que o anterior chegou, então a silhueta nunca quebra. A
 * única quina de propósito é o entalhe da coroa. A versão anterior tinha platôs
 * retos e aberturas bruscas, e lia como capacete.
 *
 * As laterais são uma onda só, quase espelhada; a assimetria fica na coroa.
 *
 *   bojo     x 12 / 188, em y≈105 — na altura da orelha, que aparece por cima
 *   cintura  x 31 / 169, em y≈155 — no queixo
 *   pé       x 6 / 194 — volta a abrir e sai pela borda do canvas
 *
 * No bojo e na cintura, o lado direito fica 2px abaixo do esquerdo: o bastante
 * para não sair como carimbo espelhado.
 *
 * No recorte redondo o bojo e a cintura ficam dentro do círculo, então a onda
 * sobrevive no avatar de 40px do header. Só a abertura do pé é cortada.
 */
const LONG_MASS =
    CROWN +
    'C174,72 188,86 188,106C188,130 169,134 169,156C169,174 188,184 194,200' +
    'L6,200C12,184 31,174 31,154C31,132 12,128 12,104C12,84 28,76 30,52Z';

/**
 * Cortina do comprido: a parte do cabelo que cobre o alto da cabeça.
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
const LONG_CURTAIN =
    CROWN + 'L147,64C147,58 146,54 144,50C140,42 128,42 118,39C109,36 103,30 100,23' + 'C97,30 91,36 82,39C72,42 60,42 56,50C54,54 53,58 53,64Z';

/**
 * Massa do chanel.
 *
 * Mesma coroa do comprido, mas as laterais descem cheias e param um pouco acima
 * do queixo, sem chegar à roupa:
 *
 *   bojo  x 21 / 179, em y 100 — atrás da orelha
 *   pé    y 145, 7px acima do queixo (152)
 *
 * **Sem ponta nenhuma**, como na referência: o pé é um canto arredondado. A
 * lateral recolhe um pouco depois do bojo (x 26 / 174 em y 137) e dobra numa
 * curva de raio ~9 até a borda de baixo, que corre quase reta.
 *
 * Do canto, a borda de baixo sobe de leve até sumir atrás da mandíbula, em
 * (62,140) e (138,140). Entre esses dois pontos ela corre escondida atrás da
 * cabeça.
 */
const BOB_MASS =
    CROWN +
    'C174,70 179,82 179,100C179,118 176,131 174,137C172,143 169,145 164,145C156,145 146,142 138,140' +
    'L62,140C54,142 44,145 36,145C31,145 28,143 26,137C24,131 21,118 21,100C21,82 28,70 30,52Z';

/**
 * Franja do chanel: lateral, pesada do lado esquerdo e subindo na diagonal.
 *
 * Do lado esquerdo ela **cobre a sobrancelha inteira**, o segundo jeito certo da
 * regra do cabeçalho: sobre a faixa dela (x 62–85) a borda fica entre y 64,5 e
 * 67,5 — abaixo do pé da sobrancelha (62,75) e acima do olho (68). A folga é
 * apertada de propósito: é o que deixa a franja pesada e rente ao olho, como na
 * referência.
 *
 * Em x 88 a borda vira e sobe na diagonal até o entalhe em (118,33). Dali para a
 * direita não tem ponta: a borda contorna o canto do rosto num quarto de
 * círculo de raio 29 e encontra a lateral da cabeça em (147,62) já na vertical,
 * então o canto de pele que sobra embaixo é arredondado. O arco passa pelo
 * menos 8px acima da sobrancelha direita em toda a largura dela.
 *
 * Como no comprido, a franja fecha por dentro da massa e para acima da orelha.
 */
const BOB_FRINGE = CROWN + 'L147,62C147,46 134,33 118,33C108,41 97,56 88,63C80,68 64,68 53,67Z';

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
            back: `<path d="${LONG_MASS}" fill="${color}"/>`,
            front: `<path d="${LONG_CURTAIN}" fill="${color}"/>`,
        };
    }

    if (style === 'chanel') {
        return {
            back: `<path d="${BOB_MASS}" fill="${color}"/>`,
            front: `<path d="${BOB_FRINGE}" fill="${color}"/>`,
        };
    }

    // O curto não tem camada de trás: ele para no alto da orelha, então não há
    // nada dele atrás do pescoço nem da roupa.
    if (style === 'curto') {
        return { back: '', front: `<path d="${CAP}" fill="${color}"/>` };
    }

    return EMPTY;
}
