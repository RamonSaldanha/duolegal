<?php

/*
| Energia do mapa novo (app para todos, /beta/play só para administradores), no lugar das
| vidas. As fases gastam, o combo devolve uma parte, o tempo e o anúncio recarregam e o Dia
| da prova dá a supercarga. Quem assina tem energia infinita. Regras em EnergyService.
|
| Regra de ouro: mesmo acertando tudo, a energia acaba. Tudo o que se repete custa mais do que
| o combo devolve (combo_gain < combo_every x custo do acerto); só a primeira entrega do Dia da
| prova de cada etapa rende, uma vez. O EnergyTest confere.
*/

return [
    // A barra cheia, e até onde o Dia da prova pode levar (a "supercarga")
    'max' => 25,
    'super_max' => 50,

    // Recarga com o tempo: +1 a cada tantos minutos, até a barra cheia
    'regen_minutes' => 60,

    // Fase nova: o mínimo para abrir (ou o custo da fase errando cada bloco uma vez, se ela tem
    // poucos blocos), o custo de cada tentativa e o combo de blocos certos seguidos. Com a barra
    // cheia, quem acerta tudo joga no máximo 2 fases (cada uma gasta 10 - 3 = 7: 25, 18, 11 e a
    // 3ª, que pede 12, não abre)
    'phase' => [
        'open' => 12,
        'correct' => 1,
        'wrong' => 2,
        'combo_every' => 3,
        'combo_gain' => 1,
    ],

    // Revisão (fase já concluída): custa como a fase nova, mas abre com menos e não tem combo
    // (revisar não recarrega)
    'review' => [
        'open' => 1,
        'correct' => 1,
        'wrong' => 2,
    ],

    // Desafio do quadrado: cada questão nova custa, o combo é de questões certas seguidas.
    // Refazer um material já feito custa o mesmo por questão, sem combo
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
        // Refazer a prova (da segunda vez em diante): custo fixo ao começar, sem render nada
        'retake' => 5,
    ],

    // Anúncio assistido até o fim (pouco, para o anúncio não virar energia sem fim): no máximo
    // `ad_daily` por dia (o dia de Brasília). O do Google e o nosso (o vídeo do Premium em
    // `ad_video`) se revezam; o do Google conta `ad_google_seconds` antes de dar a energia.
    'ad_reward' => 3,
    'ad_daily' => 3,
    'ad_timezone' => 'America/Sao_Paulo',
    'ad_video' => 'ads/memorize-premium.mp4',
    'ad_google_seconds' => 30,
];
