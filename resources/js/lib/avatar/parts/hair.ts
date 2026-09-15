/**
 * Cabelo.
 *
 * Traçado a partir do SVG de referência, adaptado à grade daqui — o rosto de lá
 * é mais largo que alto (112x94) e o daqui é o contrário (94x132), então as
 * curvas foram remapeadas em proporção da cabeça, não copiadas em escala.
 *
 * Sai em duas camadas:
 *
 *   `back`  — a massa. Maciça, sem recorte: cobre toda a parte de trás do
 *             pescoço. Vai **antes** do pescoço e da roupa, então a gola passa
 *             por cima dela, como na referência.
 *   `front` — a cortina que cai sobre a testa. Vai **depois** da cabeça, senão o
 *             rosto cobre tudo.
 *
 * Duas coisas dão o caráter da referência e não podem ser simplificadas:
 *
 *   A silhueta **ondula**. Os lados não são um arco: saem e voltam três vezes
 *   entre o alto e o pé do desenho. É o que separa "cabelo comprido" de "capuz".
 *
 *   A franja é uma **cortina**, não um bico simétrico. Ela desce do centro da
 *   testa varrendo na diagonal até a lateral do rosto, na altura do olho, e o
 *   bico no meio é só onde as duas metades se encontram.
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
 * O lado direito é o traçado da referência ao pé da letra, só dividido por 5.
 * O que faz a onda aparecer é o trecho **reto**: a silhueta desce quase vertical
 * em x≈173 de y 80 a 110 e só então abre para 184 e 192. Sem esse platô entre
 * as duas aberturas a borda vira um arco liso — foi o que eu tinha feito antes,
 * suavizando exatamente os pontos de controle que criam a onda.
 *
 * O lado esquerdo **não é o espelho**: segura o estreitamento mais para baixo
 * (até y 130, contra 124) e abre mais tarde. Cabelo espelhado ponto a ponto lê
 * como forma geométrica, não como cabelo.
 *
 *   esquerda  30@60 · 26@82 · 27@99 · 27@115 · 20@130 · 12@146 · 10@163 · 4@200
 *   direita  170@60 · 174@80 · 173@96 · 175@110 · 184@124 · 192@140 · 196@200
 *
 * A cúpula do alto é adaptada, não copiada: a cabeça daqui é mais alta, então a
 * da referência não caberia. Sobe até y 3, dentro do círculo inscrito, para não
 * ser decepada no recorte redondo.
 */
const MASS =
    'M100,6C72,-4 42,14 30,60C20,92 36,110 20,130C2,150 16,176 4,200' +
    'L196,200C180,170 204,144 184,124C164,104 180,90 170,60C158,14 128,-4 100,6Z';

/**
 * Cortina. A borda de baixo sai do bico, sobe ao alto do arco em y 32 e daí
 * varre na diagonal até encostar na lateral do rosto, cobrindo a ponta de fora
 * da sobrancelha — que é da mesma cor e some sob a linha do cabelo, como cabelo
 * de verdade faz.
 *
 * A risca fica em x 104, fora do centro: a metade esquerda é mais larga e desce
 * mais (até y 76, contra 72). Isso compensa a massa, que é mais cheia do lado
 * direito — as duas assimetrias se equilibram em vez de somar.
 *
 * A varredura é mais curta que a da referência, e isso é limite da cabeça, não
 * do traçado: lá os olhos ocupam 53% da largura do rosto e aqui ocupam 81%, de
 * modo que qualquer cortina mais longa entra no branco do olho.
 *
 * O contorno de cima corre por dentro da massa. Fechar a faixa mais para dentro
 * deixa os cantos do rosto vazarem por cima do cabelo, num risco claro de pele.
 */
const CURTAIN =
    'M30,76C28,58 32,50 38,42C48,16 76,0 100,8C124,0 152,16 162,42C168,50 172,58 170,72' +
    'L147,72C142,58 136,53 128,47C118,33 110,28 104,43C97,27 88,32 75,46C65,52 59,58 53,76Z';

export function hair(style: HairStyle, color: string): HairLayers {
    if (style !== 'comprido') {
        return EMPTY;
    }

    return {
        back: `<path d="${MASS}" fill="${color}"/>`,
        front: `<path d="${CURTAIN}" fill="${color}"/>`,
    };
}
