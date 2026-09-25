/**
 * Cabelo.
 *
 * Seis cortes, cada um com a sua própria lógica de camada:
 *
 *   comprido  sai em **duas** camadas. A massa (`back`) é maciça e vai antes do
 *             pescoço e da roupa, então a gola passa por cima dela — é o cabelo
 *             cobrindo a nuca e saindo por trás da roupa. A cortina (`front`)
 *             cai sobre a testa e vai depois da cabeça, senão o rosto a cobre.
 *   liso      as mesmas duas camadas do comprido, com a massa reta dos lados.
 *   chanel    as mesmas duas camadas, mas a massa termina na altura do queixo,
 *             antes de chegar à roupa, e a cortina é uma franja lateral.
 *   curto     só `front`. Ele para no alto da orelha, então não existe nada
 *   afro      dele atrás do pescoço para desenhar. Vale para os três.
 *   calvo
 *
 * Todos foram traçados a partir de imagens de referência, adaptados à grade
 * daqui — o rosto das referências é mais largo que alto e o daqui é o contrário
 * (94x132), então as curvas foram remapeadas em proporção da cabeça, não
 * copiadas em escala.
 *
 * O limite que vale para qualquer corte novo: **a franja não pode cortar a
 * sobrancelha ao meio**. A sobrancelha é desenhada depois do cabelo e na mesma
 * cor dele, então só existem dois jeitos certos:
 *
 *   parar acima da faixa dela (y 49..62), com a borda em y 46 no máximo — é o
 *   que o curto, o afro, o calvo, o comprido e o liso fazem;
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
 * O pé continua abrindo até y 232, fora da tela. Não aparece aqui, mas a toga
 * encolhe a cena inteira e traz esse trecho para dentro — ver `parts/outfit.ts`.
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
    'L206,232H-6L6,200C12,184 31,174 31,154C31,132 12,128 12,104C12,84 28,76 30,52Z';

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
 * Coroa do liso, **decalcada da imagem de referência** (798x795, cabeça em
 * x 213..618): dois lóbulos largos, com topo em y 5 e um entalhe raso no meio
 * (y 9,4), que descem num ombro cheio até as laterais retas em x 35,7 / 164,3.
 * As laterais ficam 17px para fora da cabeça, como na referência.
 *
 * Na vertical o decalque foi mapeado em trechos, porque a cabeça daqui é mais
 * alta que a de lá: a coroa e a testa numa escala de 0,31, a lateral do rosto
 * até o alto da orelha, o corpo até o queixo e o pé até a borda da tela — assim
 * os cantos arredondados de baixo continuam à vista, como na referência.
 *
 * Vai do lado esquerdo, na altura do alto da orelha (y 77,5), ao direito. A
 * massa e a franja começam por ela, então as duas camadas se encaixam sem
 * emenda.
 */
const STRAIGHT_CROWN = 'M35.7,77.5C35.7,32 52,5.04 84,5.04C90,5.04 96.5,6.6 100,9.35' + 'C103.5,6.6 110,5.04 116,5.04C148,5.04 164.3,32 164.3,77.5';

/** Massa do liso: a coroa, as laterais retas e os cantos de baixo, que entram atrás dos ombros. */
const STRAIGHT_MASS = STRAIGHT_CROWN + 'L164.2,165C164.2,178 156,192 145,200L55,200C44,192 35.8,178 35.8,165Z';

/**
 * Franja do liso: linha da testa reta em y 42,6, com a risca no meio (ápice em
 * y 32). Nas laterais ela cobre a borda do rosto até o alto da orelha (y 77,5),
 * onde a orelha passa a ficar por cima do cabelo. A borda de dentro fica em
 * x 57, a mesma folga do afro: nenhum estilo de sobrancelha nem o cílio encosta
 * nela (conferido ponto a ponto).
 *
 * Sem quina viva: a risca sai da linha da testa na horizontal antes de subir ao
 * ápice (só o ápice é pontudo, como na referência), e a faixa da lateral acaba
 * num quarto de círculo que encontra a borda da cabeça no alto da orelha.
 */
const STRAIGHT_FRINGE =
    STRAIGHT_CROWN +
    'L147,77.5C144.8,77.5 143,75.7 143,73.5L143,62C143,50 137,42.6 129,42.6L114,42.6C108,42.6 103.5,37.5 100,32' +
    'C96.5,37.5 92,42.6 86,42.6L71,42.6C63,42.6 57,50 57,62L57,73.5C57,75.7 55.2,77.5 53,77.5Z';

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

/**
 * Afro curto, **decalcado da imagem de referência**.
 *
 * O contorno foi extraído dos pixels da referência (596x773, cabeça em
 * x 146..475) e trazido para esta grade, com a linha da testa em y 44 e escala
 * vertical de 0,33. Com essa escala o fim da lateral de lá cai logo acima da
 * orelha daqui, como na referência.
 *
 * Lá o cabelo sai para fora da cabeça dos dois lados. Aqui **não**: as laterais
 * ficam rente à linha da cabeça, e a coroa foi redistribuída nessa largura
 * (x comprimido em 0,82, alturas mantidas). Mais largo que o rosto, o afro
 * parecia um capacete.
 *
 *   coroa     cinco lóbulos com vales em ponta entre eles: o do meio mais alto
 *             (y 8,5), os vizinhos em y 11,4 e os de fora em y 19,6.
 *   lado      em x 52,5 / 147,5 — meio pixel para fora da cabeça, para a pele
 *             não vazar na borda.
 *   costeleta dentro do rosto, em x 52,5..57, com ponta em meio círculo em
 *             y 73,3, acima da orelha (78). É estreita para não encostar no
 *             cílio de fora, que chega a x 57,5.
 *   testa     reta em y 44, com cantos arredondados que descem até a costeleta.
 *             Nenhum estilo de sobrancelha nem o cílio encosta na borda
 *             (conferido ponto a ponto).
 */
const AFRO =
    'M57,71C57,72.24 56,73.25 54.75,73.25C53.5,73.25 52.5,72.24 52.5,71L52.5,34' +
    // Coroa, da esquerda para a direita.
    'C52.5,24 58,19.6 65,19.6C67.4,19.6 69.1,20.3 69.8,21.2C71.1,14.5 76.1,11.4 81.9,11.4C85.2,11.4 88,12.8 89.2,15' +
    'C90.9,10.5 95.1,8.5 100,8.5C104.9,8.5 109.1,10.5 110.8,15C112,12.8 114.8,11.4 118.1,11.4C123.9,11.4 128.9,14.5 130.2,21.2' +
    'C130.9,20.3 132.6,19.6 135,19.6C142,19.6 147.5,24 147.5,34L147.5,71' +
    'C147.5,72.24 146.5,73.25 145.25,73.25C144,73.25 143,72.24 143,71' +
    // Linha da testa, da direita para a esquerda.
    'L143,60C143,50 138,44 131,44L69,44C62,44 57,50 57,60Z';

/**
 * Calvo, **decalcado da imagem de referência**.
 *
 * Lá o cabelo é uma faixa em degradê colada ao topo da cabeça, e o contorno foi
 * tirado no meio do degradê. Aqui a faixa é chapada e acompanha o contorno da
 * cabeça, 0,7px para fora, para a pele não vazar na borda.
 *
 * A borda de baixo faz um **M**: a faixa chega a y 30 no meio e quase some nas
 * entradas (x≈74 / 126), onde fica com 2,7px. É esse recuo que faz ler
 * como calvície, e não como touca. Nos cantos a faixa desce pela curva da
 * cabeça e termina na lateral em y 54, onde a curva acaba.
 *
 * A cabeça daqui é bem mais arredondada que a da referência (canto de raio 34
 * numa largura de 94, contra 70 em 368), então a faixa foi desenhada sobre o
 * contorno daqui, não copiada em escala.
 */
const BALDING =
    'M52.3,54C52.3,34.84 67.84,19.3 87,19.3L113,19.3C132.16,19.3 147.7,34.84 147.7,54' +
    // Borda de baixo, da direita para a esquerda: entrada, meio, entrada.
    'C147.2,52 145.6,51 144.4,51C143.3,39.8 136.3,30 126,25.3C120.5,22.8 107,30 100,30' +
    'C93,30 79.5,22.8 74,25.3C63.7,30 56.7,39.8 55.6,51C54.4,51 52.8,52 52.3,54Z';

export function hair(style: HairStyle, color: string): HairLayers {
    if (style === 'comprido') {
        return {
            back: `<path d="${LONG_MASS}" fill="${color}"/>`,
            front: `<path d="${LONG_CURTAIN}" fill="${color}"/>`,
        };
    }

    if (style === 'liso') {
        return {
            back: `<path d="${STRAIGHT_MASS}" fill="${color}"/>`,
            front: `<path d="${STRAIGHT_FRINGE}" fill="${color}"/>`,
        };
    }

    if (style === 'chanel') {
        return {
            back: `<path d="${BOB_MASS}" fill="${color}"/>`,
            front: `<path d="${BOB_FRINGE}" fill="${color}"/>`,
        };
    }

    // Curto, afro e calvo não têm camada de trás: param no alto da orelha, então
    // não há nada deles atrás do pescoço nem da roupa.
    if (style === 'curto') {
        return { back: '', front: `<path d="${CAP}" fill="${color}"/>` };
    }

    if (style === 'afro') {
        return { back: '', front: `<path d="${AFRO}" fill="${color}"/>` };
    }

    if (style === 'calvo') {
        return { back: '', front: `<path d="${BALDING}" fill="${color}"/>` };
    }

    return EMPTY;
}
