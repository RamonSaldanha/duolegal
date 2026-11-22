<?php

/*
| Energia do mapa novo (app para todos, /beta/play só para administradores), no lugar das
| vidas. As fases gastam, o combo devolve uma parte, o tempo e o anúncio recarregam e o Dia
| da prova dá a supercarga. Quem assina tem energia infinita. Regras em EnergyService.
*/

return [
    // A barra cheia, e até onde o Dia da prova pode levar (a "supercarga")
    'max' => 25,
    'super_max' => 50,

    // Recarga com o tempo: +1 a cada tantos minutos, até a barra cheia
    'regen_minutes' => 60,

    // Fase nova: o mínimo para abrir (ou o custo da fase sem erro, se ela tem menos blocos),
    // o custo de cada tentativa e o combo de blocos certos seguidos
    'phase' => [
        'open' => 10,
        'correct' => 1,
        'wrong' => 2,
        'combo_every' => 5,
        'combo_gain' => 2,
    ],

    // Revisão (fase já concluída): abre mesmo sem energia, só o erro custa e não tem combo
    // (revisar não recarrega)
    'review' => [
        'correct' => 0,
        'wrong' => 1,
    ],

    // Desafio do quadrado: cada questão nova custa, o combo é de questões certas seguidas
    'challenge' => [
        'open' => 1,
        'question' => 1,
        'combo_every' => 3,
        'combo_gain' => 2,
    ],

    // Dia da prova: de graça. Rende por acerto (só no primeiro acerto de cada questão), combo
    // só na primeira entrega e, com nota alta na primeira entrega, completa a barra
    'exam' => [
        'correct' => 1,
        'combo_every' => 3,
        'combo_gain' => 2,
        'fill_at' => 0.8,
    ],

    // Anúncio assistido
    'ad_reward' => 5,
];
