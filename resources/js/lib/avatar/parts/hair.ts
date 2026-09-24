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
